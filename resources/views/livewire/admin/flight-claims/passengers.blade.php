<div class="h-full overflow-y-auto bg-slate-50/50">
    <div class="max-w-[1320px] mx-auto p-6 pb-24">
        <x-flash />

        <div class="mb-6">
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Passengers</h1>
            <p class="text-sm text-slate-500 mt-1">Everyone on a claim or a monitored trip - their claims, signatures and documents in one place.</p>
        </div>

        {{-- Counters double as filters: the number you care about is the button you press. --}}
        <div class="grid sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
            @foreach ([
                ['all', 'People', $stats['people'], 'text-slate-900'],
                ['pending', 'Awaiting signature', $stats['pending'], 'text-amber-600'],
                ['stuck', 'No email on file', $stats['stuck'], $stats['stuck'] ? 'text-rose-600' : 'text-slate-900'],
                ['minors', 'Minors', $stats['minors'], 'text-violet-600'],
            ] as [$key, $label, $value, $cls])
                <button wire:click="setFilter('{{ $key }}')"
                        class="bg-white rounded-2xl border shadow-sm p-5 text-left transition-all hover:shadow {{ $filter === $key ? 'border-slate-900 ring-1 ring-slate-900' : 'border-slate-200 hover:border-slate-300' }}">
                    <p class="text-[11px] uppercase tracking-wider font-bold text-slate-400">{{ $label }}</p>
                    <p class="text-2xl font-bold mt-2 {{ $cls }}">{{ $value }}</p>
                </button>
            @endforeach
        </div>

        <div class="flex items-center justify-between gap-3 flex-wrap mb-4">
            <div class="inline-flex items-center gap-1 bg-white rounded-xl border border-slate-200 shadow-sm p-1 overflow-x-auto">
                @foreach ($filters as $key => $label)
                    <button wire:click="setFilter('{{ $key }}')"
                            class="px-3.5 py-2 rounded-lg text-sm font-bold whitespace-nowrap transition-all {{ $filter === $key ? 'bg-slate-900 text-white shadow' : 'text-slate-500 hover:text-slate-800' }}">
                        {{ $label }}
                    </button>
                @endforeach
            </div>
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Name, email, claim, flight…"
                   class="w-72 px-3.5 py-2 rounded-xl border border-slate-200 text-sm focus:border-primary-500 outline-none">
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            @if ($people->isEmpty())
                <p class="px-6 py-12 text-sm text-slate-400 text-center">No passengers match this view.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-[11px] uppercase tracking-wider text-slate-400 border-b border-slate-100">
                                <th class="px-4 py-3 font-bold">Passenger</th>
                                <th class="px-4 py-3 font-bold">Contact</th>
                                <th class="px-4 py-3 font-bold text-center">Claims</th>
                                <th class="px-4 py-3 font-bold text-center">Trips</th>
                                <th class="px-4 py-3 font-bold">Signatures</th>
                                <th class="px-4 py-3 font-bold">Last activity</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            @foreach ($people as $person)
                                <tr class="hover:bg-slate-50/70 transition-colors">
                                    <td class="px-4 py-3.5">
                                        <div class="flex items-center gap-2">
                                            <span class="font-bold text-slate-800">{{ $person->name }}</span>
                                            @if ($person->is('minor'))
                                                <span class="text-[9px] font-black bg-violet-100 text-violet-700 px-1.5 py-0.5 rounded-full">MINOR</span>
                                            @elseif ($person->is('guardian'))
                                                <span class="text-[9px] font-black bg-sky-100 text-sky-700 px-1.5 py-0.5 rounded-full">GUARDIAN</span>
                                            @endif
                                        </div>
                                        @if ($person->guardian)
                                            <p class="text-[11px] text-slate-400">signed for by {{ $person->guardian }}</p>
                                        @elseif ($person->signsFor)
                                            <p class="text-[11px] text-slate-400">signs for {{ implode(', ', $person->signsFor) }}</p>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3.5">
                                        @if ($person->email())
                                            <span class="text-slate-600">{{ $person->email() }}</span>
                                        @elseif ($person->hasPendingSignature())
                                            <span class="text-[11px] font-bold text-rose-600">No email - cannot be chased</span>
                                        @else
                                            <span class="text-slate-300">-</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3.5 text-center font-bold text-slate-700">{{ $person->claims->count() ?: '-' }}</td>
                                    <td class="px-4 py-3.5 text-center text-slate-500">{{ $person->trips->count() ?: '-' }}</td>
                                    <td class="px-4 py-3.5">
                                        @php $pending = $person->pendingSignatures()->count(); $signed = $person->signers->where('status', 'signed')->count(); @endphp
                                        @if ($pending)
                                            <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-black bg-amber-100 text-amber-700">{{ $pending }} PENDING</span>
                                        @elseif ($signed)
                                            <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-black bg-emerald-100 text-emerald-700">SIGNED</span>
                                        @else
                                            <span class="text-slate-300">-</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3.5 text-[12px] text-slate-500 whitespace-nowrap">{{ $person->lastActivity?->diffForHumans() ?? '-' }}</td>
                                    <td class="px-4 py-3.5 text-right">
                                        <button wire:click="open(@js($person->key))" class="text-[11px] font-bold text-primary-600 hover:underline">Open</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="px-4 py-3 border-t border-slate-100">{{ $people->links() }}</div>
            @endif
        </div>
    </div>

    {{-- Passenger drawer --}}
    @if ($selected)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" wire:click="close"></div>
            <div class="relative bg-white w-full max-w-2xl rounded-2xl shadow-2xl max-h-[92vh] overflow-y-auto p-6">
                <div class="flex items-start justify-between gap-3 mb-5">
                    <div>
                        <h2 class="font-bold text-slate-900 text-lg">{{ $selected->name }}</h2>
                        <p class="text-[12px] text-slate-400">
                            {{ $selected->roleLabel() }}
                            @if ($selected->email()) · {{ $selected->email() }} @endif
                            @if ($selected->guardian) · signed for by {{ $selected->guardian }} @endif
                        </p>
                    </div>
                    <button wire:click="close" class="p-2 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100"><i data-lucide="x" class="w-5 h-5"></i></button>
                </div>

                {{-- Signatures: the actionable part --}}
                <h3 class="text-[11px] uppercase tracking-wider font-black text-slate-400 mb-2">Signatures</h3>
                @forelse ($selected->signers as $signer)
                    <div class="rounded-xl border border-slate-200 p-3.5 mb-2">
                        <div class="flex items-center gap-2.5 flex-wrap">
                            <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-black {{ $signer->status === 'signed' ? 'bg-emerald-100 text-emerald-700' : ($signer->status === 'declined' ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-700') }}">
                                {{ strtoupper($signer->status) }}
                            </span>
                            <a href="{{ route('admin.flight-claims.claims.show', $signer->claim_id) }}" class="text-sm font-bold text-primary-600 hover:underline">#{{ $signer->claim?->number }}</a>
                            <span class="text-[12px] text-slate-500">{{ $signer->claim?->airline }} {{ $signer->claim?->flight_number }}</span>
                            @if ($signer->role === 'guardian')
                                <span class="text-[10px] font-bold text-sky-700">for {{ $signer->signs_for }}</span>
                            @endif
                            <span class="ml-auto text-[11px] text-slate-400">
                                {{ $signer->status === 'signed' ? 'signed ' . $signer->signed_at?->format('d M Y') : ($signer->invited_at ? 'invited ' . $signer->invited_at->diffForHumans() : 'not invited yet') }}
                            </span>
                        </div>

                        @if ($signer->status !== 'signed')
                            <div class="flex items-center gap-2 mt-3 flex-wrap">
                                @if ($editingSignerId === $signer->id)
                                    <input type="email" wire:model="signerEmail" placeholder="passenger@email.com"
                                           class="flex-1 min-w-[220px] px-3 py-2 rounded-lg border border-slate-200 text-sm focus:border-primary-500 outline-none">
                                    <button wire:click="sendSignatureRequest({{ $signer->id }})" wire:loading.attr="disabled"
                                            class="px-3.5 py-2 rounded-lg bg-slate-900 text-white text-[11px] font-bold disabled:opacity-60">
                                        <span wire:loading.remove wire:target="sendSignatureRequest({{ $signer->id }})">Save &amp; send</span>
                                        <span wire:loading wire:target="sendSignatureRequest({{ $signer->id }})">Sending…</span>
                                    </button>
                                    <button wire:click="$set('editingSignerId', null)" class="text-[11px] font-bold text-slate-400 hover:text-slate-700">Cancel</button>
                                    @error('signerEmail') <span class="w-full text-rose-500 text-[10px] font-bold">{{ $message }}</span> @enderror
                                @else
                                    <span class="text-[12px] {{ $signer->email ? 'text-slate-500' : 'text-rose-600 font-bold' }}">{{ $signer->email ?: 'No email on file' }}</span>
                                    <button wire:click="editEmail({{ $signer->id }})" class="text-[11px] font-bold text-slate-400 hover:text-slate-700 underline underline-offset-2">Change</button>
                                    @if ($signer->email)
                                        <button @click="$dispatch('admin-confirm', { title: 'Send signature request', message: 'Email the signing link to {{ $signer->email }}?', confirmLabel: 'Send', method: 'sendSignatureRequest', params: [{{ $signer->id }}] })"
                                                class="ml-auto px-3 py-1.5 rounded-lg border border-slate-200 text-[11px] font-bold text-slate-700 hover:border-slate-300">
                                            {{ $signer->invited_at ? 'Resend request' : 'Send request' }}
                                        </button>
                                    @endif
                                    <button type="button" x-data
                                            @click="navigator.clipboard.writeText(@js(route('claim-signature.show', $signer->sign_token))); $dispatch('toast', { type: 'success', message: 'Signing link copied - share it however you like.' })"
                                            class="px-3 py-1.5 rounded-lg border border-slate-200 text-[11px] font-bold text-slate-700 hover:border-slate-300">Copy link</button>
                                @endif
                            </div>
                        @elseif ($signer->poa_path)
                            <a href="{{ route('admin.flight-claims.claims.document', ['claim' => $signer->claim_id, 'key' => 'poa-' . $signer->id]) }}" target="_blank"
                               class="inline-block mt-2 text-[11px] font-bold text-primary-600 hover:underline">View signed Power of Attorney</a>
                        @endif
                    </div>
                @empty
                    <p class="text-[12px] text-slate-400 mb-2">No signature roster yet - the customer has not confirmed a claim for this passenger.</p>
                @endforelse

                {{-- Claims --}}
                <h3 class="text-[11px] uppercase tracking-wider font-black text-slate-400 mt-5 mb-2">Claims ({{ $selected->claims->count() }})</h3>
                @forelse ($selected->claims as $claim)
                    <a href="{{ route('admin.flight-claims.claims.show', $claim) }}"
                       class="flex items-center gap-3 rounded-xl border border-slate-200 px-3.5 py-2.5 mb-2 hover:border-slate-300 transition-colors">
                        <span class="font-bold text-slate-800 text-sm">#{{ $claim->number }}</span>
                        <span class="text-[12px] text-slate-500">{{ $claim->airline }} {{ $claim->flight_number }} · {{ $claim->departure_airport }} → {{ $claim->arrival_airport }}</span>
                        @if ($claim->compensation_amount)
                            <span class="ml-auto font-mono text-[12px] font-bold text-slate-700">{{ $claim->compensation_currency }} {{ number_format((float) $claim->compensation_amount, 2) }}</span>
                        @endif
                        <span class="text-[10px] font-black text-slate-500 bg-slate-100 px-2 py-0.5 rounded-full shrink-0">{{ strtoupper(str_replace('_', ' ', $claim->workflow_state)) }}</span>
                    </a>
                @empty
                    <p class="text-[12px] text-slate-400">No claims yet.</p>
                @endforelse

                @if ($selected->compensation())
                    <p class="text-[11px] text-slate-400 mt-1">
                        Compensation across these claims:
                        <span class="font-bold text-slate-600">{{ collect($selected->compensation())->map(fn ($total, $currency) => $currency . ' ' . number_format($total, 2))->implode(' + ') }}</span>
                    </p>
                @endif

                {{-- Trips --}}
                @if ($selected->trips->isNotEmpty())
                    <h3 class="text-[11px] uppercase tracking-wider font-black text-slate-400 mt-5 mb-2">Monitored trips ({{ $selected->trips->count() }})</h3>
                    @foreach ($selected->trips as $trip)
                        <div class="flex items-center gap-3 rounded-xl border border-slate-200 px-3.5 py-2.5 mb-2">
                            <span class="font-bold text-slate-800 text-sm">{{ $trip->flight_number }}</span>
                            <span class="text-[12px] text-slate-500">{{ $trip->departure_airport }} → {{ $trip->arrival_airport }} · {{ $trip->departure_date?->format('d M Y') }}</span>
                            <span class="ml-auto text-[10px] font-black text-slate-500 bg-slate-100 px-2 py-0.5 rounded-full">{{ strtoupper($trip->eligibility_status ?: ($trip->monitoring_status ?: 'watching')) }}</span>
                        </div>
                    @endforeach
                @endif
            </div>
        </div>
    @endif

    <x-admin.confirm />
</div>
