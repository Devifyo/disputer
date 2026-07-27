<?php

namespace App\Livewire\Admin\FlightClaims;

use App\Models\Claim;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\PayoutTransaction;
use App\Models\UserPayoutAccount;
use App\Services\Payments\PaymentService;
use App\Services\Payments\WisePayoutService;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Flight Claims -> Payments: airline money in, success fee, passenger money
 * out (Wise or manual), the full ledger and the audit trail. Every mutation
 * goes through PaymentService / WisePayoutService - this component is UI.
 */
class Payments extends Component
{
    use WithPagination;

    public string $tab = 'payments';

    // Filters (transaction + payment lists).
    public string $search = '';
    public string $status = 'all';
    public string $currency = 'all';
    public string $from = '';
    public string $to = '';

    // Record-payment modal.
    public bool $showRecordModal = false;
    public string $claimSearch = '';
    public ?int $recordClaimId = null;
    public array $form = [];

    // Detail modal.
    public ?int $paymentId = null;
    public string $payoutCurrency = 'CAD';
    public array $manual = [];
    public string $feeOverride = '';
    public string $feeReason = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->can('payments.view'), 403);
    }

    public function updating($name): void
    {
        // The two tabs share the paginator - page 3 of transactions does not
        // exist on the payments list, so any tab or filter change restarts
        // from page 1.
        if (in_array($name, ['tab', 'search', 'status', 'currency', 'from', 'to'], true)) {
            $this->resetPage();
        }
    }

    // ── Record a payment ────────────────────────────────────

    public function openRecord(?int $claimId = null): void
    {
        $this->authorizeManage();
        $this->recordClaimId = $claimId;
        $this->claimSearch   = '';
        $this->form = [
            'gross_amount' => null,
            'currency'     => 'CAD',
            'fee_percent'  => PaymentService::defaultFeePercent(),
            'payment_date' => now()->toDateString(),
            'reference'    => '',
            'notes'        => '',
        ];
        $this->showRecordModal = true;
        $this->resetErrorBag();
    }

    public function chooseClaim(int $claimId): void
    {
        $this->recordClaimId = $claimId;
        $claim = Claim::find($claimId);
        if ($claim?->compensation_amount) {
            $paxCount = max(1, count($claim->passengerNames()));
            $this->form['gross_amount'] = round((float) $claim->compensation_amount * $paxCount, 2);
            $this->form['currency']     = $claim->compensation_currency ?: 'CAD';
        }
    }

    public function saveRecord(PaymentService $payments): void
    {
        $this->authorizeManage();

        $data = $this->validate([
            'recordClaimId'       => 'required|integer|exists:claims,id',
            'form.gross_amount'   => 'required|numeric|min:0.01|max:999999',
            'form.currency'       => ['required', Rule::in(Payment::CURRENCIES)],
            'form.fee_percent'    => 'required|numeric|min:0|max:100',
            'form.payment_date'   => 'required|date|before_or_equal:today',
            'form.reference'      => 'nullable|string|max:120',
            'form.notes'          => 'nullable|string|max:2000',
        ], [], ['recordClaimId' => 'claim', 'form.gross_amount' => 'gross amount']);

        try {
            $payment = $payments->record(Claim::findOrFail($this->recordClaimId), $data['form'], auth()->user());
        } catch (\Throwable $e) {
            $this->dispatch('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return;
        }

        $this->showRecordModal = false;
        $this->paymentId = $payment->id;
        $this->dispatch('toast', ['type' => 'success', 'message' => "Payment recorded - net payout {$payment->money($payment->net_amount)}."]);
    }

    // ── Detail actions ──────────────────────────────────────

    public function open(int $paymentId): void
    {
        $this->paymentId      = $paymentId;
        $this->feeOverride    = '';
        $this->feeReason      = '';
        $this->payoutCurrency = $this->payment()?->currency ?? 'CAD';
        $this->manual         = ['amount' => null, 'currency' => $this->payoutCurrency, 'exchange_rate' => null, 'reference' => ''];
    }

    public function applyFeeOverride(PaymentService $payments): void
    {
        $this->validate(['feeOverride' => 'required|numeric|min:0|max:100', 'feeReason' => 'nullable|string|max:300']);

        try {
            $payments->overrideFee($this->payment(), (float) $this->feeOverride, auth()->user(), $this->feeReason ?: null);
        } catch (\Throwable $e) {
            $this->dispatch('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return;
        }

        $this->feeOverride = $this->feeReason = '';
        $this->dispatch('toast', ['type' => 'success', 'message' => 'Success fee updated - amounts recalculated.']);
    }

    public function draftPayout(WisePayoutService $wise): void
    {
        $this->authorizeManage();
        $this->validate(['payoutCurrency' => Rule::in(Payment::CURRENCIES)]);

        // The destination is the customer's default account - resolved by
        // the service, never chosen here. The currency only applies to the
        // Wise-email fallback when no account is saved.
        $this->guarded(fn () => $wise->draft($this->payment(), $this->payoutCurrency, auth()->user()),
            'Payout drafted - review it, then send.');
    }

    /**
     * One-click send: draft + queue in a single action, behind the styled
     * confirm popup - so payouts never sit forgotten in draft. Admins
     * without payouts.send still get the two-step draft flow.
     */
    public function sendPayoutNow(WisePayoutService $wise): void
    {
        $this->authorizeManage();
        abort_unless(auth()->user()->can('payouts.send'), 403);
        $this->validate(['payoutCurrency' => Rule::in(Payment::CURRENCIES)]);

        $this->guarded(function () use ($wise) {
            $payout = $wise->draft($this->payment(), $this->payoutCurrency, auth()->user());
            $wise->send($payout, auth()->user());
        }, 'Payout queued - the Wise transfer is processing.');
    }

    public function requestBankDetails(PaymentService $payments): void
    {
        $this->authorizeManage();

        $this->guarded(fn () => $payments->requestBankDetails($this->payment(), auth()->user()),
            'Customer notified - email and in-app alert sent asking for their bank details.');
    }

    public function sendPayout(WisePayoutService $wise, int $payoutId): void
    {
        $this->guarded(fn () => $wise->send(Payout::findOrFail($payoutId), auth()->user()),
            'Payout queued - the Wise transfer is processing.');
    }

    public function retryPayout(WisePayoutService $wise, int $payoutId): void
    {
        $this->guarded(fn () => $wise->retry(Payout::findOrFail($payoutId), auth()->user()),
            'Retry queued.');
    }

    public function cancelPayout(WisePayoutService $wise, int $payoutId): void
    {
        $this->guarded(fn () => $wise->cancel(Payout::findOrFail($payoutId), auth()->user()),
            'Payout cancelled.');
    }

    public function refreshPayout(WisePayoutService $wise, int $payoutId): void
    {
        $this->guarded(fn () => $wise->refreshStatus(Payout::findOrFail($payoutId)),
            'Status refreshed from Wise.');
    }

    public function recordManualPayout(WisePayoutService $wise): void
    {
        $this->authorizeManage();
        $this->validate([
            'manual.amount'        => 'required|numeric|min:0.01|max:999999',
            'manual.currency'      => ['required', Rule::in(Payment::CURRENCIES)],
            'manual.exchange_rate' => 'nullable|numeric|min:0.000001',
            'manual.reference'     => 'nullable|string|max:120',
        ], [], ['manual.amount' => 'amount paid']);

        $this->guarded(fn () => $wise->recordManual($this->payment(), $this->manual, auth()->user()),
            'Manual payout recorded - the payment is settled.');
    }

    public function refund(PaymentService $payments): void
    {
        $this->authorizeManage();

        $this->guarded(fn () => $payments->refund($this->payment(), auth()->user(), 'Refund issued from the payments module.'),
            'Payment marked refunded - the customer has been notified.');
    }

    // ── CSV export ──────────────────────────────────────────

    public function exportCsv(): StreamedResponse
    {
        abort_unless(auth()->user()->can('payments.view'), 403);
        $rows = $this->transactionQuery()->limit(5000)->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Timestamp', 'Type', 'Claim', 'Passenger', 'Amount', 'Currency', 'Reference', 'Status', 'Performed by', 'Notes']);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row->created_at->toDateTimeString(),
                    $row->typeLabel(),
                    '#' . ($row->payment?->claim?->number ?? $row->payment_id),
                    $row->payment?->user?->name,
                    $row->amount,
                    $row->currency,
                    $row->reference,
                    $row->status,
                    $row->actor?->name ?? 'system',
                    $row->notes,
                ]);
            }
            fclose($out);
        }, 'unjamm-transactions-' . now()->format('Ymd-His') . '.csv');
    }

    // ── Internals ───────────────────────────────────────────

    private function authorizeManage(): void
    {
        abort_unless(auth()->user()->can('payments.manage'), 403, 'You do not have permission to manage payments.');
    }

    private function guarded(callable $action, string $success): void
    {
        try {
            $action();
        } catch (\Throwable $e) {
            $this->dispatch('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return;
        }

        $this->dispatch('toast', ['type' => 'success', 'message' => $success]);
    }

    public function payment(): ?Payment
    {
        return $this->paymentId
            ? Payment::with(['claim', 'user', 'payouts', 'transactions.actor', 'logs.actor'])->find($this->paymentId)
            : null;
    }

    private function transactionQuery()
    {
        return PayoutTransaction::with(['payment.claim', 'payment.user', 'actor'])
            ->when($this->search !== '', function ($q) {
                $term = '%' . trim($this->search) . '%';
                $q->where(fn ($w) => $w
                    ->where('reference', 'like', $term)
                    ->orWhereHas('payment.claim', fn ($c) => $c->where('number', 'like', $term)->orWhere('reference', 'like', $term))
                    ->orWhereHas('payment.user', fn ($u) => $u->where('name', 'like', $term)->orWhere('email', 'like', $term)));
            })
            ->when($this->currency !== 'all', fn ($q) => $q->where('currency', $this->currency))
            ->when($this->status !== 'all', fn ($q) => $q->where('type', $this->status))
            ->when($this->from !== '', fn ($q) => $q->where('created_at', '>=', Carbon::parse($this->from)->startOfDay()))
            ->when($this->to !== '', fn ($q) => $q->where('created_at', '<=', Carbon::parse($this->to)->endOfDay()))
            ->latest('id');
    }

    /** KPI card titles - also the detail popup's heading. */
    public const STAT_LABELS = [
        'collected' => 'Compensation collected',
        'fees'      => 'Success fees earned',
        'paid_out'  => 'Paid out to passengers',
    ];

    public ?string $statDetail = null;

    public function openStat(string $key): void
    {
        $this->statDetail = isset(self::STAT_LABELS[$key]) ? $key : null;
    }

    public function closeStat(): void
    {
        $this->statDetail = null;
    }

    private function stats(): array
    {
        $sum = fn ($query) => $query->selectRaw('currency, SUM(gross_amount) g, SUM(fee_amount) f, SUM(net_amount) n, COUNT(*) c')
            ->groupBy('currency')->orderBy('currency')->get();

        $collected = Payment::whereNotIn('status', [Payment::STATUS_CANCELLED, Payment::STATUS_REFUNDED]);
        $totals    = $sum(clone $collected);
        $paid      = $sum(Payment::where('status', Payment::STATUS_PAID));

        return [
            'collected'  => $this->moneyStat($totals, 'g'),
            'fees'       => $this->moneyStat($totals, 'f'),
            'paid_out'   => $this->moneyStat($paid, 'n'),
            'pending'    => Payment::whereIn('status', [Payment::STATUS_RECEIVED, Payment::STATUS_READY_FOR_PAYOUT])->count(),
            'processing' => Payment::where('status', Payment::STATUS_PROCESSING)->count(),
            'failed'     => Payment::where('status', Payment::STATUS_FAILED)->count(),
        ];
    }

    /**
     * One readable number per card: the base-currency equivalent (Wise
     * mid-market rate, cached) headlines; the true per-currency figures stay
     * as the breakdown. Falls back to the breakdown alone when a rate is
     * unavailable. Display only - money always moves on live quotes.
     */
    private function moneyStat($rows, string $col): array
    {
        $base = strtoupper(config('services.wise.dashboard_currency', 'CAD'));
        $wise = app(WisePayoutService::class);

        $details = $rows->map(function ($row) use ($wise, $base, $col) {
            $rate = $wise->rate($row->currency, $base);

            return [
                'currency'  => $row->currency,
                'amount'    => (float) $row->{$col},
                'count'     => (int) $row->c,
                'rate'      => $rate,
                'converted' => $rate !== null ? round((float) $row->{$col} * $rate, 2) : null,
            ];
        })->values();

        $exact = $details->every(fn ($d) => $d['currency'] === $base);
        $total = $details->contains(fn ($d) => $d['converted'] === null) ? null : $details->sum('converted');

        $breakdown = $details->map(fn ($d) => $d['currency'] . ' ' . number_format($d['amount'], 2))->implode(' + ');

        return [
            'headline'  => $total !== null && $details->isNotEmpty()
                ? ($exact ? '' : '≈ ') . $base . ' ' . number_format($total, 2)
                : ($breakdown ?: '-'),
            'breakdown' => $total !== null && !$exact && $details->isNotEmpty() ? $breakdown : null,
            'details'   => $details,
            'total'     => $total,
            'base'      => $base,
        ];
    }

    public function render()
    {
        $payments = Payment::with(['claim', 'user', 'payouts'])
            ->when($this->search !== '', function ($q) {
                $term = '%' . trim($this->search) . '%';
                $q->where(fn ($w) => $w
                    ->where('reference', 'like', $term)
                    ->orWhereHas('claim', fn ($c) => $c->where('number', 'like', $term)->orWhere('reference', 'like', $term))
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', $term)->orWhere('email', 'like', $term)));
            })
            ->when($this->status !== 'all' && isset(Payment::STATUSES[$this->status]), fn ($q) => $q->where('status', $this->status))
            ->when($this->currency !== 'all', fn ($q) => $q->where('currency', $this->currency))
            ->when($this->from !== '', fn ($q) => $q->whereDate('payment_date', '>=', $this->from))
            ->when($this->to !== '', fn ($q) => $q->whereDate('payment_date', '<=', $this->to))
            ->latest('id')
            ->paginate(12);

        $claimOptions = collect();
        if ($this->showRecordModal && !$this->recordClaimId && trim($this->claimSearch) !== '') {
            $term = '%' . trim($this->claimSearch) . '%';
            $claimOptions = Claim::with('user')
                ->where(fn ($w) => $w->where('number', 'like', $term)->orWhere('reference', 'like', $term)
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', $term)->orWhere('email', 'like', $term)))
                ->latest('id')->limit(6)->get();
        }

        return view('livewire.admin.flight-claims.payments', [
                'stats'         => $this->stats(),
                'statLabels'    => self::STAT_LABELS,
                'payments'      => $payments,
                'transactions'  => $this->tab === 'transactions' ? $this->transactionQuery()->paginate(20) : null,
                'detail'        => $this->payment(),
                'claimOptions'  => $claimOptions,
                'wiseReady'     => app(WisePayoutService::class)->configured(),
                'defaultAccount' => ($user = $this->payment()?->user) ? UserPayoutAccount::defaultFor($user) : null,
                'canManage'     => auth()->user()->can('payments.manage'),
                'canOverride'   => auth()->user()->can('payments.override_fee'),
                'canSend'       => auth()->user()->can('payouts.send'),
                'currencies'    => Payment::CURRENCIES,
                'txTypes'       => PayoutTransaction::TYPES,
            ])
            ->extends('layouts.admin')
            ->section('content');
    }
}
