<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One out-of-pocket expense receipt uploaded by the passenger. The admin
 * verifies each one and decides whether it is claimed from the airline -
 * only approved receipts reach a letter or an attachment set.
 */
class ClaimExpense extends Model
{
    public const STATUS_PENDING  = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    /** Receipt types the customer can pick, in the order they are offered. */
    public const CATEGORIES = [
        'meal'      => 'Meals and drinks',
        'hotel'     => 'Hotel / accommodation',
        'taxi'      => 'Taxi / ride-hailing',
        'transport' => 'Train, bus or other transport',
        'rebooking' => 'Replacement ticket / rebooking',
        'other'     => 'Other expense',
    ];

    protected $fillable = [
        'claim_id', 'uploaded_by', 'category', 'description', 'amount', 'currency',
        'expense_date', 'file_path', 'original_filename', 'mime', 'size_bytes',
        'status', 'review_reason', 'admin_note', 'reviewed_by', 'reviewed_at',
        'reimbursed_amount', 'reimbursed_at',
    ];

    protected $casts = [
        'amount'            => 'decimal:2',
        'reimbursed_amount' => 'decimal:2',
        'expense_date'      => 'date',
        'reviewed_at'       => 'datetime',
        'reimbursed_at'     => 'datetime',
    ];

    public function claim(): BelongsTo
    {
        return $this->belongsTo(Claim::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? 'Expense';
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /** "EUR 180.00" - blank when the customer did not state an amount. */
    public function formattedAmount(): string
    {
        if ($this->amount === null) {
            return '';
        }

        return trim(($this->currency ?? '') . ' ' . number_format((float) $this->amount, 2));
    }

    public function badgeClasses(): string
    {
        return match ($this->status) {
            self::STATUS_APPROVED => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
            self::STATUS_REJECTED => 'bg-rose-50 text-rose-700 ring-rose-200',
            default               => 'bg-amber-50 text-amber-700 ring-amber-200',
        };
    }
}
