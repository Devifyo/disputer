<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\Payout;
use App\Services\Payments\WisePayoutService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Wise transfer state changes (transfers#state-change). Signature-verified
 * with Wise's public key when configured; events for unknown transfers are
 * acknowledged and ignored.
 */
class WiseWebhookController extends Controller
{
    public function handle(Request $request, WisePayoutService $wise)
    {
        $publicKey = config('services.wise.webhook_public_key');

        if ($publicKey) {
            $signature = base64_decode($request->header('X-Signature-SHA256', ''), true);
            $verified  = $signature !== false && openssl_verify(
                $request->getContent(), $signature, $publicKey, OPENSSL_ALGO_SHA256
            ) === 1;

            if (!$verified) {
                Log::warning('Wise webhook rejected - bad signature');

                return response()->json(['error' => 'invalid signature'], 400);
            }
        }

        $payload    = $request->json()->all();
        $transferId = (string) ($payload['data']['resource']['id'] ?? '');

        if ($transferId) {
            $payout = Payout::where('wise_transfer_id', $transferId)->first();

            if ($payout) {
                try {
                    // The payload is treated as a PING only: the state we act
                    // on is re-fetched from Wise's API with our own token, so
                    // a forged webhook can never inject a false status - at
                    // worst it makes us look at the truth.
                    $wise->refreshStatus($payout);
                } catch (\Throwable $e) {
                    Log::error('Wise webhook processing failed', ['transfer' => $transferId, 'error' => $e->getMessage()]);

                    return response()->json(['error' => 'processing failed'], 500);
                }
            }
        }

        return response()->json(['received' => true]);
    }
}
