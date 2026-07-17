<?php

namespace App\Livewire\Admin\FlightClaims;

use App\Models\Claim;
use App\Models\ClaimSigner;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Flight Claims Management - every compensation claim with its workflow
 * stage (evaluation -> confirmation -> signatures -> filing) and a
 * read-only detail panel for support and oversight.
 */
class Claims extends Component
{
    use WithPagination;

    public string $search = '';
    public string $status = 'all';

    public const FILTERS = [
        'all'        => 'All',
        'review'     => 'In review',
        'eligible'   => 'Eligible',
        'signatures' => 'Awaiting signatures',
        'ready'      => 'Ready to file',
        'rejected'   => 'Not eligible',
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function setStatus(string $status): void
    {
        $this->status = array_key_exists($status, self::FILTERS) ? $status : 'all';
        $this->resetPage();
    }

    /** The customer-facing workflow stage, for the Stage column. */
    public static function stage(Claim $claim): array
    {
        return match (true) {
            $claim->status === Claim::STATUS_REJECTED            => ['Not eligible', 'bg-rose-50 text-rose-700 ring-rose-200'],
            $claim->status === Claim::STATUS_PENDING_ELIGIBILITY => ['Team review', 'bg-amber-50 text-amber-700 ring-amber-200'],
            $claim->status !== Claim::STATUS_ELIGIBLE            => ['Evaluating', 'bg-slate-50 text-slate-600 ring-slate-200'],
            $claim->signed_at !== null                           => ['Ready to file', 'bg-emerald-50 text-emerald-700 ring-emerald-200'],
            $claim->confirmed_at !== null                        => [
                sprintf('Signatures %d/%d', $claim->signers->where('status', ClaimSigner::STATUS_SIGNED)->count(), max(1, $claim->signers->count())),
                'bg-violet-50 text-violet-700 ring-violet-200',
            ],
            default                                              => ['Awaiting confirmation', 'bg-blue-50 text-blue-700 ring-blue-200'],
        };
    }

    public function render()
    {
        $claims = Claim::query()
            ->with(['user', 'signers', 'itinerary.passengers'])
            ->when($this->search !== '', function ($q) {
                $s = '%' . trim($this->search) . '%';
                $q->where(fn ($w) => $w
                    ->where('number', 'like', $s)
                    ->orWhere('reference', 'like', $s)
                    ->orWhere('flight_number', 'like', $s)
                    ->orWhere('departure_airport', 'like', $s)
                    ->orWhere('arrival_airport', 'like', $s)
                    ->orWhere('passenger_name', 'like', $s)
                    ->orWhereHas('user', fn ($u) => $u->where('email', 'like', $s)->orWhere('name', 'like', $s)));
            })
            ->when($this->status !== 'all', fn ($q) => match ($this->status) {
                'review'     => $q->where('status', Claim::STATUS_PENDING_ELIGIBILITY),
                'eligible'   => $q->where('status', Claim::STATUS_ELIGIBLE),
                'signatures' => $q->whereNotNull('confirmed_at')->whereNull('signed_at'),
                'ready'      => $q->whereNotNull('signed_at'),
                'rejected'   => $q->where('status', Claim::STATUS_REJECTED),
                default      => $q,
            })
            ->latest()
            ->paginate(15);

        return view('livewire.admin.flight-claims.claims', [
                'claims'      => $claims,
                'filters'     => self::FILTERS,
                'reviewCount' => Claim::where('status', Claim::STATUS_PENDING_ELIGIBILITY)->count(),
            ])
            ->extends('layouts.admin')
            ->section('content');
    }
}
