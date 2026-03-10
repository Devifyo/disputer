<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use UnexpectedValueException;
use Throwable;

class StripeWebhookController extends Controller
{
    /**
     * Utilize PHP 8 constructor property promotion and read-only properties.
     */
    public function __construct(
        private readonly SubscriptionService $subscriptionService
    ) {}

    /**
     * Handle incoming Stripe webhook requests.
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');

        // 1. Verify Request Authenticity
        try {
            $event = $this->verifySignature($payload, $signature);
        } catch (SignatureVerificationException $e) {
            Log::warning('Stripe Webhook: Invalid Signature.', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Invalid signature.'], 400);
        } catch (UnexpectedValueException $e) {
            Log::warning('Stripe Webhook: Invalid Payload or Config.', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Invalid payload.'], 400);
        }

        // 2. Process the Event
        try {
            $this->routeEvent($event);
        } catch (Throwable $e) {
            // Catching Throwable ensures that if our service crashes, we log the exact stack trace,
            // and return a 500 status so Stripe knows to retry the webhook later.
            Log::error('Stripe Webhook Processing Failed.', [
                'event_id' => $event->id ?? null,
                'event_type' => $event->type ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json(['error' => 'Internal server error processing webhook.'], 500);
        }

        // 3. Acknowledge Receipt
        return response()->json(['message' => 'Webhook handled successfully.']);
    }

    /**
     * Construct and verify the Stripe event payload.
     * * @throws UnexpectedValueException|SignatureVerificationException
     */
    private function verifySignature(string $payload, ?string $signature): Event
    {
        $secret = config('services.stripe.webhook_secret');

        // Fail fast if the environment isn't configured properly
        if (empty($secret)) {
            throw new UnexpectedValueException('Stripe webhook secret is not configured on this environment.');
        }

        if (empty($signature)) {
            throw new SignatureVerificationException('Missing Stripe-Signature header.', $signature, $payload);
        }

        return Webhook::constructEvent($payload, $signature, $secret);
    }

    /**
     * Route the verified Stripe event to the appropriate service handler.
     */
    private function routeEvent(Event $event): void
    {
        Log::info("Stripe Webhook Received: {$event->type}", ['event_id' => $event->id]);

        match ($event->type) {
            'checkout.session.completed' => $this->subscriptionService->handleSuccessfulPayment($event->data->object),
            'invoice.paid' => $this->subscriptionService->handleRecurringPayment($event->data->object),
            
            // Sad Paths: Failed payments and cancellations
            'invoice.payment_failed' => $this->subscriptionService->handleFailedPayment($event->data->object),
            'customer.subscription.deleted' => $this->subscriptionService->handleCancellation($event->data->object),
            
            default => Log::debug("Stripe Webhook Unhandled Event Type: {$event->type}", ['event_id' => $event->id]),
        };
    }
}