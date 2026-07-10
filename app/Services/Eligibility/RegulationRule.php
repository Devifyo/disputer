<?php

namespace App\Services\Eligibility;

/**
 * One air passenger rights regime (EU261, UK261, APPR, US DOT).
 * A rule first decides whether it covers the route at all, then judges
 * the disruption and reports its verdict with a legal article and a
 * confidence score.
 */
interface RegulationRule
{
    /** Short regulation code, e.g. "EU261". */
    public function code(): string;

    /** Does this regulation cover the trip's route at all? */
    public function applies(EligibilityContext $context): bool;

    /** Judge the disruption. Only called when applies() is true. */
    public function evaluate(EligibilityContext $context): EligibilityResult;
}
