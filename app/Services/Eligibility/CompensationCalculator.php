<?php

namespace App\Services\Eligibility;

use App\Services\FlightAwareService;
use Throwable;

/**
 * Statutory compensation amounts for an eligible verdict.
 *
 * EU261/UK261 pay fixed tiers by great-circle route distance (with the
 * Article 7(2) 50% reduction), APPR by length of delay, US DOT is fare-based.
 * Distances come from FlightAware's cached airport coordinates.
 *
 * Alongside the amount, every result carries a structured "breakdown" the UI
 * renders visually: the regulation's tier table (with the applicable tier
 * marked active), the customer's own facts, and a one-line note on what the
 * amount does or doesn't depend on.
 *
 * @phpstan-type Breakdown array{tiers: array, facts: array, note: string}
 */
class CompensationCalculator
{
    public function __construct(private FlightAwareService $flightAware)
    {
    }

    /** @return ?array{amount: ?float, currency: string, basis: string, breakdown: array} */
    public function calculate(EligibilityResult $verdict, EligibilityContext $context, ?float $ticketPrice = null, ?string $ticketCurrency = null): ?array
    {
        if (!$verdict->eligible) {
            return null;
        }

        $disruption = $this->disruptionKind($context);
        $distanceKm = $this->routeDistanceKm($context);

        $result = match ($verdict->regulation) {
            'EU261'  => $this->euStyle($disruption, $distanceKm, $context, 'EUR', [250, 400, 600], $ticketPrice, $ticketCurrency),
            'UK261'  => $this->euStyle($disruption, $distanceKm, $context, 'GBP', [220, 350, 520], $ticketPrice, $ticketCurrency),
            'APPR'   => $this->appr($disruption, $context),
            'US_DOT' => $this->usDot($disruption, $ticketPrice, $ticketCurrency),
            default  => null,
        };

        if ($result) {
            $result['breakdown']['entitlements'] = $this->entitlements(
                $verdict->regulation, $disruption, $context, $result['amount'], $result['currency'], $ticketPrice, $ticketCurrency
            );
        }

        return $result;
    }

    /**
     * The passenger's separate entitlements under the winning regulation.
     * Compensation, ticket refund, re-routing and expense reimbursement are
     * independent rights - one never substitutes another, and compensation
     * is never derived from the ticket price (only fare-based remedies the
     * law defines that way are).
     */
    private function entitlements(string $regulation, string $disruption, EligibilityContext $context, ?float $amount, string $currency, ?float $ticketPrice, ?string $ticketCurrency): array
    {
        $fare = $ticketPrice ? sprintf('%s %s', $ticketCurrency ?: $currency, number_format($ticketPrice, 2)) : null;

        if ($regulation === 'US_DOT' && $disruption !== 'denied_boarding') {
            $compensation = ['state' => 'none', 'value' => 'Not mandated', 'detail' => 'US rules require a refund rather than fixed cash compensation for this disruption.'];
        } elseif ($amount !== null) {
            $compensation = ['state' => 'included', 'value' => sprintf('%s %s', $currency, number_format($amount, 2)),
                'detail' => $disruption === 'downgrade' || ($regulation === 'US_DOT' && $disruption === 'denied_boarding')
                    ? 'A fare-based remedy - this one the law defines as a share of what you paid.'
                    : 'Fixed by the regulation - independent of your ticket price.'];
        } else {
            $compensation = ['state' => 'conditional', 'value' => 'Pending details', 'detail' => 'Computed once the missing facts (fare or final arrival time) are on file.'];
        }

        if ($disruption === 'cancelled') {
            $refund = $context->didNotTravel
                ? ['state' => 'included', 'value' => $fare ?: 'Your full ticket price', 'detail' => 'You chose not to travel, so the unused ticket is refunded in full - on top of any compensation.']
                : ['state' => 'none', 'value' => 'Ticket used', 'detail' => 'You travelled on the rebooking, so the ticket was used - a refund applies only when you choose not to travel.'];
        } elseif ($disruption === 'denied_boarding') {
            $refund = ['state' => 'conditional', 'value' => 'If you choose not to travel', 'detail' => 'Declining the alternative flight entitles you to a full refund of the unused ticket instead.'];
        } elseif ($disruption === 'delayed' && in_array($regulation, ['EU261', 'UK261'], true)) {
            $refund = ['state' => 'conditional', 'value' => 'From a 5-hour delay', 'detail' => 'At 5+ hours you may abandon the journey and claim the unused ticket back (Article 8).'];
        } else {
            $refund = ['state' => 'none', 'value' => 'Not applicable', 'detail' => 'A ticket refund applies when a flight is cancelled or you choose not to travel.'];
        }

        if (in_array($disruption, ['cancelled', 'denied_boarding'], true)) {
            $rerouting = $context->didNotTravel
                ? ['state' => 'none', 'value' => 'Refund chosen', 'detail' => 'Re-routing and the refund are alternatives - you picked the refund.']
                : ['state' => 'included', 'value' => 'Alternative flight', 'detail' => 'The airline must re-route you to your destination at the earliest opportunity, at no extra cost.'];
        } elseif (in_array($disruption, ['diverted', 'missed_connection'], true)) {
            $rerouting = ['state' => 'included', 'value' => 'Onward transport', 'detail' => 'The airline must get you to your booked destination at no extra cost.'];
        } else {
            $rerouting = ['state' => 'none', 'value' => 'Not applicable', 'detail' => 'Applies when a flight is cancelled, diverted or you are denied boarding.'];
        }

        if ($disruption === 'downgrade') {
            $expenses = ['state' => 'none', 'value' => 'Not applicable', 'detail' => 'Expense reimbursement covers waits caused by delays and cancellations.'];
        } else {
            $expenses = match ($regulation) {
                'EU261', 'UK261' => ['state' => 'included', 'value' => 'With receipts', 'detail' => 'Right to care (Article 9): meals and refreshments during the wait, hotel and transfers if an overnight stay was needed - keep receipts.'],
                'APPR'           => ['state' => 'included', 'value' => 'With receipts', 'detail' => 'Standards of treatment (ss. 8-10): food, drink and, for overnight waits, hotel - when the disruption is within the airline\'s control. Keep receipts.'],
                default          => ['state' => 'conditional', 'value' => 'Airline policy', 'detail' => 'US rules leave meals and hotels to each airline\'s customer service commitments - keep receipts and check the airline\'s plan.'],
            };
        }

        return [
            array_merge(['key' => 'compensation', 'label' => 'Compensation'], $compensation),
            array_merge(['key' => 'refund', 'label' => 'Ticket refund'], $refund),
            array_merge(['key' => 'rerouting', 'label' => 'Re-routing'], $rerouting),
            array_merge(['key' => 'expenses', 'label' => 'Expenses'], $expenses),
        ];
    }

    private function disruptionKind(EligibilityContext $context): string
    {
        // Schedule changes and flights returned to their origin never
        // delivered the booked journey - the law treats both as cancellations.
        $cancelledLike = ['cancelled', 'schedule_change', 'returned_to_origin'];

        return match (true) {
            $context->cancelled || in_array($context->reportedDisruption, $cancelledLike, true) => 'cancelled',
            $context->reportedDisruption === 'denied_boarding'                   => 'denied_boarding',
            $context->reportedDisruption === 'downgrade'                         => 'downgrade',
            $context->reportedDisruption === 'missed_connection'                 => 'missed_connection',
            $context->diverted                                                   => 'diverted',
            default                                                              => 'delayed',
        };
    }

    // ── EU261 / UK261: fixed tiers by route distance ────────

    private function euStyle(string $disruption, ?float $distanceKm, EligibilityContext $context, string $currency, array $tiers, ?float $ticketPrice, ?string $ticketCurrency): array
    {
        if ($disruption === 'downgrade') {
            return $this->euDowngrade($distanceKm, $context, $currency, $ticketPrice, $ticketCurrency);
        }

        [$short, $medium, $long] = $tiers;

        $tierTable = [
            ['label' => 'Up to 1,500 km',    'amount' => "{$currency} {$short}",  'active' => $distanceKm !== null && $distanceKm <= 1500],
            ['label' => '1,500 - 3,500 km',  'amount' => "{$currency} {$medium}", 'active' => $distanceKm !== null && $distanceKm > 1500 && $distanceKm <= 3500],
            ['label' => 'Over 3,500 km',     'amount' => "{$currency} {$long}",   'active' => $distanceKm !== null && $distanceKm > 3500],
        ];

        $note = 'Fixed by law (Article 7) - the amount depends on route distance, not your ticket price.';

        if ($distanceKm === null) {
            return ['amount' => null, 'currency' => $currency, 'basis' => 'Fixed amount by route distance (Article 7) - distance could not be determined', 'breakdown' => [
                'tiers' => $tierTable,
                'facts' => [['label' => 'Your route', 'value' => 'Distance not determined yet - the exact tier is pending']],
                'note'  => $note,
            ]];
        }

        $amount = $distanceKm <= 1500 ? $short : ($distanceKm <= 3500 ? $medium : $long);
        $basis  = sprintf('Article 7 tier for a %s km route', number_format($distanceKm));

        $facts = [[
            'label' => 'Your route',
            'value' => sprintf('%s → %s = %s km', $context->departureAirport, $context->arrivalAirport, number_format($distanceKm)),
        ]];

        if ($disruption === 'cancelled') {
            if ($context->didNotTravel) {
                $facts[] = ['label' => 'Plus your refund', 'value' => $ticketPrice
                    ? sprintf('You chose not to travel, so a full refund of your ticket (%s %s) is owed on top (Article 8)', $ticketCurrency ?: $currency, number_format($ticketPrice, 2))
                    : 'You chose not to travel, so a full refund of your ticket is owed on top (Article 8) - add your ticket price to show it'];
            } else {
                $facts[] = ['label' => 'Your ticket', 'value' => 'You were rebooked and travelled, so the ticket was used - a refund applies only when you choose not to travel (Article 8)'];
            }
        }

        // Article 7(2)(c): long-haul delays under 4h pay 50%.
        if ($disruption === 'delayed' && $distanceKm > 3500 && $context->delayIsActual) {
            $delayHuman = sprintf('%dh %02dm', intdiv($context->arrivalDelayMinutes, 60), $context->arrivalDelayMinutes % 60);

            if ($context->arrivalDelayMinutes < 240) {
                $amount = round($amount / 2, 2);
                $basis .= ', reduced 50% (arrival delay under 4 hours, Article 7(2))';
                $facts[] = ['label' => 'Your delay', 'value' => "{$delayHuman} - under 4 hours on a long route, so the law halves the amount to {$currency} {$amount}"];
            } else {
                $facts[] = ['label' => 'Your delay', 'value' => "{$delayHuman} - over 4 hours, so the full amount applies"];
            }
        }

        return ['amount' => (float) $amount, 'currency' => $currency, 'basis' => $basis, 'breakdown' => [
            'tiers' => $tierTable, 'facts' => $facts, 'note' => $note,
        ]];
    }

    private function euDowngrade(?float $distanceKm, EligibilityContext $context, string $currency, ?float $ticketPrice, ?string $ticketCurrency): array
    {
        $pct = $distanceKm === null ? 50 : ($distanceKm <= 1500 ? 30 : ($distanceKm <= 3500 ? 50 : 75));

        $tierTable = [
            ['label' => 'Up to 1,500 km',   'amount' => '30% of fare', 'active' => $pct === 30],
            ['label' => '1,500 - 3,500 km', 'amount' => '50% of fare', 'active' => $pct === 50],
            ['label' => 'Over 3,500 km',    'amount' => '75% of fare', 'active' => $pct === 75],
        ];

        $facts = [[
            'label' => 'Your route',
            'value' => $distanceKm !== null
                ? sprintf('%s → %s = %s km', $context->departureAirport, $context->arrivalAirport, number_format($distanceKm))
                : 'Distance not determined - middle rate assumed',
        ]];

        $note = 'Downgrade reimbursement (Article 10) is a share of what you paid for the ticket.';

        if ($ticketPrice) {
            $amount   = round($ticketPrice * $pct / 100, 2);
            $facts[]  = ['label' => 'Calculation', 'value' => sprintf('%d%% × %s %s = %s %s', $pct, $ticketCurrency ?: $currency, number_format($ticketPrice, 2), $ticketCurrency ?: $currency, number_format($amount, 2))];

            return ['amount' => $amount, 'currency' => $ticketCurrency ?: $currency, 'basis' => "{$pct}% of the ticket price (Article 10)", 'breakdown' => [
                'tiers' => $tierTable, 'facts' => $facts, 'note' => $note,
            ]];
        }

        $facts[] = ['label' => 'Your fare', 'value' => 'Not on file yet - add it and the exact amount is computed from it'];

        return ['amount' => null, 'currency' => $currency, 'basis' => "{$pct}% of the ticket price (Article 10) - fare not on file yet", 'breakdown' => [
            'tiers' => $tierTable, 'facts' => $facts, 'note' => $note,
        ]];
    }

    // ── APPR: fixed tiers by length of delay (large carrier) ─

    private function appr(string $disruption, EligibilityContext $context): array
    {
        $delay      = $context->arrivalDelayMinutes;
        $delayHuman = sprintf('%dh %02dm', intdiv($delay, 60), $delay % 60);

        // Passenger abandoned the trip for a refund: flat CAD 400 (s.19(2))
        // plus the ticket money back (s.17) - the delay tiers don't apply
        // because they never arrived.
        if ($disruption === 'cancelled' && $context->didNotTravel) {
            return ['amount' => 400.0, 'currency' => 'CAD', 'basis' => 'APPR s.19(2) - compensation when a refund is chosen instead of rebooking', 'breakdown' => [
                'tiers' => [],
                'facts' => [
                    ['label' => 'Your choice', 'value' => 'You chose a refund instead of rebooking, so the flat CAD 400 applies (s.19(2)) - the delay tiers only apply when you travel'],
                    ['label' => 'Plus your refund', 'value' => 'A full refund of your unused ticket is owed on top (s.17)'],
                ],
                'note'  => 'Applies when the disruption was within the airline\'s control.',
            ]];
        }

        if ($disruption === 'denied_boarding') {
            $amount = $delay >= 540 ? 2400 : ($delay >= 360 ? 1800 : 900);

            return ['amount' => (float) $amount, 'currency' => 'CAD', 'basis' => 'APPR denied boarding tier (ss. 20(2))', 'breakdown' => [
                'tiers' => [
                    ['label' => 'Under 6h late',  'amount' => 'CAD 900',   'active' => $delay < 360],
                    ['label' => '6 - 9h late',    'amount' => 'CAD 1,800', 'active' => $delay >= 360 && $delay < 540],
                    ['label' => '9h+ late',       'amount' => 'CAD 2,400', 'active' => $delay >= 540],
                ],
                'facts' => [['label' => 'Reaching your destination', 'value' => "{$delayHuman} late"]],
                'note'  => 'Fixed by regulation (APPR ss. 20(2)) - not based on your ticket price.',
            ]];
        }

        $tierTable = [
            ['label' => '3 - 6h delay', 'amount' => 'CAD 400',   'active' => $delay >= 180 && $delay < 360],
            ['label' => '6 - 9h delay', 'amount' => 'CAD 700',   'active' => $delay >= 360 && $delay < 540],
            ['label' => '9h+ delay',    'amount' => 'CAD 1,000', 'active' => $delay >= 540],
        ];

        $note = 'Fixed by regulation (APPR s.19(1), large carriers) - applies when the disruption was within the airline\'s control. Not based on your ticket price.';

        if (!$context->delayIsActual && $delay < 180) {
            return ['amount' => null, 'currency' => 'CAD', 'basis' => 'APPR s.19(1) tier (CAD 400-1,000) - final arrival delay not verified yet', 'breakdown' => [
                'tiers' => $tierTable,
                'facts' => [['label' => 'Your delay', 'value' => 'Final arrival delay not verified yet - the exact tier is pending']],
                'note'  => $note,
            ]];
        }

        $amount = $delay >= 540 ? 1000 : ($delay >= 360 ? 700 : 400);

        $facts = $disruption === 'cancelled' && $delay < 180
            ? [['label' => 'Your rebooking', 'value' => 'How late you finally arrived is not on file - the CAD 400 baseline applies, rising to 700/1,000 if you reached your destination 6h/9h+ late']]
            : [['label' => 'Your delay', 'value' => "{$delayHuman} at your destination"]];

        if ($disruption === 'cancelled') {
            $facts[] = ['label' => 'Your ticket', 'value' => 'You were rebooked and travelled, so the ticket was used - a refund applies only when you choose not to travel (s.17)'];
        }

        $basis = $disruption === 'cancelled' && $delay < 180
            ? 'APPR s.19(1) baseline for a cancellation (large carrier)'
            : sprintf('APPR s.19(1) tier for a %dh delay (large carrier)', intdiv($delay, 60));

        return ['amount' => (float) $amount, 'currency' => 'CAD', 'basis' => $basis, 'breakdown' => [
            'tiers' => $tierTable,
            'facts' => $facts,
            'note'  => $note,
        ]];
    }

    // ── US DOT: fare-based ──────────────────────────────────

    private function usDot(string $disruption, ?float $ticketPrice, ?string $ticketCurrency): array
    {
        $currency = $ticketCurrency ?: 'USD';

        if ($disruption === 'denied_boarding') {
            $facts = $ticketPrice
                ? [['label' => 'Calculation', 'value' => sprintf('400%% × %s %s (capped by the regulation)', $currency, number_format($ticketPrice, 2))]]
                : [['label' => 'Your fare', 'value' => 'Not on file yet - add it and the exact amount is computed from it']];

            return [
                'amount'    => $ticketPrice ? round(min($ticketPrice * 4, 2150), 2) : null,
                'currency'  => $currency,
                'basis'     => 'Up to 400% of the one-way fare (14 CFR Part 250)' . ($ticketPrice ? '' : ' - fare not on file yet'),
                'breakdown' => [
                    'tiers' => [],
                    'facts' => $facts,
                    'note'  => 'US rules set denied boarding compensation as a multiple of your fare (14 CFR Part 250), up to 400% of the one-way fare.',
                ],
            ];
        }

        // Cancellations: a refund of the unused ticket, not compensation.
        $facts = $ticketPrice
            ? [['label' => 'Refund', 'value' => sprintf('Equals your ticket price on file: %s %s', $currency, number_format($ticketPrice, 2))]]
            : [['label' => 'Your fare', 'value' => 'Not on file yet - once added, the refund amount equals it']];

        return [
            'amount'    => $ticketPrice ? (float) $ticketPrice : null,
            'currency'  => $currency,
            'basis'     => 'Full refund of the unused ticket (14 CFR Part 260)' . ($ticketPrice ? '' : ' - fare not on file yet'),
            'breakdown' => [
                'tiers' => [],
                'facts' => $facts,
                'note'  => 'The US mandates a full refund of the unused ticket for cancellations (14 CFR Part 260), rather than fixed cash compensation.',
            ],
        ];
    }

    // ── Route distance ──────────────────────────────────────

    /** Great-circle distance between the two airports, from cached coordinates. */
    public function routeDistanceKm(EligibilityContext $context): ?float
    {
        $origin      = $this->airportCoordinates($context->departureAirport);
        $destination = $this->airportCoordinates($context->arrivalAirport);

        if (!$origin || !$destination) {
            return null;
        }

        [$lat1, $lng1] = $origin;
        [$lat2, $lng2] = $destination;

        $rad = M_PI / 180;
        $a   = sin((($lat2 - $lat1) * $rad) / 2) ** 2
            + cos($lat1 * $rad) * cos($lat2 * $rad) * sin((($lng2 - $lng1) * $rad) / 2) ** 2;

        return round(6371 * 2 * atan2(sqrt($a), sqrt(1 - $a)), 1);
    }

    private function airportCoordinates(?string $code): ?array
    {
        try {
            $info = $code ? $this->flightAware->airportInfo($code) : null;
        } catch (Throwable) {
            return null;
        }

        return isset($info['latitude'], $info['longitude'])
            ? [(float) $info['latitude'], (float) $info['longitude']]
            : null;
    }
}
