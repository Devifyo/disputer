<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use RuntimeException;

/**
 * An append-only record of admin actions that are not scoped to one claim -
 * airline and template management. Immutable by construction: an audit trail
 * that can be edited is not an audit trail.
 */
class AdminActivityLog extends Model
{
    protected $fillable = ['subject_type', 'subject_id', 'action', 'actor_id', 'ip', 'old_values', 'new_values', 'notes'];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new RuntimeException('Activity log entries cannot be modified.'));
        static::deleting(fn () => throw new RuntimeException('Activity log entries cannot be deleted.'));
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
