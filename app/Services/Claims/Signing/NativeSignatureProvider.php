<?php

namespace App\Services\Claims\Signing;

use App\Models\Claim;
use App\Models\ClaimSigner;

/**
 * Built-in signature pad - signers draw their signature in the app (or on
 * a tokenised public page for passengers without an account). Active until
 * Dropbox Sign credentials are configured.
 */
class NativeSignatureProvider implements SignatureProvider
{
    public function name(): string
    {
        return 'native';
    }

    public function createRequests(Claim $claim): void
    {
        // Nothing to register - documents are signed directly in the app.
    }

    public function embeddedSignUrl(ClaimSigner $signer): ?string
    {
        return null;
    }

    public function remind(ClaimSigner $signer): void
    {
        if ($signer->email) {
            send_dynamic_email($signer->email, 'claim-signature-request', [
                '[NAME]'     => $signer->name,
                '[FLIGHT]'   => trim(($signer->claim->airline ?? '') . ' ' . ($signer->claim->flight_number ?? '')),
                '[ROUTE]'    => "{$signer->claim->departure_airport} - {$signer->claim->arrival_airport}",
                '[SIGN_URL]' => route('claim-signature.show', $signer->sign_token),
            ]);
        }
    }
}
