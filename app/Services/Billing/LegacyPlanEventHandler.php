<?php

namespace App\Services\Billing;

use App\Services\SubscriptionService;
use Stripe\Event;

/**
 * The pre-existing case-management plans (/admin/plans, user_subscriptions).
 * Routes exactly the events the old StripeWebhookController routed, to the
 * unchanged legacy service - so the webhook URL already registered with
 * Stripe keeps doing everything it did before, plus Unjamm Plus.
 */
class LegacyPlanEventHandler implements StripeEventHandler
{
    public function __construct(private readonly SubscriptionService $subscriptions)
    {
    }

    public function handle(Event $event): void
    {
        $object = $event->data->object;

        // Unjamm Plus traffic is marked; it is never a legacy plan purchase.
        if (($object->metadata->product ?? null) === 'unjamm_plus') {
            return;
        }

        match ($event->type) {
            'checkout.session.completed'    => $this->subscriptions->handleSuccessfulPayment($object),
            'invoice.paid'                  => $this->subscriptions->handleRecurringPayment($object),
            'invoice.payment_failed'        => $this->subscriptions->handleFailedPayment($object),
            'customer.subscription.deleted' => $this->subscriptions->handleCancellation($object),
            default                         => null,
        };
    }
}
