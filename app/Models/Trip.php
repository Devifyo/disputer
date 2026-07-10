<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A future trip registered for protection ("Protect Your Trip") — added
 * manually or extracted from an uploaded itinerary. Not a claim: it exists
 * before any disruption so Unjamm can monitor the flight via FlightAware.
 */
class Trip extends Model
{
    use SoftDeletes;

    public const STATUS_PROTECTED = 'protected';

    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_UPLOAD = 'upload';

    // Monitoring lifecycle
    public const MONITORING_PENDING   = 'pending';    // not yet registered with FlightAware
    public const MONITORING_ACTIVE    = 'monitoring'; // registered, polling until completion
    public const MONITORING_COMPLETED = 'completed';  // flight landed / cancelled — no more polls
    public const MONITORING_FAILED    = 'failed';     // flight could not be matched on FlightAware

    // Flight status (normalized from FlightAware)
    public const FLIGHT_SCHEDULED = 'scheduled';
    public const FLIGHT_ON_TIME   = 'on_time';
    public const FLIGHT_DELAYED   = 'delayed';
    public const FLIGHT_CANCELLED = 'cancelled';
    public const FLIGHT_COMPLETED = 'completed';

    protected $fillable = [
        'user_id', 'itinerary_id', 'source', 'status',
        'airline', 'flight_number',
        'departure_airport', 'departure_city', 'arrival_airport', 'arrival_city',
        'departure_date', 'departure_time',
        'booking_reference', 'passenger_name', 'passengers',
        'ticket_file_path', 'ticket_price', 'ticket_currency', 'delay_score',
        'fa_flight_id', 'fa_ident', 'flight_status', 'monitoring_status', 'flight_status_text',
        'scheduled_departure', 'scheduled_arrival', 'estimated_departure', 'estimated_arrival',
        'actual_departure', 'actual_arrival', 'departure_delay_minutes', 'arrival_delay_minutes',
        'origin_gate', 'destination_gate', 'potentially_eligible', 'disruption_notified_at',
        'route_stats', 'last_synced_at', 'next_poll_at',
    ];

    protected $casts = [
        'departure_date'          => 'date',
        'passengers'              => 'array',
        'ticket_price'            => 'decimal:2',
        'scheduled_departure'     => 'datetime',
        'scheduled_arrival'       => 'datetime',
        'estimated_departure'     => 'datetime',
        'estimated_arrival'       => 'datetime',
        'actual_departure'        => 'datetime',
        'actual_arrival'          => 'datetime',
        'potentially_eligible'    => 'boolean',
        'disruption_notified_at'  => 'datetime',
        'route_stats'             => 'array',
        'last_synced_at'          => 'datetime',
        'next_poll_at'            => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function itinerary(): BelongsTo
    {
        return $this->belongsTo(Itinerary::class);
    }

    public function monitorLogs(): HasMany
    {
        return $this->hasMany(TripMonitorLog::class)->orderByDesc('polled_at');
    }

    public function events(): HasMany
    {
        return $this->hasMany(TripEvent::class)->orderByDesc('detected_at');
    }

    public function isUpcoming(): bool
    {
        // departure_date is date-cast (midnight), so compare against end of day:
        // a flight departing later today is still upcoming.
        return $this->departure_date !== null && $this->departure_date->endOfDay()->isFuture();
    }

    /** Ident used to look the flight up on FlightAware, e.g. "AC845". */
    public function flightIdent(): ?string
    {
        $ident = $this->fa_ident ?: $this->flight_number;

        return $ident ? strtoupper(preg_replace('/\s+/', '', $ident)) : null;
    }

    /** Best-known departure moment (FlightAware scheduled time wins over the parsed one). */
    public function departureMoment(): ?Carbon
    {
        if ($this->scheduled_departure) {
            return $this->scheduled_departure;
        }
        if (!$this->departure_date) {
            return null;
        }
        $time = $this->departure_time ?: '12:00';

        return $this->departure_date->copy()->setTimeFromTimeString($time);
    }

    /**
     * Status shown on the Trip Protection dashboard:
     * scheduled | monitoring | on_time | delayed | cancelled | completed |
     * potentially_eligible | eligibility_review_pending
     */
    public function displayStatus(): string
    {
        if ($this->potentially_eligible) {
            $flightOver = $this->monitoring_status === self::MONITORING_COMPLETED
                || in_array($this->flight_status, [self::FLIGHT_CANCELLED, self::FLIGHT_COMPLETED], true);

            return $flightOver ? 'eligibility_review_pending' : 'potentially_eligible';
        }

        if ($this->flight_status === self::FLIGHT_CANCELLED) {
            return 'cancelled';
        }
        if ($this->flight_status === self::FLIGHT_COMPLETED) {
            return 'completed';
        }
        if ($this->flight_status === self::FLIGHT_DELAYED) {
            return 'delayed';
        }
        if ($this->flight_status === self::FLIGHT_ON_TIME) {
            return 'on_time';
        }
        if ($this->monitoring_status === self::MONITORING_ACTIVE) {
            return 'monitoring';
        }

        return 'scheduled';
    }
}
