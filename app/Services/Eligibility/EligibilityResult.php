<?php

namespace App\Services\Eligibility;

/** One regulation's verdict on a disrupted trip. */
class EligibilityResult
{
    /**
     * @param array<string> $factors human-readable notes that shaped the confidence score
     * @param array<string, bool> $flags advisory recommendations from the evaluator
     *        (refund_recommended, expenses_recommended, manual_review_recommended) -
     *        they never set amounts; the deterministic engine stays authoritative
     */
    public function __construct(
        public string $regulation,   // EU261 | UK261 | APPR | US_DOT
        public bool $eligible,
        public string $article,      // legal basis, e.g. "Article 7(1)"
        public int $confidence,      // 0–100
        public string $reason,
        public array $factors = [],
        public array $flags = [],
    ) {
        $this->confidence = max(0, min(100, $this->confidence));
    }

    public function flagged(string $flag): bool
    {
        return (bool) ($this->flags[$flag] ?? false);
    }

    public function toArray(): array
    {
        return array_filter([
            'regulation' => $this->regulation,
            'eligible'   => $this->eligible,
            'article'    => $this->article,
            'confidence' => $this->confidence,
            'reason'     => $this->reason,
            'factors'    => $this->factors,
            'flags'      => $this->flags,
        ], fn ($v, $k) => $k !== 'flags' || $v !== [], ARRAY_FILTER_USE_BOTH);
    }
}
