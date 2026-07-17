<?php

namespace App\Models;

use App\Jobs\EvaluateClaim;
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
    public const STATUS_ELIGIBLE            = 'eligible';
    public const STATUS_REJECTED            = 'rejected';

    public const DISRUPTIONS = [
        'delayed'            => 'Delayed 3h+',
        'cancelled'          => 'Cancelled',
        'denied_boarding'    => 'Denied boarding',
        'downgrade'          => 'Downgraded',
        'missed_connection'  => 'Missed connection',
        'schedule_change'    => 'Schedule change',
        'returned_to_origin' => 'Returned to departure airport',
        'other'              => 'Other',
    ];

    protected $fillable = [
        'reference', 'number', 'user_id', 'itinerary_id', 'trip_id', 'itinerary_passenger_id', 'status',
        'departure_city', 'departure_airport', 'arrival_city', 'arrival_airport',
        'airline', 'flight_number', 'flight_date', 'disruption_type', 'disruption_note',
        'passenger_name', 'booking_reference', 'contact_email',
        'compensation_currency', 'compensation_amount', 'compensation_basis', 'submitted_at',
        'ticket_price', 'ticket_currency', 'documents', 'compensation_explanation',
        'fa_flight_id', 'flight_arrival_delay_minutes', 'reported_arrival_delay_minutes', 'did_not_travel', 'flight_cancelled', 'flight_diverted', 'flight_verified_at', 'flight_snapshot',
        'eligibility_status', 'eligibility_regulation', 'eligibility_article', 'eligibility_confidence',
        'eligibility_reason', 'eligibility_details', 'eligibility_evaluated_at', 'eligibility_decision_source',
        'confirmed_at', 'consents', 'plus_selected', 'signed_at', 'signature_path', 'poa_path', 'assignment_path',
        'airline_letter',
    ];

    protected $casts = [
        'flight_date'              => 'date',
        'submitted_at'             => 'datetime',
        'compensation_amount'      => 'decimal:2',
        'flight_cancelled'         => 'boolean',
        'did_not_travel'           => 'boolean',
        'flight_diverted'          => 'boolean',
        'flight_verified_at'       => 'datetime',
        'flight_snapshot'          => 'array',
        'ticket_price'             => 'decimal:2',
        'documents'                => 'array',
        'compensation_explanation' => 'array',
        'eligibility_details'      => 'array',
        'eligibility_evaluated_at' => 'datetime',
        'confirmed_at'             => 'datetime',
        'consents'                 => 'array',
        'airline_letter'           => 'array',
        'plus_selected'            => 'boolean',
        'signed_at'                => 'datetime',
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

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function passenger(): BelongsTo
    {
        return $this->belongsTo(ItineraryPassenger::class, 'itinerary_passenger_id');
    }

    public function signers(): HasMany
    {
        return $this->hasMany(ClaimSigner::class);
    }

    /** All authorisations collected - the claim may be filed. */
    public function signaturesComplete(): bool
    {
        $signers = $this->relationLoaded('signers') ? $this->signers : $this->signers()->get();

        return $signers->isNotEmpty() && $signers->every(fn (ClaimSigner $s) => $s->status === ClaimSigner::STATUS_SIGNED);
    }

    public function events(): HasMany
    {
        // Open (pending) steps always render last - they are the current state.
        return $this->hasMany(ClaimEvent::class)
            ->orderByRaw("status = 'pending'")
            ->orderBy('sort')
            ->orderBy('happened_at');
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT               => 'Draft',
            self::STATUS_PENDING_ELIGIBILITY => 'Pending Eligibility Review',
            self::STATUS_ELIGIBLE            => 'Eligible for Compensation',
            self::STATUS_REJECTED            => 'Not Eligible',
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
            'label'       => Str::limit($label, 250),
            'status'      => $status,
            'happened_at' => $when ?: now(),
            'sort'        => $sort,
        ]);
    }

    /**
     * Create a draft Claim for every passenger on the itinerary that does not
     * already have one, copying the flight snapshot onto the claim.
     */
    /**
     * One master claim per booking: every passenger on the itinerary is
     * covered by the same claim file, with per-passenger compensation and
     * booking totals presented on top of it.
     */
    public static function ensureForItinerary(Itinerary $itinerary): void
    {
        // Itineraries registered to protect a future trip are not disputes.
        if ($itinerary->purpose === Itinerary::PURPOSE_TRIP) {
            return;
        }

        $itinerary->load('passengers', 'flights');

        if (static::where('itinerary_id', $itinerary->id)->exists()) {
            return;
        }

        $first = $itinerary->flights->first();
        $last  = $itinerary->flights->last();
        $lead  = $itinerary->passengers->first();

        // Skip if the same booking + flight already has a claim (e.g. the
        // same ticket re-uploaded as a different file, or a photo vs PDF).
        $duplicate = self::findDuplicate($itinerary->user_id, [
            'passenger_name'    => $lead?->full_name,
            'flight_date'       => $first?->departure_at?->toDateString(),
            'departure_airport' => $first?->departure_airport,
            'arrival_airport'   => $last?->arrival_airport,
            'booking_reference' => $itinerary->booking_reference,
        ]);
        if ($duplicate) {
            return;
        }

        $claim = static::create([
            'user_id'                => $itinerary->user_id,
            'itinerary_id'           => $itinerary->id,
            'itinerary_passenger_id' => $lead?->id,
            'status'                 => self::STATUS_DRAFT,
            'departure_airport'      => $first?->departure_airport,
            'arrival_airport'        => $last?->arrival_airport,
            'airline'                => $itinerary->primary_airline ?: $first?->airline,
            'flight_number'          => $first?->flight_number,
            'flight_date'            => $first?->departure_at?->toDateString(),
            'passenger_name'         => $lead?->full_name,
            'booking_reference'      => $itinerary->booking_reference,
        ]);

        $claim->recordEvent('Your claim case has been received', 'done', $claim->created_at);
        $claim->recordEvent('Claim under review', 'pending', $claim->created_at, 1);

        // Verify the flight + evaluate eligibility + estimate compensation
        // (covers both the upload funnel and inbound claims@ emails).
        EvaluateClaim::dispatch($claim);
    }

    /** Everyone the claim covers - all booking passengers, or the named one. */
    public function passengerNames(): array
    {
        $names = $this->itinerary?->passengers?->pluck('full_name')->filter()->values()->all() ?: [];

        return $names ?: array_values(array_filter([$this->passenger_name]));
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
