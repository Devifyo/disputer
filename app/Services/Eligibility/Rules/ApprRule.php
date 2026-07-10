<?php

namespace App\Services\Eligibility\Rules;

use App\Services\Eligibility\EligibilityContext;
use App\Services\Eligibility\EligibilityResult;
use App\Services\Eligibility\RegulationRule;

/**
 * Canada's Air Passenger Protection Regulations (SOR/2019-150) — flights
 * to, from and within Canada. Compensation for delays of 3+ hours and
 * short-notice cancellations, but only when the disruption is within the
 * carrier's control — a condition we can't verify yet, so APPR verdicts
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
