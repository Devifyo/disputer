<div class="h-full overflow-y-auto bg-slate-50/50" x-data="{ preview: null, previewName: '' }" @keydown.escape.window="preview = null">
    <div class="max-w-[1440px] mx-auto p-6 pb-24">
        <x-flash />

        {{-- Compact header: identity + stage + key numbers in one scan line --}}
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm px-6 py-4 mb-6">
            <div class="flex items-center gap-2 text-xs font-bold text-slate-400 mb-3">
                <a href="{{ route('admin.flight-claims.claims') }}" wire:navigate class="hover:text-slate-700 transition-colors">Claims</a>
                <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
                <span class="text-slate-600">#{{ $claim->number }}</span>
            </div>
            <div class="flex flex-wrap items-center gap-x-6 gap-y-3">
                <div class="min-w-0">
                    <div class="flex items-center gap-3 flex-wrap">
                        <h1 class="text-xl font-black text-slate-900 tracking-tight">{{ $claim->departure_airport }} → {{ $claim->arrival_airport }}</h1>
                        <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-bold ring-1 {{ $stageCls }}">{{ $stageLabel }}</span>
                    </div>
                    <p class="text-sm text-slate-500 mt-0.5">{{ $claim->airline }} {{ $claim->flight_number }} · {{ $claim->flight_date?->format('d M Y') }} · {{ $claim->user?->name }} ({{ $claim->user?->email }})</p>
                </div>
                <div class="flex items-center gap-6 ml-auto">
                    <div class="text-right">
                        <div class="text-[10px] uppercase tracking-wider font-bold text-slate-400">Total claim</div>
                        <div class="font-black text-slate-900">{{ $gross ? $claim->compensation_currency . ' ' . number_format($gross, 2) : '-' }}</div>
                    </div>
                    <div class="text-right">
                        <div class="text-[10px] uppercase tracking-wider font-bold text-slate-400">Customer payout</div>
                        <div class="font-black text-emerald-600">{{ $gross ? $claim->compensation_currency . ' ' . number_format($gross * (100 - $feePercent) / 100, 2) : '-' }}</div>
                    </div>
                    <div class="text-right">
                        <div class="text-[10px] uppercase tracking-wider font-bold text-slate-400">Signatures</div>
                        <div class="font-black {{ $claim->signaturesComplete() ? 'text-emerald-600' : 'text-amber-600' }}">
                            {{ $claim->signers->where('status', 'signed')->count() }}/{{ max(1, $claim->signers->count()) }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid xl:grid-cols-5 gap-6 items-start">
            {{-- WORK AREA: tabbed - compose now; correspondence and notes join after the sending flow --}}
            <div class="xl:col-span-3 min-w-0" x-data="{ tab: 'email' }">
                <div class="inline-flex items-center gap-1 bg-white rounded-xl border border-slate-200 shadow-sm p-1 mb-4">
                    <button @click="tab = 'email'"
                            :class="tab === 'email' ? 'bg-slate-900 text-white shadow' : 'text-slate-500 hover:text-slate-800'"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-bold transition-all">
                        <i data-lucide="mail" class="w-4 h-4"></i> Claim email
                    </button>
                    <button @click="tab = 'timeline'"
                            :class="tab === 'timeline' ? 'bg-slate-900 text-white shadow' : 'text-slate-500 hover:text-slate-800'"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-bold transition-all">
                        <i data-lucide="history" class="w-4 h-4"></i> Timeline
                        <span class="text-[10px] font-black px-1.5 py-0.5 rounded-full"
                              :class="tab === 'timeline' ? 'bg-white/20' : 'bg-slate-100 text-slate-500'">{{ $claim->events->count() }}</span>
                    </button>
                </div>

                <div x-show="tab === 'email'" class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between gap-3 flex-wrap">
                        <div>
                            <h2 class="font-bold text-slate-900 flex items-center gap-2">
                                <i data-lucide="mail" class="w-4.5 h-4.5 text-primary-500"></i>
                                Claim email to the airline
                            </h2>
                            <p class="text-xs text-slate-500 mt-0.5">AI drafts it from the verified facts - review, adjust, send.</p>
                        </div>
                        <button wire:click="generate" wire:loading.attr="disabled" wire:target="generate"
                                class="inline-flex items-center gap-2 bg-violet-600 hover:bg-violet-700 text-white text-sm font-bold px-4 py-2 rounded-xl transition-colors disabled:opacity-60 shrink-0">
                            <i data-lucide="sparkles" class="w-4 h-4"></i>
                            <span wire:loading.remove wire:target="generate">{{ $subject ? 'Regenerate' : 'Generate with AI' }}</span>
                            <span wire:loading wire:target="generate">Drafting…</span>
                        </button>
                    </div>

                    <div class="p-6 space-y-3" wire:loading.class="opacity-50" wire:target="generate">
                        <div class="grid sm:grid-cols-[70px_1fr] items-center gap-2">
                            <label class="text-[11px] uppercase tracking-wider font-bold text-slate-400">To</label>
                            <input type="email" wire:model="to" placeholder="Airline claims department email - set in the sending step"
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:border-primary-500 outline-none">
                        </div>
                        @error('to') <p class="text-xs font-bold text-rose-600 sm:ml-[78px]">{{ $message }}</p> @enderror

                        <div class="grid sm:grid-cols-[70px_1fr] items-center gap-2">
                            <label class="text-[11px] uppercase tracking-wider font-bold text-slate-400">Subject</label>
                            <input type="text" wire:model="subject" placeholder="Generate a draft or write the subject"
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm font-medium focus:border-primary-500 outline-none">
                        </div>
                        @error('subject') <p class="text-xs font-bold text-rose-600 sm:ml-[78px]">{{ $message }}</p> @enderror

                        <textarea wire:model="body" rows="14" placeholder="The claim letter body - click 'Generate with AI' to draft it from this claim's facts."
                                  class="w-full px-4 py-3 rounded-xl border border-slate-200 text-sm leading-relaxed focus:border-primary-500 outline-none"></textarea>
                        @error('body') <p class="text-xs font-bold text-rose-600">{{ $message }}</p> @enderror

                        {{-- Attachments --}}
                        <div class="rounded-xl bg-slate-50 border border-slate-100 p-4">
                            <div class="flex items-center justify-between gap-3 mb-2 flex-wrap">
                                <p class="text-[11px] uppercase tracking-wider font-bold text-slate-400">Attachments - tick what goes to the airline</p>
                                <span class="text-[11px] font-bold text-slate-400">{{ count($attached) }} selected</span>
                            </div>
                            <ul class="grid md:grid-cols-2 gap-x-6 gap-y-1.5">
                                @forelse ($attachments as $doc)
                                    <li class="flex items-center gap-2.5 text-sm min-w-0">
                                        <input type="checkbox" wire:model.live="attached" value="{{ $doc['key'] }}"
                                               class="w-4 h-4 rounded border-slate-300 text-slate-900 focus:ring-slate-900 shrink-0">
                                        <button type="button"
                                                @click="preview = @js(route('admin.flight-claims.claims.document', ['claim' => $claim, 'key' => $doc['key']])); previewName = @js($doc['name'])"
                                                class="text-slate-700 hover:text-primary-600 hover:underline truncate text-left">{{ $doc['name'] }}</button>
                                        @if ($doc['signed'] === true)
                                            <span class="text-[10px] font-black bg-emerald-100 text-emerald-700 px-1.5 py-0.5 rounded-full shrink-0">SIGNED</span>
                                        @elseif ($doc['signed'] === false)
                                            <span class="text-[10px] font-black bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded-full shrink-0">UNSIGNED</span>
                                        @endif
                                        @if (isset($doc['extra']))
                                            <button wire:click="removeExtra({{ $doc['extra'] }})" title="Remove this document"
                                                    class="ml-auto p-1 rounded text-slate-300 hover:text-rose-500 hover:bg-rose-50 transition-colors shrink-0">
                                                <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                            </button>
                                        @endif
                                    </li>
                                @empty
                                    <li class="text-xs text-slate-400 md:col-span-2">No documents yet - signed POAs appear once the customer confirms and signs.</li>
                                @endforelse
                            </ul>
                            <label class="mt-3 flex items-center justify-center gap-2 border-2 border-dashed border-slate-200 hover:border-primary-300 rounded-xl px-4 py-2.5 cursor-pointer transition-colors text-xs font-bold text-slate-500">
                                <i data-lucide="upload" class="w-4 h-4"></i>
                                <span wire:loading.remove wire:target="uploads">Add external documents (PDF or images, 12 MB each)</span>
                                <span wire:loading wire:target="uploads">Uploading…</span>
                                <input type="file" wire:model="uploads" multiple accept=".pdf,.jpg,.jpeg,.png,.webp" class="hidden">
                            </label>
                            @error('uploads.*') <p class="text-xs font-bold text-rose-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex items-center justify-between gap-3 flex-wrap">
                        <p class="text-xs text-slate-400">
                            @if (($claim->airline_letter['generated_at'] ?? null))
                                Draft {{ ($claim->airline_letter['generated_by'] ?? '') === 'ai' ? 'AI-generated' : 'template-generated' }}
                                {{ \Illuminate\Support\Carbon::parse($claim->airline_letter['generated_at'])->diffForHumans() }}
                            @else
                                No draft yet - generate one to get started.
                            @endif
                        </p>
                        <div class="flex items-center gap-2">
                            <button wire:click="saveDraft" class="px-5 py-2.5 rounded-xl border border-slate-200 bg-white text-slate-700 text-sm font-bold hover:border-slate-300 transition-colors">
                                Save draft
                            </button>
                            <button disabled title="Sending flow comes next - outbound mailbox not configured yet"
                                    class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-slate-900 text-white text-sm font-bold opacity-40 cursor-not-allowed">
                                <i data-lucide="send" class="w-4 h-4"></i> Send to airline
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Timeline tab: the full claim history, room to breathe --}}
                <div x-show="tab === 'timeline'" x-cloak class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
                    <ol>
                        @foreach ($claim->events->values() as $i => $event)
                            @php $isLastEvent = $i === $claim->events->count() - 1; @endphp
                            <li class="flex gap-3.5">
                                <div class="flex flex-col items-center">
                                    <span class="w-6 h-6 rounded-full flex items-center justify-center shrink-0 z-10
                                        {{ $event->status === 'done' ? 'bg-emerald-500 text-white' : ($event->status === 'failed' ? 'bg-rose-100 text-rose-500 border border-rose-300' : 'bg-amber-100 text-amber-600 border border-amber-300') }}">
                                        <i data-lucide="{{ $event->status === 'done' ? 'check' : ($event->status === 'failed' ? 'x' : 'clock') }}" class="w-3.5 h-3.5"></i>
                                    </span>
                                    @unless ($isLastEvent)
                                        <span class="w-px flex-1 bg-slate-200 my-1"></span>
                                    @endunless
                                </div>
                                <div class="min-w-0 flex-1 {{ $isLastEvent ? '' : 'pb-5' }}">
                                    <div class="text-sm {{ $isLastEvent ? 'font-bold text-slate-900' : 'font-medium text-slate-700' }}">{{ $event->label }}</div>
                                    <div class="text-xs text-slate-400 mt-0.5">{{ $event->happened_at?->format('d M Y, H:i') }}</div>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </div>
            </div>

            {{-- CONTEXT RAIL: everything the admin checks before sending --}}
            <div class="xl:col-span-2 space-y-4 min-w-0">
                {{-- Review decision: this claim is waiting on the team --}}
                @if ($claim->status === \App\Models\Claim::STATUS_PENDING_ELIGIBILITY)
                    <div class="bg-amber-50 rounded-2xl border border-amber-200 shadow-sm p-5" x-data="{ rejecting: false }">
                        <div class="flex items-center gap-2 mb-1.5">
                            <i data-lucide="alert-triangle" class="w-4.5 h-4.5 text-amber-500"></i>
                            <h2 class="font-bold text-amber-900 text-sm">Your decision is needed</h2>
                        </div>
                        <p class="text-xs text-amber-800/80 mb-4">The engine couldn't settle this claim - the customer sees "Our team is reviewing your eligibility" until you decide.</p>

                        <div x-show="!rejecting" class="flex gap-2">
                            <button wire:click="approve" wire:loading.attr="disabled"
                                    class="flex-1 inline-flex items-center justify-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold px-4 py-2.5 rounded-xl transition-colors disabled:opacity-60">
                                <i data-lucide="check" class="w-4 h-4"></i> Approve claim
                            </button>
                            <button @click="rejecting = true"
                                    class="flex-1 inline-flex items-center justify-center gap-2 bg-white border border-amber-200 hover:border-rose-300 hover:text-rose-600 text-amber-900 text-sm font-bold px-4 py-2.5 rounded-xl transition-colors">
                                <i data-lucide="x" class="w-4 h-4"></i> Reject
                            </button>
                        </div>

                        <div x-show="rejecting" x-cloak class="space-y-2">
                            <textarea wire:model="rejection_reason" rows="3" placeholder="Reason shown and emailed to the customer (min 10 characters)…"
                                      class="w-full px-3.5 py-2.5 rounded-xl border border-amber-200 bg-white text-sm focus:border-rose-400 outline-none"></textarea>
                            @error('rejection_reason') <p class="text-xs font-bold text-rose-600">{{ $message }}</p> @enderror
                            <div class="flex gap-2">
                                <button @click="rejecting = false" class="px-4 py-2 rounded-xl bg-white border border-amber-200 text-amber-900 text-sm font-bold">Cancel</button>
                                <button wire:click="reject" wire:loading.attr="disabled"
                                        class="flex-1 bg-rose-600 hover:bg-rose-700 text-white text-sm font-bold px-4 py-2 rounded-xl transition-colors disabled:opacity-60">
                                    Reject &amp; notify customer
                                </button>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Verdict --}}
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
                    <div class="flex items-center justify-between gap-2 mb-3">
                        <h2 class="font-bold text-slate-900 text-sm">Verdict</h2>
                        <span class="flex items-center gap-1 text-[11px] font-bold {{ $claim->flight_verified_at ? 'text-emerald-600' : 'text-amber-600' }}">
                            <i data-lucide="{{ $claim->flight_verified_at ? 'check-circle-2' : 'alert-circle' }}" class="w-3.5 h-3.5"></i>
                            {{ $claim->flight_verified_at ? 'Verified' . ($claim->flight_cancelled ? ' · cancelled' : '') : 'Declared facts' }}
                        </span>
                    </div>
                    <div class="flex flex-wrap gap-1.5 mb-2">
                        <span class="bg-slate-900 text-white text-[11px] font-bold px-2.5 py-0.5 rounded-full">{{ $claim->eligibility_regulation ?: '-' }}</span>
                        <span class="bg-slate-100 text-slate-700 text-[11px] font-bold px-2.5 py-0.5 rounded-full">{{ $claim->eligibility_article ?: '-' }}</span>
                        <span class="bg-slate-100 text-slate-700 text-[11px] font-bold px-2.5 py-0.5 rounded-full">{{ $claim->eligibility_confidence }}% · {{ $claim->eligibility_decision_source ?: '-' }}</span>
                    </div>
                    <p class="text-xs text-slate-500 leading-relaxed">{{ $claim->eligibility_reason }}</p>
                </div>

                {{-- Flight tracking data (FlightAware snapshot from the flight day) --}}
                @php $snap = $claim->flight_snapshot; @endphp
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
                    <div class="flex items-center justify-between gap-2 mb-3">
                        <h2 class="font-bold text-slate-900 text-sm">Flight tracking</h2>
                        @if ($snap)
                            @if ($snap['cancelled'] ?? false)
                                <span class="inline-flex px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-rose-50 text-rose-700 ring-1 ring-rose-200">Cancelled</span>
                            @elseif ($snap['diverted'] ?? false)
                                <span class="inline-flex px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-amber-50 text-amber-700 ring-1 ring-amber-200">Diverted</span>
                            @elseif (($snap['arrival_delay_minutes'] ?? 0) >= 180)
                                <span class="inline-flex px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-rose-50 text-rose-700 ring-1 ring-rose-200">{{ intdiv($snap['arrival_delay_minutes'], 60) }}h {{ str_pad($snap['arrival_delay_minutes'] % 60, 2, '0', STR_PAD_LEFT) }}m late</span>
                            @else
                                <span class="inline-flex px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-slate-50 text-slate-600 ring-1 ring-slate-200">{{ ucfirst($snap['status'] ?? 'tracked') }}</span>
                            @endif
                        @endif
                    </div>

                    @if ($snap)
                        <div class="grid grid-cols-2 gap-3 text-sm">
                            @foreach (['Departure' => ['scheduled_departure', 'actual_departure', 'departure_delay_minutes', $claim->departure_airport], 'Arrival' => ['scheduled_arrival', 'actual_arrival', 'arrival_delay_minutes', $claim->arrival_airport]] as $side => [$schedKey, $actualKey, $delayKey, $airport])
                                @php $delay = $snap[$delayKey] ?? null; @endphp
                                <div class="rounded-xl bg-slate-50 p-3">
                                    <div class="text-[10px] uppercase tracking-wider font-bold text-slate-400 mb-1.5">{{ $side }} · {{ $airport }}</div>
                                    <dl class="space-y-1 text-[13px]">
                                        <div class="flex justify-between gap-2">
                                            <dt class="text-slate-400">Sched</dt>
                                            <dd class="font-medium text-slate-700">{{ ($snap[$schedKey] ?? null) ? \Illuminate\Support\Carbon::parse($snap[$schedKey])->format('d M H:i') : '-' }}</dd>
                                        </div>
                                        <div class="flex justify-between gap-2">
                                            <dt class="text-slate-400">Actual</dt>
                                            <dd class="font-bold {{ ($snap['cancelled'] ?? false) ? 'text-rose-600' : 'text-slate-800' }}">
                                                {{ ($snap['cancelled'] ?? false) ? 'Never flew' : (($snap[$actualKey] ?? null) ? \Illuminate\Support\Carbon::parse($snap[$actualKey])->format('d M H:i') : '-') }}
                                            </dd>
                                        </div>
                                        <div class="flex justify-between gap-2">
                                            <dt class="text-slate-400">Delay</dt>
                                            <dd class="font-bold {{ ($delay ?? 0) >= 180 ? 'text-rose-600' : (($delay ?? 0) > 0 ? 'text-amber-600' : 'text-emerald-600') }}">
                                                {{ $delay !== null ? ($delay > 0 ? '+' . $delay . ' min' : 'on time') : '-' }}
                                            </dd>
                                        </div>
                                        @if (($snap[$side === 'Departure' ? 'origin_gate' : 'destination_gate'] ?? null) || ($snap[$side === 'Departure' ? 'origin_terminal' : 'destination_terminal'] ?? null))
                                            <div class="flex justify-between gap-2">
                                                <dt class="text-slate-400">Gate/Term</dt>
                                                <dd class="font-medium text-slate-700">{{ $snap[$side === 'Departure' ? 'origin_gate' : 'destination_gate'] ?? '-' }} / {{ $snap[$side === 'Departure' ? 'origin_terminal' : 'destination_terminal'] ?? '-' }}</dd>
                                            </div>
                                        @endif
                                    </dl>
                                </div>
                            @endforeach
                        </div>
                        <p class="text-[11px] text-slate-400 mt-2.5">Times in UTC · verified {{ $claim->flight_verified_at?->format('d M Y H:i') }} · {{ $claim->fa_flight_id }}</p>
                    @else
                        <p class="text-xs text-slate-500">No live tracking record - the flight is outside the ~10-day tracking history (or unknown), so the claim rests on the passenger's declaration and documents.
                            @if ($claim->reported_arrival_delay_minutes)
                                Declared arrival delay: <strong>{{ $claim->reported_arrival_delay_minutes }} min</strong>.
                            @endif
                        </p>
                    @endif
                </div>

                {{-- Money --}}
                @if ($gross)
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
                        <h2 class="font-bold text-slate-900 text-sm mb-3">Payout</h2>
                        <dl class="space-y-1.5 text-sm">
                            <div class="flex justify-between"><dt class="text-slate-500">Per passenger</dt><dd class="font-bold text-slate-800">{{ $claim->compensation_currency }} {{ number_format((float) $claim->compensation_amount, 2) }}</dd></div>
                            <div class="flex justify-between"><dt class="text-slate-500">Total ({{ $paxCount }} pax)</dt><dd class="font-bold text-slate-800">{{ $claim->compensation_currency }} {{ number_format($gross, 2) }}</dd></div>
                            <div class="flex justify-between"><dt class="text-slate-500">Success fee ({{ $feePercent }}%)</dt><dd class="font-bold text-slate-800">- {{ $claim->compensation_currency }} {{ number_format($gross * $feePercent / 100, 2) }}</dd></div>
                            <div class="flex justify-between pt-1.5 border-t border-slate-100"><dt class="font-bold text-emerald-700">Customer payout</dt><dd class="font-black text-emerald-700">{{ $claim->compensation_currency }} {{ number_format($gross * (100 - $feePercent) / 100, 2) }}</dd></div>
                        </dl>
                    </div>
                @endif

                {{-- Passengers & signatures, merged: one row per person --}}
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
                    <h2 class="font-bold text-slate-900 text-sm mb-3">Passengers &amp; signatures</h2>
                    <ul class="space-y-2">
                        @forelse ($claim->signers as $signer)
                            <li class="flex items-center gap-2.5 text-sm">
                                <span class="w-5 h-5 rounded-full flex items-center justify-center shrink-0
                                    {{ $signer->status === 'signed' ? 'bg-emerald-500 text-white' : 'bg-amber-100 text-amber-600 border border-amber-300' }}">
                                    <i data-lucide="{{ $signer->status === 'signed' ? 'check' : 'clock' }}" class="w-3 h-3"></i>
                                </span>
                                <span class="text-slate-700 min-w-0 truncate">{{ $signer->signs_for ?: $signer->name }}</span>
                                @if ($signer->role === 'guardian')
                                    <span class="text-[10px] font-black bg-violet-100 text-violet-700 px-1.5 py-0.5 rounded-full shrink-0">GUARDIAN</span>
                                @endif
                                <span class="text-[11px] text-slate-400 ml-auto shrink-0">{{ $signer->status === 'signed' ? $signer->signed_at?->format('d M') : 'pending' }}</span>
                                @if ($signer->poa_path)
                                    <button type="button" title="View POA"
                                            @click="preview = @js(route('admin.flight-claims.claims.document', ['claim' => $claim, 'key' => 'poa-' . $signer->id])); previewName = @js('Power of Attorney - ' . ($signer->signs_for ?: $signer->name))"
                                            class="p-1 rounded text-slate-300 hover:text-primary-600 hover:bg-primary-50 transition-colors shrink-0">
                                        <i data-lucide="eye" class="w-4 h-4"></i>
                                    </button>
                                @endif
                            </li>
                        @empty
                            @foreach ($claim->passengerNames() as $name)
                                <li class="text-sm text-slate-700">{{ $name }}</li>
                            @endforeach
                            <li class="text-xs text-slate-400">No signature roster yet - the customer hasn't confirmed.</li>
                        @endforelse
                    </ul>
                    @if ($claim->confirmed_at)
                        <p class="text-[11px] text-slate-400 mt-2.5">Consent {{ $claim->confirmed_at->format('d M Y H:i') }} UTC · booking {{ $claim->booking_reference ?: '-' }}</p>
                    @endif
                </div>

            </div>
        </div>
    </div>

    {{-- Document preview modal --}}
    <div x-show="preview" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-8">
        <div class="absolute inset-0 bg-slate-900/70 backdrop-blur-sm" @click="preview = null"></div>
        <div class="relative bg-white w-full max-w-4xl h-[85vh] rounded-2xl shadow-2xl flex flex-col overflow-hidden"
             x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100">
            <div class="px-5 py-3.5 border-b border-slate-100 flex items-center gap-3 shrink-0">
                <i data-lucide="file-text" class="w-4.5 h-4.5 text-primary-500 shrink-0"></i>
                <span class="font-bold text-slate-800 text-sm truncate" x-text="previewName"></span>
                <div class="ml-auto flex items-center gap-1 shrink-0">
                    <a :href="preview" target="_blank" title="Open in a new tab"
                       class="p-2 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors">
                        <i data-lucide="external-link" class="w-4 h-4"></i>
                    </a>
                    <button @click="preview = null" class="p-2 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors">
                        <i data-lucide="x" class="w-4 h-4"></i>
                    </button>
                </div>
            </div>
            <iframe :src="preview" class="flex-1 w-full bg-slate-100" frameborder="0"></iframe>
        </div>
    </div>
</div>
