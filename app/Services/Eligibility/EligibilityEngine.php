<?php

namespace App\Services\Eligibility;

use App\Models\Setting;
use App\Models\Trip;
use App\Models\TripEvent;
use App\Notifications\TripEligibleForCompensation;
use App\Services\Eligibility\Evaluators\AiEligibilityEvaluator;
use App\Services\Eligibility\Evaluators\RuleBasedEligibilityEvaluator;
use App\Services\FlightAwareService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Automatic eligibility evaluation for disrupted, monitored trips.
 *
 * Detects which air passenger rights regime covers the route (APPR, EU261,
 * UK261, US DOT), evaluates the disruption against each applicable one,
 * picks the strongest verdict with its legal article and a 0–100 confidence
 * score, and either marks the trip Eligible (notifying the user) or
 * auto-rejects it when confidence falls below the admin-managed threshold.
 */
class EligibilityEngine
{
    public const STATUS_ELIGIBLE = 'eligible';
    public const STATUS_REJECTED = 'rejected';

    public const SETTING_THRESHOLD = 'eligibility.confidence_threshold';

    public function __construct(
        private FlightAwareService $flightAware,
        private AiEligibilityEvaluator $ai,
        private RuleBasedEligibilityEvaluator $rules,
    ) {
    }

    public static function confidenceThreshold(): int
    {
        return (int) Setting::get(
            self::SETTING_THRESHOLD,
            config('eligibility.default_confidence_threshold', 70)
        );
    }

    /**
     * Evaluate a trip whose disruption is final and persist the outcome.
     * Returns the winning result, or null when no regulation covers the route.
     */
    public function evaluate(Trip $trip): ?EligibilityResult
    {
        $context = $this->buildContext($trip);

        [$outcomes, $evaluatedBy] = $this->runEvaluators($context);

        $best      = $this->pickVerdict($outcomes);
        $threshold = self::confidenceThreshold();

        $this->persist($trip, $best, $outcomes, $context, $threshold, $evaluatedBy);

        return $best;
    }

    /**
     * AI evaluates first (broader regulation knowledge); any failure or
     * invalid output falls back to the deterministic rules. Valid AI output
     * is still reconciled against the rules so a hallucinated jurisdiction
     * or an omitted regulation can never decide a claim alone.
     *
     * @return array{0: array<EligibilityResult>, 1: string}
     */
    private function runEvaluators(EligibilityContext $context): array
    {
        $ruleOutcomes = $this->rules->evaluate($context);

        if (config('eligibility.evaluator', 'ai') === 'ai') {
            try {
                return $this->reconcile($this->ai->evaluate($context), $ruleOutcomes, $context);
            } catch (Throwable $e) {
                Log::warning('AI eligibility evaluation failed — falling back to rules', [
                    'trip'  => $context->trip->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [$ruleOutcomes, $this->rules->name()];
    }

    /**
     * Guard rails around the AI verdicts:
     * - when both airport countries are known, AI outcomes for regulations
     *   the deterministic jurisdiction check says don't cover the route
     *   are dropped (anti-hallucination);
     * - duplicate outcomes per regulation collapse to the most confident;
     * - regulations the rules deem applicable but the AI omitted are
     *   backfilled from the rule verdicts, labelled "ai+rules".
     *
     * @return array{0: array<EligibilityResult>, 1: string}
     */
    private function reconcile(array $aiOutcomes, array $ruleOutcomes, EligibilityContext $context): array
    {
        $applicable        = array_map(fn (EligibilityResult $r) => $r->regulation, $ruleOutcomes);
        $jurisdictionKnown = $context->originCountry !== null && $context->destinationCountry !== null;

        $byRegulation = [];
        foreach ($aiOutcomes as $outcome) {
            if ($jurisdictionKnown && !in_array($outcome->regulation, $applicable, true)) {
                Log::warning('Dropped AI eligibility outcome for non-applicable regulation', [
                    'trip' => $context->trip->id, 'regulation' => $outcome->regulation,
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

        return [array_values($byRegulation), $backfilled ? 'ai+rules' : $this->ai->name()];
    }

    // ── Internals ───────────────────────────────────────────

    private function buildContext(Trip $trip): EligibilityContext
    {
        $delayIsActual = $trip->actual_arrival !== null;

        return new EligibilityContext(
            trip:                $trip,
            originCountry:       $this->countryOf($trip->departure_airport),
            destinationCountry:  $this->countryOf($trip->arrival_airport),
            cancelled:           $trip->flight_status === Trip::FLIGHT_CANCELLED,
            arrivalDelayMinutes: max(0, (int) $trip->arrival_delay_minutes),
            delayIsActual:       $delayIsActual,
        );
    }

    private function countryOf(?string $airportCode): ?string
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

    private function persist(Trip $trip, ?EligibilityResult $best, array $outcomes, EligibilityContext $context, int $threshold, string $evaluatedBy): void
    {
        $details = [
            'evaluated_by'        => $evaluatedBy,
            'origin_country'      => $context->originCountry,
            'destination_country' => $context->destinationCountry,
            'threshold'           => $threshold,
            'outcomes'            => array_map(fn (EligibilityResult $r) => $r->toArray(), $outcomes),
        ];

        if (!$best) {
            $trip->forceFill([
                'eligibility_status'       => self::STATUS_REJECTED,
                'eligibility_confidence'   => 0,
                'eligibility_reason'       => 'No air passenger rights regulation covers this route.',
                'eligibility_details'      => $details,
                'eligibility_evaluated_at' => now(),
            ])->save();

            return;
        }

        $accepted = $best->eligible && $best->confidence >= $threshold;

        $reason = $best->reason;
        if ($best->eligible && !$accepted) {
            $reason = sprintf(
                'Automatically rejected: confidence %d%% is below the %d%% review threshold. %s',
                $best->confidence, $threshold, $best->reason
            );
        }

        $trip->forceFill([
            'eligibility_status'       => $accepted ? self::STATUS_ELIGIBLE : self::STATUS_REJECTED,
            'eligibility_regulation'   => $best->regulation,
            'eligibility_article'      => $best->article,
            'eligibility_confidence'   => $best->confidence,
            'eligibility_reason'       => $reason,
            'eligibility_details'      => $details,
            'eligibility_evaluated_at' => now(),
        ])->save();

        $trip->events()->create([
            'type'        => TripEvent::TYPE_ELIGIBILITY,
            'description' => $accepted
                ? sprintf('Trip found eligible for compensation under %s (%s) with %d%% confidence.', $best->regulation, $best->article, $best->confidence)
                : sprintf('Eligibility review outcome: %s', $reason),
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
    }
}
