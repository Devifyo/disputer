<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Airline directory entry - the carrier's identity and its purpose-based
 * contact addresses (claims department, legal, escalation...). Lifecycle
 * stages route their outbound email to the right contact per airline.
 */
class Airline extends Model
{
    protected $fillable = ['name', 'iata_code', 'is_active', 'claim_workflow_id', 'notes'];

    protected $casts = ['is_active' => 'boolean'];

    public function contacts(): HasMany
    {
        return $this->hasMany(AirlineContact::class);
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(ClaimWorkflow::class, 'claim_workflow_id');
    }

    public function contactFor(string $purpose): ?AirlineContact
    {
        return $this->contacts->firstWhere('purpose', $purpose);
    }

    /**
     * Resolve the airline for a claim: the flight number's IATA prefix wins
     * (authoritative), the airline name is the fallback.
     */
    public static function match(?string $name, ?string $flightNumber): ?self
    {
        if ($flightNumber && preg_match('/^([A-Z]{2}|[A-Z]\d|\d[A-Z])\s*\d/', strtoupper(trim($flightNumber)), $m)) {
            $byCode = static::where('is_active', true)->where('iata_code', $m[1])->first();
            if ($byCode) {
                return $byCode;
            }
        }

        if ($name) {
            return static::where('is_active', true)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($name))])
                ->first()
                ?? static::where('is_active', true)->where('name', 'like', '%' . trim($name) . '%')->first();
        }

        return null;
    }
}
