<?php

namespace App\Services\Eligibility\Rules;

use App\Services\Eligibility\EligibilityContext;
use App\Services\Eligibility\EligibilityResult;
use App\Services\Eligibility\RegulationRule;

/**
 * Shared logic for EU261 and its UK retained-law twin (UK261): both grant
 * compensation for cancellations (Articles 5 & 7) and for arrival delays
 * of 3+ hours (Article 7, per the Sturgeon ruling), with the same scope
 * rule — any departure from the territory, or an arrival into it on a
 * carrier of that territory.
 */
abstract class EuStyleRule implements RegulationRule
{
    /** Countries where this regulation applies. */
    abstract protected function countries(): array;

    /** Minimum arrival delay (minutes) for Article 7 compensation. */
    abstract protected function delayThreshold(): int;

    public function applies(EligibilityContext $context): bool
    {
        // Departures are always covered. Arrivals are only covered on a
        // carrier of the territory — we can't verify carrier nationality
        // yet, so inbound flights still apply but score lower confidence.
        return $context->originIn($this->countries()) || $context->destinationIn($this->countries());
    }

    public function evaluate(EligibilityContext $context): EligibilityResult
    {
        $factors  = [];
        $score    = 90;
        $departed = $context->originIn($this->countries());

        if (!$departed) {
            $score -= 25;
            $factors[] = 'Inbound flight: coverage requires a ' . $this->code() . ' carrier, which is not verified yet.';
        }

        // The airline can avoid paying when the disruption was caused by
        // extraordinary circumstances (weather, ATC…) — unknown at this stage.
        $score -= 10;
        $factors[] = 'Disruption cause (extraordinary circumstances) not yet verified.';

        if ($context->cancelled) {
            return new EligibilityResult(
                $this->code(),
                true,
                'Articles 5 & 7',
                $score,
                'The flight was cancelled without the 14-day notice window, which entitles passengers to compensation.',
                [...$factors, 'Cancellation detected while actively monitoring the flight (short notice).'],
            );
        }

        $delay = $context->arrivalDelayMinutes;

        if ($delay < $this->delayThreshold()) {
            return new EligibilityResult(
                $this->code(),
                false,
                'Article 7',
                90,
                sprintf('Arrival delay of %d minutes is below the 3-hour threshold for compensation.', $delay),
            );
        }

        if (!$context->delayIsActual) {
            $score -= 15;
            $factors[] = 'Delay is based on estimated (not actual) arrival time.';
        } else {
            $score += 5;
            $factors[] = 'Delay confirmed from actual arrival time.';
        }

        if ($delay < $this->delayThreshold() + 15) {
            $score -= 10;
            $factors[] = 'Delay is within 15 minutes of the legal threshold.';
        }

        return new EligibilityResult(
            $this->code(),
            true,
            'Article 7(1)',
            $score,
            sprintf('The flight arrived %s late — 3 hours or more entitles passengers to compensation.', $this->humanDelay($delay)),
            $factors,
        );
    }

    protected function humanDelay(int $minutes): string
    {
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;

        return $h ? ($m ? "{$h}h {$m}m" : "{$h} hours") : "{$m} minutes";
    }
}
