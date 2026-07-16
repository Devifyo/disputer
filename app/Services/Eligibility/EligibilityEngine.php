<?php

namespace App\Services\Eligibility;

use App\Models\Setting;
use App\Models\Trip;
use App\Models\TripEvent;
use App\Notifications\TripEligibleForCompensation;
use App\Services\Eligibility\Evaluators\RuleBasedEligibilityEvaluator;
use App\Services\FlightAwareService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Automatic eligibility evaluation for disrupted flights.
 *
 * Detects which air passenger rights regime covers the route (APPR, EU261,
 * UK261, US DOT), evaluates the disruption against each applicable one,
 * picks the strongest verdict with its legal article and a 0-100 confidence
 * score, and resolves a status: eligible (auto-approved), review (a human
 * confirms), or rejected. decide() is model-agnostic - trips and claims
 * both run through it.
 */
class EligibilityEngine
{
    public const STATUS_ELIGIBLE = 'eligible';
    public const STATUS_REVIEW   = 'review';   // eligible but below the confidence threshold - a human confirms
    public const STATUS_REJECTED = 'rejected';

    public const SETTING_THRESHOLD = 'eligibility.confidence_threshold';

    public function __construct(
        private FlightAwareService $flightAware,
        private RuleBasedEligibilityEvaluator $rules,
    ) {
    }

    /**
     * The provider that judges eligibility, resolved from config so the AI
     * assistant can later be swapped for the official rules engine without
     * touching the rest of the application.
     */
    private function primaryEvaluator(): EligibilityEvaluator
    {
        $key   = config('eligibility.evaluator', 'ai');
        $class = config("eligibility.providers.{$key}") ?? RuleBasedEligibilityEvaluator::class;

        return app($class);
    }

    public static function confidenceThreshold(): int
    {
        return (int) Setting::get(
            self::SETTING_THRESHOLD,
            config('eligibility.default_confidence_threshold', 70)
        );
    }

    /**
     * The full decision pipeline for any disrupted flight: evaluate, pick
     * the winning verdict, apply the confidence cap and threshold, resolve
     * the status and customer-facing reason.
     *
     * @return array{best: ?EligibilityResult, status: string, reason: string, details: array}
     */
    public function decide(EligibilityContext $context): array
    {
        [$outcomes, $evaluatedBy] = $this->runEvaluators($context);

        $best      = $this->pickVerdict($outcomes);
        $threshold = self::confidenceThreshold();

        // Whatever the evaluator felt, a verdict resting on unverified or
        // passenger-reported facts can never be near-certain - the airline's
        // records may differ.
        if ($best?->eligible && ($context->reportedDisruption || !$context->factsVerified) && $best->confidence > 75) {
            $best->confidence = 75;
            $best->factors[]  = 'Confidence capped: the decisive facts cannot be fully verified automatically.';
        }

        $details = [
            'evaluated_by'        => $evaluatedBy,
            'origin_country'      => $context->originCountry,
            'destination_country' => $context->destinationCountry,
            'facts_verified'      => $context->factsVerified,
            'threshold'           => $threshold,
            'outcomes'            => array_map(fn (EligibilityResult $r) => $r->toArray(), $outcomes),
        ];

        if (!$best) {
            return [
                'best'    => null,
                'status'  => self::STATUS_REJECTED,
                'reason'  => 'No air passenger rights regulation covers this route.',
                'details' => $details,
            ];
        }

        // The evaluator may only escalate to a human, never bypass one.
        $reviewAsked = $best->flagged('manual_review_recommended');
        $accepted    = $best->eligible && $best->confidence >= $threshold && !$reviewAsked;

        // Below-threshold eligible verdicts go to a human, never to a flat
        // rejection - the customer may genuinely be owed money.
        $status = $accepted ? self::STATUS_ELIGIBLE
            : ($best->eligible ? self::STATUS_REVIEW : self::STATUS_REJECTED);

        // "Something else" reports can't be classified automatically - they
        // always go to the team, as the customer was promised.
        if ($status === self::STATUS_REJECTED && $context->reportedDisruption === 'other') {
            $status = self::STATUS_REVIEW;
        }

        // A rejection the engine itself isn't confident about is flagged for
        // a human, never issued to the customer as final.
        if ($status === self::STATUS_REJECTED && $best->confidence < $threshold) {
            $status = self::STATUS_REVIEW;
        }

        $reason = $best->reason;
        if ($status === self::STATUS_REVIEW) {
            // The engine's raw verdict stays in the audit trail; the customer
            // gets review-appropriate copy, not internals stapled together.
            $reason = $best->eligible
                ? $best->reason . ' Our team is verifying the details before confirming your claim.'
                : 'Our automated check couldn\'t match your report to a compensation rule, so our team will review what happened and confirm whether you have a claim.';
            $details['auto_review'] = $best->eligible
                ? ($reviewAsked && $best->confidence >= $threshold
                    ? 'The evaluator recommended a manual check despite an eligible verdict.'
                    : sprintf('Confidence %d%% below the %d%% threshold.', $best->confidence, $threshold))
                : 'Engine verdict was not-eligible but not confident enough to be final - a human decides.';
        }

        return ['best' => $best, 'status' => $status, 'reason' => $reason, 'details' => $details];
    }

    /**
     * Evaluate a trip whose disruption is final and persist the outcome.
     * Returns the winning result, or null when no regulation covers the route.
     */
    public function evaluate(Trip $trip): ?EligibilityResult
    {
        $context  = $this->contextFromTrip($trip);
        $decision = $this->decide($context);
        $best     = $decision['best'];

        $trip->forceFill([
            'eligibility_status'          => $decision['status'],
            'eligibility_regulation'      => $best?->regulation,
            'eligibility_article'         => $best?->article,
            'eligibility_confidence'      => $best?->confidence ?? 0,
            'eligibility_reason'          => $decision['reason'],
            'eligibility_details'         => $decision['details'],
            'eligibility_evaluated_at'    => now(),
            // A fresh automated verdict supersedes any earlier manual decision.
            'eligibility_decision_source' => $decision['details']['evaluated_by'],
            'eligibility_decided_by'      => null,
            'eligibility_decided_at'      => null,
        ])->save();

        if (!$best) {
            return null;
        }

        $accepted = $decision['status'] === self::STATUS_ELIGIBLE;

        $trip->events()->create([
            'type'        => TripEvent::TYPE_ELIGIBILITY,
            'description' => match ($decision['status']) {
                self::STATUS_ELIGIBLE => sprintf('Trip found eligible for compensation under %s (%s) with %d%% confidence.', $best->regulation, $best->article, $best->confidence),
                self::STATUS_REVIEW   => $best->eligible
                    ? sprintf('Likely eligible under %s (%s) - sent to our team for manual confirmation.', $best->regulation, $best->article)
                    : 'Report sent to our team for manual review.',
                default               => sprintf('Eligibility review outcome: %s', $decision['reason']),
            },
            'data'        => $best->toArray(),
            'qualifying'  => $accepted,
            'detected_at' => now(),
        ]);

        if ($accepted && $trip->user) {
            try {
                $trip->user->notify(new TripEligibleForCompensation($trip));
            } catch (Throwable $e) {
                Log::error('Eligibility notification failed', ['trip' => $trip->id, 'error' => $e->getMessage()]);
            }
        }

        return $best;
    }

    /** Resolve an airport's country from FlightAware's forever-cached metadata. */
    public function countryOf(?string $airportCode): ?string
    {
        if (!$airportCode) {
            return null;
        }

        try {
            return $this->flightAware->airportInfo($airportCode)['country_code'] ?? null;
        } catch (Throwable $e) {
            Log::warning('Airport country lookup failed', ['airport' => $airportCode, 'error' => $e->getMessage()]);

            return null;
        }
    }

    // ── Internals ───────────────────────────────────────────

    private function contextFromTrip(Trip $trip): EligibilityContext
    {
        return new EligibilityContext(
            ref:                 "trip:{$trip->id}",
            airline:             $trip->airline,
            flightNumber:        $trip->flightIdent(),
            flightDate:          $trip->departure_date,
            departureAirport:    $trip->departure_airport,
            arrivalAirport:      $trip->arrival_airport,
            originCountry:       $this->countryOf($trip->departure_airport),
            destinationCountry:  $this->countryOf($trip->arrival_airport),
            cancelled:           $trip->flight_status === Trip::FLIGHT_CANCELLED,
            arrivalDelayMinutes: max(0, (int) $trip->arrival_delay_minutes),
            delayIsActual:       $trip->actual_arrival !== null,
            factsVerified:       $trip->fa_flight_id !== null,
            diverted:            (bool) $trip->diverted,
            reportedDisruption:  $trip->reported_disruption,
            reportAnswers:       $trip->report_details['questions'] ?? [],
        );
    }

    /**
     * The configured provider evaluates first (broader regulation
     * knowledge); any failure or invalid output falls back to the
     * deterministic rules. Valid provider output is still reconciled
     * against the rules so a hallucinated jurisdiction or an omitted
     * regulation can never decide a claim alone.
     *
     * @return array{0: array<EligibilityResult>, 1: string}
     */
    private function runEvaluators(EligibilityContext $context): array
    {
        $ruleOutcomes = $this->rules->evaluate($context);
        $primary      = $this->primaryEvaluator();

        if (!$primary instanceof RuleBasedEligibilityEvaluator) {
            try {
                return $this->reconcile($primary->evaluate($context), $ruleOutcomes, $context, $primary->name());
            } catch (Throwable $e) {
                Log::warning('Eligibility provider failed - falling back to rules', [
                    'ref'      => $context->ref,
                    'provider' => $primary->name(),
                    'error'    => $e->getMessage(),
                ]);
            }
        }

        return [$ruleOutcomes, $this->rules->name()];
    }

    /**
     * Guard rails around the provider's verdicts:
     * - when both airport countries are known, outcomes for regulations
     *   the deterministic jurisdiction check says don't cover the route
     *   are dropped (anti-hallucination);
     * - duplicate outcomes per regulation collapse to the most confident;
     * - regulations the rules deem applicable but the provider omitted are
     *   backfilled from the rule verdicts, labelled "<provider>+rules".
     *
     * @return array{0: array<EligibilityResult>, 1: string}
     */
    private function reconcile(array $aiOutcomes, array $ruleOutcomes, EligibilityContext $context, string $providerName): array
    {
        $applicable        = array_map(fn (EligibilityResult $r) => $r->regulation, $ruleOutcomes);
        $jurisdictionKnown = $context->originCountry !== null && $context->destinationCountry !== null;

        $byRegulation = [];
        foreach ($aiOutcomes as $outcome) {
            if ($jurisdictionKnown && !in_array($outcome->regulation, $applicable, true)) {
                Log::warning('Dropped AI eligibility outcome for non-applicable regulation', [
                    'ref' => $context->ref, 'regulation' => $outcome->regulation,
                ]);
                continue;
            }
            $current = $byRegulation[$outcome->regulation] ?? null;
            if (!$current || $outcome->confidence > $current->confidence) {
                $byRegulation[$outcome->regulation] = $outcome;
            }
        }

        $backfilled = false;
        foreach ($ruleOutcomes as $outcome) {
            if (!isset($byRegulation[$outcome->regulation])) {
                $byRegulation[$outcome->regulation] = $outcome;
                $backfilled = true;
            }
        }

        return [array_values($byRegulation), $backfilled ? "{$providerName}+rules" : $providerName];
    }

    /**
     * Remedy strength of each regime: cash-compensation regulations outrank
     * the refund-only US DOT rules when several cover the same route.
     */
    private const REGULATION_WEIGHT = ['EU261' => 3, 'UK261' => 3, 'APPR' => 2, 'US_DOT' => 1];

    /** Strongest eligible verdict wins; otherwise the most confident rejection. */
    private function pickVerdict(array $outcomes): ?EligibilityResult
    {
        if (empty($outcomes)) {
            return null;
        }

        $eligible = array_filter($outcomes, fn (EligibilityResult $r) => $r->eligible);
        $pool     = $eligible ?: $outcomes;

        usort($pool, fn (EligibilityResult $a, EligibilityResult $b) =>
            [self::REGULATION_WEIGHT[$b->regulation] ?? 0, $b->confidence]
            <=> [self::REGULATION_WEIGHT[$a->regulation] ?? 0, $a->confidence]);

        return $pool[0];
    }
}
