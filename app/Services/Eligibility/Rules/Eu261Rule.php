<?php

namespace App\Services\Eligibility\Rules;

/** Regulation (EC) No 261/2004 - EU-27, EEA and Switzerland. */
class Eu261Rule extends EuStyleRule
{
    public function code(): string
    {
        return 'EU261';
    }

    protected function countries(): array
    {
        return config('eligibility.eu261_countries', []);
    }

    protected function delayThreshold(): int
    {
        return (int) config('eligibility.delay_thresholds.eu261', 180);
    }
}
