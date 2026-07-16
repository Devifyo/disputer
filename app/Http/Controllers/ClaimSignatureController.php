<?php

namespace App\Http\Controllers;

use App\Models\ClaimSigner;
use App\Services\Claims\ClaimSignatureService;
use Illuminate\Http\Request;

/**
 * Tokenised public signing page for passengers on the booking who don't
 * have an Unjamm account - reached from their emailed signing request.
 */
class ClaimSignatureController extends Controller
{
    public function show(string $token, ClaimSignatureService $signatures)
    {
        $signer = ClaimSigner::where('sign_token', $token)->with('claim')->firstOrFail();

        // A just-finished embedded signature may beat the webhook here -
        // ask the provider directly so the signer never sees the pad again.
        $signer = $signatures->reconcile($signer)->load('claim');

        return view('claims.sign', [
            'signer'  => $signer,
            'claim'   => $signer->claim,
            'mode'    => $signatures->provider()->name(),
            'signUrl' => $signer->status === ClaimSigner::STATUS_PENDING
                ? $signatures->provider()->embeddedSignUrl($signer)
                : null,
            'clientId' => config('services.dropbox_sign.client_id'),
        ]);
    }

    public function store(Request $request, string $token, ClaimSignatureService $signatures)
    {
        $signer = ClaimSigner::where('sign_token', $token)->firstOrFail();

        abort_unless($signer->status === ClaimSigner::STATUS_PENDING, 422, 'This authorisation is already signed.');

        $data = $request->validate([
            'signature' => ['required', 'string', 'starts_with:data:image/png;base64,', 'max:700000'],
        ]);

        $signatures->recordNativeSignature($signer, $data['signature']);

        return response()->json(['success' => true]);
    }
}
