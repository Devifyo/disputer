<?php

namespace App\Services\Billing;

use Illuminate\Support\Facades\Log;
use RuntimeException;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use UnexpectedValueException;

/**
 * The one Stripe webhook pipeline: verify the signature once, then offer the
 * event to every registered product handler (legacy case plans, Unjamm
 * Plus). One handler failing never stops the others - failures are
 * collected and re-thrown afterwards so the controller returns 500 and
 * Stripe retries; every handler is idempotent, so retries are safe.
 */
class StripeWebhookDispatcher
{
    /** @param iterable<StripeEventHandler> $handlers */
    public function __construct(private readonly iterable $handlers)
    {
    }

    /**
     * @throws SignatureVerificationException|UnexpectedValueException on a
     *         request that is not a verified Stripe event (-> 400)
     * @throws RuntimeException when a handler failed (-> 500, Stripe retries)
     */
    public function dispatch(string $payload, ?string $signature): Event
    {
        $event    = $this->verify($payload, $signature);
        $failures = [];

        foreach ($this->handlers as $handler) {
            try {
                $handler->handle($event);
            } catch (\Throwable $e) {
                $failures[] = $handler::class . ': ' . $e->getMessage();
                Log::error('Stripe webhook handler failed', [
                    'handler' => $handler::class,
                    'type'    => $event->type,
                    'event'   => $event->id,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        if ($failures !== []) {
            throw new RuntimeException(implode(' | ', $failures));
        }

        return $event;
    }

    private function verify(string $payload, ?string $signature): Event
    {
        $secret = config('services.stripe.webhook_secret');

        if ($secret) {
            if (!$signature) {
                throw new SignatureVerificationException('Missing Stripe-Signature header.');
            }

            return Webhook::constructEvent($payload, $signature, $secret);
        }

        // No secret configured (local/test): accept the payload unverified.
        $decoded = json_decode($payload, true);

        if (!is_array($decoded) || empty($decoded['type'])) {
            throw new UnexpectedValueException('Payload is not a Stripe event.');
        }

        return Event::constructFrom($decoded);
    }
}
