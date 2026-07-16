<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\ClaimSigner;
use App\Services\Claims\ClaimSignatureService;
use App\Services\Claims\Signing\DropboxSignProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Dropbox Sign event callbacks - signature progress for claim
 * authorisation documents. Events are HMAC-verified against the API key.
 */
class DropboxSignWebhookController extends Controller
{
    public function handle(Request $request, ClaimSignatureService $signatures)
    {
        $payload = json_decode((string) $request->input('json'), true);

        if (!is_array($payload) || !$this->verified($payload)) {
            return response('Invalid event', 400);
        }

        $event     = $payload['event']['event_type'] ?? '';
        $requestId = $payload['signature_request']['signature_request_id'] ?? null;

        if ($requestId && in_array($event, ['signature_request_signed', 'signature_request_all_signed', 'signature_request_downloadable'], true)) {
            $signer = ClaimSigner::where('provider_request_id', $requestId)->first();

            if ($signer) {
                // The signed PDF often isn't downloadable at the moment the
                // "signed" event fires - the later "downloadable" event
                // backfills it onto the already-signed record.
                $signed = app(DropboxSignProvider::class)->downloadSigned($signer);

                if ($signer->status === ClaimSigner::STATUS_PENDING) {
                    $signatures->recordProviderSignature($signer, $signed);
                } elseif ($signed && !$signer->signature_path) {
                    $signer->forceFill(['poa_path' => $signed, 'signature_path' => $signed])->save();
                }
            }
        }

        if ($requestId && $event === 'signature_request_declined') {
            ClaimSigner::where('provider_request_id', $requestId)
                ->update(['status' => ClaimSigner::STATUS_DECLINED]);
        }

        Log::info('Dropbox Sign webhook', ['event' => $event, 'request' => $requestId]);

        // Dropbox Sign requires this exact body to acknowledge the event.
        return response('Hello API Event Received', 200);
    }

    private function verified(array $payload): bool
    {
        $hash = $payload['event']['event_hash'] ?? '';
        $time = $payload['event']['event_time'] ?? '';
        $type = $payload['event']['event_type'] ?? '';

        return hash_equals(
            hash_hmac('sha256', $time . $type, (string) config('services.dropbox_sign.api_key')),
            (string) $hash
        );
    }
}
