<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Compensation the airline paid Unjamm for one claim, and its split into
 * the success fee and the passenger's net payout. Status only ever moves
 * through PaymentService, which writes the ledger and the audit log.
 */
class Payment extends Model
{
    public const STATUS_PENDING          = 'pending';
    public const STATUS_RECEIVED         = 'received';
    public const STATUS_READY_FOR_PAYOUT = 'ready_for_payout';
    public const STATUS_PROCESSING       = 'processing';
    public const STATUS_PAID             = 'paid';
    public const STATUS_FAILED           = 'failed';
    public const STATUS_CANCELLED        = 'cancelled';
    public const STATUS_REFUNDED         = 'refunded';

    public const STATUSES = [
        self::STATUS_PENDING          => 'Pending',
        self::STATUS_RECEIVED         => 'Payment received',
        self::STATUS_READY_FOR_PAYOUT => 'Ready for payout',
        self::STATUS_PROCESSING       => 'Processing',
        self::STATUS_PAID             => 'Paid',
        self::STATUS_FAILED           => 'Failed',
        self::STATUS_CANCELLED        => 'Cancelled',
        self::STATUS_REFUNDED         => 'Refunded',
    ];

    public const CURRENCIES = ['CAD', 'USD', 'EUR', 'GBP'];

    protected $fillable = [
        'claim_id', 'user_id', 'airline', 'currency', 'gross_amount', 'fee_percent',
        'fee_amount', 'net_amount', 'status', 'payment_date', 'reference', 'notes',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'gross_amount' => 'decimal:2',
        'fee_percent'  => 'decimal:2',
        'fee_amount'   => 'decimal:2',
        'net_amount'   => 'decimal:2',
        'payment_date' => 'date',
    ];

    public function claim(): BelongsTo
    {
        return $this->belongsTo(Claim::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(Payout::class)->latest('id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PayoutTransaction::class)->latest('id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(PaymentLog::class)->latest('id');
    }

    public function latestPayout(): ?Payout
    {
        return $this->payouts->first();
    }

    public function money(float|string|null $amount): string
    {
        return $amount === null ? '-' : $this->currency . ' ' . number_format((float) $amount, 2);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }

    public function badgeClasses(): string
    {
        return match ($this->status) {
            self::STATUS_PAID                                    => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
            self::STATUS_RECEIVED, self::STATUS_READY_FOR_PAYOUT => 'bg-blue-50 text-blue-700 ring-blue-200',
            self::STATUS_PROCESSING                              => 'bg-violet-50 text-violet-700 ring-violet-200',
            self::STATUS_FAILED                                  => 'bg-rose-50 text-rose-700 ring-rose-200',
            self::STATUS_REFUNDED, self::STATUS_CANCELLED        => 'bg-slate-100 text-slate-600 ring-slate-200',
            default                                              => 'bg-amber-50 text-amber-700 ring-amber-200',
        };
    }
}
