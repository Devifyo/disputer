<?php

namespace App\Livewire\Admin\FlightClaims;

use App\Models\ClaimSigner;
use App\Services\Claims\ClaimSignatureService;
use App\Services\Claims\ClaimWorkflowService;
use App\Services\Claims\PassengerDirectory;
use App\Support\Passengers\PassengerProfile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Flight Claims -> Passengers: every person on a claim or a monitored trip,
 * merged into one profile. Built for the two jobs support actually does -
 * "find this passenger and tell me everything about them" and "this
 * signature is stuck, fix it" - so chasing a signature never means hunting
 * through claims first.
 */
class Passengers extends Component
{
    use WithPagination;

    public string $search = '';
    public string $filter = 'all';
    public ?string $selectedKey = null;

    /** Editing a signer's address in the drawer. */
    public ?int $editingSignerId = null;
    public string $signerEmail = '';

    public const FILTERS = [
        'all'      => 'Everyone',
        'pending'  => 'Awaiting signature',
        'stuck'    => 'No email on file',
        'minors'   => 'Minors',
        'guardian' => 'Guardians',
        'trips'    => 'Monitored trips',
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function setFilter(string $filter): void
    {
        $this->filter = array_key_exists($filter, self::FILTERS) ? $filter : 'all';
        $this->resetPage();
    }

    public function open(string $key): void
    {
        $this->selectedKey     = $key;
        $this->editingSignerId = null;
    }

    public function close(): void
    {
        $this->selectedKey     = null;
        $this->editingSignerId = null;
    }

    // ── Signature actions ───────────────────────────────────

    public function editEmail(int $signerId): void
    {
        $signer = ClaimSigner::findOrFail($signerId);

        $this->editingSignerId = $signer->id;
        $this->signerEmail     = (string) $signer->email;
    }

    /**
     * Fix the address and send the request in one go - the case this exists
     * for is a typo'd email blocking a claim from being filed.
     */
    public function sendSignatureRequest(int $signerId, ClaimSignatureService $signatures, ClaimWorkflowService $workflow): void
    {
        $signer = ClaimSigner::with('claim')->findOrFail($signerId);
        $email  = trim($this->editingSignerId === $signer->id ? $this->signerEmail : (string) $signer->email);

        $this->validate(
            ['signerEmail' => 'nullable|email'],
            [],
            ['signerEmail' => 'email address']
        );

        if ($email === '') {
            $this->dispatch('toast', ['type' => 'error', 'message' => 'Add an email address first - we have nowhere to send it.']);

            return;
        }

        if ($signer->status === ClaimSigner::STATUS_SIGNED) {
            $this->dispatch('toast', ['type' => 'error', 'message' => 'This passenger has already signed.']);

            return;
        }

        $wasEmail = (string) $signer->email;
        $signatures->invite($signer, $email);

        $workflow->audit(
            $signer->claim,
            $wasEmail === $email ? 'Signature request resent' : 'Signer email corrected and request sent',
            'admin',
            auth()->id(),
            $wasEmail === $email ? "To {$email}" : "{$wasEmail} -> {$email}",
        );

        $this->editingSignerId = null;
        $this->dispatch('toast', ['type' => 'success', 'message' => "Signature request sent to {$email}."]);
    }

    // ── Rendering ───────────────────────────────────────────

    /** @return Collection<int, PassengerProfile> */
    private function matching(Collection $people): Collection
    {
        $term = Str::lower(trim($this->search));

        return $people
            ->filter(function (PassengerProfile $person) use ($term) {
                if ($term === '') {
                    return true;
                }

                $haystack = Str::lower(implode(' ', array_merge(
                    [$person->name, $person->guardian ?? ''],
                    $person->emails,
                    $person->signsFor,
                    $person->claims->map(fn ($c) => "{$c->number} {$c->reference} {$c->flight_number} {$c->airline}")->all(),
                    $person->trips->map(fn ($t) => (string) $t->flight_number)->all(),
                )));

                return str_contains($haystack, $term);
            })
            ->filter(fn (PassengerProfile $person) => match ($this->filter) {
                'pending'  => $person->hasPendingSignature(),
                'stuck'    => $person->isUnreachable(),
                'minors'   => $person->is(PassengerProfile::ROLE_MINOR),
                'guardian' => $person->is(PassengerProfile::ROLE_GUARDIAN),
                'trips'    => $person->trips->isNotEmpty(),
                default    => true,
            })
            ->values();
    }

    public function render(PassengerDirectory $directory)
    {
        $people  = $directory->all();
        $matched = $this->matching($people);

        $perPage = 15;
        $page    = $this->getPage();
        $paginator = new LengthAwarePaginator(
            $matched->forPage($page, $perPage)->values(),
            $matched->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()],
        );

        return view('livewire.admin.flight-claims.passengers', [
                'people'    => $paginator,
                'filters'   => self::FILTERS,
                'selected'  => $this->selectedKey ? $people->get($this->selectedKey) : null,
                'stats'     => [
                    'people'   => $people->count(),
                    'pending'  => $people->filter(fn (PassengerProfile $p) => $p->hasPendingSignature())->count(),
                    'stuck'    => $people->filter(fn (PassengerProfile $p) => $p->isUnreachable())->count(),
                    'minors'   => $people->filter(fn (PassengerProfile $p) => $p->is(PassengerProfile::ROLE_MINOR))->count(),
                ],
            ])
            ->extends('layouts.admin')
            ->section('content');
    }
}
