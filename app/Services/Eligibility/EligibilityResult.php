<?php

namespace App\Services\Eligibility;

/** One regulation's verdict on a disrupted trip. */
class EligibilityResult
{
    /**
     * @param array<string> $factors human-readable notes that shaped the confidence score
     */
    public function __construct(
        public string $regulation,   // EU261 | UK261 | APPR | US_DOT
        public bool $eligible,
        public string $article,      // legal basis, e.g. "Article 7(1)"
        public int $confidence,      // 0–100
        public string $reason,
        public array $factors = [],
    ) {
        $this->confidence = max(0, min(100, $this->confidence));
    }

    public function toArray(): array
    {
        return [
            'regulation' => $this->regulation,
            'eligible'   => $this->eligible,
            'article'    => $this->article,
            'confidence' => $this->confidence,
            'reason'     => $this->reason,
            'factors'    => $this->factors,
        ];
    }
}
