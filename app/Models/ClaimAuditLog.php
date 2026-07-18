<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable audit record: every state transition and every important
 * action on a claim - who (customer/admin/system/airline), what, when.
 */
class ClaimAuditLog extends Model
{
    protected $fillable = ['claim_id', 'action', 'from_state', 'to_state', 'via', 'actor_id', 'notes'];

    public function claim(): BelongsTo
    {
        return $this->belongsTo(Claim::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
