<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use Illuminate\Http\Request;
use Stripe\Stripe;
use Stripe\Checkout\Session;

class CheckoutController extends Controller
{
    /**
     * Redirect the user to the Stripe Checkout page.
     */
    public function checkout(Request $request, $slug)
    {
        $plan = Plan::where('slug', $slug)->firstOrFail();

        // Ensure we have a Stripe Price ID for this plan
        if (!$plan->payment_gateway_id) {
            return back()->with('error', 'This plan is not properly configured with the payment gateway.');
        }

        // Initialize Stripe
        Stripe::setApiKey(config('services.stripe.secret'));

        // Stripe requires 'subscription' mode for recurring plans, and 'payment' for one-time bundles
        $mode = $plan->type === 'recurring_yearly' ? 'subscription' : 'payment';

        try {
            // Create the Checkout Session
            $session = Session::create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price' => $plan->payment_gateway_id, // The Stripe Price ID
                    'quantity' => 1,
                ]],
                'mode' => $mode,
                
                // ----------------------------------------------------
                // THIS IS THE MAGIC LINE THAT ENABLES STRIPE TAX
                // ----------------------------------------------------
                'automatic_tax' => [
                    'enabled' => true,
                ],
                
                // Where to send the user after payment
                'success_url' => route('checkout.success') . '?session_id={CHECKOUT_SESSION_ID}',
                
                // Where to send them if they click "back"
                'cancel_url' => route('profile.edit') . '#billing',
                
                // Pre-fill their email
                'customer_email' => $request->user()->email,
                
                // We critically need this for the webhook to know WHO bought WHAT
                'client_reference_id' => $request->user()->id . '_' . $plan->id, 
            ]);

            // Redirect to the Stripe-hosted checkout page
            return redirect($session->url);

        } catch (\Exceptions $e) {
            return back()->with('error', 'Unable to initiate checkout: ' . $e->getMessage());
        }
    }

    /**
     * Handle the return from Stripe after a successful payment.
     */
    public function success(Request $request)
    {
        return redirect()->route('profile.edit', ['#billing'])
            ->with('success', 'Payment successful! Your subscription is being activated.');
    }
}