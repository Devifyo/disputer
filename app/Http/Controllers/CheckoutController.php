<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\UserSubscription;
use Illuminate\Http\Request;
use Stripe\Stripe;
use Stripe\Checkout\Session;
use Stripe\Subscription as StripeSubscription; // Aliased to avoid confusion
use Stripe\SetupIntent;
use Stripe\PaymentMethod;
use Stripe\Customer as StripeCustomer;
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
                    'enabled' => false,
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

    public function getPaymentMethods(Request $request)
    {
        $user = $request->user();

        $subscription = UserSubscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->whereNotNull('stripe_customer_id')
            ->latest()
            ->first();

        if (!$subscription) {
            return response()->json(['error' => 'No active subscription found.'], 422);
        }

        try {
            Stripe::setApiKey(config('services.stripe.secret'));

            $customer = StripeCustomer::retrieve($subscription->stripe_customer_id);
            $defaultPmId = $customer->invoice_settings->default_payment_method;

            $paymentMethods = PaymentMethod::all([
                'customer' => $subscription->stripe_customer_id,
                'type' => 'card',
            ]);

            $methods = array_map(fn($pm) => [
                'id'         => $pm->id,
                'brand'      => $pm->card->brand,
                'last4'      => $pm->card->last4,
                'exp_month'  => str_pad($pm->card->exp_month, 2, '0', STR_PAD_LEFT),
                'exp_year'   => $pm->card->exp_year,
                'is_default' => $pm->id === $defaultPmId,
            ], $paymentMethods->data);

            return response()->json(['payment_methods' => array_values($methods)]);

        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function setDefaultPaymentMethod(Request $request)
    {
        $request->validate(['payment_method_id' => 'required|string']);

        $user = $request->user();

        $subscription = UserSubscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->whereNotNull('stripe_customer_id')
            ->latest()
            ->first();

        if (!$subscription) {
            return response()->json(['error' => 'No active subscription found.'], 422);
        }

        try {
            Stripe::setApiKey(config('services.stripe.secret'));

            StripeCustomer::update($subscription->stripe_customer_id, [
                'invoice_settings' => ['default_payment_method' => $request->payment_method_id],
            ]);

            if ($subscription->stripe_subscription_id) {
                StripeSubscription::update($subscription->stripe_subscription_id, [
                    'default_payment_method' => $request->payment_method_id,
                ]);
            }

            return response()->json(['success' => true]);

        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function removePaymentMethod(Request $request)
    {
        $request->validate(['payment_method_id' => 'required|string']);

        $user = $request->user();

        $subscription = UserSubscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->whereNotNull('stripe_customer_id')
            ->latest()
            ->first();

        if (!$subscription) {
            return response()->json(['error' => 'No active subscription found.'], 422);
        }

        try {
            Stripe::setApiKey(config('services.stripe.secret'));

            // Refuse to remove the default payment method
            $customer = StripeCustomer::retrieve($subscription->stripe_customer_id);
            if ($customer->invoice_settings->default_payment_method === $request->payment_method_id) {
                return response()->json(['error' => 'Cannot remove the default payment method.'], 422);
            }

            $paymentMethod = PaymentMethod::retrieve($request->payment_method_id);
            $paymentMethod->detach();

            return response()->json(['success' => true]);

        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getSetupIntent(Request $request)
    {
        $user = $request->user();

        $subscription = UserSubscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->whereNotNull('stripe_customer_id')
            ->latest()
            ->first();

        if (!$subscription) {
            return response()->json(['error' => 'No active subscription found.'], 422);
        }

        try {
            Stripe::setApiKey(config('services.stripe.secret'));

            $setupIntent = SetupIntent::create([
                'customer' => $subscription->stripe_customer_id,
                'payment_method_types' => ['card'],
            ]);

            return response()->json(['client_secret' => $setupIntent->client_secret]);

        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function updatePaymentMethod(Request $request)
    {
        $request->validate(['payment_method_id' => 'required|string']);

        $user = $request->user();

        $subscription = UserSubscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->whereNotNull('stripe_customer_id')
            ->latest()
            ->first();

        if (!$subscription) {
            return response()->json(['error' => 'No active subscription found.'], 422);
        }

        try {
            Stripe::setApiKey(config('services.stripe.secret'));

            $paymentMethodId = $request->input('payment_method_id');

            // Attach the new card to the customer
            $paymentMethod = PaymentMethod::retrieve($paymentMethodId);
            $paymentMethod->attach(['customer' => $subscription->stripe_customer_id]);

            // Set as customer's default for future invoices
            StripeCustomer::update($subscription->stripe_customer_id, [
                'invoice_settings' => ['default_payment_method' => $paymentMethodId],
            ]);

            // Also update the subscription's default payment method if one exists
            if ($subscription->stripe_subscription_id) {
                StripeSubscription::update($subscription->stripe_subscription_id, [
                    'default_payment_method' => $paymentMethodId,
                ]);
            }

            return response()->json(['success' => true]);

        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
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