<?php

namespace App\Services\Eligibility\Rules;

use App\Services\Eligibility\EligibilityContext;
use App\Services\Eligibility\EligibilityResult;
use App\Services\Eligibility\RegulationRule;

/**
 * Canada's Air Passenger Protection Regulations (SOR/2019-150) - flights
 * to, from and within Canada. Compensation for delays of 3+ hours and
 * short-notice cancellations, but only when the disruption is within the
 * carrier's control - a condition we can't verify yet, so APPR verdicts
 * carry a larger confidence penalty than EU-style ones.
 */
class ApprRule implements RegulationRule
{
    public function code(): string
    {
        return 'APPR';
    }

    public function applies(EligibilityContext $context): bool
    {
        $canada = config('eligibility.canada', []);

        return $context->originIn($canada) || $context->destinationIn($canada);
    }

    public function evaluate(EligibilityContext $context): EligibilityResult
    {
        $factors = ['Whether the disruption was within the carrier\'s control is not yet verified (APPR only compensates controllable disruptions).'];
        $score   = 75;

        if ($context->cancelled) {
            return new EligibilityResult(
                $this->code(),
                true,
                'Sections 12(2) & 19',
                $score,
                'The flight was cancelled on short notice, which entitles passengers to compensation when within the carrier\'s control.',
                $factors,
            );
        }

        $unverified = 'Reported by the passenger - not verifiable from flight data.';

        if ($context->reportedDisruption === 'denied_boarding') {
            return new EligibilityResult(
                $this->code(),
                true,
                'Sections 20-22',
                $score - 15,
                'Being denied boarding (e.g. overbooking) entitles passengers to compensation of up to CAD 2,400 under APPR.',
                [...$factors, $unverified],
            );
        }

        if ($context->reportedDisruption === 'downgrade') {
            return new EligibilityResult(
                $this->code(),
                false,
                'Section 19',
                85,
                'APPR does not provide a fixed downgrade reimbursement - EU/UK rules may apply instead if the route is covered by them.',
                $factors,
            );
        }

        if ($context->reportedDisruption === 'missed_connection') {
            return new EligibilityResult(
                $this->code(),
                true,
                'Section 19(1)',
                $score - 25,
                'A missed connection caused by the carrier entitles passengers to compensation based on the delay at their final destination.',
                [...$factors, 'Arrival time at the final destination is not verified yet.', $unverified],
            );
        }

        if ($context->reportedDisruption === 'other') {
            return new EligibilityResult(
                $this->code(),
                false,
                'Section 19',
                40,
                'The reported issue could not be matched to a compensable disruption automatically - our team will review it.',
                $factors,
            );
        }

        if ($context->diverted) {
            return new EligibilityResult(
                $this->code(),
                true,
                'Sections 17 & 19',
                $score - 10,
                'The flight was diverted, which entitles passengers to compensation if the resulting delay at their destination is 3+ hours and within the carrier\'s control.',
                [...$factors, 'Final arrival delay at the booked destination is not verified yet.'],
            );
        }

        $threshold = (int) config('eligibility.delay_thresholds.appr', 180);
        $delay     = $context->arrivalDelayMinutes;

        if ($delay < $threshold) {
            return new EligibilityResult(
                $this->code(),
                false,
                'Section 19(1)',
                90,
                sprintf('Arrival delay of %d minutes is below the 3-hour threshold for compensation.', $delay),
            );
        }

        if (!$context->delayIsActual) {
            $score -= 15;
            $factors[] = 'Delay is based on estimated (not actual) arrival time.';
        }

        // APPR tiers compensation at 3–6h / 6–9h / 9h+ for large carriers.
        $tier = $delay >= 540 ? '9+ hours' : ($delay >= 360 ? '6–9 hours' : '3–6 hours');

        return new EligibilityResult(
            $this->code(),
            true,
            'Section 19(1)(a)',
            $score,
            sprintf('The flight arrived about %dh %02dm late (APPR %s tier), which qualifies for compensation if the delay was within the carrier\'s control.', intdiv($delay, 60), $delay % 60, $tier),
            $factors,
        );
    }
}
