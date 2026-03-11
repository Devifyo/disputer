<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\UserSubscription;
use Illuminate\Http\Request;
use Stripe\Stripe;
use Stripe\Checkout\Session;
use Stripe\Subscription as StripeSubscription; // Aliased to avoid confusion
use Exception;

class CheckoutController extends Controller
{
    public function checkout(Request $request, $slug)
    {
        $plan = Plan::where('slug', $slug)->firstOrFail();

        if (!$plan->payment_gateway_id) {
            return back()->with('error', 'This plan is not properly configured with the payment gateway.');
        }

        Stripe::setApiKey(config('services.stripe.secret'));

        $mode = $plan->type === 'recurring_yearly' ? 'subscription' : 'payment';
        
        $cancelUrl = session()->has('dispute_draft') 
            ? route('user.cases.create') 
            : route('profile.edit') . '#billing';
            
        $successUrl = route('checkout.success') . '?session_id={CHECKOUT_SESSION_ID}';
        
        try {
            $session = Session::create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price' => $plan->payment_gateway_id, 
                    'quantity' => 1,
                ]],
                'mode' => $mode,
                'automatic_tax' => [
                    'enabled' => true,
                ],
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'customer_email' => $request->user()->email,
                'client_reference_id' => $request->user()->id . '_' . $plan->id, 
            ]);

            return redirect($session->url);

        } catch (Exception $e) { 
            return back()->with('error', 'Unable to initiate checkout: ' . $e->getMessage());
        }
    }

    public function success(Request $request)
    {
        if (session()->has('dispute_draft')) {
            return redirect()->route('user.cases.create')
                ->with('success', 'Payment successful! Your draft is now unlocked and ready to send.');
        }
        
        return redirect()->route('profile.edit', ['#billing'])
            ->with('success', 'Payment successful! Your subscription is being activated.');
    }

    public function cancelSubscription(Request $request)
    {
        $user = $request->user();

        // 1. Find active subscription using the imported UserSubscription model
        $subscription = UserSubscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->whereNotNull('stripe_subscription_id')
            ->first();

        // Prevent errors if they already cancelled
        if (!$subscription || $subscription->canceled_at !== null) {
            return back()->with('error', 'No active subscription found, or it is already scheduled to cancel.');
        }

        try {
            Stripe::setApiKey(config('services.stripe.secret'));

            // 2. Tell Stripe to cancel at period end using the imported StripeSubscription model
            StripeSubscription::update($subscription->stripe_subscription_id, [
                'cancel_at_period_end' => true,
            ]);

            // 3. Update local DB (Keep status active so they don't lose access, but mark the cancellation date)
            $subscription->update([
                'canceled_at' => now() 
            ]);

            return redirect()->route('profile.edit', ['#billing'])
                ->with('success', 'Your subscription has been canceled. You will retain access until the end of your billing cycle.');

        } catch (Exception $e) {
            return back()->with('error', 'Unable to cancel subscription: ' . $e->getMessage());
        }
    }

    public function resumeSubscription(Request $request)
    {
        $user = $request->user();

        // 1. Find the active subscription that is currently pending cancellation
        $subscription = UserSubscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->whereNotNull('stripe_subscription_id')
            ->whereNotNull('canceled_at')
            ->first();

        if (!$subscription) {
            return back()->with('error', 'No eligible subscription found to resume.');
        }

        try {
            Stripe::setApiKey(config('services.stripe.secret'));

            // 2. Tell Stripe to remove the cancellation flag!
            // This turns auto-renew back on without charging them today.
            StripeSubscription::update($subscription->stripe_subscription_id, [
                'cancel_at_period_end' => false,
            ]);

            // 3. Update local DB (Remove the canceled_at date)
            $subscription->update([
                'canceled_at' => null 
            ]);

            return redirect()->route('profile.edit', ['#billing'])
                ->with('success', 'Welcome back! Your subscription has been successfully resumed and will auto-renew as normal.');

        } catch (Exception $e) {
            return back()->with('error', 'Unable to resume subscription: ' . $e->getMessage());
        }
    }
}