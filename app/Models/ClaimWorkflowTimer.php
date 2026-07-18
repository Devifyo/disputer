<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A workflow deadline (airline response timer, future reminders). The
 * scheduler evaluates pending timers and fires the configured transition.
 */
class ClaimWorkflowTimer extends Model
{
    public const STATUS_PENDING   = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = ['claim_id', 'purpose', 'due_at', 'status', 'completed_at', 'meta'];

    protected $casts = [
        'due_at'       => 'datetime',
        'completed_at' => 'datetime',
        'meta'         => 'array',
    ];

    public function claim(): BelongsTo
    {
        return $this->belongsTo(Claim::class);
    }
}
