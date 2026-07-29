<div class="h-full overflow-y-auto p-6 pb-24 bg-slate-50/50" x-data="{ tab: @js($tab) }">
    <x-flash />

    {{-- Header --}}
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Trip Eligibility Reviews</h1>
        <p class="text-sm text-slate-500">Verdicts the engine wasn't confident enough to auto-approve, and passenger reports that need a human decision.</p>
    </div>

    {{-- Tabs --}}
    <div class="inline-flex items-center gap-1 bg-white rounded-xl border border-slate-200 shadow-sm p-1 mb-4">
        <button @click="tab = 'review'" wire:click="setTab('review')"
                :class="tab === 'review' ? 'bg-slate-900 text-white shadow' : 'text-slate-500 hover:text-slate-800'"
                class="px-5 py-2 rounded-lg text-sm font-bold transition-all">
            Review
            <span class="ml-1 px-1.5 py-0.5 rounded-full text-[11px] font-black" :class="tab === 'review' ? 'bg-white/20' : 'bg-rose-100 text-rose-600'">{{ $counts['review'] }}</span>
        </button>
        <button @click="tab = 'all'" wire:click="setTab('all')"
                :class="tab === 'all' ? 'bg-slate-900 text-white shadow' : 'text-slate-500 hover:text-slate-800'"
                class="px-5 py-2 rounded-lg text-sm font-bold transition-all">
            All
            <span class="ml-1 px-1.5 py-0.5 rounded-full text-[11px] font-black" :class="tab === 'all' ? 'bg-white/20' : 'bg-slate-100 text-slate-500'">{{ $counts['all'] }}</span>
        </button>
    </div>

    {{-- Queue --}}
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden relative">
        <div wire:loading.flex wire:target="setTab"
             class="absolute inset-0 z-10 bg-white/70 backdrop-blur-[1px] items-center justify-center">
            <svg class="w-6 h-6 animate-spin text-slate-400" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
        </div>
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-[11px] uppercase tracking-wider text-slate-400 border-b border-slate-100">
                    <th class="px-5 py-3 font-bold">Customer</th>
                    <th class="px-5 py-3 font-bold">Flight</th>
                    <th class="px-5 py-3 font-bold">Disruption</th>
                    <th class="px-5 py-3 font-bold">Engine verdict</th>
                    @if ($tab === 'all')
                        <th class="px-5 py-3 font-bold">Status</th>
                    @endif
                    <th class="px-5 py-3 font-bold">{{ $tab === 'review' ? 'Waiting since' : 'Evaluated' }}</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($trips as $trip)
                    <tr class="border-b border-slate-50 hover:bg-slate-50/60 transition-colors">
                        <td class="px-5 py-3.5">
                            <div class="font-bold text-slate-800">{{ $trip->user?->name ?? '-' }}</div>
                            <div class="text-xs text-slate-400">{{ $trip->user?->email }}</div>
                        </td>
                        <td class="px-5 py-3.5">
                            <div class="font-bold text-slate-800">{{ $trip->flight_number }}</div>
                            <div class="text-xs text-slate-400">{{ $trip->departure_airport }} → {{ $trip->arrival_airport }} · {{ $trip->departure_date?->format('d M Y') }}</div>
                        </td>
                        <td class="px-5 py-3.5">
                            <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-bold bg-violet-50 text-violet-700 ring-1 ring-violet-200">
                                {{ $trip->reported_disruption ? str_replace('_', ' ', $trip->reported_disruption) . ' (reported)' : ucfirst($trip->flight_status ?? 'disrupted') }}
                            </span>
                        </td>
                        <td class="px-5 py-3.5">
                            <div class="font-bold text-slate-800">{{ $trip->eligibility_regulation }} · {{ $trip->eligibility_article }}</div>
                            <div class="text-xs text-slate-400">{{ $trip->eligibility_confidence }}% confidence · {{ $trip->eligibility_details['evaluated_by'] ?? '-' }}</div>
                        </td>
                        @if ($tab === 'all')
                            <td class="px-5 py-3.5">
                                @php
                                    $statusStyles = [
                                        'eligible' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
                                        'review'   => 'bg-violet-50 text-violet-700 ring-violet-200',
                                        'rejected' => 'bg-slate-100 text-slate-600 ring-slate-200',
                                    ];
                                @endphp
                                <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-bold ring-1 {{ $statusStyles[$trip->eligibility_status] ?? $statusStyles['rejected'] }}">
                                    {{ ucfirst($trip->eligibility_status) }}
                                </span>
                                <div class="text-[10px] text-slate-400 font-bold mt-1">
                                    by {{ $trip->eligibility_decision_source === 'admin' ? ($trip->eligibilityDecider?->name ?? 'admin') : ($trip->eligibility_decision_source ?: 'engine') }}
                                </div>
                            </td>
                        @endif
                        <td class="px-5 py-3.5 text-xs text-slate-500">{{ $trip->eligibility_evaluated_at?->diffForHumans() }}</td>
                        <td class="px-5 py-3.5 text-right">
                            <button wire:click="open({{ $trip->id }})" class="px-4 py-2 rounded-xl text-xs font-bold transition-all {{ $trip->eligibility_status === 'review' ? 'bg-slate-900 text-white hover:bg-slate-800' : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50' }}">
                                {{ $trip->eligibility_status === 'review' ? 'Review' : 'View' }}
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $tab === 'all' ? 7 : 6 }}" class="px-5 py-14 text-center text-sm text-slate-400">
                            <i data-lucide="check-circle-2" class="w-8 h-8 mx-auto mb-2 text-emerald-400"></i>
                            {{ $tab === 'review' ? 'Nothing waiting for review - the engine is handling everything confidently.' : 'No evaluated trips yet.' }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $trips->links() }}</div>

    {{-- Review modal --}}
    @if ($showModal && $selected)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/50" wire:click="close"></div>
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
                <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-bold text-slate-800">{{ $selected->flight_number }} · {{ $selected->departure_airport }} → {{ $selected->arrival_airport }}</h2>
                        <p class="text-xs text-slate-500">{{ $selected->user?->name }} ({{ $selected->user?->email }}) · {{ $selected->departure_date?->format('d M Y') }} · {{ count($selected->passengers ?? []) }} passenger(s)</p>
                    </div>
                    <button wire:click="close" class="text-slate-400 hover:text-slate-600"><i data-lucide="x" class="w-5 h-5"></i></button>
                </div>

                <div class="p-6 space-y-5">
                    {{-- Flight facts --}}
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm">
                        <div class="rounded-xl bg-slate-50 p-3">
                            <div class="text-[10px] uppercase font-bold text-slate-400">Flight status</div>
                            <div class="font-bold text-slate-800 capitalize">{{ str_replace('_', ' ', $selected->flight_status ?? '-') }}</div>
                        </div>
                        <div class="rounded-xl bg-slate-50 p-3">
                            <div class="text-[10px] uppercase font-bold text-slate-400">Arrival delay</div>
                            <div class="font-bold text-slate-800">{{ $selected->arrival_delay_minutes !== null ? $selected->arrival_delay_minutes . ' min' : '-' }}</div>
                        </div>
                        <div class="rounded-xl bg-slate-50 p-3">
                            <div class="text-[10px] uppercase font-bold text-slate-400">Reported</div>
                            <div class="font-bold text-slate-800 capitalize">{{ $selected->reported_disruption ? str_replace('_', ' ', $selected->reported_disruption) : '-' }}</div>
                        </div>
                        <div class="rounded-xl bg-slate-50 p-3">
                            <div class="text-[10px] uppercase font-bold text-slate-400">Confidence</div>
                            <div class="font-bold text-slate-800">{{ $selected->eligibility_confidence }}%</div>
                        </div>
                    </div>

                    {{-- Engine verdict --}}
                    <div class="rounded-xl border border-violet-100 bg-violet-50/60 p-4">
                        <div class="text-[10px] uppercase font-bold text-violet-500 mb-1">Engine verdict - {{ $selected->eligibility_regulation }} · {{ $selected->eligibility_article }} · by {{ $selected->eligibility_details['evaluated_by'] ?? '-' }}</div>
                        <p class="text-sm text-slate-700">{{ $selected->eligibility_reason }}</p>
                        @if (!empty($selected->eligibility_details['auto_review']))
                            <p class="text-xs text-violet-600 font-bold mt-2">{{ $selected->eligibility_details['auto_review'] }}</p>
                        @endif
                        @php $winning = collect($selected->eligibility_details['outcomes'] ?? [])->firstWhere('regulation', $selected->eligibility_regulation); @endphp
                        @if (!empty($winning['factors']))
                            <ul class="mt-2 space-y-1">
                                @foreach ($winning['factors'] as $factor)
                                    <li class="text-xs text-slate-500 flex gap-1.5"><span class="text-violet-400">•</span>{{ $factor }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>

                    {{-- Passenger's funnel answers --}}
                    @if (!empty($selected->report_details['questions']))
                        <div class="rounded-xl border border-slate-200 p-4">
                            <div class="text-[10px] uppercase font-bold text-slate-400 mb-2">Passenger's answers</div>
                            <dl class="space-y-3">
                                @foreach ($selected->report_details['questions'] as $qa)
                                    <div>
                                        <dt class="text-xs text-slate-400">{{ $qa['question'] ?? '' }}</dt>
                                        <dd class="text-sm font-bold text-slate-800">{{ $qa['answer'] ?? '' }}</dd>
                                    </div>
                                @endforeach
                            </dl>
                        </div>
                    @endif

                    {{-- Supporting documents --}}
                    @if (!empty($selected->report_details['documents']))
                        <div class="rounded-xl border border-slate-200 p-4">
                            <div class="text-[10px] uppercase font-bold text-slate-400 mb-2">Supporting documents</div>
                            <ul class="space-y-1.5">
                                @foreach ($selected->report_details['documents'] as $index => $doc)
                                    <li>
                                        <a href="{{ route('admin.trip-reviews.document', ['trip' => $selected->id, 'index' => $index]) }}" target="_blank"
                                           class="inline-flex items-center gap-2 text-sm font-bold text-primary-600 hover:text-primary-700">
                                            <i data-lucide="paperclip" class="w-3.5 h-3.5"></i>
                                            {{ $doc['name'] ?? 'Document ' . ($index + 1) }}
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @elseif ($selected->reported_disruption)
                        <p class="text-xs text-slate-400">No supporting documents were uploaded.</p>
                    @endif

                    {{-- Recent events --}}
                    <div>
                        <div class="text-[10px] uppercase font-bold text-slate-400 mb-2">Recent events</div>
                        <ul class="space-y-1.5">
                            @foreach ($selected->events as $event)
                                <li class="text-xs text-slate-600 flex gap-2">
                                    <span class="text-slate-300 shrink-0">{{ $event->detected_at?->format('d M H:i') }}</span>
                                    {{ $event->description }}
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    {{-- Reject reason (only when a decision is still open) --}}
                    @if ($selected->eligibility_status === 'review')
                        <div>
                            <label class="block text-[11px] font-bold text-slate-400 uppercase mb-2">
                                If rejecting - reason shown to the customer <span class="text-rose-500">*</span>
                            </label>
                            <textarea wire:model.live.debounce.300ms="rejection_reason" rows="2" placeholder="e.g. The airline's records show you boarded this flight, so denied boarding compensation does not apply."
                                      class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:border-primary-500 outline-none"></textarea>
                            @error('rejection_reason')
                                <span class="text-rose-500 text-[10px] font-bold mt-1 block">{{ $message }}</span>
                            @else
                                <span class="text-slate-400 text-[10px] font-medium mt-1 block">Required to reject - the customer receives this text by email, word for word.</span>
                            @enderror
                        </div>
                    @endif
                </div>

                @if ($selected->eligibility_status === 'review')
                    <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex justify-end gap-3">
                        <button wire:click="reject({{ $selected->id }})" wire:loading.attr="disabled"
                                @disabled(mb_strlen(trim($rejection_reason ?? '')) < 10)
                                title="{{ mb_strlen(trim($rejection_reason ?? '')) < 10 ? 'Write the reason the customer will receive first' : '' }}"
                                class="px-5 py-2 bg-white text-rose-600 ring-1 ring-rose-200 hover:bg-rose-50 rounded-xl text-sm font-bold transition-all disabled:opacity-40 disabled:cursor-not-allowed">
                            Reject
                        </button>
                        <button wire:click="approve({{ $selected->id }})" wire:loading.attr="disabled" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-sm font-bold shadow-lg shadow-emerald-600/20 transition-all disabled:opacity-60">
                            Approve - customer can claim
                        </button>
                    </div>
                @else
                    <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 text-xs text-slate-500">
                        This case is already decided ({{ ucfirst($selected->eligibility_status) }})
                        @if ($selected->eligibility_decision_source === 'admin')
                            by <strong>{{ $selected->eligibilityDecider?->name ?? 'an admin' }}</strong>
                            {{ $selected->eligibility_decided_at?->diffForHumans() }}
                        @else
                            automatically by the engine ({{ $selected->eligibility_decision_source ?: 'engine' }})
                        @endif
                        - shown read-only.
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
