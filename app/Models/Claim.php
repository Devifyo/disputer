<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Claim extends Model
{
    use SoftDeletes;

    public const STATUS_DRAFT               = 'draft';
    public const STATUS_PENDING_ELIGIBILITY = 'pending_eligibility_review';

    public const DISRUPTIONS = [
        'delayed'           => 'Delayed 3h+',
        'cancelled'         => 'Cancelled',
        'denied_boarding'   => 'Denied boarding',
        'missed_connection' => 'Missed connection',
        'other'             => 'Other',
    ];

    protected $fillable = [
        'reference', 'number', 'user_id', 'itinerary_id', 'itinerary_passenger_id', 'status',
        'departure_city', 'departure_airport', 'arrival_city', 'arrival_airport',
        'airline', 'flight_number', 'flight_date', 'disruption_type',
        'passenger_name', 'booking_reference', 'contact_email',
        'compensation_currency', 'compensation_amount', 'submitted_at',
    ];

    protected $casts = [
        'flight_date'         => 'date',
        'submitted_at'        => 'datetime',
        'compensation_amount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (Claim $claim) {
            if (empty($claim->reference)) {
                $claim->reference = self::generateReference();
            }
            if (empty($claim->number)) {
                $claim->number = self::generateNumber();
            }
            if (empty($claim->submitted_at)) {
                $claim->submitted_at = now();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function itinerary(): BelongsTo
    {
        return $this->belongsTo(Itinerary::class);
    }

    public function passenger(): BelongsTo
    {
        return $this->belongsTo(ItineraryPassenger::class, 'itinerary_passenger_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ClaimEvent::class)->orderBy('sort')->orderBy('happened_at');
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT               => 'Draft',
            self::STATUS_PENDING_ELIGIBILITY => 'Pending Eligibility Review',
            default                          => ucwords(str_replace('_', ' ', (string) $this->status)),
        };
    }

    public function getDisruptionLabelAttribute(): ?string
    {
        return $this->disruption_type ? (self::DISRUPTIONS[$this->disruption_type] ?? ucfirst($this->disruption_type)) : null;
    }

    public function recordEvent(string $label, string $status = 'done', $when = null, int $sort = 0): ClaimEvent
    {
        return $this->events()->create([
            'label'       => $label,
            'status'      => $status,
            'happened_at' => $when ?: now(),
            'sort'        => $sort,
        ]);
    }

    /**
     * Create a draft Claim for every passenger on the itinerary that does not
     * already have one, copying the flight snapshot onto the claim.
     */
    public static function ensureForItinerary(Itinerary $itinerary): void
    {
        $itinerary->load('passengers.claim', 'flights');

        $first = $itinerary->flights->first();
        $last  = $itinerary->flights->last();

        foreach ($itinerary->passengers as $passenger) {
            if ($passenger->claim) {
                continue;
            }

            // Skip if the same passenger + flight already has a claim (e.g. the
            // same ticket re-uploaded as a different file, or a photo vs PDF).
            $duplicate = self::findDuplicate($itinerary->user_id, [
                'passenger_name'    => $passenger->full_name,
                'flight_date'       => $first?->departure_at?->toDateString(),
                'departure_airport' => $first?->departure_airport,
                'arrival_airport'   => $last?->arrival_airport,
                'booking_reference' => $itinerary->booking_reference,
            ]);
            if ($duplicate) {
                continue;
            }

            $claim = static::create([
                'user_id'                => $itinerary->user_id,
                'itinerary_id'           => $itinerary->id,
                'itinerary_passenger_id' => $passenger->id,
                'status'                 => self::STATUS_DRAFT,
                'departure_airport'      => $first?->departure_airport,
                'arrival_airport'        => $last?->arrival_airport,
                'airline'                => $itinerary->primary_airline ?: $first?->airline,
                'flight_number'          => $first?->flight_number,
                'flight_date'            => $first?->departure_at?->toDateString(),
                'passenger_name'         => $passenger->full_name,
                'booking_reference'      => $itinerary->booking_reference,
            ]);

            $claim->recordEvent('Your claim case has been received', 'done', $claim->created_at);
            $claim->recordEvent('Claim under review', 'pending', $claim->created_at, 1);
        }
    }

    /**
     * Find an existing (non-deleted) claim for the same user that describes the
     * same passenger + flight. Requires passenger name, flight date and both
     * airports to match; booking reference tightens the match when present.
     * Returns null when there isn't enough data to judge.
     */
    public static function findDuplicate(int $userId, array $a): ?Claim
    {
        $name    = trim((string) ($a['passenger_name'] ?? ''));
        $date    = $a['flight_date'] ?? null;
        $depart  = strtoupper(trim((string) ($a['departure_airport'] ?? '')));
        $arrive  = strtoupper(trim((string) ($a['arrival_airport'] ?? '')));

        if ($name === '' || !$date || $depart === '' || $arrive === '') {
            return null;
        }

        $query = static::where('user_id', $userId)
            ->whereRaw('LOWER(TRIM(passenger_name)) = ?', [mb_strtolower($name)])
            ->whereDate('flight_date', $date)
            ->where('departure_airport', $depart)
            ->where('arrival_airport', $arrive);

        if (!empty($a['booking_reference'])) {
            $query->where('booking_reference', $a['booking_reference']);
        }

        return $query->first();
    }

    public static function generateReference(): string
    {
        do {
            $ref = 'CLM-' . strtoupper(Str::random(8));
        } while (static::withTrashed()->where('reference', $ref)->exists());

        return $ref;
    }

    public static function generateNumber(): int
    {
        do {
            $n = random_int(1_000_000, 9_999_999);
        } while (static::withTrashed()->where('number', $n)->exists());

        return $n;
    }
}
