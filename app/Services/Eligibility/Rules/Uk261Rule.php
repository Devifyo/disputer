<?php

namespace App\Services\Eligibility\Rules;

/** UK261 — Regulation (EC) No 261/2004 as retained in UK law post-Brexit. */
class Uk261Rule extends EuStyleRule
{
    public function code(): string
    {
        return 'UK261';
    }

    protected function countries(): array
    {
        return config('eligibility.uk_countries', []);
    }

    protected function delayThreshold(): int
    {
        return (int) config('eligibility.delay_thresholds.uk261', 180);
    }
}
