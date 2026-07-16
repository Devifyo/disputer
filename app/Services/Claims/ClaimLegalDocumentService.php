<?php

namespace App\Services\Claims;

use App\Models\Claim;
use App\Models\ClaimSigner;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Generates the authorisation documents a customer signs before Unjamm
 * files their claim: one jurisdiction-specific Power of Attorney per
 * passenger (a guardian's POA covers the minor) and one Assignment of
 * Claims for the booking. Documents are generated unsigned at confirmation
 * and regenerated with the signature embedded once a signer signs.
 */
class ClaimLegalDocumentService
{
    /** Jurisdiction of the winning regulation - drives the POA template. */
    public static function jurisdiction(Claim $claim): string
    {
        return match ($claim->eligibility_regulation) {
            'UK261'  => 'UK',
            'APPR'   => 'CA',
            'US_DOT' => 'US',
            default  => 'EU',
        };
    }

    public function ensureGenerated(Claim $claim): void
    {
        foreach ($claim->signers as $signer) {
            if (!$signer->poa_path) {
                $this->generatePoa($signer);
            }
        }

        if (!$claim->assignment_path) {
            $this->generateAssignment($claim);
        }
    }

    public function generatePoa(ClaimSigner $signer): void
    {
        $claim = $signer->claim;

        $pdf = Pdf::loadView('legal.poa', [
            'claim'        => $claim,
            'signer'       => $signer,
            'jurisdiction' => self::jurisdiction($claim),
            'signature'    => $this->signatureData($signer->signature_path),
        ]);

        $slug = Str::slug($signer->signs_for ?: $signer->name) ?: $signer->id;
        $path = "claims/{$claim->user_id}/legal/poa-{$claim->number}-{$slug}.pdf";
        Storage::disk('local')->put($path, $pdf->output());

        $signer->forceFill(['poa_path' => $path])->save();
    }

    public function generateAssignment(Claim $claim): void
    {
        $lead = $claim->signers->first();

        $pdf = Pdf::loadView('legal.assignment', [
            'claim'        => $claim,
            'passengers'   => $claim->passengerNames(),
            'lead'         => $lead,
            'jurisdiction' => self::jurisdiction($claim),
            'signature'    => $this->signatureData($lead?->signature_path),
        ]);

        $path = "claims/{$claim->user_id}/legal/assignment-{$claim->number}.pdf";
        Storage::disk('local')->put($path, $pdf->output());

        $claim->forceFill(['assignment_path' => $path])->save();
    }

    /** Re-render a signer's documents with their signature embedded. */
    public function regenerateSigned(ClaimSigner $signer): void
    {
        $this->generatePoa($signer);

        // The lead signer also executes the booking's Assignment of Claims.
        if ($signer->claim->signers->first()?->is($signer)) {
            $this->generateAssignment($signer->claim);
        }
    }

    private function signatureData(?string $path): ?string
    {
        if (!$path || !Storage::disk('local')->exists($path)) {
            return null;
        }

        return 'data:image/png;base64,' . base64_encode(Storage::disk('local')->get($path));
    }
}
