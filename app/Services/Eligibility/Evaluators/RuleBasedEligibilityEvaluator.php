<?php

namespace App\Services\Eligibility\Evaluators;

use App\Services\Eligibility\EligibilityContext;
use App\Services\Eligibility\EligibilityEvaluator;
use App\Services\Eligibility\Rules\ApprRule;
use App\Services\Eligibility\Rules\Eu261Rule;
use App\Services\Eligibility\Rules\Uk261Rule;
use App\Services\Eligibility\Rules\UsDotRule;

/**
 * Deterministic evaluation using the hand-written regulation rules.
 * Serves as the always-available fallback behind the AI evaluator.
 */
class RuleBasedEligibilityEvaluator implements EligibilityEvaluator
{
    public function name(): string
    {
        return 'rules';
    }

    public function evaluate(EligibilityContext $context): array
    {
        $outcomes = [];

        foreach ([new Eu261Rule(), new Uk261Rule(), new ApprRule(), new UsDotRule()] as $rule) {
            if ($rule->applies($context)) {
                $outcomes[] = $rule->evaluate($context);
            }
        }

        return $outcomes;
    }
}
