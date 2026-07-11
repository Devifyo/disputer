<?php

namespace App\Services\Eligibility\Rules;

use App\Services\Eligibility\EligibilityContext;
use App\Services\Eligibility\EligibilityResult;
use App\Services\Eligibility\RegulationRule;

/**
 * US DOT rules - flights to, from and within the United States. The US
 * mandates no cash compensation for delays; cancellations entitle the
 * passenger to a full refund of the unused ticket (DOT refund rule,
 * 14 CFR Part 260).
 */
class UsDotRule implements RegulationRule
{
    public function code(): string
    {
        return 'US_DOT';
    }

    public function applies(EligibilityContext $context): bool
    {
        $usa = config('eligibility.usa', []);

        return $context->originIn($usa) || $context->destinationIn($usa);
    }

    public function evaluate(EligibilityContext $context): EligibilityResult
    {
        // Denied boarding is the one case where US DOT mandates cash
        // compensation (up to 400% of the one-way fare).
        if ($context->reportedDisruption === 'denied_boarding') {
            return new EligibilityResult(
                $this->code(),
                true,
                '14 CFR Part 250',
                65,
                'Involuntary denied boarding entitles passengers to cash compensation of up to 400% of the one-way fare under US DOT rules.',
                ['Reported by the passenger - not verifiable from flight data.'],
            );
        }

        if ($context->reportedDisruption === 'cancelled') {
            return new EligibilityResult(
                $this->code(),
                true,
                '14 CFR Part 260',
                60,
                'A cancelled flight entitles passengers to a full refund of the unused ticket under US DOT rules, subject to verification.',
                ['Reported by the passenger - not verifiable from flight data.'],
            );
        }

        if (in_array($context->reportedDisruption, ['downgrade', 'missed_connection', 'delayed', 'other'], true)) {
            return new EligibilityResult(
                $this->code(),
                false,
                '14 CFR Part 260',
                85,
                'US DOT rules do not mandate cash compensation for this kind of disruption - a fare-difference refund may still apply.',
            );
        }

        if ($context->cancelled) {
            return new EligibilityResult(
                $this->code(),
                true,
                '14 CFR Part 260',
                85,
                'The flight was cancelled - US DOT rules entitle passengers to a full refund of the unused ticket.',
                ['US DOT mandates refunds, not cash compensation, for cancellations.'],
            );
        }

        return new EligibilityResult(
            $this->code(),
            false,
            '14 CFR Part 260',
            90,
            'US DOT rules do not mandate cash compensation for flight delays.',
            ['A significantly delayed flight may still qualify for a refund if the passenger chose not to travel.'],
        );
    }
}
