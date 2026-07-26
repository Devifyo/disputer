<?php

namespace App\Services\Billing;

use Stripe\Event;

/**
 * One product's interest in Stripe events. The dispatcher verifies the
 * signature once and offers every event to every registered handler; each
 * handler ignores what it does not care about. New billing products plug in
 * by implementing this and registering in AppServiceProvider - no changes
 * to the webhook pipeline (open/closed).
 */
interface StripeEventHandler
{
    public function handle(Event $event): void;
}
