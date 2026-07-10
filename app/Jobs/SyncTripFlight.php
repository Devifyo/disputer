<?php

namespace App\Jobs;

use App\Models\Trip;
use App\Services\TripMonitoringService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * One FlightAware sync cycle for a protected trip — dispatched right after
 * a trip is created (registration) and by trips:monitor at each checkpoint.
 */
class SyncTripFlight implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int> seconds between retries */
    public array $backoff = [60, 300];

    /** Trip removed before the job ran → drop the job, don't fail it. */
    public bool $deleteWhenMissingModels = true;

    public function __construct(public Trip $trip, public string $trigger = 'schedule')
    {
    }

    public function handle(TripMonitoringService $monitor): void
    {
        // Trip may have been removed between dispatch and execution.
        if (!$this->trip->exists || $this->trip->trashed()) {
            return;
        }

        $monitor->sync($this->trip->refresh(), $this->trigger);
    }
}
