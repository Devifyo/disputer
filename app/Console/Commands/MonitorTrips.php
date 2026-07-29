<?php

namespace App\Console\Commands;

use App\Jobs\SyncTripFlight;
use App\Models\Trip;
use App\Support\Modules;
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
        // Module switch: monitoring pauses while Protect Your Trip is off -
        // polls resume from where they left off when it is switched back on.
        if (!Modules::enabled(Modules::TRIPS)) {
            $this->info('Trip Protection module is switched off - skipping.');

            return self::SUCCESS;
        }

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
