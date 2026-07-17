<?php

namespace App\Livewire\Admin\FlightClaims;

use App\Models\Trip;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Flight Claims Management - every protected trip, searchable and
 * filterable, with a read-only detail panel. Decisions stay in Trip Reviews.
 */
class Trips extends Component
{
    use WithPagination;

    public string $search = '';
    public string $status = 'all';
    public ?int $selectedId = null;

    public const FILTERS = [
        'all'      => 'All',
        'upcoming' => 'Upcoming',
        'eligible' => 'Eligible',
        'review'   => 'In review',
        'rejected' => 'Not eligible',
        'claimed'  => 'Claim filed',
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

    public function open(int $id): void
    {
        $this->selectedId = $id;
    }

    public function close(): void
    {
        $this->selectedId = null;
    }

    public function render()
    {
        $trips = Trip::query()
            ->with('user')
            ->withExists('claims')
            ->when($this->search !== '', function ($q) {
                $s = '%' . trim($this->search) . '%';
                $q->where(fn ($w) => $w
                    ->where('flight_number', 'like', $s)
                    ->orWhere('departure_airport', 'like', $s)
                    ->orWhere('arrival_airport', 'like', $s)
                    ->orWhere('passenger_name', 'like', $s)
                    ->orWhereHas('user', fn ($u) => $u->where('email', 'like', $s)->orWhere('name', 'like', $s)));
            })
            ->when($this->status !== 'all', fn ($q) => match ($this->status) {
                'upcoming' => $q->whereDate('departure_date', '>=', today()),
                'claimed'  => $q->whereHas('claims'),
                default    => $q->where('eligibility_status', $this->status),
            })
            ->orderByRaw('departure_date IS NULL')
            ->orderByDesc('departure_date')
            ->paginate(15);

        $selected = $this->selectedId
            ? Trip::with(['user', 'events' => fn ($q) => $q->latest('detected_at')->limit(10)])->find($this->selectedId)
            : null;

        return view('livewire.admin.flight-claims.trips', [
                'trips'    => $trips,
                'selected' => $selected,
                'filters'  => self::FILTERS,
            ])
            ->extends('layouts.admin')
            ->section('content');
    }
}
