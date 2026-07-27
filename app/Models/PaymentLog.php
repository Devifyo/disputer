<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/** Immutable audit: who did what to money, from where, before and after. */
class PaymentLog extends Model
{
    protected $fillable = ['payment_id', 'action', 'user_id', 'ip', 'old_values', 'new_values', 'notes'];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new RuntimeException('Payment audit entries are immutable.'));
        static::deleting(fn () => throw new RuntimeException('Payment audit entries cannot be deleted.'));
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
