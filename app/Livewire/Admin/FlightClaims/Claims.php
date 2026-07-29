<?php

namespace App\Livewire\Admin\FlightClaims;

use App\Models\Claim;
use App\Models\ClaimLifecycleStage;
use App\Models\ClaimSigner;
use App\Models\Subscription;
use App\Services\Billing\SubscriptionGate;
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

    /** Membership is a separate axis from lifecycle - combine it with any tab. */
    public bool $plusOnly = false;

    public const FILTERS = [
        'all'        => 'All',
        'review'     => 'In review',
        'confirmation' => 'Awaiting confirmation',
        'signatures' => 'Awaiting signatures',
        'ready'      => 'Ready to file',
        'filed'      => 'Awaiting airline',
        'responded'  => 'Responded',
        'escalation' => 'Escalation',
        'paid'       => 'Paid',
        'denied'     => 'Denied',
        'closed'     => 'Closed',
        'rejected'   => 'Not eligible',
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingPlusOnly(): void
    {
        $this->resetPage();
    }

    public function setStatus(string $status): void
    {
        $this->status = array_key_exists($status, self::FILTERS) ? $status : 'all';
        $this->resetPage();
    }

    /** Stage pill: eligibility overlays first, then the configured lifecycle stage. */
    public static function stage(Claim $claim): array
    {
        if ($claim->status === Claim::STATUS_REJECTED) {
            return ['Not eligible', 'bg-rose-50 text-rose-700 ring-rose-200'];
        }
        if ($claim->status === Claim::STATUS_PENDING_ELIGIBILITY) {
            return ['Team review', 'bg-amber-50 text-amber-700 ring-amber-200'];
        }
        if ($claim->status !== Claim::STATUS_ELIGIBLE) {
            return ['Evaluating', 'bg-slate-50 text-slate-600 ring-slate-200'];
        }

        if ($claim->workflow_state === 'awaiting_signature') {
            return [
                sprintf('Signatures %d/%d', $claim->signers->where('status', ClaimSigner::STATUS_SIGNED)->count(), max(1, $claim->signers->count())),
                'bg-violet-50 text-violet-700 ring-violet-200',
            ];
        }
        if ($claim->workflow_state === 'draft') {
            return ['Awaiting confirmation', 'bg-blue-50 text-blue-700 ring-blue-200'];
        }

        $stage = ClaimLifecycleStage::byKey($claim->workflow_state);

        return $stage
            ? [$stage->name, $stage->badgeClasses()]
            : [ucfirst(str_replace('_', ' ', $claim->workflow_state)), 'bg-slate-50 text-slate-600 ring-slate-200'];
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
                // Eligible, but the customer has not confirmed yet - the claim
                // cannot move until they do, so it is worth chasing.
                'confirmation' => $q->where('status', Claim::STATUS_ELIGIBLE)->where('workflow_state', 'draft'),
                'signatures' => $q->where('workflow_state', 'awaiting_signature'),
                'ready'      => $q->where('workflow_state', 'ready_to_file'),
                'filed'      => $q->whereIn('workflow_state', ['filed', 'awaiting_response']),
                'responded'  => $q->where('workflow_state', 'responded'),
                'escalation' => $q->whereIn('workflow_state', ['awaiting_escalation', 'escalated', 'litigation']),
                'paid'       => $q->where('workflow_state', 'paid'),
                'denied'     => $q->where('workflow_state', 'denied'),
                'closed'     => $q->where('workflow_state', 'closed'),
                'rejected'   => $q->where('status', Claim::STATUS_REJECTED),
                default      => $q,
            })
            ->when($this->plusOnly, fn ($q) => $q->whereHas('user.subscriptions',
                fn ($sub) => $sub->whereIn('status', Subscription::GOOD_STANDING)))
            // Priority filing queue: when the admin makes it a Plus perk,
            // members' claims surface first; otherwise pure date order.
            ->select('claims.*')
            ->addSelect(['is_plus_member' => Subscription::selectRaw('1')
                ->whereColumn('subscriptions.user_id', 'claims.user_id')
                ->whereIn('status', Subscription::GOOD_STANDING)
                ->limit(1)])
            ->when(
                SubscriptionGate::requiresSubscription('priority_processing'),
                fn ($q) => $q->orderByRaw('is_plus_member IS NULL')
            )
            ->latest()
            ->paginate(15);

        return view('livewire.admin.flight-claims.claims', [
                'claims'      => $claims,
                'filters'     => self::FILTERS,
                'reviewCount' => Claim::where('status', Claim::STATUS_PENDING_ELIGIBILITY)->count(),
                'confirmationCount' => Claim::where('status', Claim::STATUS_ELIGIBLE)->where('workflow_state', 'draft')->count(),
                'plusCount'   => Claim::whereHas('user.subscriptions', fn ($sub) => $sub->whereIn('status', Subscription::GOOD_STANDING))->count(),
                'plusBadges'  => SubscriptionGate::enabled(),
            ])
            ->extends('layouts.admin')
            ->section('content');
    }
}
