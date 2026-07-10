<?php

namespace App\Services\Eligibility;

/**
 * A strategy that judges a disrupted trip under every applicable
 * regulation and returns one EligibilityResult per regime.
 */
interface EligibilityEvaluator
{
    /** Identifier stored with the verdict, e.g. "ai" or "rules". */
    public function name(): string;

    /** @return array<EligibilityResult> */
    public function evaluate(EligibilityContext $context): array;
}
