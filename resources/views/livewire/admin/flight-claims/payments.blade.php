<div class="h-full overflow-y-auto bg-slate-50/50">
    <div class="max-w-[1320px] mx-auto p-6 pb-24">
        <x-flash />

        <div class="flex items-start justify-between gap-4 flex-wrap mb-6">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 flex items-center gap-2.5">
                    Payments
                    @if (config('services.wise.sandbox'))
                        <span class="inline-flex items-center gap-1 bg-violet-100 text-violet-700 text-[10px] font-black uppercase tracking-wider px-2.5 py-1 rounded-full ring-1 ring-violet-200" title="Wise sandbox - transfers are fake. Set WISE_SANDBOX=false for real money.">
                            <i data-lucide="flask-conical" class="w-3 h-3"></i> Wise sandbox
                        </span>
                    @endif
                </h1>
                <p class="text-sm text-slate-500 mt-1">Airline money in, success fee, passenger payouts out - with the full ledger behind every cent.</p>
            </div>
            @if ($canManage)
                <button wire:click="openRecord" class="inline-flex items-center gap-2 bg-slate-900 hover:bg-slate-800 text-white text-sm font-bold px-5 py-2.5 rounded-xl transition-colors">
                    <i data-lucide="hand-coins" class="w-4 h-4"></i> Record airline payment
                </button>
            @endif
        </div>

        @unless ($wiseReady)
            <div class="flex items-start gap-2.5 bg-amber-50 border border-amber-200 text-amber-800 rounded-2xl px-5 py-3.5 text-sm mb-6">
                <i data-lucide="triangle-alert" class="w-4 h-4 shrink-0 mt-0.5"></i>
                <span>Wise {{ config('services.wise.sandbox') ? 'sandbox' : '' }} is not configured (<code class="font-mono text-xs">{{ config('services.wise.sandbox') ? 'WISE_SANDBOX_API_TOKEN' : 'WISE_API_TOKEN' }}</code>). Payments can be recorded and manual payouts logged; Wise transfers activate once the token is set. Run <code class="font-mono text-xs">php artisan wise:setup</code> after adding it.</span>
            </div>
        @endunless

        {{-- Dashboard --}}
        <div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-4 mb-6">
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
                <p class="text-[11px] uppercase tracking-wider font-bold text-slate-400">Compensation collected</p>
                <p class="text-xl font-bold text-slate-900 mt-2">{{ $stats['collected'] }}</p>
            </div>
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
                <p class="text-[11px] uppercase tracking-wider font-bold text-slate-400">Success fees earned</p>
                <p class="text-xl font-bold text-slate-900 mt-2">{{ $stats['fees'] }}</p>
            </div>
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
                <p class="text-[11px] uppercase tracking-wider font-bold text-slate-400">Paid out to passengers</p>
                <p class="text-xl font-bold text-emerald-700 mt-2">{{ $stats['paid_out'] }}</p>
            </div>
            @foreach ([['Pending payouts', $stats['pending'], 'text-amber-600'], ['Processing', $stats['processing'], 'text-violet-600'], ['Failed', $stats['failed'], $stats['failed'] ? 'text-rose-600' : 'text-slate-900']] as [$label, $value, $cls])
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
                    <p class="text-[11px] uppercase tracking-wider font-bold text-slate-400">{{ $label }}</p>
                    <p class="text-2xl font-bold mt-2 {{ $cls }}">{{ $value }}</p>
                </div>
            @endforeach
        </div>

        {{-- Tabs + filters --}}
        <div class="flex items-center justify-between gap-3 flex-wrap mb-4">
            <div class="inline-flex items-center gap-1 bg-white rounded-xl border border-slate-200 shadow-sm p-1">
                @foreach (['payments' => 'Payments', 'transactions' => 'Transaction history'] as $key => $label)
                    <button wire:click="$set('tab', '{{ $key }}')"
                            class="px-4 py-2 rounded-lg text-sm font-bold transition-all {{ $tab === $key ? 'bg-slate-900 text-white shadow' : 'text-slate-500 hover:text-slate-800' }}">
                        {{ $label }}
                    </button>
                @endforeach
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="Claim, passenger, reference…"
                       class="w-56 px-3.5 py-2 rounded-xl border border-slate-200 text-sm focus:border-primary-500 outline-none">
                <select wire:model.live="status" class="px-3 py-2 rounded-xl border border-slate-200 text-sm outline-none">
                    <option value="all">All {{ $tab === 'transactions' ? 'types' : 'statuses' }}</option>
                    @foreach ($tab === 'transactions' ? $txTypes : \App\Models\Payment::STATUSES as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <select wire:model.live="currency" class="px-3 py-2 rounded-xl border border-slate-200 text-sm outline-none">
                    <option value="all">All currencies</option>
                    @foreach ($currencies as $c) <option value="{{ $c }}">{{ $c }}</option> @endforeach
                </select>
                <input type="date" wire:model.live="from" class="px-3 py-2 rounded-xl border border-slate-200 text-sm outline-none">
                <input type="date" wire:model.live="to" class="px-3 py-2 rounded-xl border border-slate-200 text-sm outline-none">
                <button wire:click="exportCsv" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl border border-slate-200 bg-white text-slate-700 text-sm font-bold hover:border-slate-300 transition-colors">
                    <i data-lucide="download" class="w-4 h-4"></i> CSV
                </button>
            </div>
        </div>

        {{-- PAYMENTS TAB --}}
        @if ($tab === 'payments')
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                @if ($payments->isEmpty())
                    <p class="px-6 py-12 text-sm text-slate-400 text-center">No payments yet. Record one when an airline pays a claim.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-left text-[11px] uppercase tracking-wider text-slate-400 border-b border-slate-100">
                                    <th class="px-6 py-3 font-bold">Claim / passenger</th>
                                    <th class="px-4 py-3 font-bold text-right">Gross</th>
                                    <th class="px-4 py-3 font-bold text-right">Fee</th>
                                    <th class="px-4 py-3 font-bold text-right">Net payout</th>
                                    <th class="px-4 py-3 font-bold">Status</th>
                                    <th class="px-4 py-3 font-bold">Date</th>
                                    <th class="px-4 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                @foreach ($payments as $payment)
                                    <tr class="hover:bg-slate-50/60 transition-colors">
                                        <td class="px-6 py-3.5">
                                            <a href="{{ route('admin.flight-claims.claims.show', $payment->claim_id) }}" wire:navigate class="font-bold text-primary-600 hover:underline">#{{ $payment->claim?->number }}</a>
                                            <span class="block text-[11px] text-slate-400">{{ $payment->user?->name }} · {{ $payment->airline }}</span>
                                        </td>
                                        <td class="px-4 py-3.5 text-right font-bold text-slate-800">{{ $payment->money($payment->gross_amount) }}</td>
                                        <td class="px-4 py-3.5 text-right text-slate-500">{{ $payment->money($payment->fee_amount) }} <span class="text-[10px] text-slate-400">({{ rtrim(rtrim(number_format($payment->fee_percent, 2), '0'), '.') }}%)</span></td>
                                        <td class="px-4 py-3.5 text-right font-bold text-emerald-700">{{ $payment->money($payment->net_amount) }}</td>
                                        <td class="px-4 py-3.5">
                                            <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-black ring-1 {{ $payment->badgeClasses() }}">{{ strtoupper($payment->statusLabel()) }}</span>
                                        </td>
                                        <td class="px-4 py-3.5 text-[12px] text-slate-500 whitespace-nowrap">{{ $payment->payment_date->format('d M Y') }}</td>
                                        <td class="px-4 py-3.5 text-right">
                                            <button wire:click="open({{ $payment->id }})" class="text-[11px] font-bold text-primary-600 hover:underline">
                                                <span wire:loading.remove wire:target="open({{ $payment->id }})">Open</span>
                                                <span wire:loading wire:target="open({{ $payment->id }})">…</span>
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="px-6 py-3 border-t border-slate-100">{{ $payments->links() }}</div>
                @endif
            </div>
        @endif

        {{-- TRANSACTIONS TAB --}}
        @if ($tab === 'transactions' && $transactions)
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                @if ($transactions->isEmpty())
                    <p class="px-6 py-12 text-sm text-slate-400 text-center">No transactions match these filters.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-left text-[11px] uppercase tracking-wider text-slate-400 border-b border-slate-100">
                                    <th class="px-6 py-3 font-bold">When</th>
                                    <th class="px-4 py-3 font-bold">Type</th>
                                    <th class="px-4 py-3 font-bold">Claim / passenger</th>
                                    <th class="px-4 py-3 font-bold text-right">Amount</th>
                                    <th class="px-4 py-3 font-bold">Reference</th>
                                    <th class="px-4 py-3 font-bold">By</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                @foreach ($transactions as $tx)
                                    <tr class="hover:bg-slate-50/60 transition-colors">
                                        <td class="px-6 py-3 text-[12px] text-slate-500 whitespace-nowrap">{{ $tx->created_at->format('d M Y H:i') }}</td>
                                        <td class="px-4 py-3"><span class="text-[11px] font-bold text-slate-700">{{ $tx->typeLabel() }}</span>
                                            @if ($tx->type === 'conversion' && $tx->meta)
                                                <span class="block text-[10px] text-slate-400 font-mono">{{ $tx->meta['from'] ?? '' }}→{{ $tx->meta['to'] ?? '' }} @ {{ $tx->meta['rate'] ?? '' }}</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-[12px] text-slate-600">#{{ $tx->payment?->claim?->number }} · {{ $tx->payment?->user?->name }}</td>
                                        <td class="px-4 py-3 text-right font-bold {{ (float) $tx->amount < 0 ? 'text-rose-600' : 'text-slate-800' }}">
                                            {{ $tx->amount !== null ? trim(($tx->currency ?? '') . ' ' . number_format((float) $tx->amount, 2)) : '-' }}
                                        </td>
                                        <td class="px-4 py-3 text-[11px] font-mono text-slate-400">{{ $tx->reference ?: '-' }}</td>
                                        <td class="px-4 py-3 text-[12px] text-slate-500">{{ $tx->actor?->name ?? 'system' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="px-6 py-3 border-t border-slate-100">{{ $transactions->links() }}</div>
                @endif
            </div>
        @endif
    </div>

    {{-- Record payment modal --}}
    @if ($showRecordModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" wire:click="$set('showRecordModal', false)"></div>
            <div class="relative bg-white w-full max-w-lg rounded-2xl shadow-2xl max-h-[90vh] overflow-y-auto p-6">
                <div class="flex items-center justify-between mb-1">
                    <h2 class="font-bold text-slate-900">Record airline payment</h2>
                    <button wire:click="$set('showRecordModal', false)" class="p-2 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100"><i data-lucide="x" class="w-5 h-5"></i></button>
                </div>
                <p class="text-xs text-slate-400 mb-4">The fee and net payout calculate automatically; the passenger is notified when you save.</p>

                @if (!$recordClaimId)
                    <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1.5">Which claim was paid?</label>
                    <input type="search" wire:model.live.debounce.300ms="claimSearch" placeholder="Claim number, reference or customer…"
                           class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:border-primary-500 outline-none">
                    <ul class="mt-2 divide-y divide-slate-50">
                        @foreach ($claimOptions as $option)
                            <li>
                                <button wire:click="chooseClaim({{ $option->id }})" class="w-full text-left px-3 py-2.5 rounded-xl hover:bg-slate-50 transition-colors">
                                    <span class="font-bold text-slate-800 text-sm">#{{ $option->number }}</span>
                                    <span class="text-[11px] text-slate-400 ml-2">{{ $option->user?->name }} · {{ $option->airline }} {{ $option->flight_number }}</span>
                                </button>
                            </li>
                        @endforeach
                    </ul>
                    @error('recordClaimId') <p class="text-rose-500 text-[10px] font-bold mt-2">Pick the claim the airline paid.</p> @enderror
                @else
                    @php $chosen = \App\Models\Claim::find($recordClaimId); @endphp
                    <div class="flex items-center justify-between gap-2 rounded-xl bg-slate-50 border border-slate-100 px-3.5 py-2.5 mb-4">
                        <span class="text-sm font-bold text-slate-800">#{{ $chosen?->number }} <span class="text-slate-400 font-medium">{{ $chosen?->user?->name }} · {{ $chosen?->airline }}</span></span>
                        <button wire:click="$set('recordClaimId', null)" class="text-[11px] font-bold text-primary-600 hover:underline">Change</button>
                    </div>

                    <div class="grid sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1.5">Gross amount received</label>
                            <input type="number" step="0.01" min="0" wire:model.live="form.gross_amount" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:border-primary-500 outline-none">
                            @error('form.gross_amount') <span class="text-rose-500 text-[10px] font-bold">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1.5">Currency</label>
                            <select wire:model.live="form.currency" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm outline-none">
                                @foreach ($currencies as $c) <option value="{{ $c }}">{{ $c }}</option> @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1.5">Success fee %</label>
                            <input type="number" step="0.5" min="0" max="100" wire:model.live="form.fee_percent" @disabled(!$canOverride)
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:border-primary-500 outline-none disabled:bg-slate-50 disabled:text-slate-400">
                            @unless ($canOverride) <span class="text-[10px] text-slate-400">Fixed - you don't have the override permission.</span> @endunless
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1.5">Payment date</label>
                            <input type="date" wire:model="form.payment_date" max="{{ now()->toDateString() }}" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm outline-none">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1.5">Airline payment reference</label>
                            <input type="text" wire:model="form.reference" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:border-primary-500 outline-none">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1.5">Notes</label>
                            <textarea wire:model="form.notes" rows="2" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:border-primary-500 outline-none"></textarea>
                        </div>
                    </div>

                    @php
                        $g = (float) ($form['gross_amount'] ?? 0);
                        $p = (float) ($form['fee_percent'] ?? 25);
                        $f = round($g * $p / 100, 2);
                    @endphp
                    <div class="grid grid-cols-3 gap-2 mt-4">
                        <div class="rounded-xl bg-slate-50 border border-slate-100 px-3 py-2 text-center">
                            <p class="text-[10px] uppercase font-black text-slate-400">Gross</p>
                            <p class="text-sm font-bold text-slate-800">{{ $form['currency'] ?? '' }} {{ number_format($g, 2) }}</p>
                        </div>
                        <div class="rounded-xl bg-slate-50 border border-slate-100 px-3 py-2 text-center">
                            <p class="text-[10px] uppercase font-black text-slate-400">Fee {{ rtrim(rtrim(number_format($p, 2), '0'), '.') }}%</p>
                            <p class="text-sm font-bold text-slate-800">{{ $form['currency'] ?? '' }} {{ number_format($f, 2) }}</p>
                        </div>
                        <div class="rounded-xl bg-emerald-50 border border-emerald-100 px-3 py-2 text-center">
                            <p class="text-[10px] uppercase font-black text-emerald-600">Net payout</p>
                            <p class="text-sm font-bold text-emerald-800">{{ $form['currency'] ?? '' }} {{ number_format($g - $f, 2) }}</p>
                        </div>
                    </div>

                    <button wire:click="saveRecord" wire:loading.attr="disabled" class="mt-4 w-full bg-slate-900 hover:bg-slate-800 text-white text-sm font-bold px-5 py-3 rounded-xl transition-colors disabled:opacity-60">
                        <span wire:loading.remove wire:target="saveRecord">Record payment</span>
                        <span wire:loading wire:target="saveRecord">Recording…</span>
                    </button>
                @endif
            </div>
        </div>
    @endif

    {{-- Payment detail --}}
    @if ($detail)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" wire:click="$set('paymentId', null)"></div>
            <div class="relative bg-white w-full max-w-3xl rounded-2xl shadow-2xl max-h-[92vh] overflow-y-auto p-6">
                <div class="flex items-start justify-between gap-3 mb-4">
                    <div>
                        <h2 class="font-bold text-slate-900">Payment · claim #{{ $detail->claim?->number }}</h2>
                        <p class="text-[12px] text-slate-400">{{ $detail->user?->name }} · {{ $detail->airline }} · received {{ $detail->payment_date->format('d M Y') }}@if ($detail->reference) · ref {{ $detail->reference }}@endif</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="inline-flex px-2.5 py-1 rounded-full text-[10px] font-black ring-1 {{ $detail->badgeClasses() }}">{{ strtoupper($detail->statusLabel()) }}</span>
                        <button wire:click="$set('paymentId', null)" class="p-2 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100"><i data-lucide="x" class="w-5 h-5"></i></button>
                    </div>
                </div>

                {{-- The split --}}
                <div class="grid grid-cols-3 gap-2 mb-5">
                    <div class="rounded-xl bg-slate-50 border border-slate-100 px-3 py-2.5 text-center">
                        <p class="text-[10px] uppercase font-black text-slate-400">Gross compensation</p>
                        <p class="text-lg font-bold text-slate-900">{{ $detail->money($detail->gross_amount) }}</p>
                    </div>
                    <div class="rounded-xl bg-slate-50 border border-slate-100 px-3 py-2.5 text-center">
                        <p class="text-[10px] uppercase font-black text-slate-400">{{ rtrim(rtrim(number_format($detail->fee_percent, 2), '0'), '.') }}% success fee</p>
                        <p class="text-lg font-bold text-slate-900">{{ $detail->money($detail->fee_amount) }}</p>
                    </div>
                    <div class="rounded-xl bg-emerald-50 border border-emerald-100 px-3 py-2.5 text-center">
                        <p class="text-[10px] uppercase font-black text-emerald-600">Net payout</p>
                        <p class="text-lg font-bold text-emerald-800">{{ $detail->money($detail->net_amount) }}</p>
                    </div>
                </div>

                {{-- Fee override --}}
                @if ($canOverride && !in_array($detail->status, ['paid', 'refunded', 'cancelled']))
                    <div class="flex items-end gap-2 mb-5" x-data="{ open: false }">
                        <button @click="open = !open" class="text-[11px] font-bold text-slate-500 hover:text-slate-800 underline underline-offset-2">Override fee…</button>
                        <div x-show="open" x-cloak class="flex items-end gap-2 flex-1">
                            <div>
                                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">New fee %</label>
                                <input type="number" step="0.5" min="0" max="100" wire:model="feeOverride" class="w-24 px-3 py-2 rounded-lg border border-slate-200 text-sm outline-none">
                            </div>
                            <div class="flex-1">
                                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Reason (audited)</label>
                                <input type="text" wire:model="feeReason" class="w-full px-3 py-2 rounded-lg border border-slate-200 text-sm outline-none">
                            </div>
                            <button wire:click="applyFeeOverride" wire:loading.attr="disabled" class="px-4 py-2 rounded-lg bg-slate-900 text-white text-xs font-bold disabled:opacity-60">Apply</button>
                        </div>
                        @error('feeOverride') <span class="text-rose-500 text-[10px] font-bold">{{ $message }}</span> @enderror
                    </div>
                @endif

                {{-- Payouts --}}
                <h3 class="text-[11px] uppercase tracking-wider font-black text-slate-400 mb-2">Payouts</h3>
                @forelse ($detail->payouts as $payout)
                    <div class="rounded-xl border border-slate-200 p-3.5 mb-2">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-black ring-1 {{ $payout->badgeClasses() }}">{{ strtoupper($payout->status) }}</span>
                            <span class="text-sm font-bold text-slate-800">{{ $payout->money() }}</span>
                            <span class="text-[11px] text-slate-400">{{ $payout->method === 'wise' ? 'Wise' : 'Manual' }} · ref {{ $payout->transfer_reference }}</span>
                            @if ($payout->exchange_rate && $payout->currency !== $payout->source_currency)
                                <span class="text-[10px] font-mono text-slate-400">{{ $payout->source_currency }}→{{ $payout->currency }} @ {{ rtrim(rtrim(number_format((float) $payout->exchange_rate, 6), '0'), '.') }}</span>
                            @endif
                            @if ($payout->wise_transfer_id)
                                <span class="text-[10px] font-mono text-slate-400">transfer {{ $payout->wise_transfer_id }} ({{ $payout->transfer_status }})</span>
                            @endif
                            <span class="ml-auto flex items-center gap-2">
                                @if ($payout->status === 'draft' && $canSend && $wiseReady)
                                    <button @click="$dispatch('admin-confirm', { title: 'Send payout', message: 'Send {{ $payout->money() }} to {{ $payout->recipient_name }} via Wise now?', confirmLabel: 'Send payout', method: 'sendPayout', params: [{{ $payout->id }}] })"
                                            class="text-[11px] font-bold text-emerald-600 hover:underline">Send</button>
                                @endif
                                @if ($payout->isRetryable() && $canSend)
                                    <button wire:click="retryPayout({{ $payout->id }})" class="text-[11px] font-bold text-amber-600 hover:underline">Retry</button>
                                @endif
                                @if ($payout->wise_transfer_id && !in_array($payout->status, ['completed', 'cancelled']))
                                    <button wire:click="refreshPayout({{ $payout->id }})" class="text-[11px] font-bold text-primary-600 hover:underline">Refresh</button>
                                @endif
                                @if ($payout->isCancellable() && $canManage)
                                    <button wire:click="cancelPayout({{ $payout->id }})" class="text-[11px] font-bold text-rose-600 hover:underline">Cancel</button>
                                @endif
                            </span>
                        </div>
                        @if ($payout->error_message)
                            <p class="text-[11px] text-rose-600 mt-2">{{ $payout->error_message }}</p>
                        @endif
                    </div>
                @empty
                    <p class="text-xs text-slate-400 mb-2">No payout yet.</p>
                @endforelse

                @if ($canManage && in_array($detail->status, ['received', 'ready_for_payout', 'failed']))
                    @if ($defaultAccount)
                        <div class="rounded-xl bg-slate-50 border border-slate-100 p-3.5 mb-2">
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1.5">Destination - set by customer</label>
                            <div class="flex flex-wrap items-center gap-2">
                                <div class="flex flex-1 min-w-[220px] items-center gap-2 px-3 py-2 rounded-lg border border-slate-200 bg-white text-sm">
                                    <svg class="w-4 h-4 text-emerald-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    <span class="font-bold text-slate-800">{{ $defaultAccount->currency }} {{ $defaultAccount->masked }}</span>
                                    <span class="text-slate-400 truncate">{{ $defaultAccount->account_holder_name }}</span>
                                </div>
                                @if ($canSend)
                                    <button @click="$dispatch('admin-confirm', { title: 'Send payout via Wise', message: 'Send {{ $detail->money($detail->net_amount) }} to {{ $defaultAccount->currency }} {{ $defaultAccount->masked }} ({{ addslashes($defaultAccount->account_holder_name) }})?{{ $defaultAccount->currency !== $detail->currency ? ' The amount converts to ' . $defaultAccount->currency . ' at the live Wise rate.' : '' }} The transfer is created and queued immediately.', confirmLabel: 'Send payout', method: 'sendPayoutNow', params: [] })"
                                            wire:loading.attr="disabled" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-slate-900 text-white text-xs font-bold whitespace-nowrap disabled:opacity-60">
                                        <svg class="w-3.5 h-3.5 text-[#9fe870] shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M4.7168 4.2004l2.4463 4.2715L0 15.5289h11.2422l1.2119-2.7558H6.4209l4.0117-3.9316-1.8076-3.1553h8.4981L9.8296 21.5996h3.0196L24 4.2004Z"/></svg>
                                        <span wire:loading.remove wire:target="sendPayoutNow">Send Wise payout</span>
                                        <span wire:loading wire:target="sendPayoutNow">Sending…</span>
                                    </button>
                                @else
                                    <button wire:click="draftPayout" wire:loading.attr="disabled" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-slate-900 text-white text-xs font-bold whitespace-nowrap disabled:opacity-60">
                                        <svg class="w-3.5 h-3.5 text-[#9fe870] shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M4.7168 4.2004l2.4463 4.2715L0 15.5289h11.2422l1.2119-2.7558H6.4209l4.0117-3.9316-1.8076-3.1553h8.4981L9.8296 21.5996h3.0196L24 4.2004Z"/></svg>
                                        <span wire:loading.remove wire:target="draftPayout">Prepare Wise payout</span>
                                        <span wire:loading wire:target="draftPayout">Preparing…</span>
                                    </button>
                                @endif
                            </div>
                            <span class="block text-[10px] text-slate-400 mt-1.5">The customer's payout account - only they can change it, from their claim page.</span>
                        </div>
                    @else
                        <div class="rounded-xl bg-rose-50 border border-rose-200 p-3.5 mb-2">
                            <div class="flex flex-wrap items-center gap-3">
                                <span class="flex h-7 w-7 items-center justify-center rounded-full bg-rose-100 text-rose-600 font-black shrink-0">!</span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-xs font-bold text-rose-700">No bank details yet</p>
                                    <p class="text-[11px] text-rose-600">{{ $detail->user?->name }} has not added a payout account. Ask them to add one - it becomes the destination automatically.</p>
                                </div>
                                <button wire:click="requestBankDetails" wire:loading.attr="disabled" class="px-4 py-2 rounded-lg bg-slate-900 text-white text-xs font-bold disabled:opacity-60">
                                    <span wire:loading.remove wire:target="requestBankDetails">Request bank details</span>
                                    <span wire:loading wire:target="requestBankDetails">Sending…</span>
                                </button>
                            </div>
                            <div class="flex flex-wrap items-end gap-2 mt-3 pt-3 border-t border-rose-100" x-data="{ open: false }">
                                <button @click="open = !open" class="text-[11px] font-bold text-slate-500 hover:text-slate-800 underline underline-offset-2">Or ask via Wise email instead…</button>
                                <div x-show="open" x-cloak class="flex items-end gap-2">
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Currency</label>
                                        <select wire:model="payoutCurrency" class="px-3 py-2 rounded-lg border border-slate-200 text-sm outline-none">
                                            @foreach ($currencies as $c) <option value="{{ $c }}">{{ $c }}</option> @endforeach
                                        </select>
                                    </div>
                                    @if ($canSend)
                                        <button @click="$dispatch('admin-confirm', { title: 'Send via Wise email request', message: 'Wise will email {{ $detail->user?->email }} for their bank details, then pay {{ $detail->money($detail->net_amount) }}. The transfer waits until they reply. Send now?', confirmLabel: 'Send', method: 'sendPayoutNow', params: [] })"
                                                wire:loading.attr="disabled" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-slate-300 text-slate-700 text-xs font-bold whitespace-nowrap disabled:opacity-60">
                                            <svg class="w-3.5 h-3.5 shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M4.7168 4.2004l2.4463 4.2715L0 15.5289h11.2422l1.2119-2.7558H6.4209l4.0117-3.9316-1.8076-3.1553h8.4981L9.8296 21.5996h3.0196L24 4.2004Z"/></svg>
                                            <span wire:loading.remove wire:target="sendPayoutNow">Send via Wise email</span>
                                            <span wire:loading wire:target="sendPayoutNow">Sending…</span>
                                        </button>
                                    @else
                                        <button wire:click="draftPayout" wire:loading.attr="disabled" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-slate-300 text-slate-700 text-xs font-bold whitespace-nowrap disabled:opacity-60">
                                            <svg class="w-3.5 h-3.5 shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M4.7168 4.2004l2.4463 4.2715L0 15.5289h11.2422l1.2119-2.7558H6.4209l4.0117-3.9316-1.8076-3.1553h8.4981L9.8296 21.5996h3.0196L24 4.2004Z"/></svg>
                                            <span wire:loading.remove wire:target="draftPayout">Prepare via Wise email</span>
                                            <span wire:loading wire:target="draftPayout">Preparing…</span>
                                        </button>
                                    @endif
                                    <span class="text-[10px] text-slate-400 pb-2">Wise emails {{ $detail->user?->email }} for their details - slower, waits on their reply.</span>
                                </div>
                            </div>
                        </div>
                    @endif

                    <div class="flex flex-wrap items-end gap-2 rounded-xl border border-slate-200 p-3.5" x-data="{ open: false }">
                        <button @click="open = !open" class="text-[11px] font-bold text-slate-500 hover:text-slate-800 underline underline-offset-2">Record a manual payout instead…</button>
                        <div x-show="open" x-cloak class="flex flex-wrap items-end gap-2 w-full mt-1">
                            <div>
                                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Amount paid</label>
                                <input type="number" step="0.01" wire:model="manual.amount" class="w-28 px-3 py-2 rounded-lg border border-slate-200 text-sm outline-none">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Currency</label>
                                <select wire:model="manual.currency" class="px-3 py-2 rounded-lg border border-slate-200 text-sm outline-none">
                                    @foreach ($currencies as $c) <option value="{{ $c }}">{{ $c }}</option> @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">FX rate (if converted)</label>
                                <input type="number" step="0.000001" wire:model="manual.exchange_rate" class="w-28 px-3 py-2 rounded-lg border border-slate-200 text-sm outline-none">
                            </div>
                            <div class="flex-1 min-w-[140px]">
                                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Reference</label>
                                <input type="text" wire:model="manual.reference" class="w-full px-3 py-2 rounded-lg border border-slate-200 text-sm outline-none">
                            </div>
                            <button wire:click="recordManualPayout" wire:loading.attr="disabled" class="px-4 py-2 rounded-lg bg-emerald-600 text-white text-xs font-bold disabled:opacity-60">Mark paid</button>
                            @error('manual.amount') <span class="text-rose-500 text-[10px] font-bold w-full">{{ $message }}</span> @enderror
                        </div>
                    </div>
                @endif

                @if ($canManage && $detail->status === 'paid')
                    <button @click="$dispatch('admin-confirm', { title: 'Refund payment', message: 'Mark this payment refunded? The customer is notified and the ledger records it. This cannot be undone.', confirmLabel: 'Mark refunded', danger: true, method: 'refund', params: [] })"
                            class="text-[11px] font-bold text-rose-600 hover:underline">Mark refunded…</button>
                @endif

                {{-- Ledger + audit --}}
                <div class="grid md:grid-cols-2 gap-4 mt-5">
                    <div>
                        <h3 class="text-[11px] uppercase tracking-wider font-black text-slate-400 mb-2">Transaction history</h3>
                        <ul class="space-y-1.5 max-h-64 overflow-y-auto pr-1">
                            @foreach ($detail->transactions as $tx)
                                <li class="rounded-lg border border-slate-100 bg-white px-3 py-2">
                                    <div class="flex items-baseline gap-2">
                                        <span class="text-[12px] font-bold text-slate-800">{{ $tx->typeLabel() }}</span>
                                        <span class="text-[10px] font-mono text-slate-400">{{ $tx->created_at->format('d M · H:i') }}</span>
                                        @if ($tx->amount !== null)
                                            <span class="ml-auto shrink-0 font-mono text-[12px] font-bold {{ (float) $tx->amount < 0 ? 'text-rose-600' : 'text-slate-800' }}">
                                                {{ $tx->currency }} {{ number_format((float) $tx->amount, 2) }}
                                            </span>
                                        @endif
                                    </div>
                                    @if ($tx->notes || $tx->reference)
                                        <p class="text-[11px] text-slate-400 mt-0.5 leading-snug">
                                            {{ $tx->notes }}@if ($tx->notes && $tx->reference) · @endif
                                            @if ($tx->reference)<span class="font-mono">{{ $tx->reference }}</span>@endif
                                        </p>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                    <div>
                        <h3 class="text-[11px] uppercase tracking-wider font-black text-slate-400 mb-2">Audit log <span class="normal-case font-medium text-slate-300">immutable</span></h3>
                        <ul class="space-y-1.5 max-h-64 overflow-y-auto pr-1">
                            @foreach ($detail->logs as $log)
                                <li class="rounded-lg border border-slate-100 bg-white px-3 py-2">
                                    <div class="flex items-baseline gap-2">
                                        <span class="text-[12px] font-bold text-slate-800">{{ ucfirst(str_replace('_', ' ', $log->action)) }}</span>
                                        <span class="text-[10px] font-mono text-slate-400">{{ $log->created_at->format('d M · H:i') }}</span>
                                        <span class="ml-auto shrink-0 text-[11px] text-slate-400" @if ($log->ip) title="{{ $log->ip }}" @endif>{{ $log->actor?->name ?? 'system' }}</span>
                                    </div>
                                    @php $auditOld = $log->old_values ?? []; @endphp
                                    @if ($log->new_values)
                                        <dl class="mt-1 space-y-0.5">
                                            @foreach ($log->new_values as $field => $value)
                                                <div class="flex gap-1.5 text-[11px] leading-snug">
                                                    <dt class="shrink-0 text-slate-400">{{ str_replace('_', ' ', $field) }}</dt>
                                                    <dd class="min-w-0 break-words font-mono text-slate-600">
                                                        @if (array_key_exists($field, $auditOld))<span class="text-slate-300 line-through">{{ is_scalar($auditOld[$field]) ? $auditOld[$field] : json_encode($auditOld[$field]) }}</span> → @endif{{ $value === null ? '-' : (is_scalar($value) ? $value : json_encode($value)) }}
                                                    </dd>
                                                </div>
                                            @endforeach
                                        </dl>
                                    @endif
                                    @if ($log->notes)
                                        <p class="text-[11px] text-slate-400 mt-1 leading-snug">{{ $log->notes }}</p>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <x-admin.confirm />
</div>
