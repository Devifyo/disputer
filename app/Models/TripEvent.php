<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A flight event detected while monitoring a trip (delay, cancellation, …). */
class TripEvent extends Model
{
    public const TYPE_DELAY           = 'delay';
    public const TYPE_CANCELLATION    = 'cancellation';
    public const TYPE_GATE_CHANGE     = 'gate_change';
    public const TYPE_SCHEDULE_CHANGE = 'schedule_change';
    public const TYPE_COMPLETED       = 'completed';

    protected $fillable = [
        'trip_id', 'type', 'description', 'data', 'qualifying', 'detected_at',
    ];

    protected $casts = [
        'data'        => 'array',
        'qualifying'  => 'boolean',
        'detected_at' => 'datetime',
    ];

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }
}
