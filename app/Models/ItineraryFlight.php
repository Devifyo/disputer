<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItineraryFlight extends Model
{
    protected $fillable = [
        'itinerary_id',
        'sequence',
        'airline',
        'flight_number',
        'departure_airport',
        'arrival_airport',
        'departure_at',
        'arrival_at',
        'cabin_class',
    ];

    protected $casts = [
        'departure_at' => 'datetime',
        'arrival_at'   => 'datetime',
    ];

    public function itinerary(): BelongsTo
    {
        return $this->belongsTo(Itinerary::class);
    }
}
