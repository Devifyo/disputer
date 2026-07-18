<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One immutable version of an outbound communication draft (airline claim,
 * follow-up, regulator complaint). Every AI generation and every admin edit
 * creates a new version - the full history stays available for auditing.
 */
class ClaimDraft extends Model
{
    public const TYPE_CLAIM     = 'airline_claim';
    public const TYPE_FOLLOW_UP = 'follow_up';
    public const TYPE_REGULATOR = 'regulator_complaint';

    public const TYPES = [
        self::TYPE_CLAIM     => 'Airline claim',
        self::TYPE_FOLLOW_UP => 'Follow-up',
        self::TYPE_REGULATOR => 'Regulator complaint',
    ];

    public const FOLLOW_UP_REASONS = [
        'no_response'  => 'No response within the required period',
        'info_request' => 'Airline requested additional information',
        'partial'      => 'Airline partially approved the claim',
        'rejected'     => 'Airline rejected the claim',
        'manual'       => 'General follow-up',
    ];

    protected $fillable = [
        'claim_id', 'type', 'version', 'to', 'subject', 'body', 'context',
        'generated_by', 'created_by', 'approved_at', 'approved_by',
    ];

    protected $casts = [
        'context'     => 'array',
        'approved_at' => 'datetime',
    ];

    public function claim(): BelongsTo
    {
        return $this->belongsTo(Claim::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? ucfirst(str_replace('_', ' ', $this->type));
    }
}
