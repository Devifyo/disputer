<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A future trip registered for protection ("Protect Your Trip") — added
 * manually or extracted from an uploaded itinerary. Not a claim: it exists
 * before any disruption so Unjamm can monitor the flight.
 */
class Trip extends Model
{
    use SoftDeletes;

    public const STATUS_PROTECTED = 'protected';

    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_UPLOAD = 'upload';

    protected $fillable = [
        'user_id', 'itinerary_id', 'source', 'status',
        'airline', 'flight_number',
        'departure_airport', 'departure_city', 'arrival_airport', 'arrival_city',
        'departure_date', 'departure_time',
        'booking_reference', 'passenger_name', 'passengers',
        'ticket_file_path', 'ticket_price', 'ticket_currency', 'delay_score',
    ];

    protected $casts = [
        'departure_date' => 'date',
        'passengers'     => 'array',
        'ticket_price'   => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function itinerary(): BelongsTo
    {
        return $this->belongsTo(Itinerary::class);
    }

    public function isUpcoming(): bool
    {
        // departure_date is date-cast (midnight), so compare against end of day:
        // a flight departing later today is still upcoming.
        return $this->departure_date !== null && $this->departure_date->endOfDay()->isFuture();
    }
}
