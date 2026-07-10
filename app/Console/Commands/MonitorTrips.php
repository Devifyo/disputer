<?php

namespace App\Console\Commands;

use App\Jobs\SyncTripFlight;
use App\Models\Trip;
use Illuminate\Console\Command;

/**
 * Dispatches a FlightAware sync for every protected trip whose next
 * monitoring checkpoint (T-24h … T+24h) has arrived. Scheduled every
 * five minutes.
 */
class MonitorTrips extends Command
{
    protected $signature = 'trips:monitor {--sync : Run synchronously instead of queueing}';

    protected $description = 'Poll FlightAware for protected trips that hit a monitoring checkpoint';

    public function handle(): int
    {
        $due = Trip::whereIn('monitoring_status', [Trip::MONITORING_PENDING, Trip::MONITORING_ACTIVE])
            ->whereNotNull('next_poll_at')
            ->where('next_poll_at', '<=', now())
            ->get();

        foreach ($due as $trip) {
            $this->option('sync')
                ? SyncTripFlight::dispatchSync($trip)
                : SyncTripFlight::dispatch($trip);
        }

        $this->info("Dispatched {$due->count()} trip sync job(s).");

        return self::SUCCESS;
    }
}
