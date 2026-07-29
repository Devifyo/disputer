<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One Stripe subscription, mirrored locally by the webhook sync. Stripe is
 * the source of truth for billing; this row is the fast local answer to
 * "is this user a Plus member right now?".
 */
class Subscription extends Model
{
    /** Statuses that grant Plus access. */
    public const GOOD_STANDING = ['active', 'trialing', 'past_due'];

    protected $fillable = [
        'user_id', 'subscription_plan_id', 'stripe_customer_id', 'stripe_subscription_id',
        'stripe_price_id', 'interval', 'status', 'current_period_start', 'current_period_end',
        'cancel_at_period_end', 'trial_ends_at', 'canceled_at', 'paused_at', 'resumes_at',
    ];

    protected $casts = [
        'current_period_start' => 'datetime',
        'current_period_end'   => 'datetime',
        'cancel_at_period_end' => 'boolean',
        'trial_ends_at'        => 'datetime',
        'canceled_at'          => 'datetime',
        'paused_at'            => 'datetime',
        'resumes_at'           => 'datetime',
    ];

    /** Billing is paused at Stripe - reversible, unlike a cancellation. */
    public function isPaused(): bool
    {
        return $this->paused_at !== null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    /**
     * Access continues while a subscription is in good standing - including
     * past_due (Stripe retries the charge; access is cut when it flips to
     * canceled/unpaid) and cancel-at-period-end until the period actually ends.
     */
    public function grantsAccess(): bool
    {
        if (!in_array($this->status, self::GOOD_STANDING, true)) {
            return false;
        }

        if ($this->cancel_at_period_end && $this->current_period_end?->isPast()) {
            return false;
        }

        return true;
    }

    public function statusLabel(): string
    {
        if ($this->cancel_at_period_end && $this->grantsAccess()) {
            return 'Cancels ' . ($this->current_period_end?->format('d M Y') ?: 'at period end');
        }

        return match ($this->status) {
            'active'   => 'Active',
            'trialing' => 'Trial',
            'past_due' => 'Payment overdue',
            'canceled' => 'Cancelled',
            'unpaid'   => 'Unpaid',
            'paused'   => 'Paused',
            default    => ucfirst(str_replace('_', ' ', $this->status)),
        };
    }

    public function badgeClasses(): string
    {
        return match (true) {
            $this->status === 'past_due'                  => 'bg-amber-50 text-amber-700 ring-amber-200',
            $this->cancel_at_period_end,
            in_array($this->status, ['canceled', 'unpaid', 'incomplete_expired'], true)
                                                          => 'bg-rose-50 text-rose-700 ring-rose-200',
            in_array($this->status, self::GOOD_STANDING, true)
                                                          => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
            default                                       => 'bg-slate-100 text-slate-600 ring-slate-200',
        };
    }
}
