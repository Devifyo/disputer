<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * Append-only financial ledger. Conversion rows keep every historical
 * exchange rate - a new conversion is a new row, never an update.
 */
class PayoutTransaction extends Model
{
    public const TYPES = [
        'payment_received' => 'Payment received',
        'fee_deducted'     => 'Fee deducted',
        'payout_created'   => 'Payout created',
        'wise_transfer'    => 'Wise transfer',
        'conversion'       => 'Currency conversion',
        'completed'        => 'Completed',
        'failed'           => 'Failed',
        'refund'           => 'Refund',
        'cancelled'        => 'Cancelled',
    ];

    protected $fillable = [
        'payment_id', 'payout_id', 'type', 'amount', 'currency', 'reference',
        'status', 'performed_by', 'notes', 'meta',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'meta'   => 'array',
    ];

    /** The ledger is append-only by construction. */
    protected static function booted(): void
    {
        static::updating(fn () => throw new RuntimeException('Ledger entries are immutable.'));
        static::deleting(fn () => throw new RuntimeException('Ledger entries cannot be deleted.'));
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function payout(): BelongsTo
    {
        return $this->belongsTo(Payout::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? ucfirst(str_replace('_', ' ', $this->type));
    }
}
