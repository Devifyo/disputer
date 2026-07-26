<?php

namespace App\Services\Billing;

use Stripe\Event;

/**
 * Unjamm Plus memberships: mirrors every subscription-bearing event into the
 * local subscriptions table through the single sync path, so replays and
 * out-of-order deliveries always converge on Stripe's current truth.
 */
class PlusSubscriptionEventHandler implements StripeEventHandler
{
    public function __construct(private readonly StripeBillingService $billing)
    {
    }

    public function handle(Event $event): void
    {
        $object = $event->data->object;

        match (true) {
            str_starts_with($event->type, 'customer.subscription.')
                => $this->billing->syncFromStripe($object->toArray()),

            $event->type === 'checkout.session.completed'
                && ($object->mode ?? '') === 'subscription'
                && !empty($object->subscription)
                => $this->billing->retrieveAndSync((string) $object->subscription),

            in_array($event->type, ['invoice.paid', 'invoice.payment_failed'], true)
                && !empty($object->subscription)
                => $this->billing->retrieveAndSync((string) $object->subscription),

            default => null,
        };
    }
}
