<?php

namespace App\Services\Claims;

use App\Models\Claim;
use App\Models\ClaimSigner;
use App\Services\Claims\Signing\DropboxSignProvider;
use App\Services\Claims\Signing\NativeSignatureProvider;
use App\Services\Claims\Signing\SignatureProvider;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Orchestrates the e-signature stage: builds the signer roster (every adult
 * passenger signs their own POA, a guardian signs for each minor), generates
 * the documents, drives the configured provider, and unlocks the claim for
 * filing once every required signature is in.
 */
class ClaimSignatureService
{
    /** Passenger types the airlines mark as minors. */
    private const MINOR_TYPES = ['CHD', 'INF'];

    public function __construct(private ClaimLegalDocumentService $documents)
    {
    }

    public function provider(): SignatureProvider
    {
        return DropboxSignProvider::configured()
            ? app(DropboxSignProvider::class)
            : app(NativeSignatureProvider::class);
    }

    /**
     * Called at claim confirmation: create the signer roster, generate the
     * unsigned documents and register provider requests. Idempotent.
     */
    public function setup(Claim $claim): void
    {
        $claim->loadMissing('itinerary.passengers', 'user', 'signers');

        if ($claim->signers->isEmpty()) {
            $this->buildRoster($claim);
            $claim->load('signers');
        }

        $this->documents->ensureGenerated($claim);

        // A provider hiccup (quota, outage, unapproved app) must never block
        // confirmation - signers without a provider request simply use the
        // built-in pad until the provider recovers.
        try {
            $this->provider()->createRequests($claim);
        } catch (Throwable $e) {
            Log::warning('Signature provider request failed - built-in pad remains available', [
                'claim' => $claim->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function buildRoster(Claim $claim): void
    {
        $passengers = $claim->itinerary?->passengers ?? collect();
        $account    = $claim->user;
        $lead       = null;

        if ($passengers->isEmpty()) {
            $claim->signers()->create([
                'name'     => $claim->passenger_name ?: ($account?->name ?? 'Passenger'),
                'email'    => $claim->contact_email ?: $account?->email,
                'role'     => ClaimSigner::ROLE_PASSENGER,
                'provider' => $this->provider()->name(),
            ]);

            return;
        }

        foreach ($passengers as $passenger) {
            $isMinor = in_array(strtoupper((string) $passenger->type), self::MINOR_TYPES, true);

            if ($isMinor) {
                continue; // covered below by the guardian
            }

            $isLead = $lead === null;
            $lead ??= $passenger;

            $claim->signers()->create([
                'itinerary_passenger_id' => $passenger->id,
                'name'                   => $passenger->full_name,
                // The account holder's own signature happens in-app; other
                // adults are invited by email from the signature screen.
                'email'                  => $isLead ? ($claim->contact_email ?: $account?->email) : null,
                'role'                   => ClaimSigner::ROLE_PASSENGER,
                'provider'               => $this->provider()->name(),
            ]);
        }

        // One guardian signature per minor, signed by the lead adult.
        foreach ($passengers as $passenger) {
            if (!in_array(strtoupper((string) $passenger->type), self::MINOR_TYPES, true)) {
                continue;
            }

            $claim->signers()->create([
                'itinerary_passenger_id' => $passenger->id,
                'name'                   => $lead?->full_name ?: ($account?->name ?? 'Guardian'),
                'email'                  => $claim->contact_email ?: $account?->email,
                'role'                   => ClaimSigner::ROLE_GUARDIAN,
                'signs_for'              => $passenger->full_name,
                'provider'               => $this->provider()->name(),
            ]);
        }
    }

    /** Invite an additional adult passenger to sign, by email. */
    public function invite(ClaimSigner $signer, string $email): void
    {
        $signer->forceFill(['email' => $email, 'invited_at' => now()])->save();

        if ($this->provider() instanceof DropboxSignProvider) {
            try {
                $this->provider()->createRequests($signer->claim->load('signers'));
            } catch (Throwable $e) {
                Log::warning('Signature provider invite failed - built-in pad remains available', [
                    'signer' => $signer->id,
                    'error'  => $e->getMessage(),
                ]);
            }
        }

        send_dynamic_email($email, 'claim-signature-request', [
            '[NAME]'     => $signer->name,
            '[FLIGHT]'   => trim(($signer->claim->airline ?? '') . ' ' . ($signer->claim->flight_number ?? '')),
            '[ROUTE]'    => "{$signer->claim->departure_airport} - {$signer->claim->arrival_airport}",
            '[SIGN_URL]' => route('claim-signature.show', $signer->sign_token),
        ]);
    }

    /**
     * Atomically move a signer from pending to signed. Webhooks, page
     * reconciliation and double-clicks can race - only one caller wins,
     * so the signature is never recorded twice.
     */
    private function claimSignature(ClaimSigner $signer): bool
    {
        return ClaimSigner::whereKey($signer->id)
            ->where('status', ClaimSigner::STATUS_PENDING)
            ->update(['status' => ClaimSigner::STATUS_SIGNED, 'signed_at' => now()]) > 0;
    }

    /** A native (signature pad) signature arrived. */
    public function recordNativeSignature(ClaimSigner $signer, string $pngDataUrl): void
    {
        $binary = base64_decode(substr($pngDataUrl, strlen('data:image/png;base64,')), true);
        abort_if($binary === false || strlen($binary) > 512 * 1024, 422, 'Invalid signature image.');

        if (!$this->claimSignature($signer)) {
            return;
        }
        $signer->refresh();

        $path = "claims/{$signer->claim->user_id}/legal/signature-{$signer->claim->number}-{$signer->id}.png";
        Storage::disk('local')->put($path, $binary);
        $signer->forceFill(['signature_path' => $path])->save();

        $this->documents->regenerateSigned($signer);
        $this->afterSignature($signer->claim->fresh(['signers']), $signer);
    }

    /** A provider webhook marked a signature complete. */
    public function recordProviderSignature(ClaimSigner $signer, ?string $signedPdfPath = null): void
    {
        $claimed = $this->claimSignature($signer);

        if ($signedPdfPath) {
            $signer->forceFill(['signature_path' => $signedPdfPath, 'poa_path' => $signedPdfPath])->save();
        }

        if ($claimed) {
            $this->afterSignature($signer->claim->fresh(['signers']), $signer->refresh());
        }
    }

    /** Unlock filing once the last required signature is in. */
    private function afterSignature(Claim $claim, ClaimSigner $signer): void
    {
        $claim->recordEvent('Authorisation signed by ' . $signer->coversLabel(), 'done', now(), 2);

        $workflow = app(ClaimWorkflowService::class);
        $workflow->audit($claim, 'Authorisation signed: ' . $signer->coversLabel(), 'customer');

        if (!$claim->signaturesComplete() || $claim->signed_at) {
            return;
        }

        $claim->forceFill(['signed_at' => now()])->save();
        $claim->recordEvent('All authorisations signed - your claim is unlocked for filing', 'done', now(), 2);

        if ($workflow->can($claim, 'ready_to_file')) {
            $workflow->transition($claim, 'ready_to_file', 'system', null, 'All required signatures completed.');
        }
    }

    /**
     * Sync a pending signer against the provider's records - covers the
     * race where a signer finishes in the embedded window before the
     * completion webhook has been processed.
     */
    public function reconcile(ClaimSigner $signer): ClaimSigner
    {
        $provider = $this->provider();

        if ($signer->provider_signature_id && $provider instanceof DropboxSignProvider) {
            try {
                if ($signer->status === ClaimSigner::STATUS_PENDING && $provider->isSigned($signer)) {
                    $this->recordProviderSignature($signer, $provider->downloadSigned($signer));
                } elseif ($signer->status === ClaimSigner::STATUS_SIGNED && !$signer->signature_path) {
                    // Signed but the provider's signed PDF wasn't ready yet -
                    // fetch it so "View POA" shows the executed document.
                    if ($signed = $provider->downloadSigned($signer)) {
                        $signer->forceFill(['poa_path' => $signed, 'signature_path' => $signed])->save();
                    }
                }
            } catch (Throwable $e) {
                Log::warning('Signature reconciliation failed', ['signer' => $signer->id, 'error' => $e->getMessage()]);
            }
        }

        return $signer->fresh();
    }

    /** Nudge everyone who was invited 48h+ ago and still hasn't signed. */
    public function sendReminders(): int
    {
        $due = ClaimSigner::where('status', ClaimSigner::STATUS_PENDING)
            ->whereNotNull('email')
            ->where(fn ($q) => $q->whereNull('reminded_at')->orWhere('reminded_at', '<', now()->subHours(48)))
            ->where(fn ($q) => $q->whereNotNull('invited_at')->where('invited_at', '<', now()->subHours(48)))
            ->with('claim')
            ->get();

        foreach ($due as $signer) {
            $this->provider()->remind($signer);
            $signer->forceFill(['reminded_at' => now()])->save();
        }

        return $due->count();
    }
}
