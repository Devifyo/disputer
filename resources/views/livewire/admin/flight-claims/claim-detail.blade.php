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
            {{-- WORK AREA: tabbed - compose, correspondence with the airline, timeline --}}
            <div class="xl:col-span-3 min-w-0" x-data="{ tab: 'email' }">
                <div class="inline-flex items-center gap-1 bg-white rounded-xl border border-slate-200 shadow-sm p-1 mb-4">
                    <button @click="tab = 'email'"
                            :class="tab === 'email' ? 'bg-slate-900 text-white shadow' : 'text-slate-500 hover:text-slate-800'"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-bold transition-all">
                        <i data-lucide="mail" class="w-4 h-4"></i> Claim email
                    </button>
                    <button @click="tab = 'mailbox'"
                            :class="tab === 'mailbox' ? 'bg-slate-900 text-white shadow' : 'text-slate-500 hover:text-slate-800'"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-bold transition-all">
                        <i data-lucide="inbox" class="w-4 h-4"></i> Correspondence
                        @if ($mailbox->isNotEmpty())
                            <span class="text-[10px] font-black px-1.5 py-0.5 rounded-full"
                                  :class="tab === 'mailbox' ? 'bg-white/20' : 'bg-slate-100 text-slate-500'">{{ $mailbox->count() }}</span>
                        @endif
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
                        <div class="flex items-center gap-2 shrink-0" x-data="{ more: false, fuReason: null }" @click.outside="more = false">
                            <button wire:click="generate" wire:loading.attr="disabled" wire:target="generate,generateFollowUp,generateRegulator"
                                    class="inline-flex items-center gap-2 bg-violet-600 hover:bg-violet-700 text-white text-sm font-bold px-4 py-2 rounded-xl transition-colors disabled:opacity-60">
                                <i data-lucide="sparkles" class="w-4 h-4"></i>
                                <span wire:loading.remove wire:target="generate,generateFollowUp,generateRegulator">{{ $subject ? 'Regenerate claim' : 'Generate with AI' }}</span>
                                <span wire:loading wire:target="generate,generateFollowUp,generateRegulator">Drafting…</span>
                            </button>

                            <div class="relative">
                                <button @click="more = !more"
                                        class="inline-flex items-center gap-1.5 bg-white border border-slate-200 hover:border-slate-300 text-slate-700 text-sm font-bold px-3.5 py-2 rounded-xl transition-colors">
                                    More drafts <i data-lucide="chevron-down" class="w-3.5 h-3.5"></i>
                                </button>
                                <div x-show="more" x-cloak
                                     class="absolute right-0 top-full mt-1.5 w-80 bg-white rounded-xl border border-slate-200 shadow-xl p-2 z-20">
                                    <p class="px-2 pt-1 pb-1.5 text-[10px] uppercase tracking-wider font-bold text-slate-400">Follow-up to the airline</p>
                                    @foreach (\App\Models\ClaimDraft::FOLLOW_UP_REASONS as $reasonKey => $reasonLabel)
                                        <button @click="fuReason = '{{ $reasonKey }}'; {{ in_array($reasonKey, ['no_response', 'manual']) ? "more = false; \$wire.generateFollowUp('{$reasonKey}')" : '' }}"
                                                class="w-full text-left px-2 py-1.5 rounded-lg text-sm text-slate-700 hover:bg-slate-50 font-medium transition-colors">
                                            {{ $reasonLabel }}
                                        </button>
                                    @endforeach
                                    <p class="px-2 pt-2 pb-1.5 text-[10px] uppercase tracking-wider font-bold text-slate-400 border-t border-slate-100 mt-1">Escalation</p>
                                    <button @click="fuReason = 'regulator'"
                                            class="w-full text-left px-2 py-1.5 rounded-lg text-sm text-slate-700 hover:bg-slate-50 font-medium transition-colors">
                                        Regulator complaint ({{ ['CA' => 'CTA', 'US' => 'US DOT', 'UK' => 'CAA', 'EU' => 'NEB'][\App\Services\Claims\ClaimLegalDocumentService::jurisdiction($claim)] }})
                                    </button>

                                    {{-- Airline-response context for the reasons that need it --}}
                                    <div x-show="['info_request', 'partial', 'rejected', 'regulator'].includes(fuReason)" x-cloak class="border-t border-slate-100 mt-1 p-2 space-y-2">
                                        <label class="text-[10px] uppercase tracking-wider font-bold text-slate-400">Paste the airline's response (context for the draft)</label>
                                        <textarea wire:model="airline_response" rows="4" placeholder="Optional but recommended - the draft responds to it point by point…"
                                                  class="w-full px-3 py-2 rounded-lg border border-slate-200 text-xs focus:border-primary-500 outline-none"></textarea>
                                        <button @click="more = false; fuReason === 'regulator' ? $wire.generateRegulator() : $wire.generateFollowUp(fuReason); fuReason = null"
                                                class="w-full bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold px-3 py-2 rounded-lg transition-colors">
                                            Generate draft
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="p-6 space-y-3" wire:loading.class="opacity-50" wire:target="generate">
                        <div class="grid sm:grid-cols-[70px_1fr] items-center gap-2">
                            <label class="text-[11px] uppercase tracking-wider font-bold text-slate-400">To</label>
                            <input type="email" wire:model="to" placeholder="Airline claims department email - set in the sending step"
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:border-primary-500 outline-none">
                        </div>
                        @error('to') <p class="text-xs font-bold text-rose-600 sm:ml-[78px]">{{ $message }}</p> @enderror
                        <div class="sm:ml-[78px] -mt-1">
                            @php $dirPurpose = $wfStage?->airline_contact_purpose ?: 'claims'; @endphp
                            @if ($airlineRec && ($dirContact = $airlineRec->contactFor($dirPurpose)))
                                <p class="flex items-center gap-1.5 text-[11px] text-slate-400">
                                    <i data-lucide="book-user" class="w-3.5 h-3.5 text-emerald-500"></i>
                                    Directory: {{ $airlineRec->name }} · {{ $dirContact->purposeLabel() }} · {{ $dirContact->email }}
                                </p>
                            @elseif ($airlineRec)
                                <p class="flex items-center gap-1.5 text-[11px] font-bold text-amber-600">
                                    <i data-lucide="book-user" class="w-3.5 h-3.5"></i>
                                    {{ $airlineRec->name }} has no "{{ \App\Models\AirlineContact::PURPOSES[$dirPurpose] ?? $dirPurpose }}" contact yet -
                                    <a href="{{ route('admin.flight-claims.airlines') }}" wire:navigate class="underline">add it in the Airline Directory</a>
                                </p>
                            @else
                                <p class="flex items-center gap-1.5 text-[11px] font-bold text-amber-600">
                                    <i data-lucide="book-user" class="w-3.5 h-3.5"></i>
                                    "{{ $claim->airline }}" is not in the Airline Directory -
                                    <a href="{{ route('admin.flight-claims.airlines') }}" wire:navigate class="underline">add it</a> so emails route automatically
                                </p>
                            @endif
                        </div>

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
                                <span class="text-slate-300">·</span>
                            @endif
                            Sends from {{ config('services.inbound.claims_display') }} - replies auto-attach to this claim ({{ $claim->reference }}).
                        </p>
                        <div class="flex items-center gap-2">
                            <button wire:click="saveDraft" wire:loading.attr="disabled" class="px-5 py-2.5 rounded-xl border border-slate-200 bg-white text-slate-700 text-sm font-bold hover:border-slate-300 transition-colors disabled:opacity-60">
                                <span wire:loading.remove wire:target="saveDraft">Save draft</span>
                                <span wire:loading wire:target="saveDraft" class="inline-flex items-center gap-2"><svg class="inline w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg> Saving…</span>
                            </button>
                            <button @click="$dispatch('admin-confirm', {
                                        title: 'Send to airline',
                                        message: 'Send this email to ' + ($wire.to || 'the airline') + ' now? It goes out from {{ config('services.inbound.claims_display') }} with all selected attachments, and the reply will attach to this claim automatically.',
                                        confirmLabel: 'Send now',
                                        method: 'send',
                                    })" wire:loading.attr="disabled"
                                    class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-sm font-bold transition-colors disabled:opacity-60">
                                <span wire:loading.remove wire:target="send" class="inline-flex items-center gap-2"><i data-lucide="send" class="w-4 h-4"></i> Send to airline</span>
                                <span wire:loading wire:target="send" class="inline-flex items-center gap-2"><svg class="inline w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg> Sending…</span>
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Draft history: every version, auditable; approve the final --}}
                <div x-show="tab === 'email'" class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 mt-4">
                    <div class="flex items-center justify-between gap-2 mb-3">
                        <h2 class="font-bold text-slate-900 text-sm">Draft history</h2>
                        <span class="text-[11px] font-bold text-slate-400">{{ $drafts->count() }} version{{ $drafts->count() === 1 ? '' : 's' }}</span>
                    </div>
                    @if ($drafts->isEmpty())
                        <p class="text-xs text-slate-400">No drafts yet - every AI generation and admin edit is stored here for auditing.</p>
                    @else
                        <ul class="divide-y divide-slate-50">
                            @foreach ($drafts as $draft)
                                <li class="flex items-center gap-3 py-2.5 {{ $loadedDraftId === $draft->id ? 'bg-primary-50/50 -mx-2 px-2 rounded-lg' : '' }}">
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-black ring-1 shrink-0
                                        {{ $draft->type === 'airline_claim' ? 'bg-blue-50 text-blue-700 ring-blue-200' : ($draft->type === 'follow_up' ? 'bg-amber-50 text-amber-700 ring-amber-200' : 'bg-rose-50 text-rose-700 ring-rose-200') }}">
                                        {{ strtoupper($draft->typeLabel()) }} · V{{ $draft->version }}
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <div class="text-sm font-medium text-slate-700 truncate">{{ $draft->subject }}</div>
                                        <div class="text-[11px] text-slate-400">
                                            {{ ['ai' => 'AI-generated', 'template' => 'Template', 'admin' => 'Admin edit'][$draft->generated_by] ?? $draft->generated_by }}
                                            · {{ $draft->author?->name ?? 'system' }} · {{ $draft->created_at->format('d M H:i') }}
                                            @if (($draft->context['reason'] ?? null))
                                                · {{ \App\Models\ClaimDraft::FOLLOW_UP_REASONS[$draft->context['reason']] ?? $draft->context['reason'] }}
                                            @endif
                                        </div>
                                    </div>
                                    @if ($draft->approved_at)
                                        <span class="inline-flex items-center gap-1 text-[10px] font-black bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded-full shrink-0">
                                            <i data-lucide="check" class="w-3 h-3"></i> APPROVED
                                        </span>
                                    @else
                                        <button wire:click="approveDraft({{ $draft->id }})" wire:loading.attr="disabled" class="text-[11px] font-bold text-emerald-600 hover:underline shrink-0">
                                        <span wire:loading.remove wire:target="approveDraft({{ $draft->id }})">Approve</span>
                                        <span wire:loading wire:target="approveDraft({{ $draft->id }})"><svg class="inline w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg></span>
                                    </button>
                                    @endif
                                    <button wire:click="loadDraft({{ $draft->id }})" wire:loading.attr="disabled" class="text-[11px] font-bold text-primary-600 hover:underline shrink-0">
                                        <span wire:loading.remove wire:target="loadDraft({{ $draft->id }})">{{ $loadedDraftId === $draft->id ? 'Loaded' : 'Load' }}</span>
                                        <span wire:loading wire:target="loadDraft({{ $draft->id }})"><svg class="inline w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg></span>
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                {{-- Correspondence tab: every email exchanged with the airline --}}
                <div x-show="tab === 'mailbox'" x-cloak class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
                    @if ($mailbox->isEmpty())
                        <div class="text-center py-10">
                            <i data-lucide="inbox" class="w-8 h-8 text-slate-300 mx-auto mb-3"></i>
                            <p class="text-sm font-medium text-slate-500">No correspondence yet</p>
                            <p class="text-xs text-slate-400 mt-1">Emails sent to the airline and their replies will appear here. Replies match this claim automatically via its reference ({{ $claim->reference }}).</p>
                        </div>
                    @else
                        <ul class="space-y-3">
                            @foreach ($mailbox as $mail)
                                <li x-data="{ open: false }"
                                    class="rounded-xl border {{ $mail->direction === 'inbound' ? 'border-emerald-200 bg-emerald-50/40' : 'border-slate-200' }}">
                                    <button @click="open = !open" class="w-full text-left px-4 py-3 flex items-start gap-3">
                                        <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-black ring-1 shrink-0 mt-0.5
                                            {{ $mail->direction === 'inbound' ? 'bg-emerald-100 text-emerald-700 ring-emerald-200' : 'bg-blue-50 text-blue-700 ring-blue-200' }}">
                                            {{ $mail->direction === 'inbound' ? 'AIRLINE' : 'SENT' }}
                                        </span>
                                        <span class="min-w-0 flex-1">
                                            <span class="block text-sm font-bold text-slate-800 truncate">{{ $mail->subject ?: '(no subject)' }}</span>
                                            <span class="block text-[11px] text-slate-400 mt-0.5">
                                                @if ($mail->direction === 'inbound')
                                                    From {{ $mail->from_name ? $mail->from_name . ' - ' : '' }}{{ $mail->from_email }}
                                                    @if ($mail->matched_by) · matched by {{ $mail->matched_by === 'reply_token' ? 'reply address' : 'subject reference' }} @endif
                                                @else
                                                    To {{ $mail->to_email }} · sent by {{ $mail->sender?->name ?? 'system' }}
                                                @endif
                                                · {{ $mail->created_at->format('d M Y H:i') }}
                                                @if (count($mail->attachments ?? []))
                                                    · <i data-lucide="paperclip" class="inline w-3 h-3"></i> {{ count($mail->attachments) }}
                                                @endif
                                            </span>
                                        </span>
                                        <i data-lucide="chevron-down" class="w-4 h-4 text-slate-400 shrink-0 mt-1 transition-transform" :class="open && 'rotate-180'"></i>
                                    </button>
                                    <div x-show="open" x-collapse x-cloak>
                                        <div class="px-4 pb-4 border-t border-slate-100 pt-3" x-data="{ quoted: false }">
                                            <pre class="whitespace-pre-wrap font-sans text-[13px] leading-relaxed text-slate-700 max-h-96 overflow-y-auto">{{ $mail->newBody() }}</pre>
                                            @if ($mail->quotedBody())
                                                <button @click="quoted = !quoted" class="mt-2 inline-flex items-center gap-1 text-[11px] font-bold text-slate-400 hover:text-slate-600 transition-colors">
                                                    <i data-lucide="ellipsis" class="w-3.5 h-3.5"></i>
                                                    <span x-text="quoted ? 'Hide quoted history' : 'Show quoted history'"></span>
                                                </button>
                                                <pre x-show="quoted" x-collapse x-cloak class="whitespace-pre-wrap font-sans text-[12px] leading-relaxed text-slate-400 border-l-2 border-slate-200 pl-3 mt-2 max-h-72 overflow-y-auto">{{ $mail->quotedBody() }}</pre>
                                            @endif
                                            @if (count($mail->attachments ?? []))
                                                <div class="flex flex-wrap gap-2 mt-3">
                                                    @foreach ($mail->attachments as $i => $file)
                                                        @if ($mail->direction === 'inbound')
                                                            <button type="button"
                                                                    @click.stop="preview = @js(route('admin.flight-claims.claims.document', ['claim' => $claim, 'key' => 'inbound-' . $mail->id . '-' . $i])); previewName = @js($file['name'] ?? 'Attachment')"
                                                                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-white border border-slate-200 text-[11px] font-bold text-slate-600 hover:border-slate-300 transition-colors">
                                                                <i data-lucide="paperclip" class="w-3 h-3"></i> {{ $file['name'] ?? 'Attachment' }}
                                                            </button>
                                                        @else
                                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-slate-50 border border-slate-200 text-[11px] font-bold text-slate-500">
                                                                <i data-lucide="paperclip" class="w-3 h-3"></i> {{ $file['name'] ?? 'Attachment' }}
                                                            </span>
                                                        @endif
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
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

                    {{-- Immutable internal audit trail: every transition and action, never shown to customers --}}
                    <div class="mt-6 pt-5 border-t border-slate-100">
                        <div class="flex items-center gap-2 mb-3">
                            <h3 class="font-bold text-slate-900 text-sm">Audit trail</h3>
                            <span class="text-[10px] font-black bg-slate-100 text-slate-500 px-2 py-0.5 rounded-full">INTERNAL</span>
                        </div>
                        @if ($auditLogs->isEmpty())
                            <p class="text-xs text-slate-400">No audit entries yet.</p>
                        @else
                            <ul class="divide-y divide-slate-50">
                                @foreach ($auditLogs as $log)
                                    <li class="py-2 flex items-start gap-3 text-xs">
                                        <span class="inline-flex px-1.5 py-0.5 rounded font-black uppercase shrink-0 mt-px
                                            {{ ['admin' => 'bg-blue-50 text-blue-600', 'customer' => 'bg-emerald-50 text-emerald-600', 'airline' => 'bg-amber-50 text-amber-700', 'system' => 'bg-slate-100 text-slate-500'][$log->via] ?? 'bg-slate-100 text-slate-500' }}">
                                            {{ $log->via }}
                                        </span>
                                        <div class="min-w-0 flex-1">
                                            <span class="font-medium text-slate-700">{{ $log->action }}</span>
                                            @if ($log->notes)
                                                <span class="text-slate-400"> - {{ \Illuminate\Support\Str::limit($log->notes, 160) }}</span>
                                            @endif
                                        </div>
                                        <span class="text-slate-400 shrink-0">{{ $log->actor?->name ? $log->actor->name . ' · ' : '' }}{{ $log->created_at->format('d M H:i') }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
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
                                <span wire:loading.remove wire:target="approve" class="inline-flex items-center gap-2"><i data-lucide="check" class="w-4 h-4"></i> Approve claim</span>
                                <span wire:loading wire:target="approve" class="inline-flex items-center gap-2"><svg class="inline w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg> Approving…</span>
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
                                    <span wire:loading.remove wire:target="reject">Reject &amp; notify customer</span>
                                    <span wire:loading wire:target="reject" class="inline-flex items-center gap-2"><svg class="inline w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg> Rejecting…</span>
                                </button>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Workflow: current stage + context-specific admin actions --}}
                @if ($wfStage && $claim->status === \App\Models\Claim::STATUS_ELIGIBLE)
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5" x-data="{ target: null }">
                        <div class="flex items-center justify-between gap-2 mb-3">
                            <h2 class="font-bold text-slate-900 text-sm">Workflow</h2>
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold ring-1 {{ $wfStage->badgeClasses() }}">
                                <i data-lucide="{{ $wfStage->icon }}" class="w-3.5 h-3.5"></i> {{ $wfStage->name }}
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 mb-3">{{ $wfStage->description }}</p>

                        @if ($pendingTimer)
                            <div class="flex items-center gap-2 rounded-xl bg-blue-50 border border-blue-100 px-3 py-2 mb-3 text-xs">
                                <i data-lucide="timer" class="w-3.5 h-3.5 text-blue-500 shrink-0"></i>
                                <span class="text-blue-800">
                                    Auto-moves to <strong>{{ \App\Models\ClaimLifecycleStage::byKey($pendingTimer->meta['to_stage'] ?? '')?->name ?? '-' }}</strong>
                                    on {{ $pendingTimer->due_at->format('d M Y') }} ({{ (int) now()->diffInDays($pendingTimer->due_at, false) }} days left)
                                </span>
                            </div>
                        @endif

                        @if ($claim->filed_at)
                            <p class="text-[11px] text-slate-400 mb-3">
                                Filed {{ $claim->filed_at->format('d M Y H:i') }}
                                @if ($claim->filing['recipient'] ?? null) to {{ $claim->filing['recipient'] }} @endif
                                @if ($claim->filing['email_reference'] ?? null) · ref {{ $claim->filing['email_reference'] }} @endif
                            </p>
                        @endif

                        @if ($wfOptions->isNotEmpty())
                            <div class="flex flex-wrap gap-2">
                                @foreach ($wfOptions as $option)
                                    <button @click="target = target === '{{ $option->key }}' ? null : '{{ $option->key }}'"
                                            class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold ring-1 transition-all {{ $option->badgeClasses() }} hover:shadow"
                                            :class="target === '{{ $option->key }}' ? 'ring-2 ring-slate-900' : ''">
                                        <i data-lucide="{{ $option->icon }}" class="w-3.5 h-3.5"></i> {{ $option->name }}
                                    </button>
                                @endforeach
                            </div>

                            @foreach ($wfOptions as $option)
                                <div x-show="target === '{{ $option->key }}'" x-cloak class="mt-3 space-y-2 border-t border-slate-100 pt-3">
                                    @if ($option->key === 'filed')
                                        <input type="email" wire:model="filing_recipient" placeholder="Recipient - airline claims email *"
                                               class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:border-primary-500 outline-none">
                                        @error('filing_recipient') <p class="text-xs font-bold text-rose-600">{{ $message }}</p> @enderror
                                        <input type="text" wire:model="filing_reference" placeholder="Email / case reference (optional)"
                                               class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:border-primary-500 outline-none">
                                        <p class="text-[11px] text-slate-400">Submission date, recipient, reference and the {{ count($attached) }} selected attachment(s) are stored on the claim.</p>
                                    @endif
                                    <textarea wire:model="wf_notes" rows="3"
                                              placeholder="{{ $option->key === 'responded' ? 'Paste the airline\'s response - required, becomes part of the record…' : 'Notes (optional - stored in the audit trail)…' }}"
                                              class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:border-primary-500 outline-none"></textarea>
                                    @error('wf_notes') <p class="text-xs font-bold text-rose-600">{{ $message }}</p> @enderror
                                    <button wire:click="moveTo('{{ $option->key }}')" wire:loading.attr="disabled"
                                            class="w-full bg-slate-900 hover:bg-slate-800 text-white text-sm font-bold px-4 py-2.5 rounded-xl transition-colors disabled:opacity-60">
                                        <span wire:loading.remove wire:target="moveTo">Confirm: move to {{ $option->name }}</span>
                                        <span wire:loading wire:target="moveTo" class="inline-flex items-center gap-2"><svg class="inline w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg> Moving…</span>
                                    </button>
                                </div>
                            @endforeach
                        @else
                            <p class="text-xs text-slate-400">{{ $wfStage->is_final ? 'Final stage - the claim can no longer progress.' : 'No manual action available - the workflow advances automatically.' }}</p>
                        @endif
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

    <x-admin.confirm />
</div>
