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
    public const FLIGHT_DIVERTED  = 'diverted';
    public const FLIGHT_COMPLETED = 'completed';

    // Disruptions the passenger reports (or we infer) because no flight-data
    // API can observe them.
    public const REPORTABLE_DISRUPTIONS = ['denied_boarding', 'downgrade', 'missed_connection', 'other'];

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
        'origin_gate', 'destination_gate', 'origin_terminal', 'destination_terminal',
        'route_distance_miles', 'progress_percent', 'diverted', 'reported_disruption', 'report_details', 'reported_at',
        'potentially_eligible', 'disruption_notified_at',
        'route_stats', 'last_synced_at', 'next_poll_at',
        'eligibility_status', 'eligibility_regulation', 'eligibility_article',
        'eligibility_confidence', 'eligibility_reason', 'eligibility_details', 'eligibility_evaluated_at',
        'eligibility_decision_source', 'eligibility_decided_by', 'eligibility_decided_at',
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
        'diverted'                => 'boolean',
        'report_details'          => 'array',
        'reported_at'             => 'datetime',
        'potentially_eligible'    => 'boolean',
        'disruption_notified_at'  => 'datetime',
        'route_stats'             => 'array',
        'last_synced_at'          => 'datetime',
        'next_poll_at'            => 'datetime',
        'eligibility_details'      => 'array',
        'eligibility_evaluated_at' => 'datetime',
        'eligibility_decided_at'   => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function itinerary(): BelongsTo
    {
        return $this->belongsTo(Itinerary::class);
    }

    public function claims(): HasMany
    {
        return $this->hasMany(Claim::class);
    }

    /** Admin who manually approved/rejected the eligibility verdict. */
    public function eligibilityDecider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'eligibility_decided_by');
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
     * Plain-language reason why a finished, undisrupted trip has no claim -
     * null while the flight is still live or when a disruption was flagged
     * (those trips get an eligibility verdict instead).
     */
    public function noClaimExplanation(): ?string
    {
        $finished = $this->monitoring_status === self::MONITORING_COMPLETED
            && $this->flight_status === self::FLIGHT_COMPLETED
            && !$this->potentially_eligible;

        if (!$finished) {
            return null;
        }

        $delay = max(0, (int) $this->arrival_delay_minutes);

        if ($delay <= 15) {
            return 'This flight arrived on time, so there is nothing to claim - that\'s the good kind of trip.';
        }

        $threshold = (int) config('trip_monitoring.qualifying_delay_minutes', 180);
        $h         = intdiv($threshold, 60);

        return sprintf(
            'This flight arrived %d minutes late. Compensation rules (EU261, UK261, APPR) only apply from %d hours of arrival delay, so this trip does not qualify for a claim.',
            $delay,
            $h
        );
    }

    /** Where the aircraft is right now: scheduled | enroute | landed | cancelled. */
    public function flightPhase(): string
    {
        if ($this->flight_status === self::FLIGHT_CANCELLED) {
            return 'cancelled';
        }
        if ($this->actual_arrival) {
            return 'landed';
        }
        if ($this->actual_departure) {
            return 'enroute';
        }

        return 'scheduled';
    }

    /**
     * Status shown on the Trip Protection dashboard:
     * scheduled | monitoring | on_time | delayed | cancelled | completed |
     * potentially_eligible | eligibility_review_pending | eligible | not_eligible
     */
    public function displayStatus(): string
    {
        // The Eligibility Engine's verdict is final — it outranks the
        // interim "potentially eligible / review pending" states. Once the
        // claim is filed, the trip needs no further attention.
        if ($this->eligibility_status === 'eligible') {
            $hasClaims = $this->claims_exists ?? $this->claims()->exists();

            return $hasClaims ? 'claim_filed' : 'eligible';
        }
        if ($this->eligibility_status === 'review') {
            return 'eligibility_review_pending';
        }
        if ($this->eligibility_status === 'rejected') {
            return 'not_eligible';
        }

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
