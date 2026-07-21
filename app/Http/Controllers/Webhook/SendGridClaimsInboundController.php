<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Services\Claims\ClaimCorrespondenceService;
use App\Services\InboundItineraryImporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Dedicated SendGrid Inbound Parse endpoint for the flight-claim forwarding
 * fallback (its own Inbound Parse host, e.g. claims.unjamm.com — kept separate
 * from the case inbound handler so the two streams stay fully independent).
 *
 * One mailbox, two streams: emails that carry a claim's reply-to token or
 * reference are airline correspondence and attach to that claim; everything
 * else is a customer ticket submission and flows to the itinerary importer.
 */
class SendGridClaimsInboundController extends Controller
{
    public function handle(Request $request, InboundItineraryImporter $importer, ClaimCorrespondenceService $correspondence)
    {
        try {
            $rawFrom = (string) $request->input('from', '');

            // Airline reply? The reply-to token (or the claim reference in
            // the subject) routes it to its claim - never to a new claim.
            // Checked before any recipient filter: replies land on the
            // plus-addressed token, not the public claims address.
            $recipients = $request->input('to', '') . ' ' . $request->input('envelope', '');
            [$claim, $matchedBy] = $correspondence->matchInbound($recipients, $request->input('subject'));

            // Optional recipient filter — only when a shared-domain host is used.
            // Unset (dedicated claims.* host) means process everything.
            $claimsAddress = strtolower((string) config('services.inbound.claims_address'));
            if (!$claim && $claimsAddress !== '') {
                $to = strtolower((string) $request->input('to'));
                if (!str_contains($to, $claimsAddress)) {
                    Log::info('Claims inbound: ignored non-claims recipient.', ['to' => $request->input('to')]);
                    return response()->json(['status' => 'ignored'], 200);
                }
            }

            if ($claim) {
                $record = $correspondence->recordInbound(
                    $claim,
                    $matchedBy,
                    $this->extractEmailAddress($rawFrom),
                    $this->extractName($rawFrom),
                    $request->input('subject'),
                    $request->input('text'),
                    $request->input('html'),
                    $this->readAttachments($request)
                );

                return response()->json(['status' => 'success', 'correspondence' => $record->id], 200);
            }

            $result = $importer->import(
                $this->extractEmailAddress($rawFrom),
                $this->extractName($rawFrom),
                $request->input('subject'),
                $request->input('text'),
                $request->input('html'),
                $this->readAttachments($request)
            );

            return response()->json(['status' => 'success', 'flight_claim' => $result], 200);
        } catch (\Throwable $e) {
            Log::error('SendGrid claims inbound error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            // Always 200 so SendGrid doesn't retry.
            return response()->json(['status' => 'error_logged'], 200);
        }
    }

    /**
     * SendGrid Inbound Parse posts attachments sequentially (attachment1, ...).
     *
     * @return array<int, array{name:string, mime:string, bytes:string}>
     */
    private function readAttachments(Request $request): array
    {
        $attachments = [];
        $count = (int) $request->input('attachments', 0);

        for ($i = 1; $i <= $count; $i++) {
            if ($request->hasFile("attachment{$i}")) {
                $file = $request->file("attachment{$i}");
                $attachments[] = [
                    'name'  => $file->getClientOriginalName(),
                    'mime'  => $file->getClientMimeType(),
                    'bytes' => @file_get_contents($file->getRealPath()) ?: '',
                ];
            }
        }

        return $attachments;
    }

    private function extractEmailAddress(string $rawFrom): string
    {
        if (preg_match('/<(.+?)>/', $rawFrom, $m)) {
            return trim($m[1]);
        }
        return trim($rawFrom);
    }

    private function extractName(string $rawFrom): ?string
    {
        if (preg_match('/^\s*"?([^"<]+?)"?\s*</', $rawFrom, $m)) {
            return trim($m[1]);
        }
        return null;
    }
}
