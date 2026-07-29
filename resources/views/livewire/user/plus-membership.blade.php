<div class="space-y-6">
    @if (!$subscription)
        {{-- Not a member: one honest card, no billing controls to confuse them --}}
        <div class="p-6 sm:p-8 bg-white shadow-sm ring-1 ring-slate-900/5 sm:rounded-xl">
            <div class="flex items-start gap-4 flex-wrap">
                <span class="inline-flex items-center justify-center w-11 h-11 rounded-xl bg-slate-900 text-amber-400 text-lg shrink-0">★</span>
                <div class="min-w-0 flex-1">
                    <h3 class="text-lg font-bold text-slate-900">Unjamm Plus</h3>
                    <p class="text-sm text-slate-500 mt-1">
                        @if ($plusEnabled)
                            You're on the free plan. Plus adds priority filing, family claims and automatic filing.
                        @else
                            Every feature is included free right now - no membership needed.
                        @endif
                    </p>
                </div>
                @if ($plusEnabled)
                    <a href="{{ url('/flight-disputes/plus') }}"
                       class="shrink-0 bg-slate-900 hover:bg-slate-800 text-white text-sm font-bold px-5 py-2.5 rounded-xl transition-colors">See Unjamm Plus</a>
                @endif
            </div>
        </div>
    @else
        {{-- ===================== MEMBERSHIP ===================== --}}
        <div class="bg-white shadow-sm ring-1 ring-slate-900/5 sm:rounded-xl overflow-hidden">
            {{-- Identity + one status, never two --}}
            <div class="flex items-center gap-3.5 px-5 sm:px-6 py-5">
                <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-slate-900 text-amber-400 shrink-0">★</span>
                <div class="min-w-0 flex-1">
                    <h3 class="font-bold text-slate-900 leading-tight">{{ $subscription->plan?->name ?? 'Unjamm Plus' }}</h3>
                    <p class="text-[13px] text-slate-500">{{ ucfirst($subscription->interval ?? 'month') }}ly membership</p>
                </div>
                @php
                    [$badge, $badgeCls] = match (true) {
                        $subscription->isPaused()                  => ['Paused', 'bg-amber-100 text-amber-700'],
                        (bool) $subscription->cancel_at_period_end  => ['Cancelling', 'bg-rose-100 text-rose-700'],
                        default                                    => ['Active', 'bg-emerald-100 text-emerald-700'],
                    };
                @endphp
                <span class="shrink-0 px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider {{ $badgeCls }}">{{ $badge }}</span>
            </div>

            {{-- The facts, as a strip rather than a paragraph in a grey box --}}
            <dl class="grid grid-cols-3 divide-x divide-slate-100 border-y border-slate-100 text-center">
                <div class="px-3 py-3.5">
                    <dt class="text-[10px] uppercase tracking-wider font-bold text-slate-400">
                        {{ $subscription->isPaused() ? 'Resumes' : ($subscription->cancel_at_period_end ? 'Access until' : 'Renews') }}
                    </dt>
                    <dd class="text-[13px] font-bold text-slate-800 mt-1">
                        {{ $subscription->isPaused()
                            ? ($subscription->resumes_at?->format('d M Y') ?? 'When you resume')
                            : ($subscription->current_period_end?->format('d M Y') ?? '-') }}
                    </dd>
                </div>
                <div class="px-3 py-3.5">
                    <dt class="text-[10px] uppercase tracking-wider font-bold text-slate-400">Card</dt>
                    <dd class="text-[13px] font-bold text-slate-800 mt-1">
                        {{ $card ? $card['brand'] . ' ····' . $card['last4'] : 'On file at Stripe' }}
                    </dd>
                </div>
                <div class="px-3 py-3.5">
                    <dt class="text-[10px] uppercase tracking-wider font-bold text-slate-400">Member since</dt>
                    <dd class="text-[13px] font-bold text-slate-800 mt-1">{{ $subscription->created_at->format('M Y') }}</dd>
                </div>
            </dl>

            {{-- What happens next, only when it is not the ordinary case --}}
            @if ($subscription->isPaused() || $subscription->cancel_at_period_end)
                <p class="px-5 sm:px-6 py-3 text-[13px] {{ $subscription->isPaused() ? 'text-amber-800 bg-amber-50' : 'text-rose-800 bg-rose-50' }}">
                    @if ($subscription->isPaused())
                        Billing is paused - you won't be charged{{ $subscription->resumes_at ? ' until ' . $subscription->resumes_at->format('d M Y') : ' until you resume' }}.
                    @else
                        You keep every Plus feature until {{ $subscription->current_period_end?->format('d M Y') }}, and nothing more will be charged.
                    @endif
                </p>
            @endif

            {{-- Actions: one primary, the rest quiet but legible --}}
            <div class="px-5 sm:px-6 py-4 flex items-center gap-2 flex-wrap" x-data="{ pausing: false }">
                @if ($subscription->isPaused())
                    <button wire:click="resume" wire:loading.attr="disabled"
                            class="bg-slate-900 hover:bg-slate-800 text-white text-sm font-bold px-5 py-2.5 rounded-xl transition-colors disabled:opacity-60">
                        <span wire:loading.remove wire:target="resume">Resume membership</span>
                        <span wire:loading wire:target="resume">Resuming…</span>
                    </button>
                @elseif ($subscription->cancel_at_period_end)
                    <button wire:click="resumeRenewal" wire:loading.attr="disabled"
                            class="bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold px-5 py-2.5 rounded-xl transition-colors disabled:opacity-60">
                        <span wire:loading.remove wire:target="resumeRenewal">Keep my membership</span>
                        <span wire:loading wire:target="resumeRenewal">Restoring…</span>
                    </button>
                @else
                    <button wire:click="updateCard" wire:loading.attr="disabled"
                            class="bg-slate-900 hover:bg-slate-800 text-white text-sm font-bold px-5 py-2.5 rounded-xl transition-colors disabled:opacity-60">
                        <span wire:loading.remove wire:target="updateCard">Update card</span>
                        <span wire:loading wire:target="updateCard">Opening…</span>
                    </button>
                    <button @click="pausing = !pausing"
                            class="px-4 py-2.5 rounded-xl border border-slate-200 text-slate-700 text-sm font-bold hover:border-slate-300 transition-colors">
                        Pause billing
                    </button>
                @endif

                <button wire:click="openPortal" wire:loading.attr="disabled"
                        class="px-4 py-2.5 rounded-xl border border-slate-200 text-slate-700 text-sm font-bold hover:border-slate-300 transition-colors disabled:opacity-60">
                    Billing portal
                </button>

                @unless ($subscription->isPaused() || $subscription->cancel_at_period_end)
                    <button wire:click="$set('confirmingCancel', true)"
                            class="ml-auto text-sm font-bold text-slate-500 hover:text-rose-600 px-2 py-2.5 transition-colors">
                        Cancel membership
                    </button>
                @endunless

                {{-- Pause date picker appears inline, only when asked for --}}
                <div x-show="pausing" x-cloak class="w-full flex items-center gap-2 flex-wrap border-t border-slate-100 pt-3 mt-1">
                    <span class="text-[13px] text-slate-500">Pause until</span>
                    <input type="date" wire:model="pauseUntil" min="{{ now()->addDay()->toDateString() }}"
                           class="px-3 py-2 rounded-xl border border-slate-200 text-sm outline-none focus:border-primary-500">
                    <button wire:click="pause" wire:loading.attr="disabled"
                            class="bg-slate-900 hover:bg-slate-800 text-white text-sm font-bold px-4 py-2 rounded-xl transition-colors disabled:opacity-60">
                        <span wire:loading.remove wire:target="pause">Pause</span>
                        <span wire:loading wire:target="pause">Pausing…</span>
                    </button>
                    <span class="text-[11px] text-slate-400">Leave empty to pause indefinitely - resume whenever you like.</span>
                    @error('pauseUntil') <p class="w-full text-xs font-bold text-rose-600">{{ $message }}</p> @enderror
                </div>
            </div>

        </div>

        {{-- Cancelling is the one destructive action here: it gets a real
             dialog, states exactly what happens, and offers the gentler
             option (pausing) before the red button. --}}
        @if ($confirmingCancel)
            <div class="fixed inset-0 z-[70] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" wire:click="$set('confirmingCancel', false)"></div>
                <div class="relative bg-white w-full max-w-md rounded-2xl shadow-2xl p-6">
                    <div class="flex items-start gap-3.5">
                        <span class="shrink-0 w-10 h-10 rounded-full bg-rose-50 text-rose-600 flex items-center justify-center">
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 9v4M12 17h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg>
                        </span>
                        <div class="min-w-0">
                            <h3 class="font-bold text-slate-900">Cancel Unjamm Plus?</h3>
                            <p class="text-sm text-slate-600 mt-1.5 leading-relaxed">
                                You'll keep every Plus feature until
                                <span class="font-bold text-slate-900">{{ $subscription->current_period_end?->format('d M Y') ?? 'the end of this period' }}</span>,
                                then your account moves to the free plan. No further payments will be taken.
                            </p>
                        </div>
                    </div>

                    <div class="mt-4 rounded-xl bg-slate-50 border border-slate-100 px-4 py-3">
                        <p class="text-[13px] text-slate-600">
                            Just need a break? <span class="font-bold text-slate-800">Pause billing</span> instead - your membership stays, the charges stop, and you resume whenever you like.
                        </p>
                    </div>

                    <div class="flex items-center gap-2 mt-5">
                        <button wire:click="$set('confirmingCancel', false)"
                                class="flex-1 px-4 py-2.5 rounded-xl border border-slate-200 bg-white text-slate-800 text-sm font-bold hover:border-slate-300 transition-colors">
                            Keep my membership
                        </button>
                        <button wire:click="cancel" wire:loading.attr="disabled"
                                class="flex-1 bg-rose-600 hover:bg-rose-700 text-white text-sm font-bold px-4 py-2.5 rounded-xl transition-colors disabled:opacity-60">
                            <span wire:loading.remove wire:target="cancel">Yes, cancel</span>
                            <span wire:loading wire:target="cancel">Cancelling…</span>
                        </button>
                    </div>
                </div>
            </div>
        @endif

        {{-- ===================== INVOICES ===================== --}}
        <div class="p-5 sm:p-6 bg-white shadow-sm ring-1 ring-slate-900/5 sm:rounded-xl" wire:init="loadInvoices">
            <div class="flex items-center justify-between gap-3">
                <h3 class="font-bold text-slate-900">Invoices</h3>
                <span wire:loading wire:target="loadInvoices" class="text-[12px] text-slate-400">Loading…</span>
            </div>

            @if ($showInvoices)
                @if (!count($invoices))
                    <p class="text-[13px] text-slate-400 mt-3">No invoices yet - the first one appears after your first payment.</p>
                @else
                    <ul class="divide-y divide-slate-100 mt-4">
                        @foreach ($invoices as $invoice)
                            <li class="flex items-center gap-3 py-3 flex-wrap">
                                <span class="font-mono text-[13px] font-bold text-slate-700">{{ $invoice['number'] ?: '-' }}</span>
                                <span class="text-[13px] text-slate-500">{{ $invoice['date'] }}</span>
                                <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-black uppercase {{ $invoice['status'] === 'paid' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">{{ $invoice['status'] }}</span>
                                <span class="ml-auto font-mono text-sm font-bold text-slate-800">{{ $invoice['total'] }}</span>
                                @if ($invoice['pdf'] ?? null)
                                    <a href="{{ $invoice['pdf'] }}" target="_blank"
                                       class="shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 text-[12px] font-bold text-slate-600 hover:border-slate-300 hover:text-slate-900 transition-colors">
                                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5"/><path d="M12 15V3"/></svg>
                                        PDF
                                    </a>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            @endif
        </div>
    @endif
</div>
