<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ItineraryPassenger extends Model
{
    protected $fillable = [
        'itinerary_id',
        'full_name',
        'type',
        'ticket_number',
    ];

    public function itinerary(): BelongsTo
    {
        return $this->belongsTo(Itinerary::class);
    }

    public function claim(): HasOne
    {
        return $this->hasOne(Claim::class);
    }
}
