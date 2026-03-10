<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\UserSubscription;
use Illuminate\Support\Facades\Log;

class SubscriptionService
{
    /**
     * Handles the INITIAL purchase (checkout.session.completed)
     */
    public function handleSuccessfulPayment($session)
    {
        $clientReferenceId = $session->client_reference_id;
        
        if (!$clientReferenceId) {
            Log::error('Stripe Webhook: No client_reference_id found.', ['session_id' => $session->id]);
            return;
        }

        $parts = explode('_', $clientReferenceId);
        $userId = $parts[0] ?? null;
        $planId = $parts[1] ?? null;

        $plan = Plan::find($planId);
        
        // CRITICAL FIX: Ensure the plan actually exists before calling properties on it
        if (!$plan) {
            Log::error('Stripe Webhook: Plan not found.', ['plan_id' => $planId, 'session_id' => $session->id]);
            return;
        }

        // Deactivate old subscriptions
        UserSubscription::where('user_id', $userId)
            ->where('status', 'active')
            ->update(['status' => 'canceled']);

        $expiresAt = $plan->type === 'recurring_yearly' ? now()->addYear() : null;

        // Save the new subscription
        UserSubscription::create([
            'user_id' => $userId,
            'plan_id' => $plan->id,
            'cases_allowed' => $plan->case_limit,
            'cases_used' => 0,
            'status' => 'active',
            'transaction_id' => $session->id,
            'stripe_subscription_id' => $session->subscription ?? null,
            'stripe_customer_id' => $session->customer ?? null,
            'starts_at' => now(),
            'expires_at' => $expiresAt,
        ]);

        Log::info("Activated plan '{$plan->name}' for User ID: {$userId}");
    }

    /**
     * Handles AUTOMATIC RENEWALS (invoice.paid)
     */
    public function handleRecurringPayment($invoice)
    {
        if (!$invoice->subscription) {
            return;
        }

        // CRITICAL FIX: Ignore the very first invoice. checkout.session.completed already handles year 1.
        if ($invoice->billing_reason === 'subscription_create') {
            return;
        }

        $localSubscription = UserSubscription::where('stripe_subscription_id', $invoice->subscription)
            ->where('status', 'active')
            ->first();

        if (!$localSubscription) {
            Log::warning("Stripe Webhook: Received invoice.paid for unknown/inactive subscription ID: {$invoice->subscription}");
            return;
        }

        $newExpiration = $localSubscription->expires_at 
            ? $localSubscription->expires_at->addYear() 
            : now()->addYear();

        $localSubscription->update([
            'expires_at' => $newExpiration,
        ]);

        Log::info("Successfully renewed subscription for User ID: {$localSubscription->user_id}. New expiration: {$newExpiration}");
    }

    /**
     * Handles FAILED PAYMENTS (invoice.payment_failed)
     */
    public function handleFailedPayment($invoice)
    {
        if (!$invoice->subscription) {
            return;
        }

        UserSubscription::where('stripe_subscription_id', $invoice->subscription)
            ->where('status', 'active')
            ->update(['status' => 'expired']); // Revoke access

        Log::info("Marked subscription as expired due to failed payment: {$invoice->subscription}");
    }

    /**
     * Handles CANCELLATIONS (customer.subscription.deleted)
     */
    public function handleCancellation($subscription)
    {
        UserSubscription::where('stripe_subscription_id', $subscription->id)
            ->update(['status' => 'canceled']); // Revoke access

        Log::info("Canceled subscription in database: {$subscription->id}");
    }
}