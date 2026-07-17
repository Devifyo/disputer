<?php

namespace App\Livewire\Admin\FlightClaims;

use App\Models\Claim;
use App\Models\Setting;
use App\Services\Claims\ClaimLetterService;
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

    /** Pending admin uploads (extra documents for the airline). */
    public array $uploads = [];

    public function mount(Claim $claim): void
    {
        $this->claim = $claim->load(['user', 'signers', 'itinerary.passengers', 'events']);

        $letter        = $claim->airline_letter ?? [];
        $this->to      = $letter['to'] ?? '';
        $this->subject = $letter['subject'] ?? '';
        $this->body    = $letter['body'] ?? '';

        // Default selection: everything legal + the ticket; the admin trims.
        $this->attached = $letter['attachments']
            ?? collect($this->attachments())->pluck('key')->all();
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
        $draft = $letters->generate($this->claim);

        $this->subject = $draft['subject'];
        $this->body    = $draft['body'];

        $this->persist(['generated_at' => now()->toIso8601String(), 'generated_by' => $draft['generated_by']]);

        $this->dispatch('toast', ['type' => 'success', 'message' => $draft['generated_by'] === 'ai'
            ? 'Claim letter drafted by AI - review before sending.'
            : 'AI unavailable - a template draft was generated instead.']);
    }

    public function saveDraft(): void
    {
        $this->validate([
            'to'      => 'nullable|email|max:190',
            'subject' => 'required|string|max:190',
            'body'    => 'required|string|max:10000',
        ], [], ['to' => 'airline email']);

        $this->persist();
        $this->dispatch('toast', ['type' => 'success', 'message' => 'Draft saved.']);
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

        return view('livewire.admin.flight-claims.claim-detail', [
                'stageLabel'  => $stageLabel,
                'stageCls'    => $stageCls,
                'paxCount'    => $paxCount,
                'gross'       => $gross,
                'feePercent'  => $fee,
                'attachments' => $this->attachments(),
            ])
            ->extends('layouts.admin')
            ->section('content');
    }
}
