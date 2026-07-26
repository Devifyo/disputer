<?php

namespace App\Livewire\Admin\FlightClaims;

use App\Models\Setting;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Billing\StripeBillingService;
use App\Services\Billing\SubscriptionGate;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Flight Claims -> Subscriptions: the whole Unjamm Plus system in one
 * screen - master switch, plans (with their Stripe IDs), which features
 * need Plus, the subscriber list with billing actions, and revenue stats.
 * Entirely config: no deploy needed to launch, price or gate anything.
 */
class Subscriptions extends Component
{
    use WithPagination;

    public string $tab = 'overview';

    // Master switch + feature gates.
    public bool $systemEnabled = false;
    public array $features = [];

    // Plan editor modal.
    public bool $showPlanModal = false;
    public ?int $editingPlanId = null;
    public array $plan = [];

    // Subscriber list.
    public string $search = '';

    // Billing-history modal.
    public ?int $historyUserId = null;
    public array $invoices = [];

    public function mount(): void
    {
        $this->systemEnabled = SubscriptionGate::enabled();
        $premium = SubscriptionGate::premiumFeatures();
        foreach (array_keys(SubscriptionGate::FEATURES) as $feature) {
            $this->features[$feature] = (bool) ($premium[$feature] ?? false);
        }
    }

    public function updatedSystemEnabled(): void
    {
        Setting::set('subscriptions.enabled', $this->systemEnabled ? 1 : 0);
        $this->dispatch('toast', ['type' => 'success', 'message' => $this->systemEnabled
            ? 'Subscriptions enabled - premium features now require Unjamm Plus.'
            : 'Subscriptions disabled - everything is free for everyone; stored subscriptions are kept.']);
    }

    public function updatedFeatures(): void
    {
        Setting::set('subscriptions.features', json_encode(array_filter($this->features)));
        $this->dispatch('toast', ['type' => 'success', 'message' => 'Feature access saved.']);
    }

    // ── Plans ───────────────────────────────────────────────

    public function editPlan(?int $planId = null): void
    {
        $record = $planId ? SubscriptionPlan::findOrFail($planId) : new SubscriptionPlan();

        $this->editingPlanId = $record->id;
        $this->plan = [
            'key'                     => $record->key ?? '',
            'name'                    => $record->name ?? '',
            'description'             => $record->description ?? '',
            'monthly_price'           => $record->monthly_price,
            'annual_price'            => $record->annual_price,
            'currency'                => $record->currency ?? 'CAD',
            'trial_days'              => $record->trial_days ?? 0,
            'sort'                    => $record->sort ?? (SubscriptionPlan::max('sort') + 1),
            'is_active'               => $record->is_active ?? true,
            'perks_text'              => implode("\n", $record->perks ?? []),
        ];
        $this->showPlanModal = true;
        $this->resetErrorBag();
    }

    public function savePlan(): void
    {
        $data = $this->validate([
            'plan.key'           => ['required', 'string', 'max:40', 'alpha_dash', Rule::unique('subscription_plans', 'key')->ignore($this->editingPlanId)],
            'plan.name'          => 'required|string|max:80',
            'plan.description'   => 'nullable|string|max:500',
            'plan.monthly_price' => 'nullable|numeric|min:0|max:9999',
            'plan.annual_price'  => 'nullable|numeric|min:0|max:99999',
            'plan.currency'      => 'required|string|size:3',
            'plan.trial_days'    => 'required|integer|min:0|max:90',
            'plan.sort'          => 'required|integer|min:0|max:999',
            'plan.is_active'     => 'boolean',
            'plan.perks_text'    => 'nullable|string|max:1000',
        ], [], ['plan.key' => 'plan key', 'plan.name' => 'display name'])['plan'];

        $data['currency'] = strtoupper($data['currency']);
        $data['perks']    = collect(explode("\n", (string) ($data['perks_text'] ?? '')))
            ->map(fn ($perk) => trim($perk))->filter()->values()->all();
        unset($data['perks_text']);

        $plan = SubscriptionPlan::updateOrCreate(['id' => $this->editingPlanId], $data);
        $this->showPlanModal = false;

        // Stripe is managed for the admin: the product and prices are
        // created/replaced automatically to match what was just saved.
        $billing = app(StripeBillingService::class);

        if (!$billing->configured()) {
            $this->dispatch('toast', ['type' => 'success', 'message' => "Plan \"{$data['name']}\" saved. Stripe connects automatically once the API keys are set."]);

            return;
        }

        try {
            $sync = $billing->syncPlan($plan);
            $message = "Plan \"{$data['name']}\" saved and synced with Stripe.";
            if ($sync['migrated'] > 0) {
                $message .= " {$sync['migrated']} subscriber(s) will renew at the new price.";
            }
            if ($sync['failed'] > 0) {
                $message .= " {$sync['failed']} could not be moved (see log) - usually a currency change.";
            }
            $this->dispatch('toast', ['type' => $sync['failed'] ? 'error' : 'success', 'message' => $message]);
        } catch (\Throwable $e) {
            $this->dispatch('toast', ['type' => 'error', 'message' => "Plan saved, but the Stripe sync failed: {$e->getMessage()} - save again to retry."]);
        }
    }

    public function togglePlan(int $planId): void
    {
        $plan = SubscriptionPlan::findOrFail($planId);
        $plan->update(['is_active' => !$plan->is_active]);

        $this->dispatch('toast', ['type' => 'success', 'message' => "\"{$plan->name}\" " . ($plan->is_active ? 'activated.' : 'deactivated - hidden from customers; existing subscribers keep it.')]);
    }

    // ── Subscriber actions (all proxied to Stripe, then synced) ─

    public function cancelSubscription(StripeBillingService $billing, int $subscriptionId): void
    {
        $this->billingAction($subscriptionId, fn (Subscription $sub) => $billing->cancelAtPeriodEnd($sub),
            'Subscription will cancel at the end of the current period.');
    }

    public function reactivateSubscription(StripeBillingService $billing, int $subscriptionId): void
    {
        $this->billingAction($subscriptionId, fn (Subscription $sub) => $billing->reactivate($sub),
            'Subscription reactivated - it will renew as normal.');
    }

    public function changePlan(StripeBillingService $billing, int $subscriptionId, int $planId, string $interval): void
    {
        $plan = SubscriptionPlan::findOrFail($planId);

        $this->billingAction($subscriptionId, fn (Subscription $sub) => $billing->changePlan($sub, $plan, $interval),
            "Moved to {$plan->name} ({$interval}).");
    }

    public function showBillingHistory(StripeBillingService $billing, int $userId): void
    {
        $this->historyUserId = $userId;

        try {
            $this->invoices = $billing->configured() ? $billing->invoices(User::findOrFail($userId)) : [];
        } catch (\Throwable $e) {
            $this->invoices = [];
            $this->dispatch('toast', ['type' => 'error', 'message' => 'Could not load invoices: ' . $e->getMessage()]);
        }
    }

    private function billingAction(int $subscriptionId, callable $action, string $successMessage): void
    {
        $subscription = Subscription::findOrFail($subscriptionId);

        try {
            $action($subscription);
        } catch (\Throwable $e) {
            $this->dispatch('toast', ['type' => 'error', 'message' => 'Stripe rejected the change: ' . $e->getMessage()]);

            return;
        }

        $this->dispatch('toast', ['type' => 'success', 'message' => $successMessage]);
    }

    // ── Stats ───────────────────────────────────────────────

    private function stats(): array
    {
        $subscriptions = Subscription::with('plan')->get();
        $active        = $subscriptions->filter(fn (Subscription $sub) => $sub->grantsAccess());

        $revenue = fn (string $interval) => $active
            ->where('interval', $interval)
            ->groupBy(fn (Subscription $sub) => $sub->plan?->currency ?? 'EUR')
            ->map(fn ($group) => round($group->sum(fn (Subscription $sub) => (float) ($sub->plan?->price($sub->interval) ?? 0)), 2))
            ->map(fn ($amount, $currency) => "{$currency} " . number_format($amount, 2))
            ->implode(' + ');

        return [
            'total'     => $subscriptions->count(),
            'active'    => $active->count(),
            'cancelled' => $subscriptions->filter(fn ($sub) => $sub->cancel_at_period_end || in_array($sub->status, ['canceled', 'unpaid'], true))->count(),
            'monthly'   => $revenue('monthly') ?: '-',
            'annual'    => $revenue('annual') ?: '-',
            'failed'    => $subscriptions->where('status', 'past_due')->count(),
            'expired'   => $subscriptions->filter(fn ($sub) => in_array($sub->status, ['canceled', 'unpaid', 'incomplete_expired'], true))->count(),
        ];
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $subscribers = Subscription::with(['user', 'plan'])
            ->when(trim($this->search) !== '', function ($query) {
                $term = '%' . trim($this->search) . '%';
                $query->whereHas('user', fn ($q) => $q->where('name', 'like', $term)->orWhere('email', 'like', $term));
            })
            ->latest('id')
            ->paginate(15);

        return view('livewire.admin.flight-claims.subscriptions', [
                'stats'        => $this->stats(),
                'plans'        => SubscriptionPlan::orderBy('sort')->get(),
                'subscribers'  => $subscribers,
                'featureList'  => SubscriptionGate::FEATURES,
                'historyUser'  => $this->historyUserId ? User::find($this->historyUserId) : null,
                'stripeReady'  => app(StripeBillingService::class)->configured(),
            ])
            ->extends('layouts.admin')
            ->section('content');
    }
}
