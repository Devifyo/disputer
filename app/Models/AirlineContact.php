<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One purpose-based email address of an airline. */
class AirlineContact extends Model
{
    public const PURPOSES = [
        'claims'             => 'Claims department',
        'legal'              => 'Legal',
        'escalation'         => 'Escalation',
        'customer_relations' => 'Customer relations',
    ];

    protected $fillable = ['airline_id', 'purpose', 'email', 'label'];

    public function airline(): BelongsTo
    {
        return $this->belongsTo(Airline::class);
    }

    public function purposeLabel(): string
    {
        return self::PURPOSES[$this->purpose] ?? ucfirst(str_replace('_', ' ', $this->purpose));
    }
}
