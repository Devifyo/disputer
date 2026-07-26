<?php

namespace App\Http\Controllers\User\Api;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use App\Services\Billing\StripeBillingService;
use App\Services\Billing\SubscriptionGate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Unjamm Plus for the customer SPA: what the plans are, whether the system
 * is on, where checkout and the billing portal live. All billing UI lives
 * with Stripe - we only hand out redirect URLs.
 */
class BillingApiController extends Controller
{
    /** Plans + the user's own subscription state, for the Plus page. */
    public function overview()
    {
        $user         = Auth::user()->load('subscriptions.plan');
        $subscription = $user->activeSubscription();

        return response()->json(['data' => [
            'enabled'   => SubscriptionGate::enabled(),
            'is_plus'   => $user->hasActiveSubscription(),
            'locked'    => SubscriptionGate::lockedFor($user),
            'plans'     => SubscriptionPlan::where('is_active', true)->orderBy('sort')->get()
                ->map(fn (SubscriptionPlan $plan) => [
                    'id'            => $plan->id,
                    'key'           => $plan->key,
                    'name'          => $plan->name,
                    'description'   => $plan->description,
                    'monthly_price' => $plan->monthly_price,
                    'annual_price'  => $plan->annual_price,
                    'currency'      => $plan->currency,
                    'trial_days'    => $plan->trial_days,
                    'perks'         => $plan->perks ?: [],
                    'monthly_available' => (bool) $plan->stripe_monthly_price_id,
                    'annual_available'  => (bool) $plan->stripe_annual_price_id,
                ]),
            'subscription' => $subscription ? [
                'plan'       => $subscription->plan?->name,
                'interval'   => $subscription->interval,
                'status'     => $subscription->statusLabel(),
                'renews_at'  => $subscription->current_period_end?->format('d M Y'),
                'cancelling' => $subscription->cancel_at_period_end,
            ] : null,
        ]]);
    }

    /** Start Stripe Checkout - returns the URL to redirect to. */
    public function checkout(Request $request, StripeBillingService $billing)
    {
        abort_unless(SubscriptionGate::enabled(), 422, 'Subscriptions are not open yet.');
        abort_unless($billing->configured(), 422, 'Payments are not configured yet - please try again later.');

        $data = $request->validate([
            'plan'     => ['required', 'integer', Rule::exists('subscription_plans', 'id')->where('is_active', true)],
            'interval' => ['required', Rule::in(['monthly', 'annual'])],
        ]);

        $user = Auth::user();
        abort_if($user->hasActiveSubscription(), 422, 'You already have an active Unjamm Plus membership.');

        $url = $billing->checkoutUrl(
            $user,
            SubscriptionPlan::findOrFail($data['plan']),
            $data['interval'],
            url('/flight-disputes/plus?checkout=success'),
            url('/flight-disputes/plus?checkout=cancelled'),
        );

        return response()->json(['data' => ['url' => $url]]);
    }

    /** Stripe Customer Portal - card, invoices, self-serve cancel. */
    public function portal(StripeBillingService $billing)
    {
        abort_unless($billing->configured(), 422, 'Payments are not configured yet.');

        return response()->json(['data' => [
            'url' => $billing->portalUrl(Auth::user(), url('/flight-disputes/plus')),
        ]]);
    }
}
