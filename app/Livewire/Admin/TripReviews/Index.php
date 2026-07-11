<?php

namespace App\Livewire\Admin\TripReviews;

use App\Models\Trip;
use App\Models\TripEvent;
use App\Notifications\TripEligibilityRejected;
use App\Notifications\TripEligibleForCompensation;
use App\Services\Eligibility\EligibilityEngine;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

/**
 * The "team" behind "our team is verifying the details": trips whose
 * eligibility verdict needs a human decision - below-threshold engine
 * verdicts and passenger reports the engine couldn't match.
 */
class Index extends Component
{
    use WithPagination;

    public $showModal = false;
    public ?int $selectedId = null;
    public $rejection_reason = '';
    public string $tab = 'review';

    public function setTab(string $tab)
    {
        $this->tab = in_array($tab, ['review', 'all'], true) ? $tab : 'review';
        $this->resetPage();
    }

    public function open($id)
    {
        $this->selectedId = $id;
        $this->rejection_reason = '';
        $this->showModal = true;
    }

    public function close()
    {
        $this->showModal = false;
        $this->selectedId = null;
    }

    public function approve($id)
    {
        $trip = Trip::findOrFail($id);

        $trip->forceFill([
            'eligibility_status'          => EligibilityEngine::STATUS_ELIGIBLE,
            'eligibility_reason'          => str_replace(' Our team is verifying the details before confirming your claim.', '', (string) $trip->eligibility_reason),
            'eligibility_details'         => ($trip->eligibility_details ?? []) + ['decided_by' => 'team'],
            'eligibility_decision_source' => 'admin',
            'eligibility_decided_by'      => auth()->id(),
            'eligibility_decided_at'      => now(),
        ])->save();

        $trip->events()->create([
            'type'        => TripEvent::TYPE_ELIGIBILITY,
            'description' => sprintf('Our team confirmed your eligibility under %s (%s).', $trip->eligibility_regulation, $trip->eligibility_article),
            'qualifying'  => true,
            'detected_at' => now(),
        ]);

        try {
            $trip->user?->notify(new TripEligibleForCompensation($trip));
        } catch (Throwable $e) {
            Log::error('Eligibility approval notification failed', ['trip' => $trip->id, 'error' => $e->getMessage()]);
        }

        $this->close();
        $this->dispatch('toast', ['type' => 'success', 'message' => "Trip #{$trip->id} approved - the customer has been notified."]);
    }

    public function reject($id)
    {
        $this->validate(
            ['rejection_reason' => 'required|string|min:10|max:500'],
            [],
            ['rejection_reason' => 'reason shown to the customer']
        );

        $trip = Trip::findOrFail($id);

        $trip->forceFill([
            'eligibility_status'          => EligibilityEngine::STATUS_REJECTED,
            'eligibility_reason'          => $this->rejection_reason,
            'eligibility_details'         => ($trip->eligibility_details ?? []) + ['decided_by' => 'team'],
            'eligibility_decision_source' => 'admin',
            'eligibility_decided_by'      => auth()->id(),
            'eligibility_decided_at'      => now(),
        ])->save();

        $trip->events()->create([
            'type'        => TripEvent::TYPE_ELIGIBILITY,
            'description' => 'Eligibility review outcome: ' . $this->rejection_reason,
            'detected_at' => now(),
        ]);

        try {
            $trip->user?->notify(new TripEligibilityRejected($trip, $this->rejection_reason));
        } catch (Throwable $e) {
            Log::error('Eligibility rejection notification failed', ['trip' => $trip->id, 'error' => $e->getMessage()]);
        }

        $this->close();
        $this->dispatch('toast', ['type' => 'success', 'message' => "Trip #{$trip->id} rejected - the customer has been notified with your reason."]);
    }

    public function render()
    {
        $trips = Trip::with(['user', 'eligibilityDecider'])
            ->when(
                $this->tab === 'review',
                fn ($q) => $q->where('eligibility_status', EligibilityEngine::STATUS_REVIEW),
                fn ($q) => $q->whereNotNull('eligibility_status'),
            )
            ->orderByDesc('eligibility_evaluated_at')
            ->paginate(15);

        $counts = [
            'review' => Trip::where('eligibility_status', EligibilityEngine::STATUS_REVIEW)->count(),
            'all'    => Trip::whereNotNull('eligibility_status')->count(),
        ];

        $selected = $this->selectedId
            ? Trip::with(['user', 'eligibilityDecider', 'events' => fn ($q) => $q->latest('detected_at')->limit(12)])->find($this->selectedId)
            : null;

        return view('livewire.admin.trip-reviews.index', [
                'trips'    => $trips,
                'selected' => $selected,
                'counts'   => $counts,
            ])
            ->extends('layouts.admin')
            ->section('content');
    }
}
