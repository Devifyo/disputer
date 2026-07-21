<?php

namespace App\Services\Eligibility;

use App\Models\Claim;

/**
 * The canonical legal citation table - the single source of truth for which
 * article backs which disruption under which regime.
 *
 * The AI never chooses a citation. The eligibility engine resolves the
 * scenario here, stores the exact article on the claim, and the drafting
 * service is handed that article as structured data with the allowed set as
 * a guard. Anything the model writes that is not in the allowed set for the
 * claim's regime is treated as a hallucination.
 *
 * Scenario keys mirror EligibilityContext: cancelled | delayed |
 * denied_boarding | downgrade | missed_connection | diverted |
 * schedule_change | other.
 */
class RegulationCitation
{
    /**
     * regime => scenario => [article, what the article actually covers].
     * "article" is what appears verbatim in letters and on the claim.
     */
    private const CITATIONS = [
        'APPR' => [
            'cancelled'         => ['Section 19', 'Compensation for inconvenience - cancellation within the carrier\'s control'],
            'delayed'           => ['Section 19', 'Compensation for inconvenience - arrival delay of 3+ hours within the carrier\'s control'],
            'schedule_change'   => ['Section 19', 'Compensation for inconvenience - schedule change treated as a cancellation'],
            'returned_to_origin' => ['Section 19', 'Compensation for inconvenience - flight returned to origin'],
            'missed_connection' => ['Section 19', 'Compensation for inconvenience - delay at the final destination'],
            'diverted'          => ['Section 19', 'Compensation for inconvenience - delay at the booked destination after a diversion'],
            'denied_boarding'   => ['Section 20', 'Compensation for denied boarding'],
            'downgrade'         => ['Section 19', 'APPR provides no fixed downgrade reimbursement'],
            'other'             => ['Section 19', 'Compensation for inconvenience'],
        ],
        'EU261' => [
            'cancelled'         => ['Articles 5 and 7', 'Cancellation rights and fixed compensation'],
            'delayed'           => ['Article 7', 'Fixed compensation - 3+ hour arrival delay (Sturgeon)'],
            'schedule_change'   => ['Articles 5 and 7', 'Schedule change treated as a cancellation'],
            'returned_to_origin' => ['Articles 5 and 7', 'Flight returned to origin treated as a cancellation'],
            'missed_connection' => ['Article 7', 'Fixed compensation - delay at the final destination'],
            'diverted'          => ['Articles 8 and 7', 'Re-routing plus fixed compensation after a diversion'],
            'denied_boarding'   => ['Article 4', 'Denied boarding compensation'],
            'downgrade'         => ['Article 10', 'Reimbursement for downgrading'],
            'other'             => ['Article 7', 'Fixed compensation'],
        ],
        'UK261' => [
            'cancelled'         => ['Articles 5 and 7', 'Cancellation rights and fixed compensation (retained EU law)'],
            'delayed'           => ['Article 7', 'Fixed compensation - 3+ hour arrival delay (retained EU law)'],
            'schedule_change'   => ['Articles 5 and 7', 'Schedule change treated as a cancellation'],
            'returned_to_origin' => ['Articles 5 and 7', 'Flight returned to origin treated as a cancellation'],
            'missed_connection' => ['Article 7', 'Fixed compensation - delay at the final destination'],
            'diverted'          => ['Articles 8 and 7', 'Re-routing plus fixed compensation after a diversion'],
            'denied_boarding'   => ['Article 4', 'Denied boarding compensation'],
            'downgrade'         => ['Article 10', 'Reimbursement for downgrading'],
            'other'             => ['Article 7', 'Fixed compensation'],
        ],
        'US_DOT' => [
            'cancelled'         => ['14 CFR Part 260', 'Refunds for cancelled or significantly changed flights'],
            'delayed'           => ['14 CFR Part 260', 'Refunds for significantly delayed flights'],
            'schedule_change'   => ['14 CFR Part 260', 'Refunds for significant schedule changes'],
            'returned_to_origin' => ['14 CFR Part 260', 'Refunds where the flight did not operate as booked'],
            'missed_connection' => ['14 CFR Part 260', 'Refunds where the itinerary was not completed as booked'],
            'diverted'          => ['14 CFR Part 260', 'Refunds where the flight did not reach the booked destination'],
            'denied_boarding'   => ['14 CFR Part 250', 'Involuntary denied boarding compensation'],
            'downgrade'         => ['14 CFR Part 260', 'Refund of the fare difference'],
            'other'             => ['14 CFR Part 260', 'Refunds'],
        ],
    ];

    /** Supporting provisions cited alongside the main article when they apply. */
    private const SUPPORTING = [
        'APPR'   => ['refund' => 'Section 17', 'care' => 'Sections 8-10', 'deadline' => 'Section 19(4)'],
        'EU261'  => ['refund' => 'Article 8', 'care' => 'Article 9', 'deadline' => null],
        'UK261'  => ['refund' => 'Article 8', 'care' => 'Article 9', 'deadline' => null],
        'US_DOT' => ['refund' => '14 CFR Part 260', 'care' => null, 'deadline' => null],
    ];

    /** The authoritative article for a regime + scenario. Never null for a known regime. */
    public static function article(string $regulation, ?string $scenario): string
    {
        $table = self::CITATIONS[strtoupper($regulation)] ?? null;

        if (!$table) {
            return '';
        }

        return ($table[$scenario] ?? $table['other'])[0];
    }

    /** What that article covers - context for the AI and the admin UI. */
    public static function describes(string $regulation, ?string $scenario): string
    {
        $table = self::CITATIONS[strtoupper($regulation)] ?? null;

        if (!$table) {
            return '';
        }

        return ($table[$scenario] ?? $table['other'])[1];
    }

    public static function supporting(string $regulation, string $kind): ?string
    {
        return self::SUPPORTING[strtoupper($regulation)][$kind] ?? null;
    }

    /** Every citation a draft may legitimately use for this regime. */
    public static function allowed(string $regulation): array
    {
        $regulation = strtoupper($regulation);
        $articles   = collect(self::CITATIONS[$regulation] ?? [])->map(fn (array $row) => $row[0]);
        $supporting = collect(self::SUPPORTING[$regulation] ?? [])->filter();

        return $articles->merge($supporting)->unique()->values()->all();
    }

    /**
     * Normalise whatever an evaluator produced to the canonical article.
     * The AI's free-text citation is advisory only - the table decides.
     */
    public static function normalise(string $regulation, ?string $scenario, ?string $proposed): string
    {
        $canonical = self::article($regulation, $scenario);

        return $canonical !== '' ? $canonical : trim((string) $proposed);
    }

    /**
     * The disruption scenario for a claim, derived from verified facts first
     * and the passenger's report second - the same precedence the engine uses.
     */
    public static function scenario(EligibilityContext $context): string
    {
        if ($context->cancelled) {
            return 'cancelled';
        }

        if ($context->diverted) {
            return 'diverted';
        }

        if ($context->reportedDisruption && $context->reportedDisruption !== 'delayed') {
            return $context->reportedDisruption;
        }

        return 'delayed';
    }

    /** Scenario for a stored claim (no live context available). */
    public static function scenarioFromClaim(Claim $claim): string
    {
        if ($claim->flight_cancelled || $claim->disruption_type === 'cancelled') {
            return 'cancelled';
        }

        return $claim->disruption_type ?: 'delayed';
    }
}
