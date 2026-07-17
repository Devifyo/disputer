<?php

namespace App\Services\Claims;

use App\Models\Claim;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Drafts the formal compensation claim email Unjamm sends to the airline.
 * AI reads the full claim record AND the customer's documents (ticket,
 * receipts, correspondence) and writes a jurisdiction-specific demand
 * letter (CA / US / EU / UK); a deterministic jurisdiction-aware template
 * stands in whenever AI is unavailable. Always admin-reviewed before sending.
 */
class ClaimLetterService
{
    private const MAX_DOCUMENTS    = 6;
    private const MAX_DOCUMENT_MB  = 8;
    private const INLINE_MIMES     = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif'];

    /**
     * Legal framing per jurisdiction - drives both the AI instructions and
     * the fallback template.
     */
    private const JURISDICTIONS = [
        'CA' => [
            'law'      => "Canada's Air Passenger Protection Regulations (SOR/2019-150)",
            'deadline' => '30 days, as required by section 19(4) of the APPR',
            'body'     => 'the Canadian Transportation Agency',
            'notes'    => 'Compensation tiers under s.19(1) depend on arrival delay (CAD 400/700/1,000 for large carriers); s.19(2) flat CAD 400 plus a ticket refund (s.17) when the passenger chose a refund over rebooking; standards of treatment under ss. 8-10.',
        ],
        'US' => [
            'law'      => 'US Department of Transportation rules (14 CFR Parts 250 and 260)',
            'deadline' => '30 days',
            'body'     => "the US Department of Transportation's Office of Aviation Consumer Protection",
            'notes'    => 'Part 260 requires prompt refunds for cancelled or significantly changed flights (7 business days for card payments, 20 days for cash); Part 250 mandates denied boarding compensation up to 400% of the one-way fare.',
        ],
        'UK' => [
            'law'      => 'UK261 (Regulation (EC) No 261/2004 as retained in UK law)',
            'deadline' => '14 days',
            'body'     => 'the UK Civil Aviation Authority or the approved ADR scheme the carrier subscribes to',
            'notes'    => 'Fixed compensation under Article 7 (GBP 220/350/520 by distance); re-routing or refund under Article 8; right to care under Article 9.',
        ],
        'EU' => [
            'law'      => 'Regulation (EC) No 261/2004',
            'deadline' => '14 days',
            'body'     => 'the competent National Enforcement Body and, if necessary, the courts',
            'notes'    => 'Fixed compensation under Article 7 (EUR 250/400/600 by distance, Sturgeon for 3h+ delays); re-routing or refund under Article 8; right to care under Article 9; downgrade reimbursement under Article 10.',
        ],
    ];

    /** @return array{subject: string, body: string, generated_by: string} */
    public function generate(Claim $claim): array
    {
        $claim->loadMissing('itinerary.passengers', 'user', 'signers');

        try {
            if (config('services.gemini.api_key')) {
                return $this->generateWithAi($claim) + ['generated_by' => 'ai'];
            }
        } catch (Throwable $e) {
            Log::warning('Claim letter AI generation failed - using template', [
                'claim' => $claim->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $this->template($claim) + ['generated_by' => 'template'];
    }

    // ── AI draft: full claim record + every readable document ─

    private function generateWithAi(Claim $claim): array
    {
        $model    = config('eligibility.ai_model', 'gemini-2.5-flash');
        $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . config('services.gemini.api_key');

        $parts = array_merge([['text' => $this->prompt($claim)]], $this->documentParts($claim));

        $response = Http::timeout(60)->retry(2, 1000, throw: false)->post($endpoint, [
            'contents'         => [['parts' => $parts]],
            'generationConfig' => ['temperature' => 0.2, 'responseMimeType' => 'application/json'],
        ]);

        if (!$response->successful()) {
            throw new RuntimeException('Gemini request failed: HTTP ' . $response->status());
        }

        $raw     = $response->json('candidates.0.content.parts.0.text') ?? '';
        $decoded = json_decode(trim(str_replace(['```json', '```'], '', $raw)), true);

        if (!is_array($decoded)
            || !is_string($decoded['subject'] ?? null) || trim($decoded['subject']) === ''
            || !is_string($decoded['body'] ?? null) || trim($decoded['body']) === ''
        ) {
            throw new RuntimeException('Gemini returned malformed letter JSON.');
        }

        return ['subject' => trim($decoded['subject']), 'body' => trim($decoded['body'])];
    }

    private function prompt(Claim $claim): string
    {
        $jurisdiction = ClaimLegalDocumentService::jurisdiction($claim);
        $regime       = self::JURISDICTIONS[$jurisdiction] ?? self::JURISDICTIONS['EU'];
        $facts        = json_encode($this->facts($claim), JSON_PRETTY_PRINT);

        return <<<PROMPT
You are a legal correspondence writer for Unjamm, an air passenger rights company. Draft the formal compensation claim email to the airline for the claim below.

CLAIM RECORD (verified where stated - never invent facts):
{$facts}

JURISDICTION: {$jurisdiction}
- Governing law: {$regime['law']}
- Payment deadline to demand: {$regime['deadline']}
- Escalation body if unanswered or rejected: {$regime['body']}
- Regime specifics: {$regime['notes']}

ATTACHED PASSENGER DOCUMENTS: the customer's ticket/booking and supporting evidence follow this message. Read each one and use what strengthens the claim - booking references, fare paid, seat/class, boarding passes, airline emails admitting the disruption, receipts for meals/hotel/transport (claim those as expense reimbursement where the regime provides a right to care). Quote concrete details from the documents where they support the claim. If a document contradicts the claim record, rely on the verified claim record and do not mention the discrepancy.

Requirements:
- Address the airline's customer relations / claims department formally.
- State that Unjamm represents the passenger(s) under a signed Power of Attorney and Assignment of Claims (attached).
- Set out the flight, the disruption (with verified times/delay), and the legal basis citing the specific article/section of {$regime['law']}, and the amount claimed per passenger plus the total.
- Where a ticket refund or expense reimbursement is also owed under the regime, claim it explicitly with amounts where the documents evidence them.
- Demand payment within {$regime['deadline']} and state the claim will be escalated to {$regime['body']} if unanswered or rejected without valid grounds.
- Professional, firm, concise - around 250-400 words. Plain text paragraphs, no markdown. Sign off as "Unjamm Claims Team" with the claim reference.
- No postal address blocks or placeholders like [Airline Address].

Respond with ONLY this JSON, no markdown:
{"subject":"...","body":"..."}
PROMPT;
    }

    /** The customer's documents as Gemini inline parts (ticket, evidence, admin extras). */
    private function documentParts(Claim $claim): array
    {
        $files = [];

        if ($claim->itinerary?->file_path) {
            $files[] = ['name' => 'Ticket/booking - ' . $claim->itinerary->original_filename, 'path' => $claim->itinerary->file_path, 'mime' => $claim->itinerary->mime_type];
        }

        foreach ($claim->documents ?? [] as $doc) {
            $files[] = ['name' => 'Customer evidence - ' . ($doc['name'] ?? 'document'), 'path' => $doc['path'] ?? null, 'mime' => null];
        }

        foreach ($claim->airline_letter['extra'] ?? [] as $doc) {
            $files[] = ['name' => 'Additional document - ' . ($doc['name'] ?? 'document'), 'path' => $doc['path'] ?? null, 'mime' => null];
        }

        $parts = [];

        foreach (array_slice($files, 0, self::MAX_DOCUMENTS) as $file) {
            if (!$file['path'] || !Storage::disk('local')->exists($file['path'])) {
                continue;
            }

            $mime = $file['mime'] ?: (Storage::disk('local')->mimeType($file['path']) ?: '');
            $size = Storage::disk('local')->size($file['path']);

            if (!in_array($mime, self::INLINE_MIMES, true) || $size > self::MAX_DOCUMENT_MB * 1024 * 1024) {
                continue;
            }

            $parts[] = ['text' => 'Document: ' . $file['name']];
            $parts[] = ['inline_data' => [
                'mime_type' => $mime,
                'data'      => base64_encode(Storage::disk('local')->get($file['path'])),
            ]];
        }

        return $parts;
    }

    // ── Deterministic fallback, jurisdiction-aware ──────────

    private function template(Claim $claim): array
    {
        $jurisdiction = ClaimLegalDocumentService::jurisdiction($claim);
        $regime       = self::JURISDICTIONS[$jurisdiction] ?? self::JURISDICTIONS['EU'];
        $f            = $this->facts($claim);
        $total        = $f['total_claimed'] ?? 'the statutory amount';

        $body = "Dear {$f['airline']} Customer Relations,\n\n"
            . "We write on behalf of {$f['passengers']}, who we represent under a signed Power of Attorney and Assignment of Claims (attached), regarding flight {$f['flight']} from {$f['route']} on {$f['flight_date']}"
            . ($f['booking_reference'] ? " (booking reference {$f['booking_reference']})" : '') . ".\n\n"
            . "{$f['disruption_sentence']}\n\n"
            . "Under {$f['legal_basis']} of {$regime['law']}, the passengers are entitled to compensation of {$f['per_passenger']} each - {$total} in total for {$f['passenger_count']} passenger(s)."
            . ($f['entitlements_note'] ? " Basis: {$f['entitlements_note']}." : '') . "\n\n"
            . "We request payment of {$total} within {$regime['deadline']}. Should the claim remain unanswered or be rejected without valid grounds, we will escalate it to {$regime['body']} without further notice.\n\n"
            . "Supporting documents are attached: the signed authorisations, the booking confirmation and the passengers' evidence.\n\n"
            . "Yours faithfully,\nUnjamm Claims Team\nClaim reference: {$f['reference']}";

        return [
            'subject' => "Compensation claim under {$f['legal_basis']} - flight {$f['flight']} on {$f['flight_date']} - ref {$f['reference']}",
            'body'    => $body,
        ];
    }

    private function facts(Claim $claim): array
    {
        $passengers = $claim->passengerNames();
        $count      = max(1, count($passengers));
        $amount     = $claim->compensation_amount ? (float) $claim->compensation_amount : null;
        $snapshot   = $claim->flight_snapshot ?? [];

        $disruption = match (true) {
            (bool) $claim->flight_cancelled          => 'The flight was cancelled' . ($claim->flight_verified_at ? ', as confirmed by flight-tracking records' : '') . '.',
            (bool) $claim->flight_diverted           => 'The flight was diverted away from its booked destination.',
            $claim->flight_arrival_delay_minutes > 0 => sprintf('The flight arrived %dh %02dm late at its destination%s.', intdiv($claim->flight_arrival_delay_minutes, 60), $claim->flight_arrival_delay_minutes % 60, $claim->flight_verified_at ? ', as confirmed by flight-tracking records' : ''),
            default                                  => 'The flight was disrupted as described by the passengers: ' . ($claim->disruption_label ?: 'see attached evidence') . '.',
        };

        return [
            'reference'            => $claim->reference,
            'claim_number'         => $claim->number,
            'airline'              => $claim->airline ?: 'the operating carrier',
            'flight'               => trim(($claim->airline ?? '') . ' ' . ($claim->flight_number ?? '')),
            'route'                => "{$claim->departure_airport} to {$claim->arrival_airport}",
            'flight_date'          => $claim->flight_date?->format('d F Y'),
            'passengers'           => implode(', ', $passengers),
            'passenger_count'      => $count,
            'minors'               => $claim->itinerary?->passengers
                ?->filter(fn ($p) => in_array(strtoupper((string) $p->type), ['CHD', 'INF'], true))
                ->pluck('full_name')->values()->all() ?: [],
            'disruption_sentence'  => $disruption,
            'disruption_declared'  => $claim->disruption_label,
            'disruption_note'      => $claim->disruption_note,
            'facts_verified'       => (bool) $claim->flight_verified_at,
            'tracking'             => $snapshot ? [
                'scheduled_departure' => $snapshot['scheduled_departure'] ?? null,
                'actual_departure'    => $snapshot['actual_departure'] ?? null,
                'scheduled_arrival'   => $snapshot['scheduled_arrival'] ?? null,
                'actual_arrival'      => $snapshot['actual_arrival'] ?? null,
                'arrival_delay_min'   => $snapshot['arrival_delay_minutes'] ?? null,
                'cancelled'           => $snapshot['cancelled'] ?? false,
            ] : null,
            'legal_basis'          => trim(($claim->eligibility_regulation ?? '') . ' ' . ($claim->eligibility_article ?? '')),
            'verdict_reason'       => $claim->eligibility_reason,
            'per_passenger'        => $amount ? trim($claim->compensation_currency . ' ' . number_format($amount, 2)) : null,
            'total_claimed'        => $amount ? trim($claim->compensation_currency . ' ' . number_format($amount * $count, 2)) : null,
            'entitlements_note'    => $claim->compensation_basis,
            'entitlements'         => collect($claim->compensation_explanation['entitlements'] ?? [])
                ->map(fn ($e) => ['right' => $e['label'] ?? '', 'state' => $e['state'] ?? '', 'detail' => $e['detail'] ?? ''])->all(),
            'ticket_price'         => $claim->ticket_price ? trim(($claim->ticket_currency ?: '') . ' ' . number_format((float) $claim->ticket_price, 2)) : null,
            'did_not_travel'       => (bool) $claim->did_not_travel,
            'booking_reference'    => $claim->booking_reference,
            'authorisations'       => $claim->signers->map(fn ($s) => $s->coversLabel() . ' - ' . $s->status)->values()->all(),
            'consent_recorded_at'  => $claim->confirmed_at?->toIso8601String(),
        ];
    }
}
