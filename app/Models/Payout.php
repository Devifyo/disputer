<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One attempt to move a payment's net amount to the passenger. */
class Payout extends Model
{
    public const METHOD_WISE   = 'wise';
    public const METHOD_MANUAL = 'manual';

    public const STATUS_DRAFT      = 'draft';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SENT       = 'sent';
    public const STATUS_COMPLETED  = 'completed';
    public const STATUS_FAILED     = 'failed';
    public const STATUS_CANCELLED  = 'cancelled';

    /** Wise transfer states that mean the money arrived. */
    public const WISE_COMPLETE_STATES = ['outgoing_payment_sent'];
    public const WISE_FAILED_STATES   = ['funds_refunded', 'cancelled', 'charged_back'];

    protected $fillable = [
        'payment_id', 'user_id', 'method', 'status', 'currency', 'amount',
        'source_currency', 'source_amount', 'exchange_rate', 'converted_at',
        'recipient_name', 'recipient_iban', 'user_payout_account_id', 'wise_recipient_id', 'wise_quote_id',
        'wise_transfer_id', 'transfer_reference', 'transfer_status', 'transferred_at',
        'api_response', 'error_message', 'attempts', 'created_by',
    ];

    protected $casts = [
        'amount'        => 'decimal:2',
        'source_amount' => 'decimal:2',
        'exchange_rate' => 'decimal:6',
        'converted_at'  => 'datetime',
        'transferred_at' => 'datetime',
        'api_response'  => 'array',
        'attempts'      => 'integer',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payoutAccount(): BelongsTo
    {
        return $this->belongsTo(UserPayoutAccount::class, 'user_payout_account_id');
    }

    public function money(): string
    {
        return $this->currency . ' ' . number_format((float) $this->amount, 2);
    }

    public function isRetryable(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /** Wise states meaning the money has NOT left the balance yet. */
    public const WISE_UNFUNDED_STATES = ['incoming_payment_waiting', 'waiting_recipient_input_to_proceed'];

    public function isCancellable(): bool
    {
        if (in_array($this->status, [self::STATUS_DRAFT, self::STATUS_FAILED], true)) {
            return true;
        }

        // Sent but the transfer was never funded: safe to abort.
        return $this->status === self::STATUS_SENT
            && in_array($this->transfer_status, self::WISE_UNFUNDED_STATES, true);
    }

    /** Account number shown to admins: last four digits only. */
    public function maskedAccount(): ?string
    {
        return $this->recipient_iban
            ? '····' . substr($this->recipient_iban, -4)
            : null;
    }

    public function badgeClasses(): string
    {
        return match ($this->status) {
            self::STATUS_COMPLETED  => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
            self::STATUS_SENT       => 'bg-blue-50 text-blue-700 ring-blue-200',
            self::STATUS_PROCESSING => 'bg-violet-50 text-violet-700 ring-violet-200',
            self::STATUS_FAILED     => 'bg-rose-50 text-rose-700 ring-rose-200',
            self::STATUS_CANCELLED  => 'bg-slate-100 text-slate-600 ring-slate-200',
            default                 => 'bg-amber-50 text-amber-700 ring-amber-200',
        };
    }
}
