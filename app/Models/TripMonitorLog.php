<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One FlightAware polling cycle for a monitored trip. */
class TripMonitorLog extends Model
{
    public const RESULT_SYNCED    = 'synced';
    public const RESULT_NOT_FOUND = 'not_found';
    public const RESULT_ERROR     = 'error';

    protected $fillable = [
        'trip_id', 'polled_at', 'trigger', 'flight_status',
        'departure_delay_minutes', 'arrival_delay_minutes',
        'http_status', 'result', 'error_message',
    ];

    protected $casts = [
        'polled_at' => 'datetime',
    ];

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }
}
