<?php

namespace App\Jobs;

use App\Models\Claim;
use App\Services\Eligibility\ClaimEligibilityService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * FlightAware verification + eligibility verdict + compensation for a new
 * claim - dispatched from every claim creation path.
 */
class EvaluateClaim implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int> seconds between retries */
    public array $backoff = [60, 300];

    /** FlightAware + AI eligibility can exceed the 60s default. */
    public int $timeout = 120;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public Claim $claim)
    {
    }

    public function handle(ClaimEligibilityService $service): void
    {
        if (!$this->claim->exists || $this->claim->trashed()) {
            return;
        }

        $service->evaluate($this->claim->refresh());
    }
}
