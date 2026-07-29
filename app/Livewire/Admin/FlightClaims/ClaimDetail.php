<?php

namespace App\Livewire\Admin\FlightClaims;

use App\Models\Airline;
use App\Models\AirlineContact;
use App\Models\AirlineEmailTemplate;
use App\Models\Claim;
use App\Models\ClaimCorrespondence;
use App\Models\ClaimDraft;
use App\Notifications\ClaimActionNeeded;
use App\Models\ClaimExpense;
use App\Models\Setting;
use App\Services\Claims\ClaimCorrespondenceService;
use App\Services\Claims\ClaimLetterService;
use App\Services\Claims\ClaimWorkflowService;
use App\Services\Claims\RegulatorDirectory;
use App\Services\Claims\TemplateRenderer;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
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

    // Compose: AI drafting is the default route; a saved template is the
    // manual alternative. cc/bcc and scheduling apply to both.
    public string $composeMode = 'ai';
    public ?int $templateId = null;
    public string $cc = '';
    public string $bcc = '';
    public string $scheduleAt = '';
    public bool $scheduling = false;
    public bool $showPreview = false;
    public bool $aiGenerated = false;

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

    /** Expense review state: per-expense reason / note / reimbursed amount. */
    public array $expenseReason = [];
    public array $expenseNote = [];
    public array $expensePaid = [];

    public function mount(Claim $claim): void
    {
        $this->claim = $claim->load(['user', 'signers', 'itinerary.passengers', 'events', 'expenses']);

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

        $this->prefillAirlineResponse();
    }

    /** The claim's airline in the directory. */
    /**
     * Auto-load the airline's latest reply into the follow-up context box.
     * The admin can still edit or clear it - and can paste one manually when
     * the airline answered by phone or post.
     */
    private function prefillAirlineResponse(): void
    {
        if (trim($this->airline_response) === '' && $reply = $this->latestAirlineReply()) {
            $this->airline_response = Str::limit(trim($reply->newBody()), 4000, '');
        }
    }

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

        $added = [];
        foreach ($this->uploads as $file) {
            $extra[] = [
                'name' => $file->getClientOriginalName(),
                'path' => $file->store("claims/{$this->claim->user_id}/admin", 'local'),
            ];
            $added[]          = $file->getClientOriginalName();
            $this->attached[] = 'extra-' . (count($extra) - 1);
        }

        $this->uploads = [];
        $this->persist(['extra' => $extra]);

        app(ClaimWorkflowService::class)->audit(
            $this->claim, count($added) . ' document(s) added to the claim file', 'admin', auth()->id(), implode(', ', $added)
        );

        $this->dispatch('toast', ['type' => 'success', 'message' => 'Document(s) added to the email.']);
    }

    public function removeExtra(int $index): void
    {
        $letter = $this->claim->airline_letter ?? [];
        $extra  = $letter['extra'] ?? [];

        $removed = $extra[$index]['name'] ?? "document {$index}";
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

        app(ClaimWorkflowService::class)->audit(
            $this->claim, 'Document removed from the claim file', 'admin', auth()->id(), $removed
        );
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
        $this->claim->refresh()->load(['user', 'signers', 'itinerary.passengers', 'events', 'expenses']);
        $this->dispatch('toast', ['type' => 'success', 'message' => "Claim moved to {$stageName}."]);
    }

    /**
     * Send the composed email to the airline. Goes out from the public
     * claims address with this claim's reply-to token, so the reply lands
     * back on this claim automatically. A first send while the claim is
     * ready to file IS the filing - the workflow moves with it.
     */
    /**
     * What the customer still has to do, if anything - drives the reminder
     * button and what the reminder actually says.
     *
     * @return array{0: ?string, 1: ?string} [action, human label]
     */
    public function customerAction(): array
    {
        if ($this->claim->status !== Claim::STATUS_ELIGIBLE) {
            return [null, null];
        }

        if (!$this->claim->confirmed_at) {
            return [ClaimActionNeeded::ACTION_CONFIRM, 'confirm the claim'];
        }

        if (!$this->claim->signaturesComplete() && $this->claim->signers->isNotEmpty()) {
            return [ClaimActionNeeded::ACTION_SIGN, 'sign the authorisation'];
        }

        return [null, null];
    }

    /**
     * Nudge the customer by email AND in-app. Rate-limited to once a day:
     * a reminder that arrives twice reads as a system fault, not urgency.
     */
    public function remindCustomer(ClaimWorkflowService $workflow): void
    {
        [$action, $label] = $this->customerAction();

        if (!$action) {
            $this->dispatch('toast', ['type' => 'error', 'message' => 'Nothing is waiting on the customer for this claim.']);

            return;
        }

        if ($this->claim->reminded_at && $this->claim->reminded_at->gt(now()->subDay())) {
            $this->dispatch('toast', [
                'type'    => 'error',
                'message' => 'Already reminded ' . $this->claim->reminded_at->diffForHumans() . ' - give them a day before nudging again.',
            ]);

            return;
        }

        $recipient = $this->claim->user;

        if (!$recipient) {
            $this->dispatch('toast', ['type' => 'error', 'message' => 'This claim has no customer account to notify.']);

            return;
        }

        $recipient->notify(new ClaimActionNeeded($this->claim, $action));

        $this->claim->forceFill(['reminded_at' => now()])->save();
        $workflow->audit($this->claim, 'Customer reminded to ' . $label, 'admin', auth()->id(), $recipient->email);
        $this->claim->recordEvent('We sent you a reminder to ' . $label, 'pending', now(), 2);

        $this->claim->refresh()->load(['user', 'signers', 'itinerary.passengers', 'events', 'expenses']);
        $this->dispatch('toast', ['type' => 'success', 'message' => "Reminder sent to {$recipient->email} - and it's in their notifications."]);
    }

    public function send(ClaimCorrespondenceService $correspondence, ClaimWorkflowService $workflow): void
    {
        abort_unless(auth()->user()->can('claim_emails.send'), 403);

        // Never write to an airline on a claim we are not yet authorised to act on.
        [$allowed, $reason] = $this->claim->canContactAirline();

        if (!$allowed) {
            $this->dispatch('toast', ['type' => 'error', 'message' => $reason]);

            return;
        }

        $this->validate([
            'to'         => 'required|email|max:190',
            'subject'    => 'required|string|max:190',
            'body'       => 'required|string|min:50',
            'cc'         => 'nullable|string|max:500',
            'bcc'        => 'nullable|string|max:500',
            'scheduleAt' => 'nullable|date|after:now',
        ], [
            'body.min'         => 'The email body looks empty - generate or write the claim first.',
            'scheduleAt.after' => 'Schedule a time in the future, or send now.',
        ], ['to' => 'recipient email', 'scheduleAt' => 'schedule time']);

        $this->persist();

        $options = [
            'cc'           => $this->cc,
            'bcc'          => $this->bcc,
            'template_id'  => $this->templateId,
            'ai_generated' => $this->aiGenerated,
        ];

        // Scheduled: the record appears immediately as "scheduled" and a
        // queued job delivers it - nothing else in this flow changes.
        if (trim($this->scheduleAt) !== '') {
            try {
                $when = \Illuminate\Support\Carbon::parse($this->scheduleAt);
                $correspondence->schedule($this->claim, $this->to, $this->subject, $this->body, $this->attached, auth()->id(), $when, $options);
            } catch (\Throwable $e) {
                report($e);
                $this->dispatch('toast', ['type' => 'error', 'message' => 'Could not schedule the email: ' . $e->getMessage()]);

                return;
            }

            $this->showPreview = false;
            $this->scheduleAt  = '';
            $this->scheduling  = false;
            $this->claim->refresh()->load(['user', 'signers', 'itinerary.passengers', 'events', 'expenses']);
            $this->dispatch('toast', ['type' => 'success', 'message' => "Scheduled - it will go to {$this->to} automatically."]);

            return;
        }

        try {
            $correspondence->send($this->claim, $this->to, $this->subject, $this->body, $this->attached, auth()->id(), $options);
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('toast', ['type' => 'error', 'message' => 'Sending failed - the email was not delivered. ' . $e->getMessage()]);

            return;
        }

        if ($this->claim->workflow_state === 'ready_to_file') {
            try {
                $workflow->transition($this->claim, 'filed', 'admin', auth()->id(), 'Claim emailed to the airline.', [
                    'filed_at' => now(),
                    'filing'   => [
                        'recipient'       => $this->to,
                        'email_reference' => null,
                        'subject'         => $this->subject,
                        'attachments'     => array_values(array_unique($this->attached)),
                        'notes'           => null,
                    ],
                ]);
            } catch (\RuntimeException $e) {
                $this->dispatch('toast', ['type' => 'error', 'message' => 'Sent, but the claim could not move to Filed: ' . $e->getMessage()]);
            }
        }

        $this->showPreview = false;
        $this->claim->refresh()->load(['user', 'signers', 'itinerary.passengers', 'events', 'expenses']);
        $this->dispatch('toast', ['type' => 'success', 'message' => "Email sent to {$this->to}."]);
    }

    /**
     * Verify one expense receipt. Approved receipts become claimable and
     * attachable; rejected ones carry a reason the customer can read.
     */
    public function reviewExpense(int $expenseId, string $decision): void
    {
        $expense = $this->claim->expenses()->findOrFail($expenseId);
        $reject  = $decision === ClaimExpense::STATUS_REJECTED;

        if ($reject) {
            $this->validate(
                ["expenseReason.{$expenseId}" => 'required|string|min:4|max:190'],
                ["expenseReason.{$expenseId}.required" => 'Give the customer a reason - it is shown on their claim.'],
            );
        }

        $expense->forceFill([
            'status'        => $reject ? ClaimExpense::STATUS_REJECTED : ClaimExpense::STATUS_APPROVED,
            'review_reason' => $reject ? trim($this->expenseReason[$expenseId]) : null,
            'admin_note'    => trim((string) ($this->expenseNote[$expenseId] ?? '')) ?: $expense->admin_note,
            'reviewed_by'   => auth()->id(),
            'reviewed_at'   => now(),
        ])->save();

        // Approved receipts default into the outgoing attachment set.
        $key = "expense-{$expense->id}";
        $this->attached = $reject
            ? array_values(array_diff($this->attached, [$key]))
            : array_values(array_unique([...$this->attached, $key]));
        $this->persist();

        app(ClaimWorkflowService::class)->audit(
            $this->claim,
            sprintf('Expense receipt %s: %s%s', $reject ? 'rejected' : 'approved',
                $expense->categoryLabel(), $expense->formattedAmount() ? ' - ' . $expense->formattedAmount() : ''),
            'admin', auth()->id(), $reject ? $expense->review_reason : ($expense->admin_note ?: null)
        );

        unset($this->expenseReason[$expenseId]);
        $this->claim->refresh()->load(['user', 'signers', 'itinerary.passengers', 'events', 'expenses']);
        $this->dispatch('toast', ['type' => 'success', 'message' => $reject ? 'Receipt rejected.' : 'Receipt approved and attached.']);
    }

    /** Record what the airline actually paid back for a receipt. */
    public function recordReimbursement(int $expenseId): void
    {
        $this->validate(
            ["expensePaid.{$expenseId}" => 'required|numeric|min:0|max:99999'],
            [],
            ["expensePaid.{$expenseId}" => 'reimbursed amount']
        );

        $expense = $this->claim->expenses()->findOrFail($expenseId);
        $expense->forceFill([
            'reimbursed_amount' => $this->expensePaid[$expenseId] + 0,
            'reimbursed_at'     => now(),
        ])->save();

        app(ClaimWorkflowService::class)->audit(
            $this->claim,
            'Expense reimbursement recorded: ' . trim(($expense->currency ?? '') . ' ' . number_format((float) $expense->reimbursed_amount, 2)),
            'admin', auth()->id(), $expense->categoryLabel()
        );

        $this->claim->refresh()->load(['user', 'signers', 'itinerary.passengers', 'events', 'expenses']);
        $this->dispatch('toast', ['type' => 'success', 'message' => 'Reimbursement recorded.']);
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

        $priced = $this->claim->fresh();
        app(ClaimWorkflowService::class)->audit(
            $this->claim, 'Eligibility approved by the team', 'admin', auth()->id(),
            trim(sprintf('%s %s - %s %s per passenger',
                $priced->eligibility_regulation, $priced->eligibility_article,
                $priced->compensation_currency, number_format((float) $priced->compensation_amount, 2)
            ))
        );

        $this->claim->refresh()->load(['user', 'signers', 'itinerary.passengers', 'events', 'expenses']);
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

        app(ClaimWorkflowService::class)->audit(
            $this->claim, 'Eligibility rejected by the team', 'admin', auth()->id(), $this->rejection_reason
        );

        $this->rejection_reason = '';
        $this->claim->refresh()->load(['user', 'signers', 'itinerary.passengers', 'events', 'expenses']);
        $this->dispatch('toast', ['type' => 'success', 'message' => 'Claim rejected - the customer has been notified with your reason.']);
    }

    // ── Compose: template route ─────────────────────────────

    /** Templates this claim's airline offers, newest default first. */
    public function airlineTemplates()
    {
        // This airline's own templates plus the house ones that fit any airline.
        return AirlineEmailTemplate::with('airlines')
            ->active()
            ->forAirline($this->airlineRecord())
            ->orderByDesc('is_default')->orderBy('type')
            ->get();
    }

    /**
     * Load a saved template exactly as written, variables substituted. No AI
     * is involved - what the admin sees is what the airline gets.
     */
    public function useTemplate(TemplateRenderer $renderer): void
    {
        $template = AirlineEmailTemplate::with('airlines')->find($this->templateId);

        if (!$template) {
            $this->dispatch('toast', ['type' => 'error', 'message' => 'Pick a template first.']);

            return;
        }

        $rendered = $renderer->renderTemplate($template, $this->claim);

        $this->subject     = $rendered['subject'];
        $this->body        = $rendered['body'];
        $this->aiGenerated = false;

        // Address it the way this letter type should be addressed - using the
        // claim's own airline, since a template may cover several.
        if ($contact = $this->airlineRecord()?->contactFor($template->contactPurpose())) {
            $this->to = $contact->email;
        }

        $unknown = $renderer->unknownVariables($template->subject . ' ' . $template->body);

        $this->dispatch('toast', [
            'type'    => $unknown ? 'error' : 'success',
            'message' => $unknown
                ? 'Template loaded, but these variables are unknown and will send as written: ' . implode(', ', $unknown)
                : "\"{$template->name}\" loaded - edit anything before sending.",
        ]);
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
        abort_unless(auth()->user()->can('claim_drafts.generate'), 403);

        $context['history'] = $this->correspondenceHistory();

        // The airline's saved template for this letter type becomes the AI's
        // base, so airline-specific wording and structure survive the draft.
        if ($base = $this->baseTemplate($type)) {
            $context['base_template'] = [
                'name'    => $base->name,
                'subject' => $base->subject,
                'body'    => app(TemplateRenderer::class)->render($base->body, $this->claim),
            ];
            $context['base_template_id'] = $base->id;
        }

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

        $this->aiGenerated = $result['generated_by'] === 'ai';
        $this->templateId  = $context['base_template_id'] ?? null;

        $this->persist(['generated_at' => now()->toIso8601String(), 'generated_by' => $result['generated_by']]);

        app(ClaimWorkflowService::class)->audit(
            $this->claim, 'AI draft generated', 'admin', auth()->id(),
            sprintf('%s v%d via %s%s', $draft->typeLabel(), $draft->version, $result['generated_by'],
                isset($context['base_template']) ? ' (base: ' . $context['base_template']['name'] . ')' : ''),
        );

        $this->dispatch('toast', ['type' => 'success', 'message' => sprintf(
            '%s v%d drafted%s - review before sending.',
            $draft->typeLabel(), $draft->version,
            $result['generated_by'] === 'ai' ? ' by AI' : ' from the built-in template (AI unavailable)'
        )]);
    }

    /** The saved template the AI should build on for this letter type. */
    private function baseTemplate(string $draftType): ?AirlineEmailTemplate
    {
        $type = match ($draftType) {
            ClaimDraft::TYPE_FOLLOW_UP => AirlineEmailTemplate::TYPE_FOLLOW_UP,
            ClaimDraft::TYPE_REGULATOR => AirlineEmailTemplate::TYPE_ESCALATION,
            default                    => AirlineEmailTemplate::TYPE_INITIAL,
        };

        return AirlineEmailTemplate::defaultFor($this->airlineRecord(), $type);
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

        app(ClaimWorkflowService::class)->audit(
            $this->claim, "{$draft->typeLabel()} v{$draft->version} approved as final", 'admin', auth()->id(), $draft->subject
        );

        $this->claim->refresh()->load(['user', 'signers', 'itinerary.passengers', 'events', 'expenses']);
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
    /**
     * What the AI needs to write the next letter: our own recent letters AND
     * the airline's actual replies. The replies are already on the claim -
     * nobody should have to paste them in by hand.
     */
    private function correspondenceHistory(): array
    {
        $ours = $this->claim->drafts()
            ->get()
            ->groupBy('type')
            ->map(fn ($group) => $group->firstWhere('approved_at', '!=', null) ?? $group->first())
            ->filter()
            ->sortByDesc('id')
            ->take(3)
            ->map(fn (ClaimDraft $d) => [
                'label'   => 'Unjamm - ' . $d->typeLabel() . ' v' . $d->version . ($d->approved_at ? ' (approved)' : ''),
                'date'    => $d->created_at->format('d M Y'),
                'subject' => $d->subject,
                'body'    => $d->body,
                'sort'    => $d->created_at,
            ]);

        $theirs = $this->claim->correspondence()
            ->where('direction', ClaimCorrespondence::DIRECTION_INBOUND)
            ->latest('id')->take(3)->get()
            ->map(fn (ClaimCorrespondence $mail) => [
                'label'   => 'Airline reply - ' . ($mail->from_name ?: $mail->from_email),
                'date'    => $mail->created_at->format('d M Y'),
                'subject' => (string) $mail->subject,
                'body'    => $mail->newBody(),
                'sort'    => $mail->created_at,
            ]);

        return $ours->concat($theirs)
            ->sortByDesc('sort')
            ->map(fn (array $entry) => Arr::except($entry, 'sort'))
            ->values()
            ->all();
    }

    /** The airline's most recent inbound reply on this claim. */
    public function latestAirlineReply(): ?ClaimCorrespondence
    {
        return $this->claim->correspondence()
            ->where('direction', ClaimCorrespondence::DIRECTION_INBOUND)
            ->latest('id')
            ->first();
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
        $this->claim->refresh()->load(['user', 'signers', 'itinerary.passengers', 'events', 'expenses']);
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

        // Approved expense receipts - selectable like any other evidence.
        foreach ($this->claim->expenses->where('status', ClaimExpense::STATUS_APPROVED) as $expense) {
            $docs[] = [
                'key'    => "expense-{$expense->id}",
                'name'   => 'Receipt - ' . $expense->categoryLabel() . ($expense->formattedAmount() ? " ({$expense->formattedAmount()})" : ''),
                'signed' => null,
            ];
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
                'mailbox'      => $this->claim->correspondence()->with(['sender', 'template'])->get(),
                'regulator'    => RegulatorDirectory::for($this->claim),
                'templates'    => $this->airlineTemplates(),
                'canSendEmail' => auth()->user()->can('claim_emails.send'),
                'contactGate'  => $this->claim->canContactAirline(),
                'customerTodo' => $this->customerAction(),
                'canDraft'     => auth()->user()->can('claim_drafts.generate'),
                'expenses'     => $this->claim->expenses()->with('reviewer')->get(),
            ])
            ->extends('layouts.admin')
            ->section('content');
    }
}
