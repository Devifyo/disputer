<?php

namespace App\Services\Claims;

use App\Models\Claim;
use App\Models\ClaimDraft;
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

    /**
     * Draft one outbound communication. $type: airline_claim | follow_up |
     * regulator_complaint. $context may carry: reason (follow-up trigger),
     * airline_response (pasted by the admin), history (prior correspondence).
     *
     * @return array{subject: string, body: string, generated_by: string}
     */
    public function generate(Claim $claim, string $type = ClaimDraft::TYPE_CLAIM, array $context = []): array
    {
        $claim->loadMissing('itinerary.passengers', 'user', 'signers');

        try {
            if (config('services.gemini.api_key')) {
                return $this->generateWithAi($claim, $type, $context) + ['generated_by' => 'ai'];
            }
        } catch (Throwable $e) {
            Log::warning('Claim letter AI generation failed - using template', [
                'claim' => $claim->id,
                'type'  => $type,
                'error' => $e->getMessage(),
            ]);
        }

        return $this->template($claim, $type, $context) + ['generated_by' => 'template'];
    }

    // ── AI draft: full claim record + every readable document ─

    private function generateWithAi(Claim $claim, string $type, array $context): array
    {
        $model    = config('eligibility.ai_model', 'gemini-2.5-flash');
        $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . config('services.gemini.api_key');

        $parts = array_merge([['text' => $this->prompt($claim, $type, $context)]], $this->documentParts($claim));

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

    private function prompt(Claim $claim, string $type, array $context): string
    {
        $jurisdiction = ClaimLegalDocumentService::jurisdiction($claim);
        $regime       = self::JURISDICTIONS[$jurisdiction] ?? self::JURISDICTIONS['EU'];
        $facts        = json_encode($this->facts($claim), JSON_PRETTY_PRINT);
        $legalBasis   = trim(($claim->eligibility_regulation ?? '') . ' ' . ($claim->eligibility_article ?? ''));

        $shared = <<<SHARED
CLAIM RECORD (structured data from the Eligibility Engine - verified where stated, never invent facts):
{$facts}

JURISDICTION: {$jurisdiction}
- Governing law: {$regime['law']}
- Payment deadline to demand: {$regime['deadline']}
- Enforcement body: {$regime['body']}
- Regime specifics: {$regime['notes']}

LEGAL CITATION RULE (strict): cite the legal basis EXACTLY as provided by the Eligibility Engine: "{$legalBasis}". Never substitute, add or guess other articles or sections. You do not determine eligibility or amounts - the Eligibility Engine already did; your only job is well-written correspondence.

DATA FIDELITY RULE (strict): every date, amount, name, flight detail and reference in the letter must come from the CLAIM RECORD, the CORRESPONDENCE HISTORY or the attached documents - never invent or estimate any of them. Today's date is in the claim record ("today") - use it if you date the letter. If the date of an earlier letter is provided, reference it exactly; if it is not provided, write "our previous correspondence" without a date. Never write placeholders like [date] or [amount].

ATTACHED PASSENGER DOCUMENTS: the customer's ticket/booking and supporting evidence follow this message. Read each one and use what strengthens the case - booking references, fare paid, boarding passes, airline emails, receipts for meals/hotel/transport (claim those as expense reimbursement where the regime provides a right to care). If a document contradicts the claim record, rely on the verified claim record and do not mention the discrepancy.
SHARED;

        $history = $this->historyBlock($context);

        $task = match ($type) {
            ClaimDraft::TYPE_FOLLOW_UP => $this->followUpTask($regime, $context),
            ClaimDraft::TYPE_REGULATOR => $this->regulatorTask($regime, $context),
            default                    => <<<INITIALTASK
TASK: draft the formal INITIAL compensation claim email to the airline.
- Address the airline's customer relations / claims department formally.
- State that Unjamm represents the passenger(s) under a signed Power of Attorney and Assignment of Claims (attached).
- Set out the flight, the disruption (with verified times/delay), the legal basis, and the amount claimed per passenger plus the total.
- Where a ticket refund or expense reimbursement is also owed under the regime, claim it explicitly with amounts where the documents evidence them.
- Demand payment within {$regime['deadline']} and state the claim will be escalated to {$regime['body']} if unanswered or rejected without valid grounds.
INITIALTASK,
        };

        return <<<PROMPT
You are a legal correspondence writer for Unjamm, an air passenger rights company.

{$shared}
{$history}
{$task}

Style: professional, firm, concise - around 250-400 words. Plain text paragraphs, no markdown. Sign off as "Unjamm Claims Team" with the claim reference. No postal address blocks or placeholders like [Airline Address].

Respond with ONLY this JSON, no markdown:
{"subject":"...","body":"..."}
PROMPT;
    }

    private function followUpTask(array $regime, array $context): string
    {
        $reason = $context['reason'] ?? 'manual';

        $demandDate = $context['original_demand_date'] ?? null;
        $demandRef  = $demandDate ? "our claim of {$demandDate}" : 'our previous correspondence';

        $days    = $context['days_since_demand'] ?? null;
        $elapsed = $days !== null
            ? "\nELAPSED TIME (strict): the original demand was sent on {$demandDate}, exactly {$days} day(s) ago. When describing elapsed time or expired deadlines, use ONLY these figures - never state that more time has passed than this. If the required response period has not yet elapsed, do not claim it has; instead state the exact date it expires and demand a response by that date."
            : '';

        $angle = match ($reason) {
            'no_response'  => "The airline has NOT responded within the required period ({$regime['deadline']}). Write a firm final reminder: reference {$demandRef}, note the deadline has passed, restate the amounts, and give notice that without payment or a substantive response within 7 days the claim goes to {$regime['body']}.",
            'info_request' => 'The airline requested additional information (see AIRLINE RESPONSE). Provide the requested information strictly from the claim record and attached documents, then reiterate the demand and the original deadline.',
            'partial'      => 'The airline PARTIALLY approved the claim (see AIRLINE RESPONSE). Politely but firmly reject the partial offer as insufficient, restate the full statutory entitlement with the exact legal basis, and demand the balance.',
            'rejected'     => "The airline REJECTED the claim (see AIRLINE RESPONSE). Rebut the rejection point by point using only the verified facts in the claim record, restate the legal basis, and give notice of escalation to {$regime['body']} unless the position is reconsidered within {$regime['deadline']}.",
            default        => 'Write a courteous but firm follow-up on the pending claim: reference the earlier correspondence, restate the demand and amounts, and ask for a substantive response.',
        };

        $response = trim((string) ($context['airline_response'] ?? ''));
        $responseBlock = $response !== '' ? "\nAIRLINE RESPONSE (verbatim, provided by the administrator):\n\"{$response}\"\n" : '';

        return "TASK: draft a FOLLOW-UP email to the airline on this existing claim.\n{$angle}{$elapsed}\n{$responseBlock}";
    }

    private function regulatorTask(array $regime, array $context): string
    {
        $note = trim((string) ($context['airline_response'] ?? ''));
        $noteBlock = $note !== '' ? "\nCARRIER'S POSITION / LAST RESPONSE (provided by the administrator):\n\"{$note}\"\n" : '';

        if (($context['original_demand_date'] ?? null) !== null) {
            $days = $context['days_since_demand'] ?? 0;
            $noteBlock .= "\nELAPSED TIME (strict): the demand was submitted to the carrier on {$context['original_demand_date']}, exactly {$days} day(s) ago - describe elapsed time using ONLY these figures.\n";
        }

        return <<<REGULATORTASK
TASK: draft a formal COMPLAINT to {$regime['body']} about the carrier's handling of this claim.
- Address the regulator, not the airline.
- Identify the complainant(s) (represented by Unjamm under a signed Power of Attorney, attached) and the carrier.
- Summarise the flight, the verified disruption, the legal basis and the amounts owed.
- Describe the carrier's conduct: the claim submitted, deadlines given, and the failure to pay or respond substantively (use the correspondence history).
- Ask the regulator to investigate and enforce the passengers' entitlement under {$regime['law']}.
- Note that the claim correspondence and signed authorisations are attached.
{$noteBlock}
REGULATORTASK;
    }

    private function historyBlock(array $context): string
    {
        $history = $context['history'] ?? [];
        if (empty($history)) {
            return '';
        }

        $lines = collect($history)->map(fn ($h) => sprintf(
            "--- %s (%s)\nSubject: %s\n%s",
            $h['label'] ?? 'Previous correspondence',
            $h['date'] ?? '',
            $h['subject'] ?? '',
            mb_substr((string) ($h['body'] ?? ''), 0, 1500)
        ))->implode("\n\n");

        return "\nCORRESPONDENCE HISTORY (most recent first):\n{$lines}\n";
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

    // ── Deterministic fallbacks, jurisdiction-aware ─────────

    private function template(Claim $claim, string $type = ClaimDraft::TYPE_CLAIM, array $context = []): array
    {
        $jurisdiction = ClaimLegalDocumentService::jurisdiction($claim);
        $regime       = self::JURISDICTIONS[$jurisdiction] ?? self::JURISDICTIONS['EU'];
        $f            = $this->facts($claim);
        $total        = $f['total_claimed'] ?? 'the statutory amount';

        if ($type === ClaimDraft::TYPE_FOLLOW_UP) {
            $demandRef = ($context['original_demand_date'] ?? null)
                ? "our claim of {$context['original_demand_date']} (reference {$f['reference']})"
                : "our claim of reference {$f['reference']}";
            $body = "Dear {$f['airline']} Customer Relations,\n\n"
                . "We refer to {$demandRef} regarding flight {$f['flight']} on {$f['flight_date']} ({$f['route']}), in which we demanded {$total} under {$f['legal_basis']} of {$regime['law']}.\n\n"
                . (($context['reason'] ?? '') === 'no_response'
                    ? "The deadline we set has passed without a substantive response. Unless payment of {$total} is received within 7 days, we will escalate this claim to {$regime['body']} without further notice.\n\n"
                    : "We reiterate the demand for {$total} and request a substantive response within {$regime['deadline']}. Failing that, we will escalate this claim to {$regime['body']}.\n\n")
                . "Yours faithfully,\nUnjamm Claims Team\nClaim reference: {$f['reference']}";

            return [
                'subject' => "Follow-up: compensation claim {$f['reference']} - flight {$f['flight']} on {$f['flight_date']}",
                'body'    => $body,
            ];
        }

        if ($type === ClaimDraft::TYPE_REGULATOR) {
            $body = "Dear Sir or Madam,\n\n"
                . "We submit a complaint against {$f['airline']} on behalf of {$f['passengers']}, whom we represent under a signed Power of Attorney and Assignment of Claims (attached), regarding flight {$f['flight']} on {$f['flight_date']} ({$f['route']}).\n\n"
                . "{$f['disruption_sentence']}\n\n"
                . "Under {$f['legal_basis']} of {$regime['law']}, the passengers are entitled to {$total} in total. A formal claim was submitted to the carrier with the required supporting documents, and the carrier has failed to pay or to respond substantively within the deadline set.\n\n"
                . "We respectfully ask you to investigate the carrier's conduct and enforce the passengers' entitlement. The claim correspondence and signed authorisations are attached.\n\n"
                . "Yours faithfully,\nUnjamm Claims Team\nClaim reference: {$f['reference']}";

            return [
                'subject' => "Complaint against {$f['airline']} - flight {$f['flight']} on {$f['flight_date']} - ref {$f['reference']}",
                'body'    => $body,
            ];
        }

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
            'today'                => now()->format('d F Y'),
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
            'engine_confidence'    => $claim->eligibility_confidence,
            'decision_source'      => $claim->eligibility_decision_source,
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
