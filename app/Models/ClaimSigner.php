<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * One required signature on a claim's authorisation documents. Every adult
 * passenger signs their own Power of Attorney; a guardian signs on behalf
 * of each minor. The claim unlocks for filing once every signer has signed.
 */
class ClaimSigner extends Model
{
    public const STATUS_PENDING  = 'pending';
    public const STATUS_SIGNED   = 'signed';
    public const STATUS_DECLINED = 'declined';

    public const ROLE_PASSENGER = 'passenger';
    public const ROLE_GUARDIAN  = 'guardian';

    protected $fillable = [
        'claim_id', 'itinerary_passenger_id', 'name', 'email', 'role', 'signs_for',
        'status', 'provider', 'provider_request_id', 'provider_signature_id',
        'sign_token', 'signature_path', 'poa_path', 'signed_at', 'invited_at', 'reminded_at',
    ];

    protected $casts = [
        'signed_at'   => 'datetime',
        'invited_at'  => 'datetime',
        'reminded_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (ClaimSigner $signer) {
            if (empty($signer->sign_token)) {
                $signer->sign_token = Str::random(48);
            }
        });
    }

    public function claim(): BelongsTo
    {
        return $this->belongsTo(Claim::class);
    }

    public function passenger(): BelongsTo
    {
        return $this->belongsTo(ItineraryPassenger::class, 'itinerary_passenger_id');
    }

    /** Who this signature legally covers. */
    public function coversLabel(): string
    {
        return $this->role === self::ROLE_GUARDIAN
            ? "{$this->signs_for} (signed by guardian {$this->name})"
            : $this->name;
    }
}
