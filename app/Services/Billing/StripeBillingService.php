<?php

namespace App\Services\Billing;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;
use Throwable;

/**
 * Every Stripe call the platform makes. Product/price IDs live on the plan
 * rows (admin-managed) - nothing Stripe-specific is hardcoded. The webhook
 * keeps the local subscriptions table in sync; methods here that mutate a
 * subscription also sync immediately so the admin sees the result without
 * waiting for the webhook round-trip.
 */
class StripeBillingService
{
    private ?StripeClient $client = null;

    public function configured(): bool
    {
        return (bool) config('services.stripe.secret');
    }

    private function stripe(): StripeClient
    {
        return $this->client ??= new StripeClient(config('services.stripe.secret'));
    }

    /** Reuse the user's Stripe customer or create one. */
    public function customerId(User $user): string
    {
        if ($user->stripe_customer_id) {
            return $user->stripe_customer_id;
        }

        $customer = $this->stripe()->customers->create([
            'email'    => $user->email,
            'name'     => $user->name,
            'metadata' => ['user_id' => $user->id],
        ]);

        $user->forceFill(['stripe_customer_id' => $customer->id])->save();

        return $customer->id;
    }

    /** Hosted Stripe Checkout for a plan + interval. Returns the redirect URL. */
    public function checkoutUrl(User $user, SubscriptionPlan $plan, string $interval, string $successUrl, string $cancelUrl): string
    {
        $priceId = $plan->stripePriceId($interval);

        abort_if(!$priceId, 422, 'This plan is not connected to Stripe yet - the team has been notified.');

        // metadata.product marks this as an Unjamm Plus checkout: the shared
        // webhook serves two billing products, and each handler claims only
        // its own events by this marker.
        $params = [
            'mode'                 => 'subscription',
            'customer'             => $this->customerId($user),
            'line_items'           => [['price' => $priceId, 'quantity' => 1]],
            'success_url'          => $successUrl,
            'cancel_url'           => $cancelUrl,
            'client_reference_id'  => (string) $user->id,
            'metadata'             => ['product' => 'unjamm_plus'],
            'subscription_data'    => ['metadata' => ['product' => 'unjamm_plus', 'user_id' => $user->id, 'plan_id' => $plan->id]],
            'allow_promotion_codes' => true,
        ];

        if ($plan->trial_days > 0) {
            $params['subscription_data']['trial_period_days'] = $plan->trial_days;
        }

        return $this->stripe()->checkout->sessions->create($params)->url;
    }

    /** Stripe Customer Portal - card updates, invoices, self-serve cancel. */
    public function portalUrl(User $user, string $returnUrl): string
    {
        return $this->stripe()->billingPortal->sessions->create([
            'customer'   => $this->customerId($user),
            'return_url' => $returnUrl,
        ])->url;
    }

    public function cancelAtPeriodEnd(Subscription $subscription): void
    {
        $stripeSub = $this->stripe()->subscriptions->update($subscription->stripe_subscription_id, [
            'cancel_at_period_end' => true,
        ]);

        $this->syncFromStripe($stripeSub->toArray());
    }

    public function reactivate(Subscription $subscription): void
    {
        $stripeSub = $this->stripe()->subscriptions->update($subscription->stripe_subscription_id, [
            'cancel_at_period_end' => false,
        ]);

        $this->syncFromStripe($stripeSub->toArray());
    }

    /**
     * Pause billing without losing the subscription: Stripe stops collecting
     * (invoices are voided) and the membership resumes on unpause. Kept
     * separate from cancel-at-period-end - a pause is reversible and keeps
     * the same subscription, price and billing anchor.
     */
    public function pauseCollection(Subscription $subscription, ?Carbon $resumesAt = null): void
    {
        $stripeSub = $this->stripe()->subscriptions->update($subscription->stripe_subscription_id, [
            'pause_collection' => array_filter([
                'behavior'   => 'void',
                'resumes_at' => $resumesAt?->getTimestamp(),
            ]),
        ]);

        $this->syncFromStripe($stripeSub->toArray());
    }

    public function resumeCollection(Subscription $subscription): void
    {
        $stripeSub = $this->stripe()->subscriptions->update($subscription->stripe_subscription_id, [
            'pause_collection' => null,
        ]);

        $this->syncFromStripe($stripeSub->toArray());
    }

    /** Portal deep-link straight to the card-update screen. */
    public function paymentMethodUrl(User $user, string $returnUrl): string
    {
        return $this->stripe()->billingPortal->sessions->create([
            'customer'   => $this->customerId($user),
            'return_url' => $returnUrl,
            'flow_data'  => ['type' => 'payment_method_update'],
        ])->url;
    }

    /** The card Stripe will charge next, for display only. */
    public function paymentMethod(User $user): ?array
    {
        if (!$user->stripe_customer_id) {
            return null;
        }

        try {
            $customer = $this->stripe()->customers->retrieve($user->stripe_customer_id, ['expand' => ['invoice_settings.default_payment_method']]);
            $card     = $customer->invoice_settings->default_payment_method?->card;

            return $card ? [
                'brand'   => ucfirst($card->brand),
                'last4'   => $card->last4,
                'expires' => sprintf('%02d/%d', $card->exp_month, $card->exp_year),
            ] : null;
        } catch (Throwable $e) {
            Log::warning('Stripe payment method lookup failed', ['user' => $user->id, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /** Upgrade/downgrade: swap the subscription onto another plan/interval. */
    public function changePlan(Subscription $subscription, SubscriptionPlan $plan, string $interval): void
    {
        $priceId = $plan->stripePriceId($interval);
        abort_if(!$priceId, 422, "\"{$plan->name}\" has no Stripe price for {$interval} billing.");

        $stripeSub = $this->stripe()->subscriptions->retrieve($subscription->stripe_subscription_id);

        $updated = $this->stripe()->subscriptions->update($subscription->stripe_subscription_id, [
            'items'              => [['id' => $stripeSub->items->data[0]->id, 'price' => $priceId]],
            'proration_behavior' => 'create_prorations',
            'metadata'           => ['plan_id' => $plan->id],
        ]);

        $this->syncFromStripe($updated->toArray());
    }

    /**
     * Make Stripe match the plan row - called on every admin plan save, so
     * nobody ever copies IDs out of the Stripe dashboard. Stripe prices are
     * immutable, so a changed amount/currency archives the old price and
     * creates a fresh one; unchanged prices are left alone.
     */
    /** @return array{migrated: int, failed: int} how many live subscribers moved to changed prices */
    public function syncPlan(SubscriptionPlan $plan): array
    {
        $productId = $plan->stripe_product_id;

        if ($productId) {
            $this->stripe()->products->update($productId, ['name' => $plan->name]);
        } else {
            $productId = $this->stripe()->products->create([
                'name'     => $plan->name,
                'metadata' => ['plan_key' => $plan->key, 'product' => 'unjamm_plus'],
            ])->id;
        }

        $oldMonthly = $plan->stripe_monthly_price_id;
        $oldAnnual  = $plan->stripe_annual_price_id;

        $plan->forceFill([
            'stripe_product_id'       => $productId,
            'stripe_monthly_price_id' => $newMonthly = $this->syncPrice($plan, $productId, 'month', $plan->price('monthly'), $oldMonthly),
            'stripe_annual_price_id'  => $newAnnual = $this->syncPrice($plan, $productId, 'year', $plan->price('annual'), $oldAnnual),
        ])->save();

        // Price changes apply to EVERYONE: existing subscribers move to the
        // new price and renew at it from their next cycle (no proration, no
        // grandfathering - the business rule the client chose).
        $result = ['migrated' => 0, 'failed' => 0];
        foreach ([[$oldMonthly, $newMonthly], [$oldAnnual, $newAnnual]] as [$old, $new]) {
            if ($old && $new && $old !== $new) {
                $moved = $this->migrateSubscribers($old, $new);
                $result['migrated'] += $moved['migrated'];
                $result['failed']   += $moved['failed'];
            }
        }

        return $result;
    }

    /** Move every live subscription from a replaced price to its successor. */
    private function migrateSubscribers(string $oldPriceId, string $newPriceId): array
    {
        $result = ['migrated' => 0, 'failed' => 0];

        $live = Subscription::where('stripe_price_id', $oldPriceId)
            ->whereIn('status', Subscription::GOOD_STANDING)
            ->get();

        foreach ($live as $subscription) {
            try {
                $stripeSub = $this->stripe()->subscriptions->retrieve($subscription->stripe_subscription_id);

                $updated = $this->stripe()->subscriptions->update($subscription->stripe_subscription_id, [
                    'items'              => [['id' => $stripeSub->items->data[0]->id, 'price' => $newPriceId]],
                    // Next renewal bills the new amount; the current period
                    // stays as paid - no surprise mid-cycle charge.
                    'proration_behavior' => 'none',
                ]);

                $this->syncFromStripe($updated->toArray());
                $result['migrated']++;
            } catch (\Throwable $e) {
                // e.g. a currency change - Stripe cannot move a subscription
                // across currencies. Left on the old (archived) price, which
                // still renews; flagged for the admin in the toast.
                Log::warning('Subscriber price migration failed', [
                    'subscription' => $subscription->stripe_subscription_id,
                    'error'        => $e->getMessage(),
                ]);
                $result['failed']++;
            }
        }

        return $result;
    }

    /** The current price if it still matches, else archive it and mint a new one. */
    private function syncPrice(SubscriptionPlan $plan, string $productId, string $interval, ?float $amount, ?string $currentId): ?string
    {
        if ($amount === null || $amount <= 0) {
            $this->archivePrice($currentId);

            return null;
        }

        $cents    = (int) round($amount * 100);
        $currency = strtolower($plan->currency);

        if ($currentId) {
            try {
                $existing = $this->stripe()->prices->retrieve($currentId);

                if ($existing->active && $existing->unit_amount === $cents && $existing->currency === $currency) {
                    return $currentId;
                }
            } catch (\Throwable) {
                // Unknown/deleted price - fall through and create a fresh one.
            }

            $this->archivePrice($currentId);
        }

        return $this->stripe()->prices->create([
            'product'     => $productId,
            'unit_amount' => $cents,
            'currency'    => $currency,
            'recurring'   => ['interval' => $interval],
        ])->id;
    }

    private function archivePrice(?string $priceId): void
    {
        if (!$priceId) {
            return;
        }

        try {
            $this->stripe()->prices->update($priceId, ['active' => false]);
        } catch (\Throwable) {
            // Already archived or gone - nothing to unwind.
        }
    }

    /** Recent invoices for the admin's billing-history view. */
    public function invoices(User $user, int $limit = 12): array
    {
        if (!$user->stripe_customer_id) {
            return [];
        }

        return collect($this->stripe()->invoices->all([
                'customer' => $user->stripe_customer_id,
                'limit'    => $limit,
            ])->data)
            ->map(fn ($invoice) => [
                'number' => $invoice->number,
                'date'   => Carbon::createFromTimestamp($invoice->created)->format('d M Y'),
                'total'  => strtoupper($invoice->currency) . ' ' . number_format($invoice->total / 100, 2),
                'status' => $invoice->status,
                'url'    => $invoice->hosted_invoice_url,
                'pdf'    => $invoice->invoice_pdf,
            ])
            ->all();
    }

    /**
     * Mirror a Stripe subscription object into the local table - the ONE
     * place local state is written, used by webhooks and admin actions alike.
     *
     * Returns null for subscriptions that are not ours: the legacy
     * case-management product shares the Stripe account and webhook, and its
     * subscriptions must never be mirrored into the Plus table.
     */
    public function syncFromStripe(array $stripeSub): ?Subscription
    {
        $priceId = $stripeSub['items']['data'][0]['price']['id'] ?? null;
        $plan    = $this->planForPrice($priceId, (int) ($stripeSub['metadata']['plan_id'] ?? 0));

        $userId = (int) ($stripeSub['metadata']['user_id'] ?? 0)
            ?: User::where('stripe_customer_id', $stripeSub['customer'] ?? '')->value('id');

        // Ours = carries our metadata marker, matches one of our plans'
        // prices, or is already mirrored (covers subscriptions created
        // before the marker existed).
        $known = Subscription::where('stripe_subscription_id', $stripeSub['id'] ?? '')->exists();
        if ((($stripeSub['metadata']['product'] ?? '') !== 'unjamm_plus') && !$plan && !$known) {
            return null;
        }

        if (!$userId) {
            Log::warning('Stripe subscription has no resolvable user - skipped', ['subscription' => $stripeSub['id'] ?? null]);

            return null;
        }

        // Item-level periods (newer API versions) with top-level fallback.
        $item        = $stripeSub['items']['data'][0] ?? [];
        $periodStart = $stripeSub['current_period_start'] ?? $item['current_period_start'] ?? null;
        $periodEnd   = $stripeSub['current_period_end'] ?? $item['current_period_end'] ?? null;

        return Subscription::updateOrCreate(
            ['stripe_subscription_id' => $stripeSub['id']],
            [
                'user_id'              => $userId,
                'subscription_plan_id' => $plan?->id,
                'stripe_customer_id'   => $stripeSub['customer'] ?? null,
                'stripe_price_id'      => $priceId,
                'interval'             => ($item['price']['recurring']['interval'] ?? 'month') === 'year' ? 'annual' : 'monthly',
                'status'               => $stripeSub['status'] ?? 'incomplete',
                'current_period_start' => $periodStart ? Carbon::createFromTimestamp($periodStart) : null,
                'current_period_end'   => $periodEnd ? Carbon::createFromTimestamp($periodEnd) : null,
                'cancel_at_period_end' => (bool) ($stripeSub['cancel_at_period_end'] ?? false),
                'trial_ends_at'        => !empty($stripeSub['trial_end']) ? Carbon::createFromTimestamp($stripeSub['trial_end']) : null,
                'canceled_at'          => !empty($stripeSub['canceled_at']) ? Carbon::createFromTimestamp($stripeSub['canceled_at']) : null,
                // Mirror Stripe's pause so the app can show it without a round trip.
                'paused_at'            => !empty($stripeSub['pause_collection']) ? now() : null,
                'resumes_at'           => !empty($stripeSub['pause_collection']['resumes_at'])
                    ? Carbon::createFromTimestamp($stripeSub['pause_collection']['resumes_at'])
                    : null,
            ]
        );
    }

    public function retrieveAndSync(string $stripeSubscriptionId): ?Subscription
    {
        $stripeSub = $this->stripe()->subscriptions->retrieve($stripeSubscriptionId);

        return $this->syncFromStripe($stripeSub->toArray());
    }

    private function planForPrice(?string $priceId, int $metadataPlanId): ?SubscriptionPlan
    {
        if ($priceId) {
            $plan = SubscriptionPlan::where('stripe_monthly_price_id', $priceId)
                ->orWhere('stripe_annual_price_id', $priceId)
                ->first();

            if ($plan) {
                return $plan;
            }
        }

        return $metadataPlanId ? SubscriptionPlan::find($metadataPlanId) : null;
    }
}
