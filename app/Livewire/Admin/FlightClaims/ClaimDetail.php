<?php

namespace App\Livewire\Admin\FlightClaims;

use App\Models\Airline;
use App\Models\AirlineContact;
use App\Models\Claim;
use App\Models\ClaimDraft;
use App\Models\Setting;
use App\Services\Claims\ClaimLetterService;
use App\Services\Claims\ClaimWorkflowService;
use App\Services\Eligibility\ClaimEligibilityService;
use App\Services\Eligibility\EligibilityEngine;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Admin claim detail page: the full claim picture plus the outbound claim
 * email to the airline - AI-drafted, admin-reviewed, with a selectable
 * attachment set (signed authorisations, customer evidence, and any extra
 * documents the admin uploads). Sending is wired up in a later step.
 */
class ClaimDetail extends Component
{
    use WithFileUploads;

    public Claim $claim;

    public string $to = '';
    public string $subject = '';
    public string $body = '';

    /** Attachment keys selected to go out with the email. */
    public array $attached = [];

    /** Reason shown to the customer when the team rejects the claim. */
    public string $rejection_reason = '';

    /** The draft version currently loaded in the composer. */
    public ?int $loadedDraftId = null;
    public string $draftType = ClaimDraft::TYPE_CLAIM;

    /** Airline's reply, pasted by the admin as context for follow-ups. */
    public string $airline_response = '';

    /** Workflow action inputs. */
    public string $wf_notes = '';
    public string $filing_recipient = '';
    public string $filing_reference = '';

    /** Pending admin uploads (extra documents for the airline). */
    public array $uploads = [];

    public function mount(Claim $claim): void
    {
        $this->claim = $claim->load(['user', 'signers', 'itinerary.passengers', 'events']);

        $letter        = $claim->airline_letter ?? [];
        $this->to      = $letter['to'] ?? '';
        $this->subject = $letter['subject'] ?? '';
        $this->body    = $letter['body'] ?? '';

        // Route to the right airline inbox: the stage's configured contact
        // purpose against the carrier's directory entry.
        if ($this->to === '' && ($contact = $this->directoryContact())) {
            $this->to = $contact->email;
        }
        if ($this->filing_recipient === '' && ($contact = $this->directoryContact())) {
            $this->filing_recipient = $contact->email;
        }

        // Default selection: everything legal + the ticket; the admin trims.
        $this->attached = $letter['attachments']
            ?? collect($this->attachments())->pluck('key')->all();
    }

    /** The claim's airline in the directory. */
    public function airlineRecord(): ?Airline
    {
        return Airline::match($this->claim->airline, $this->claim->flight_number);
    }

    /** The directory contact for the current stage's configured purpose. */
    private function directoryContact(): ?AirlineContact
    {
        $purpose = $this->claim->workflowStage()?->airline_contact_purpose ?: 'claims';

        return $this->airlineRecord()?->contactFor($purpose);
    }

    public function updatedAttached(): void
    {
        $this->persist();
    }

    public function updatedUploads(): void
    {
        $this->validate([
            'uploads'   => 'array|max:6',
            'uploads.*' => 'file|mimes:pdf,jpg,jpeg,png,webp|max:12288',
        ], [], ['uploads.*' => 'document']);

        $letter = $this->claim->airline_letter ?? [];
        $extra  = $letter['extra'] ?? [];

        foreach ($this->uploads as $file) {
            $extra[] = [
                'name' => $file->getClientOriginalName(),
                'path' => $file->store("claims/{$this->claim->user_id}/admin", 'local'),
            ];
            $this->attached[] = 'extra-' . (count($extra) - 1);
        }

        $this->uploads = [];
        $this->persist(['extra' => $extra]);
        $this->dispatch('toast', ['type' => 'success', 'message' => 'Document(s) added to the email.']);
    }

    public function removeExtra(int $index): void
    {
        $letter = $this->claim->airline_letter ?? [];
        $extra  = $letter['extra'] ?? [];

        if (isset($extra[$index]['path'])) {
            Storage::disk('local')->delete($extra[$index]['path']);
        }

        unset($extra[$index]);
        $extra = array_values($extra);

        // Re-key the extras: selection keys shift after removal.
        $this->attached = collect($this->attached)
            ->reject(fn ($key) => str_starts_with($key, 'extra-'))
            ->merge(collect($extra)->keys()->map(fn ($i) => "extra-{$i}"))
            ->values()
            ->all();

        $this->persist(['extra' => $extra]);
    }

    /**
     * Move the claim to another lifecycle stage - always through the
     * workflow engine, never directly. Filing captures the submission record.
     */
    public function moveTo(ClaimWorkflowService $workflow, string $stageKey): void
    {
        try {
            if ($stageKey === 'filed') {
                $this->validate([
                    'filing_recipient' => 'required|email|max:190',
                    'filing_reference' => 'nullable|string|max:190',
                    'wf_notes'         => 'nullable|string|max:2000',
                ], [], ['filing_recipient' => 'recipient email']);

                $workflow->transition($this->claim, 'filed', 'admin', auth()->id(), $this->wf_notes ?: 'Claim filed with the airline.', [
                    'filed_at' => now(),
                    'filing'   => [
                        'recipient'       => $this->filing_recipient,
                        'email_reference' => $this->filing_reference ?: null,
                        'subject'         => $this->subject ?: null,
                        'attachments'     => array_values(array_unique($this->attached)),
                        'notes'           => $this->wf_notes ?: null,
                    ],
                ]);
            } elseif ($stageKey === 'responded') {
                $this->validate(
                    ['wf_notes' => 'required|string|min:5|max:5000'],
                    ['wf_notes.required' => 'Paste or summarise the airline\'s response - it becomes part of the claim record.'],
                    ['wf_notes' => 'airline response']
                );

                $workflow->transition($this->claim, 'responded', 'admin', auth()->id(), $this->wf_notes);
                $workflow->audit($this->claim, 'Airline response received', 'airline', auth()->id(), $this->wf_notes);
            } else {
                $this->validate(['wf_notes' => 'nullable|string|max:2000']);
                $workflow->transition($this->claim, $stageKey, 'admin', auth()->id(), $this->wf_notes ?: null);
            }
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return;
        }

        $stageName = \App\Models\ClaimLifecycleStage::byKey($stageKey)?->name ?? $stageKey;
        $this->reset('wf_notes', 'filing_recipient', 'filing_reference');
        $this->claim->refresh()->load(['user', 'signers', 'itinerary.passengers', 'events']);
        $this->dispatch('toast', ['type' => 'success', 'message' => "Claim moved to {$stageName}."]);
    }

    /** Team decision: the claim is eligible - prices it and tells the customer. */
    public function approve(ClaimEligibilityService $eligibility): void
    {
        abort_unless($this->claim->status === Claim::STATUS_PENDING_ELIGIBILITY, 422);

        $this->claim->forceFill([
            'status'                      => Claim::STATUS_ELIGIBLE,
            'eligibility_status'          => EligibilityEngine::STATUS_ELIGIBLE,
            'eligibility_reason'          => str_replace(' Our team is verifying the details before confirming your claim.', '', (string) $this->claim->eligibility_reason),
            'eligibility_details'         => ($this->claim->eligibility_details ?? []) + ['decided_by' => 'team', 'decided_by_admin' => auth()->id(), 'decided_at' => now()->toIso8601String()],
            'eligibility_decision_source' => 'admin',
        ])->save();

        $eligibility->priceCompensation($this->claim->refresh());

        $this->claim->events()->where('label', 'Our team is reviewing your eligibility')->where('status', 'pending')->update(['status' => 'done']);
        $this->claim->recordEvent(
            sprintf('Our team confirmed your eligibility under %s (%s)', $this->claim->eligibility_regulation, $this->claim->eligibility_article),
            'done', now(), 2
        );

        $eligibility->notifyEligible($this->claim->fresh(), [
            'amount'   => $this->claim->fresh()->compensation_amount ? (float) $this->claim->fresh()->compensation_amount : null,
            'currency' => $this->claim->fresh()->compensation_currency,
        ]);

        $this->claim->refresh()->load(['user', 'signers', 'itinerary.passengers', 'events']);
        $this->dispatch('toast', ['type' => 'success', 'message' => 'Claim approved - the customer has been notified.']);
    }

    /** Team decision: not eligible - the reason is shown and emailed to the customer. */
    public function reject(): void
    {
        abort_unless($this->claim->status === Claim::STATUS_PENDING_ELIGIBILITY, 422);

        $this->validate(
            ['rejection_reason' => 'required|string|min:10|max:500'],
            [],
            ['rejection_reason' => 'reason shown to the customer']
        );

        $this->claim->forceFill([
            'status'                      => Claim::STATUS_REJECTED,
            'eligibility_status'          => EligibilityEngine::STATUS_REJECTED,
            'eligibility_reason'          => $this->rejection_reason,
            'eligibility_details'         => ($this->claim->eligibility_details ?? []) + ['decided_by' => 'team', 'decided_by_admin' => auth()->id(), 'decided_at' => now()->toIso8601String()],
            'eligibility_decision_source' => 'admin',
            'compensation_amount'         => null,
            'compensation_currency'       => null,
            'compensation_basis'          => null,
            'compensation_explanation'    => null,
        ])->save();

        $this->claim->events()->where('label', 'Our team is reviewing your eligibility')->where('status', 'pending')->update(['status' => 'done']);
        $this->claim->recordEvent('Not eligible: ' . $this->rejection_reason, 'failed', now(), 2);

        $email = $this->claim->contact_email ?: $this->claim->user?->email;
        if ($email) {
            send_dynamic_email($email, 'claim-eligibility-rejected', [
                '[NAME]'      => $this->claim->passenger_name ?: 'traveller',
                '[CLAIM]'     => '#' . $this->claim->number,
                '[FLIGHT]'    => trim(($this->claim->airline ?? '') . ' ' . ($this->claim->flight_number ?? '')),
                '[ROUTE]'     => "{$this->claim->departure_airport} - {$this->claim->arrival_airport}",
                '[REASON]'    => $this->rejection_reason,
                '[CLAIM_URL]' => url('/flight-disputes/claims/' . encrypt_id($this->claim->id)),
            ]);
        }

        $this->rejection_reason = '';
        $this->claim->refresh()->load(['user', 'signers', 'itinerary.passengers', 'events']);
        $this->dispatch('toast', ['type' => 'success', 'message' => 'Claim rejected - the customer has been notified with your reason.']);
    }

    public function generate(ClaimLetterService $letters): void
    {
        $this->createDraft($letters, ClaimDraft::TYPE_CLAIM);
    }

    public function generateFollowUp(ClaimLetterService $letters, string $reason): void
    {
        abort_unless(array_key_exists($reason, ClaimDraft::FOLLOW_UP_REASONS), 422);

        if (!$this->requireInitialClaimDraft()) {
            return;
        }

        $this->createDraft($letters, ClaimDraft::TYPE_FOLLOW_UP, [
            'reason'           => $reason,
            'airline_response' => trim($this->airline_response),
        ]);
    }

    public function generateRegulator(ClaimLetterService $letters): void
    {
        if (!$this->requireInitialClaimDraft()) {
            return;
        }

        $this->createDraft($letters, ClaimDraft::TYPE_REGULATOR, [
            'airline_response' => trim($this->airline_response),
        ]);
    }

    /** Follow-ups and complaints reference the initial demand - it must exist first. */
    private function requireInitialClaimDraft(): bool
    {
        if ($this->claim->drafts()->where('type', ClaimDraft::TYPE_CLAIM)->exists()) {
            return true;
        }

        $this->dispatch('toast', ['type' => 'error', 'message' => 'Draft the initial airline claim first - follow-ups and complaints reference it.']);

        return false;
    }

    /**
     * Generate a new immutable draft version and load it into the composer.
     * Previous versions stay untouched for auditing.
     */
    private function createDraft(ClaimLetterService $letters, string $type, array $context = []): void
    {
        $context['history'] = $this->correspondenceHistory();

        // Follow-ups and complaints reference the real date of the original
        // demand - the approved claim letter, or the first version drafted.
        if ($type !== ClaimDraft::TYPE_CLAIM) {
            $claimDrafts = $this->claim->drafts()->where('type', ClaimDraft::TYPE_CLAIM)->reorder('version')->get();
            $original    = $claimDrafts->firstWhere('approved_at', '!=', null) ?? $claimDrafts->first();

            $context['original_demand_date'] = $original?->created_at->format('d F Y');
            $context['days_since_demand']    = $original ? (int) $original->created_at->diffInDays(now()) : null;
        }

        $result = $letters->generate($this->claim, $type, $context);

        $draft = $this->storeVersion($type, $result['subject'], $result['body'], $result['generated_by'], $context);

        $this->subject          = $draft->subject;
        $this->body             = $draft->body;
        $this->draftType        = $draft->type;
        $this->loadedDraftId    = $draft->id;
        $this->airline_response = '';

        $this->persist(['generated_at' => now()->toIso8601String(), 'generated_by' => $result['generated_by']]);

        $this->dispatch('toast', ['type' => 'success', 'message' => sprintf(
            '%s v%d drafted%s - review before sending.',
            $draft->typeLabel(), $draft->version,
            $result['generated_by'] === 'ai' ? ' by AI' : ' from the template (AI unavailable)'
        )]);
    }

    public function loadDraft(int $draftId): void
    {
        $draft = $this->claim->drafts()->findOrFail($draftId);

        $this->subject       = $draft->subject;
        $this->body          = $draft->body;
        $this->to            = $draft->to ?: $this->to;
        $this->draftType     = $draft->type;
        $this->loadedDraftId = $draft->id;
    }

    /** Mark one version as the approved final for its type. */
    public function approveDraft(int $draftId): void
    {
        $draft = $this->claim->drafts()->findOrFail($draftId);

        $this->claim->drafts()->where('type', $draft->type)->update(['approved_at' => null, 'approved_by' => null]);
        $draft->forceFill(['approved_at' => now(), 'approved_by' => auth()->id()])->save();

        $this->claim->refresh()->load(['user', 'signers', 'itinerary.passengers', 'events']);
        $this->dispatch('toast', ['type' => 'success', 'message' => "{$draft->typeLabel()} v{$draft->version} approved as the final version."]);
    }

    public function saveDraft(): void
    {
        $this->validate([
            'to'      => 'nullable|email|max:190',
            'subject' => 'required|string|max:190',
            'body'    => 'required|string|max:10000',
        ], [], ['to' => 'airline email']);

        // Admin edits become their own auditable version.
        $loaded = $this->loadedDraftId ? $this->claim->drafts()->find($this->loadedDraftId) : null;
        if (!$loaded || $loaded->subject !== $this->subject || $loaded->body !== $this->body) {
            $draft = $this->storeVersion($this->draftType, $this->subject, $this->body, 'admin', $loaded->context ?? []);
            $this->loadedDraftId = $draft->id;
        }

        $this->persist();
        $this->dispatch('toast', ['type' => 'success', 'message' => 'Draft saved.']);
    }

    private function storeVersion(string $type, string $subject, string $body, string $generatedBy, array $context = []): ClaimDraft
    {
        unset($context['history']);

        return $this->claim->drafts()->create([
            'type'         => $type,
            'version'      => ($this->claim->drafts()->where('type', $type)->max('version') ?? 0) + 1,
            'to'           => $this->to,
            'subject'      => $subject,
            'body'         => $body,
            'context'      => $context ?: null,
            'generated_by' => $generatedBy,
            'created_by'   => auth()->id(),
        ]);
    }

    /** Prior correspondence fed to follow-up / regulator drafting. */
    private function correspondenceHistory(): array
    {
        return $this->claim->drafts()
            ->get()
            ->groupBy('type')
            ->map(fn ($group) => $group->firstWhere('approved_at', '!=', null) ?? $group->first())
            ->filter()
            ->sortByDesc('id')
            ->take(3)
            ->map(fn (ClaimDraft $d) => [
                'label'   => $d->typeLabel() . ' v' . $d->version . ($d->approved_at ? ' (approved)' : ''),
                'date'    => $d->created_at->format('d M Y'),
                'subject' => $d->subject,
                'body'    => $d->body,
            ])
            ->values()
            ->all();
    }

    private function persist(array $extra = []): void
    {
        $this->claim->forceFill([
            'airline_letter' => array_merge($this->claim->airline_letter ?? [], [
                'to'          => $this->to,
                'subject'     => $this->subject,
                'body'        => $this->body,
                'attachments' => array_values(array_unique($this->attached)),
            ], $extra),
        ])->save();
        $this->claim->refresh()->load(['user', 'signers', 'itinerary.passengers', 'events']);
    }

    /** Everything that would go out with the email. */
    public function attachments(): array
    {
        $docs = [];

        foreach ($this->claim->signers as $signer) {
            if ($signer->poa_path) {
                $docs[] = [
                    'key'    => "poa-{$signer->id}",
                    'name'   => 'Power of Attorney - ' . ($signer->signs_for ?: $signer->name),
                    'signed' => $signer->status === 'signed',
                ];
            }
        }

        if ($this->claim->assignment_path) {
            $docs[] = ['key' => 'assignment', 'name' => 'Assignment of Claims', 'signed' => $this->claim->signers->first()?->status === 'signed'];
        }

        if ($this->claim->itinerary?->file_path) {
            $docs[] = ['key' => 'itinerary', 'name' => 'Booking / ticket - ' . $this->claim->itinerary->original_filename, 'signed' => null];
        }

        foreach ($this->claim->documents ?? [] as $index => $doc) {
            $docs[] = ['key' => "doc-{$index}", 'name' => $doc['name'] ?? ('Document ' . ($index + 1)), 'signed' => null];
        }

        foreach ($this->claim->airline_letter['extra'] ?? [] as $index => $doc) {
            $docs[] = ['key' => "extra-{$index}", 'name' => $doc['name'] ?? ('Extra document ' . ($index + 1)), 'signed' => null, 'extra' => $index];
        }

        return $docs;
    }

    public function render()
    {
        [$stageLabel, $stageCls] = Claims::stage($this->claim);

        $paxCount = max(1, count($this->claim->passengerNames()));
        $fee      = (float) Setting::get('claims.success_fee_percent', 25);
        $gross    = $this->claim->compensation_amount ? (float) $this->claim->compensation_amount * $paxCount : null;

        $workflow = app(ClaimWorkflowService::class);

        return view('livewire.admin.flight-claims.claim-detail', [
                'stageLabel'   => $stageLabel,
                'stageCls'     => $stageCls,
                'paxCount'     => $paxCount,
                'gross'        => $gross,
                'feePercent'   => $fee,
                'attachments'  => $this->attachments(),
                'drafts'       => $this->claim->drafts()->with('author')->get(),
                'wfStage'      => $this->claim->workflowStage(),
                'airlineRec'   => $this->airlineRecord(),
                'wfOptions'    => $this->claim->status === Claim::STATUS_ELIGIBLE ? $workflow->manualOptions($this->claim) : collect(),
                'pendingTimer' => $this->claim->workflowTimers()->where('status', 'pending')->orderBy('due_at')->first(),
                'auditLogs'    => $this->claim->auditLogs()->with('actor')->get(),
            ])
            ->extends('layouts.admin')
            ->section('content');
    }
}
