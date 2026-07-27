@php use App\Livewire\Admin\FlightClaims\Claims; @endphp
<div class="h-full overflow-y-auto p-6 pb-24 bg-slate-50/50">
    <x-flash />

    <div class="mb-8">
        <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Compensation Claims</h1>
        <p class="text-sm text-slate-500">Every claim across its lifecycle - evaluation, confirmation, signatures and filing.</p>
    </div>

    {{-- Filters + search --}}
    <div class="flex flex-col lg:flex-row lg:items-center gap-3 mb-4">
        <div class="inline-flex items-center gap-1 bg-white rounded-xl border border-slate-200 shadow-sm p-1 overflow-x-auto">
            @foreach ($filters as $key => $label)
                <button wire:click="setStatus('{{ $key }}')"
                        class="px-4 py-2 rounded-lg text-sm font-bold whitespace-nowrap transition-all {{ $status === $key ? 'bg-slate-900 text-white shadow' : 'text-slate-500 hover:text-slate-800' }}">
                    {{ $label }}
                    @if ($key === 'review' && $reviewCount)
                        <span class="ml-1 px-1.5 py-0.5 rounded-full text-[11px] font-black {{ $status === 'review' ? 'bg-white/20' : 'bg-rose-100 text-rose-600' }}">{{ $reviewCount }}</span>
                    @endif
                </button>
            @endforeach
        </div>
        <div class="relative lg:ml-auto lg:w-80">
            <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2"></i>
            <input type="search" wire:model.live.debounce.400ms="search" placeholder="Claim no, flight, passenger or customer…"
                   class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-slate-200 bg-white shadow-sm text-sm focus:border-primary-500 outline-none">
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-[11px] uppercase tracking-wider text-slate-400 border-b border-slate-100">
                        <th class="px-5 py-3 font-bold">Claim</th>
                        <th class="px-5 py-3 font-bold">Flight</th>
                        <th class="px-5 py-3 font-bold">Customer</th>
                        <th class="px-5 py-3 font-bold">Stage</th>
                        <th class="px-5 py-3 font-bold text-right">Total compensation</th>
                        <th class="px-5 py-3 font-bold">Submitted</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($claims as $claim)
                        @php
                            [$stageLabel, $stageCls] = Claims::stage($claim);
                            $paxCount = max(1, count($claim->passengerNames()));
                        @endphp
                        <tr class="border-b border-slate-50 hover:bg-slate-50/60 transition-colors">
                            <td class="px-5 py-3.5">
                                <div class="font-bold text-slate-800">#{{ $claim->number }}</div>
                                <div class="text-xs text-slate-400">{{ $claim->reference }}</div>
                            </td>
                            <td class="px-5 py-3.5">
                                <div class="font-bold text-slate-800">{{ $claim->flight_number ?: '-' }}</div>
                                <div class="text-xs text-slate-400">{{ $claim->departure_airport }} → {{ $claim->arrival_airport }} · {{ $claim->flight_date?->format('d M Y') }}</div>
                            </td>
                            <td class="px-5 py-3.5">
                                <div class="font-bold text-slate-800 flex items-center gap-1.5">
                                    {{ $claim->user?->name ?? $claim->passenger_name ?? '-' }}
                                    @if ($plusBadges && $claim->is_plus_member)
                                        <span class="inline-flex items-center gap-0.5 bg-slate-900 text-amber-400 text-[9px] font-black uppercase px-1.5 py-0.5 rounded-full shrink-0" title="Unjamm Plus member - priority queue">★ Plus</span>
                                    @endif
                                </div>
                                <div class="text-xs text-slate-400">{{ $paxCount }} passenger{{ $paxCount > 1 ? 's' : '' }}</div>
                            </td>
                            <td class="px-5 py-3.5">
                                <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-bold ring-1 {{ $stageCls }}">{{ $stageLabel }}</span>
                            </td>
                            <td class="px-5 py-3.5 text-right font-bold text-slate-800 whitespace-nowrap">
                                {{ $claim->compensation_amount ? $claim->compensation_currency . ' ' . number_format((float) $claim->compensation_amount * $paxCount, 2) : '-' }}
                            </td>
                            <td class="px-5 py-3.5 text-xs text-slate-400 whitespace-nowrap">{{ $claim->submitted_at?->format('d M Y') }}</td>
                            <td class="px-5 py-3.5 text-right">
                                <a href="{{ route('admin.flight-claims.claims.show', $claim) }}" wire:navigate class="text-xs font-bold text-primary-600 hover:underline">Details</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-14 text-center">
                                <div class="text-slate-300 mb-1"><i data-lucide="hand-coins" class="w-8 h-8 mx-auto"></i></div>
                                <div class="text-sm font-bold text-slate-500">No claims match</div>
                                <div class="text-xs text-slate-400">Try a different filter or search term.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($claims->hasPages())
            <div class="px-5 py-4 border-t border-slate-100">{{ $claims->links() }}</div>
        @endif
    </div>

</div>
