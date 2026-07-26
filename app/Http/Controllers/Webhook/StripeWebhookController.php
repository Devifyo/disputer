<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Services\Billing\StripeWebhookDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Stripe\Exception\SignatureVerificationException;
use UnexpectedValueException;

/**
 * HTTP shell around the Stripe webhook pipeline. Both registered endpoints
 * (/stripe/webhook - the URL configured in the Stripe dashboard - and
 * /api/webhooks/stripe) land here; verification and product routing live in
 * the dispatcher.
 */
class StripeWebhookController extends Controller
{
    public function handle(Request $request, StripeWebhookDispatcher $dispatcher): JsonResponse
    {
        try {
            $event = $dispatcher->dispatch($request->getContent(), $request->header('Stripe-Signature'));
        } catch (SignatureVerificationException|UnexpectedValueException $e) {
            Log::warning('Stripe webhook rejected', ['error' => $e->getMessage()]);

            return response()->json(['error' => 'invalid signature'], 400);
        } catch (RuntimeException) {
            // A handler failed - 500 so Stripe retries; handlers are idempotent.
            return response()->json(['error' => 'processing failed'], 500);
        }

        return response()->json(['received' => true, 'type' => $event->type]);
    }
}
