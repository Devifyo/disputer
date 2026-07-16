<?php

namespace App\Services\Claims\Signing;

use App\Models\Claim;
use App\Models\ClaimSigner;

/**
 * A way of collecting legally binding signatures on a claim's authorisation
 * documents. Dropbox Sign when configured; the built-in signature pad
 * otherwise.
 */
interface SignatureProvider
{
    public function name(): string;

    /** Register a signature request per signer (no-op for the built-in pad). */
    public function createRequests(Claim $claim): void;

    /** Embedded signing URL for the in-app experience, when the provider has one. */
    public function embeddedSignUrl(ClaimSigner $signer): ?string;

    /** Nudge a signer who hasn't signed yet. */
    public function remind(ClaimSigner $signer): void;
}
