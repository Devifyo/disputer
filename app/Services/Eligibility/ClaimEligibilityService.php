<?php

namespace App\Services\Eligibility;

use App\Models\Claim;
use App\Services\FlightAwareService;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Eligibility for claims (past flights): verify the flight with FlightAware
 * where its history still reaches, run the shared Eligibility Engine, and
 * price the compensation. Flights too old to verify are judged on the
 * passenger's declared disruption at reduced confidence.
 */
class ClaimEligibilityService
{
    public function __construct(
        private FlightAwareService $flightAware,
        private EligibilityEngine $engine,
        private CompensationCalculator $calculator,
    ) {
    }

    public function evaluate(Claim $claim): void
    {
        $wasEligible = $claim->status === Claim::STATUS_ELIGIBLE;

        $this->verifyFlight($claim);

        $context  = $this->buildContext($claim);
        $decision = $this->engine->decide($context);
        $best     = $decision['best'];

        $compensation = $best && $decision['status'] !== EligibilityEngine::STATUS_REJECTED
            ? $this->calculator->calculate($best, $context, $claim->ticket_price ?? $claim->trip?->ticket_price, $claim->ticket_currency ?? $claim->trip?->ticket_currency)
            : null;

        $claim->forceFill([
            'status'                      => $this->claimStatus($decision['status']),
            'eligibility_status'          => $decision['status'],
            'eligibility_regulation'      => $best?->regulation,
            'eligibility_article'         => $best?->article,
            'eligibility_confidence'      => $best?->confidence ?? 0,
            'eligibility_reason'          => $decision['reason'],
            'eligibility_details'         => $decision['details'],
            'eligibility_evaluated_at'    => now(),
            'eligibility_decision_source' => $decision['details']['evaluated_by'],
            'compensation_amount'         => isset($compensation['amount']) ? number_format($compensation['amount'], 2, '.', '') : null,
            'compensation_currency'       => $compensation['currency'] ?? null,
            'compensation_basis'          => $compensation['basis'] ?? null,
            'compensation_explanation'    => $compensation['breakdown'] ?? null,
        ])->save();

        $this->recordOutcome($claim, $decision, $compensation);

        if (!$wasEligible && $decision['status'] === EligibilityEngine::STATUS_ELIGIBLE) {
            $this->notifyEligible($claim, $compensation);
        }
    }

    /** "You're owed X - claim it now" email, sent once when a claim turns eligible. */
    private function notifyEligible(Claim $claim, ?array $compensation): void
    {
        $email = $claim->contact_email ?: $claim->user?->email;
        if (!$email) {
            return;
        }

        $count  = max(1, count($claim->passengerNames()));
        $amount = $compensation['amount'] ?? null;

        send_dynamic_email($email, 'claim-eligible-compensation', [
            '[NAME]'      => $claim->passenger_name ?: 'traveller',
            '[AMOUNT]'    => $amount
                ? trim(($compensation['currency'] ?? '') . ' ' . number_format($amount * $count, 2))
                : 'compensation',
            '[FLIGHT]'    => trim(($claim->airline ?? '') . ' ' . ($claim->flight_number ?? '')),
            '[ROUTE]'     => "{$claim->departure_airport} - {$claim->arrival_airport}",
            '[CLAIM_URL]' => url('/flight-disputes/claims/' . encrypt_id($claim->id)),
        ]);
    }

    /**
     * Price the compensation for a claim whose verdict is already settled
     * (claims inheriting an eligible trip's verdict).
     */
    public function priceCompensation(Claim $claim): void
    {
        if (!$claim->eligibility_regulation) {
            return;
        }

        $verdict = new EligibilityResult(
            $claim->eligibility_regulation,
            true,
            (string) $claim->eligibility_article,
            (int) $claim->eligibility_confidence,
            (string) $claim->eligibility_reason,
        );

        $compensation = $this->calculator->calculate($verdict, $this->buildContext($claim), $claim->ticket_price ?? $claim->trip?->ticket_price, $claim->ticket_currency ?? $claim->trip?->ticket_currency);

        if ($compensation) {
            $claim->forceFill([
                'compensation_amount'      => $compensation['amount'] !== null ? number_format($compensation['amount'], 2, '.', '') : null,
                'compensation_currency'    => $compensation['currency'],
                'compensation_basis'       => $compensation['basis'],
                'compensation_explanation' => $compensation['breakdown'] ?? null,
            ])->save();
        }
    }

    // ── FlightAware verification ────────────────────────────

    private function verifyFlight(Claim $claim): void
    {
        $ident = $claim->flight_number ? strtoupper(preg_replace('/\s+/', '', $claim->flight_number)) : null;
        $date  = $claim->flight_date ? Carbon::parse($claim->flight_date)->setTime(12, 0) : null;

        if (!$ident || !$date) {
            return;
        }

        try {
            $result = $this->flightAware->findFlight($ident, $date, $claim->departure_airport);
        } catch (Throwable) {
            return;
        }

        if (!$result['ok'] || empty($result['data']['fa_flight_id'])) {
            return; // too old for /flights history or unknown - stay unverified
        }

        $flight = $result['data'];

        $claim->forceFill([
            'fa_flight_id'                 => $flight['fa_flight_id'],
            'flight_arrival_delay_minutes' => isset($flight['arrival_delay']) ? (int) round($flight['arrival_delay'] / 60) : null,
            'flight_cancelled'             => (bool) ($flight['cancelled'] ?? false),
            'flight_diverted'              => (bool) ($flight['diverted'] ?? false),
            'flight_verified_at'           => now(),
            'flight_snapshot'              => self::snapshot($flight),
        ])->save();
    }

    /** The tracking record kept as evidence on the claim. */
    public static function snapshot(array $flight): array
    {
        return [
            'status'                  => $flight['status'] ?? null,
            'scheduled_departure'     => $flight['scheduled_out'] ?? $flight['scheduled_off'] ?? null,
            'actual_departure'        => $flight['actual_out'] ?? $flight['actual_off'] ?? null,
            'scheduled_arrival'       => $flight['scheduled_in'] ?? $flight['scheduled_on'] ?? null,
            'actual_arrival'          => $flight['actual_in'] ?? $flight['actual_on'] ?? null,
            'departure_delay_minutes' => isset($flight['departure_delay']) ? (int) round($flight['departure_delay'] / 60) : null,
            'arrival_delay_minutes'   => isset($flight['arrival_delay']) ? (int) round($flight['arrival_delay'] / 60) : null,
            'origin_gate'             => $flight['gate_origin'] ?? null,
            'destination_gate'        => $flight['gate_destination'] ?? null,
            'origin_terminal'         => $flight['terminal_origin'] ?? null,
            'destination_terminal'    => $flight['terminal_destination'] ?? null,
            'cancelled'               => (bool) ($flight['cancelled'] ?? false),
            'diverted'                => (bool) ($flight['diverted'] ?? false),
        ];
    }

    private function buildContext(Claim $claim): EligibilityContext
    {
        $verified = $claim->flight_verified_at !== null;

        // Unverified flights are judged on what the passenger declared;
        // verified ones on FlightAware's facts (with the declared type kept
        // for disruptions flight data can't see, like denied boarding).
        $reported = $verified
            ? (in_array($claim->disruption_type, ['denied_boarding', 'downgrade', 'missed_connection', 'schedule_change', 'returned_to_origin', 'other'], true) ? $claim->disruption_type : null)
            // Unverifiable and no declared type either: route to the team
            // ('other') instead of rejecting on zero facts.
            : ($claim->disruption_type ?? 'other');

        // On a cancelled flight (or one that never delivered the journey -
        // schedule change, returned to origin) the tracking "arrival delay"
        // is meaningless. The reported rebooking arrival is the real number.
        $cancelled = $claim->flight_cancelled
            || in_array($claim->disruption_type, ['cancelled', 'schedule_change', 'returned_to_origin'], true);
        $delay     = $cancelled
            ? $claim->reported_arrival_delay_minutes
            : ($verified ? ($claim->flight_arrival_delay_minutes ?? $claim->reported_arrival_delay_minutes) : $claim->reported_arrival_delay_minutes);

        return new EligibilityContext(
            ref:                 "claim:{$claim->id}",
            airline:             $claim->airline,
            flightNumber:        $claim->flight_number,
            flightDate:          $claim->flight_date ? Carbon::parse($claim->flight_date) : null,
            departureAirport:    $claim->departure_airport,
            arrivalAirport:      $claim->arrival_airport,
            originCountry:       $this->engine->countryOf($claim->departure_airport),
            destinationCountry:  $this->engine->countryOf($claim->arrival_airport),
            cancelled:           $verified && $claim->flight_cancelled,
            arrivalDelayMinutes: max(0, (int) $delay),
            delayIsActual:       $verified,
            factsVerified:       $verified,
            diverted:            $verified && $claim->flight_diverted,
            didNotTravel:        (bool) $claim->did_not_travel,
            reportedDisruption:  $reported,
            reportAnswers:       $claim->disruption_note
                ? [['question' => 'Describe what happened in your own words.', 'answer' => $claim->disruption_note]]
                : [],
        );
    }

    private function claimStatus(string $eligibilityStatus): string
    {
        return match ($eligibilityStatus) {
            EligibilityEngine::STATUS_ELIGIBLE => Claim::STATUS_ELIGIBLE,
            EligibilityEngine::STATUS_REVIEW   => Claim::STATUS_PENDING_ELIGIBILITY,
            default                            => Claim::STATUS_REJECTED,
        };
    }

    private function recordOutcome(Claim $claim, array $decision, ?array $compensation): void
    {
        $best = $decision['best'];

        // The evaluation supersedes the "Claim under review" placeholder -
        // complete it so the timeline reads as finished steps + one outcome.
        $claim->events()->where('label', 'Claim under review')->where('status', 'pending')->update(['status' => 'done']);

        $label = match ($decision['status']) {
            EligibilityEngine::STATUS_ELIGIBLE => $compensation && $compensation['amount']
                ? sprintf('Eligible under %s (%s) - estimated %s %s', $best->regulation, $best->article, $compensation['currency'], number_format($compensation['amount'], 2))
                : sprintf('Eligible under %s (%s)', $best->regulation, $best->article),
            EligibilityEngine::STATUS_REVIEW   => 'Our team is reviewing your eligibility',
            default                            => 'Not eligible: ' . $decision['reason'],
        };

        // Ongoing team review stays an open (pending) step; final verdicts close.
        $status = match ($decision['status']) {
            EligibilityEngine::STATUS_ELIGIBLE => 'done',
            EligibilityEngine::STATUS_REVIEW   => 'pending',
            default                            => 'failed',
        };

        $claim->recordEvent($label, $status, now(), 2);
    }
}
