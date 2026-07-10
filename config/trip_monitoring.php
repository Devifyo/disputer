<?php

/*
|--------------------------------------------------------------------------
| Trip Protection — FlightAware monitoring
|--------------------------------------------------------------------------
| Every protected trip is polled against FlightAware AeroAPI at fixed
| checkpoints around its scheduled departure. Offsets are minutes relative
| to departure (negative = before). The scheduler runs trips:monitor every
| five minutes and dispatches a sync for any trip whose next checkpoint
| has passed.
*/

return [

    // T-24h, T-6h, T-2h, departure, T+2h, T+4h, T+8h, T+24h
    'checkpoints' => [-1440, -360, -120, 0, 120, 240, 480, 1440],

    // Arrival/departure delay (minutes) from which a trip is flagged
    // "Potentially Eligible" and the user is notified. EU261-style
    // compensation generally starts at 3 hours.
    'qualifying_delay_minutes' => (int) env('TRIP_QUALIFYING_DELAY_MINUTES', 180),

    // Smaller delays are still recorded as events (but don't notify).
    'notable_delay_minutes' => (int) env('TRIP_NOTABLE_DELAY_MINUTES', 15),

    // Retries for a failed AeroAPI request within one sync.
    'api_retries'        => 2,
    'api_retry_delay_ms' => 500,
];
