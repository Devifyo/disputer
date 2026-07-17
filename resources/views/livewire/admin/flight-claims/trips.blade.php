<div class="h-full overflow-y-auto p-6 pb-24 bg-slate-50/50">
    <x-flash />

    <div class="mb-8">
        <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Protected Trips</h1>
        <p class="text-sm text-slate-500">Every trip under FlightAware monitoring - upcoming, disrupted and completed. Eligibility decisions live in Trip Reviews.</p>
    </div>

    {{-- Filters + search --}}
    <div class="flex flex-col lg:flex-row lg:items-center gap-3 mb-4">
        <div class="inline-flex items-center gap-1 bg-white rounded-xl border border-slate-200 shadow-sm p-1 overflow-x-auto">
            @foreach ($filters as $key => $label)
                <button wire:click="setStatus('{{ $key }}')"
                        class="px-4 py-2 rounded-lg text-sm font-bold whitespace-nowrap transition-all {{ $status === $key ? 'bg-slate-900 text-white shadow' : 'text-slate-500 hover:text-slate-800' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>
        <div class="relative lg:ml-auto lg:w-80">
            <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2"></i>
            <input type="search" wire:model.live.debounce.400ms="search" placeholder="Flight, airport, passenger or customer…"
                   class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-slate-200 bg-white shadow-sm text-sm focus:border-primary-500 outline-none">
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-[11px] uppercase tracking-wider text-slate-400 border-b border-slate-100">
                        <th class="px-5 py-3 font-bold">Flight</th>
                        <th class="px-5 py-3 font-bold">Customer</th>
                        <th class="px-5 py-3 font-bold">Passengers</th>
                        <th class="px-5 py-3 font-bold">Flight status</th>
                        <th class="px-5 py-3 font-bold">Eligibility</th>
                        <th class="px-5 py-3 font-bold">Claim</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($trips as $trip)
                        <tr class="border-b border-slate-50 hover:bg-slate-50/60 transition-colors">
                            <td class="px-5 py-3.5">
                                <div class="font-bold text-slate-800">{{ $trip->flight_number ?: '-' }}</div>
                                <div class="text-xs text-slate-400">{{ $trip->departure_airport }} → {{ $trip->arrival_airport }} · {{ $trip->departure_date?->format('d M Y') ?? 'date unknown' }}</div>
                            </td>
                            <td class="px-5 py-3.5">
                                <div class="font-bold text-slate-800">{{ $trip->user?->name ?? '-' }}</div>
                                <div class="text-xs text-slate-400">{{ $trip->user?->email }}</div>
                            </td>
                            <td class="px-5 py-3.5 text-slate-600">
                                {{ is_array($trip->passengers) ? count($trip->passengers) : 1 }}
                            </td>
                            <td class="px-5 py-3.5">
                                @php
                                    $flight = $trip->flight_status ?: ($trip->departure_date?->isFuture() ? 'scheduled' : 'unknown');
                                    $flightCls = match ($flight) {
                                        'cancelled', 'diverted' => 'bg-rose-50 text-rose-700 ring-rose-200',
                                        'delayed'               => 'bg-amber-50 text-amber-700 ring-amber-200',
                                        'landed', 'arrived'     => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
                                        default                 => 'bg-slate-50 text-slate-600 ring-slate-200',
                                    };
                                @endphp
                                <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-bold ring-1 {{ $flightCls }}">{{ ucfirst(str_replace('_', ' ', $flight)) }}</span>
                            </td>
                            <td class="px-5 py-3.5">
                                @if ($trip->eligibility_status)
                                    @php
                                        $eligCls = match ($trip->eligibility_status) {
                                            'eligible' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
                                            'review'   => 'bg-amber-50 text-amber-700 ring-amber-200',
                                            default    => 'bg-rose-50 text-rose-700 ring-rose-200',
                                        };
                                    @endphp
                                    <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-bold ring-1 {{ $eligCls }}">
                                        {{ ['eligible' => 'Eligible', 'review' => 'In review', 'rejected' => 'Not eligible'][$trip->eligibility_status] ?? ucfirst($trip->eligibility_status) }}
                                    </span>
                                @else
                                    <span class="text-xs text-slate-300 font-bold">-</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5">
                                <span class="text-xs font-bold {{ $trip->claims_exists ? 'text-emerald-600' : 'text-slate-300' }}">{{ $trip->claims_exists ? 'Filed' : '-' }}</span>
                            </td>
                            <td class="px-5 py-3.5 text-right whitespace-nowrap">
                                @if ($trip->eligibility_status === 'review')
                                    <a href="{{ route('admin.trip-reviews.index') }}" wire:navigate class="text-xs font-bold text-amber-600 hover:underline mr-3">Review</a>
                                @endif
                                <button wire:click="open({{ $trip->id }})" class="text-xs font-bold text-primary-600 hover:underline">Details</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-14 text-center">
                                <div class="text-slate-300 mb-1"><i data-lucide="plane" class="w-8 h-8 mx-auto"></i></div>
                                <div class="text-sm font-bold text-slate-500">No trips match</div>
                                <div class="text-xs text-slate-400">Try a different filter or search term.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($trips->hasPages())
            <div class="px-5 py-4 border-t border-slate-100">{{ $trips->links() }}</div>
        @endif
    </div>

    {{-- Detail panel --}}
    @if ($selected)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" wire:click="close"></div>
            <div class="relative bg-white w-full max-w-2xl rounded-2xl shadow-2xl max-h-[90vh] overflow-y-auto">
                <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between sticky top-0 bg-white">
                    <div>
                        <h2 class="font-bold text-slate-900">{{ $selected->flight_number }} · {{ $selected->departure_airport }} → {{ $selected->arrival_airport }}</h2>
                        <p class="text-xs text-slate-400">{{ $selected->departure_date?->format('d M Y') }} · {{ $selected->user?->email }}</p>
                    </div>
                    <button wire:click="close" class="p-2 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors"><i data-lucide="x" class="w-5 h-5"></i></button>
                </div>
                <div class="p-6 space-y-5">
                    <dl class="grid sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
                        <div><dt class="text-[11px] uppercase tracking-wider font-bold text-slate-400">Passengers</dt><dd class="mt-0.5 font-medium text-slate-800">{{ is_array($selected->passengers) ? implode(', ', $selected->passengers) : ($selected->passenger_name ?: '-') }}</dd></div>
                        <div><dt class="text-[11px] uppercase tracking-wider font-bold text-slate-400">Booking reference</dt><dd class="mt-0.5 font-medium text-slate-800">{{ $selected->booking_reference ?: '-' }}</dd></div>
                        <div><dt class="text-[11px] uppercase tracking-wider font-bold text-slate-400">Monitoring</dt><dd class="mt-0.5 font-medium text-slate-800">{{ ucfirst($selected->monitoring_status ?: '-') }}</dd></div>
                        <div><dt class="text-[11px] uppercase tracking-wider font-bold text-slate-400">Flight status</dt><dd class="mt-0.5 font-medium text-slate-800">{{ $selected->flight_status_text ?: ucfirst($selected->flight_status ?: '-') }}</dd></div>
                        <div><dt class="text-[11px] uppercase tracking-wider font-bold text-slate-400">Arrival delay</dt><dd class="mt-0.5 font-medium text-slate-800">{{ $selected->arrival_delay_minutes !== null ? $selected->arrival_delay_minutes . ' min' : '-' }}</dd></div>
                        <div><dt class="text-[11px] uppercase tracking-wider font-bold text-slate-400">Verdict</dt><dd class="mt-0.5 font-medium text-slate-800">{{ $selected->eligibility_regulation ? $selected->eligibility_regulation . ' · ' . $selected->eligibility_article . ' · ' . $selected->eligibility_confidence . '%' : '-' }}</dd></div>
                    </dl>

                    @if ($selected->eligibility_reason)
                        <div class="rounded-xl bg-slate-50 p-4 text-sm text-slate-600">{{ $selected->eligibility_reason }}</div>
                    @endif

                    @if ($selected->events->isNotEmpty())
                        <div>
                            <p class="text-[11px] uppercase tracking-wider font-bold text-slate-400 mb-2">Latest monitoring events</p>
                            <ol class="space-y-1.5">
                                @foreach ($selected->events as $event)
                                    <li class="flex gap-2 text-xs text-slate-600">
                                        <span class="text-slate-300 shrink-0 w-24">{{ $event->detected_at?->format('d M H:i') }}</span>
                                        <span>{{ $event->description }}</span>
                                    </li>
                                @endforeach
                            </ol>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
