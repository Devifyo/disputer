<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Itinerary extends Model
{
    use SoftDeletes;

    public const STATUS_UPLOADED   = 'uploaded';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_PARSED     = 'parsed';
    public const STATUS_FAILED     = 'failed';

    public const PURPOSE_CLAIM = 'claim';
    public const PURPOSE_TRIP  = 'trip';

    protected $fillable = [
        'user_id',
        'original_filename',
        'file_path',
        'file_size',
        'mime_type',
        'file_hash',
        'status',
        'purpose',
        'parse_error',
        'parsed_at',
        'booking_reference',
        'primary_airline',
        'raw_text',
        'parsed_raw',
    ];

    protected $casts = [
        'parsed_at'  => 'datetime',
        'parsed_raw' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function flights(): HasMany
    {
        return $this->hasMany(ItineraryFlight::class)->orderBy('sequence');
    }

    public function passengers(): HasMany
    {
        return $this->hasMany(ItineraryPassenger::class);
    }

    public function claims(): HasMany
    {
        return $this->hasMany(Claim::class);
    }

    /**
     * Human-friendly travel date range across all flights, e.g.
     * "24 Oct 2023 – 26 Oct 2023" (or a single date, or null).
     */
    public function getTravelDatesAttribute(): ?string
    {
        $flights = $this->relationLoaded('flights') ? $this->flights : $this->flights()->get();

        $dates = $flights
            ->flatMap(fn ($f) => [$f->departure_at, $f->arrival_at])
            ->filter()
            ->sort()
            ->values();

        if ($dates->isEmpty()) {
            return null;
        }

        $start = $dates->first()->format('d M Y');
        $end   = $dates->last()->format('d M Y');

        return $start === $end ? $start : "{$start} – {$end}";
    }

    public function isParsed(): bool
    {
        return $this->status === self::STATUS_PARSED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    /**
     * A concise route summary, e.g. "LHR → JFK → LHR".
     */
    public function getRouteSummaryAttribute(): ?string
    {
        $flights = $this->relationLoaded('flights') ? $this->flights : $this->flights()->get();
        if ($flights->isEmpty()) {
            return null;
        }

        $codes = [];
        foreach ($flights as $flight) {
            if ($flight->departure_airport && (empty($codes) || end($codes) !== $flight->departure_airport)) {
                $codes[] = $flight->departure_airport;
            }
            if ($flight->arrival_airport) {
                $codes[] = $flight->arrival_airport;
            }
        }

        return empty($codes) ? null : implode(' → ', $codes);
    }
}
