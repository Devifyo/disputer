<?php

namespace App\Services\Claims\Signing;

use App\Models\Claim;
use App\Models\ClaimSigner;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Dropbox Sign (Essentials) embedded signing. Each signer gets their own
 * signature request containing their Power of Attorney (the lead signer's
 * request also carries the Assignment of Claims), signed in-app via the
 * embedded experience - no redirect. Completion arrives on the webhook.
 */
class DropboxSignProvider implements SignatureProvider
{
    private const BASE = 'https://api.hellosign.com/v3';

    public function name(): string
    {
        return 'dropbox_sign';
    }

    public static function configured(): bool
    {
        // Embedded signing needs both credentials - with only an API key the
        // built-in signature pad stays active so the flow never breaks.
        return (bool) (config('services.dropbox_sign.api_key') && config('services.dropbox_sign.client_id'));
    }

    public function createRequests(Claim $claim): void
    {
        foreach ($claim->signers as $index => $signer) {
            if ($signer->provider_request_id || !$signer->email) {
                continue;
            }

            $files = array_filter([
                $signer->poa_path,
                $index === 0 ? $claim->assignment_path : null,
            ]);

            $request = Http::withBasicAuth(config('services.dropbox_sign.api_key'), '')
                ->asMultipart();

            foreach (array_values($files) as $i => $path) {
                $request = $request->attach("files[{$i}]", Storage::disk('local')->get($path), basename($path));
            }

            $response = $request->post(self::BASE . '/signature_request/create_embedded', [
                'client_id'                  => config('services.dropbox_sign.client_id'),
                'title'                      => "Unjamm claim {$claim->reference} - authorisation",
                'subject'                    => 'Sign your claim authorisation documents',
                'message'                    => 'Please sign so Unjamm can file your compensation claim with the airline.',
                'signers[0][email_address]'  => $signer->email,
                'signers[0][name]'           => $signer->name,
                // Text tags in the PDFs become guided signature fields - the
                // signing window jumps straight to them, no hunting.
                'use_text_tags'              => 1,
                'hide_text_tags'             => 1,
                'test_mode'                  => config('services.dropbox_sign.test_mode') ? 1 : 0,
            ]);

            if (!$response->successful()) {
                throw new RuntimeException('Dropbox Sign request failed: HTTP ' . $response->status());
            }

            $signature = $response->json('signature_request.signatures.0');

            $signer->forceFill([
                'provider'              => $this->name(),
                'provider_request_id'   => $response->json('signature_request.signature_request_id'),
                'provider_signature_id' => $signature['signature_id'] ?? null,
            ])->save();
        }
    }

    public function embeddedSignUrl(ClaimSigner $signer): ?string
    {
        if (!$signer->provider_signature_id) {
            return null;
        }

        $response = Http::withBasicAuth(config('services.dropbox_sign.api_key'), '')
            ->get(self::BASE . "/embedded/sign_url/{$signer->provider_signature_id}");

        return $response->successful() ? $response->json('embedded.sign_url') : null;
    }

    public function remind(ClaimSigner $signer): void
    {
        if (!$signer->provider_request_id || !$signer->email) {
            return;
        }

        Http::withBasicAuth(config('services.dropbox_sign.api_key'), '')
            ->post(self::BASE . "/signature_request/remind/{$signer->provider_request_id}", [
                'email_address' => $signer->email,
            ]);
    }

    /** Ask Dropbox directly whether this signer has signed - webhook-independent. */
    public function isSigned(ClaimSigner $signer): bool
    {
        if (!$signer->provider_request_id) {
            return false;
        }

        $response = Http::withBasicAuth(config('services.dropbox_sign.api_key'), '')
            ->get(self::BASE . "/signature_request/{$signer->provider_request_id}");

        if (!$response->successful()) {
            return false;
        }

        $signature = collect($response->json('signature_request.signatures', []))
            ->firstWhere('signature_id', $signer->provider_signature_id);

        return ($signature['status_code'] ?? null) === 'signed'
            || (bool) $response->json('signature_request.is_complete');
    }

    /** Download the completed, signed PDF for a finished request. */
    public function downloadSigned(ClaimSigner $signer): ?string
    {
        if (!$signer->provider_request_id) {
            return null;
        }

        $response = Http::withBasicAuth(config('services.dropbox_sign.api_key'), '')
            ->get(self::BASE . "/signature_request/files/{$signer->provider_request_id}", ['file_type' => 'pdf']);

        if (!$response->successful()) {
            return null;
        }

        $path = "claims/{$signer->claim->user_id}/legal/signed-{$signer->claim->number}-{$signer->id}.pdf";
        Storage::disk('local')->put($path, $response->body());

        return $path;
    }
}
