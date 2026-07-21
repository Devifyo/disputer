<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * Immutable audit record: every state transition and every important
 * action on a claim - who (customer/admin/system/airline), what, when.
 */
class ClaimAuditLog extends Model
{
    protected $fillable = ['claim_id', 'action', 'from_state', 'to_state', 'via', 'actor_id', 'notes'];

    /** Append-only by construction: entries can never be edited or removed. */
    protected static function booted(): void
    {
        static::updating(fn () => throw new RuntimeException('Audit log entries are immutable.'));
        static::deleting(fn () => throw new RuntimeException('Audit log entries cannot be deleted.'));
    }

    public function claim(): BelongsTo
    {
        return $this->belongsTo(Claim::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
