<?php

namespace App\Livewire\User;

use App\Models\Subscription;
use App\Services\Billing\StripeBillingService;
use App\Services\Billing\SubscriptionGate;
use Illuminate\Support\Carbon;
use Livewire\Component;
use Throwable;

/**
 * Profile -> Billing: everything a member can do with their own Unjamm Plus
 * membership - see it, change the card, pause, resume, cancel, come back,
 * and download invoices. Stripe stays the source of truth: every action
 * calls it and mirrors the result locally.
 */
class PlusMembership extends Component
{
    public bool $confirmingCancel = false;
    public string $pauseUntil = '';
    /** Invoices come from Stripe, so they load after first paint. */
    public bool $showInvoices = false;

    public function loadInvoices(): void
    {
        $this->showInvoices = true;
    }

    private function billing(): StripeBillingService
    {
        return app(StripeBillingService::class);
    }

    public function subscription(): ?Subscription
    {
        return auth()->user()?->subscriptions()
            ->with('plan')
            ->whereIn('status', Subscription::GOOD_STANDING)
            ->latest('id')
            ->first();
    }

    /** Run a Stripe action, surface failures instead of throwing at the user. */
    private function attempt(callable $action, string $success): void
    {
        try {
            $action();
            $this->dispatch('toast', ['type' => 'success', 'message' => $success]);
        } catch (Throwable $e) {
            // The customer gets a plain sentence; the Stripe detail goes to
            // the log where it is useful.
            report($e);
            $this->dispatch('toast', [
                'type'    => 'error',
                'message' => "That didn't go through. Please try again - or open the billing portal to make the change there.",
            ]);
        }
    }

    // ── Payment method ──────────────────────────────────────

    public function updateCard()
    {
        $subscription = $this->subscription();
        abort_unless($subscription, 404);

        return redirect()->away($this->billing()->paymentMethodUrl(auth()->user(), route('profile.edit') . '#billing'));
    }

    public function openPortal()
    {
        abort_unless(auth()->user()->stripe_customer_id, 404);

        return redirect()->away($this->billing()->portalUrl(auth()->user(), route('profile.edit') . '#billing'));
    }

    // ── Membership actions ──────────────────────────────────

    /** Stop the next renewal; access continues to the end of the period. */
    public function cancel(): void
    {
        $subscription = $this->subscription();
        abort_unless($subscription, 404);

        $endsOn = $subscription->current_period_end?->format('d M Y');

        $this->attempt(
            fn () => $this->billing()->cancelAtPeriodEnd($subscription),
            $endsOn
                ? "Cancelled. You keep Unjamm Plus until {$endsOn}, and you won't be charged again."
                : "Cancelled. You won't be charged again.",
        );

        $this->confirmingCancel = false;
    }

    /** Undo a pending cancellation. */
    public function resumeRenewal(): void
    {
        $subscription = $this->subscription();
        abort_unless($subscription, 404);

        $renewsOn = $subscription->current_period_end?->format('d M Y');

        $this->attempt(
            fn () => $this->billing()->reactivate($subscription),
            $renewsOn
                ? "Your membership is back on - it renews on {$renewsOn}."
                : 'Your membership is back on.',
        );
    }

    /** Pause billing (optionally until a date) without losing the membership. */
    public function pause(): void
    {
        $subscription = $this->subscription();
        abort_unless($subscription, 404);

        $this->validate(['pauseUntil' => 'nullable|date|after:today'], [
            'pauseUntil.after' => 'Pick a date in the future, or leave it empty to pause indefinitely.',
        ]);

        $until = trim($this->pauseUntil) !== '' ? Carbon::parse($this->pauseUntil) : null;

        $this->attempt(
            fn () => $this->billing()->pauseCollection($subscription, $until),
            $until
                ? 'Paused. Nothing will be charged until ' . $until->format('d M Y') . ', when your membership picks up automatically.'
                : "Paused. Nothing will be charged until you resume - your membership is still yours.",
        );

        $this->pauseUntil = '';
    }

    public function resume(): void
    {
        $subscription = $this->subscription();
        abort_unless($subscription, 404);

        $nextCharge = $subscription->current_period_end?->format('d M Y');

        $this->attempt(
            fn () => $this->billing()->resumeCollection($subscription),
            $nextCharge
                ? "You're active again - your next payment is on {$nextCharge}."
                : "You're active again.",
        );
    }

    public function render()
    {
        $subscription = $this->subscription();

        return view('livewire.user.plus-membership', [
            'subscription'  => $subscription,
            'card'          => $subscription ? $this->billing()->paymentMethod(auth()->user()) : null,
            'invoices'      => $this->showInvoices ? $this->billing()->invoices(auth()->user()) : [],
            'plusEnabled'   => SubscriptionGate::enabled(),
        ]);
    }
}
