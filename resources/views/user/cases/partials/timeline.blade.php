<div class="lg:col-span-5 relative h-full">
    {{-- Added this wrapper to force the height to match the left column --}}
    <div class="lg:absolute lg:inset-0 h-full w-full">
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm h-full flex flex-col overflow-hidden">
            {{-- button area --}}
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/30 shrink-0">
                <div class="flex items-center gap-2">
                    <i data-lucide="activity" class="w-4 h-4 text-slate-400"></i>
                    <h3 class="font-bold text-slate-800 text-sm">Activity Log</h3>
                </div>
                
                <div class="flex items-center gap-3">
                    {{-- EXTERNAL PORTAL BUTTON: Only shows if a URL exists --}}
                    <template x-if="dynamicRecipientUrl">
                        <a :href="dynamicRecipientUrl" target="_blank" class="text-xs font-bold text-violet-600 hover:text-violet-700 hover:underline flex items-center gap-1.5 transition-colors bg-violet-50 px-2 py-1.5 rounded border border-violet-100">
                            <i data-lucide="external-link" class="w-3.5 h-3.5"></i> External Escalation
                        </a>
                    </template>

                    {{-- NEW EMAIL BUTTON --}}
                    <button type="button" 
                            @click="$dispatch('open-compose-modal', { 
                                subject: 'Case #{{ $case->case_reference_id }}',
                                body: '',
                                isEscalation: false,
                                isFollowUp: false
                            })" 
                            class="text-xs font-bold text-blue-600 hover:text-blue-700 hover:underline flex items-center gap-1 transition-colors">
                        <i data-lucide="plus" class="w-3 h-3"></i> New Email
                    </button>
                </div>
            </div>
            {{-- button area  --}}
            <div class="p-6 flex-1 min-h-0 overflow-y-auto custom-scrollbar">
                <div class="relative space-y-8">
                    <div class="absolute top-2 bottom-2 left-4 w-0.5 bg-slate-100"></div>
                
                    @foreach($case->timeline->sortByDesc('id') as $log)
                        @php
                            // --- FILTER: HIDE INTERNAL LOGS ---
                            if (in_array($log->type, ['Ai_guidance_workflow', 'system_suggestion', 'debug_log', 'escalation_deadline_alert'])) {
                                continue;
                            }

                            // --- Logic ---
                            $direction = $log->metadata['direction'] ?? 'outbound'; 
                            if(str_contains($log->type, 'received')) { $direction = 'inbound'; }

                            $rawEmailId = $log->metadata['email_id'] ?? null;
                            $rawSubject = $log->metadata['subject'] ?? 'Case #'.$case->case_reference_id;
                            if (is_array($rawSubject)) { $rawSubject = reset($rawSubject); }
                            $safeSubject = (string) $rawSubject;

                            $rawRecipient = $log->metadata['sender_email'] ?? $log->metadata['recipient'] ?? '';
                            if (is_array($rawRecipient)) { $rawRecipient = reset($rawRecipient); }
                            $safeRecipient = (string) $rawRecipient;

                            // 1. Get Body & Check Unread Status
                            $rawBody = $log->metadata['full_body'] ?? $log->metadata['body'] ?? '';
                            if (is_array($rawBody)) { $rawBody = reset($rawBody); }
                            
                            $attachmentsData = []; 
                            $isUnread = false; // NEW: Track unread state
                            $isBounced = false; // ADDED: Track bounce state
                            
                            if ($rawEmailId) {
                                $linkedEmail = \App\Models\Email::find($rawEmailId);
                                if ($linkedEmail) {
                                    if (empty($rawBody)) {
                                        $rawBody = $linkedEmail->body_html ?? $linkedEmail->body_text ?? '';
                                    }
                                    
                                    // NEW: If inbound and not read, flag it as unread
                                    if ($direction === 'inbound' && !$linkedEmail->is_read) {
                                        $isUnread = true;
                                    }

                                    // ADDED: Check delivery status
                                    if ($linkedEmail->delivery_status === 'bounced') {
                                        $isBounced = true;
                                    }

                                    $attachments = \App\Models\Attachment::where('email_id', $rawEmailId)->get();
                                    foreach($attachments as $att) {
                                        $attachmentsData[] = [
                                            'name' => $att->file_name,
                                            'url'  => $att->public_link,
                                            'type' => $att->mime_type,
                                            'path' => $att->file_path,
                                        ];
                                    }
                                }
                            }
                            
                            $safeBody = (string) $rawBody;

                            $emailPayload = [
                                'emailId' => $rawEmailId ?? '',
                                'subject' => $safeSubject,
                                'body' => $safeBody, 
                                'direction' => $direction,
                                'attachments' => $attachmentsData,
                                'recipient' => $safeRecipient,
                                'caseId' => $case->id,
                            ];
                        @endphp

                        {{-- Main wrapper --}}
                        {{-- Alpine State Wrapper: Tracks 'isUnread' for this specific log --}}
                        <div x-data="{ isUnread: {{ $isUnread ? 'true' : 'false' }} }" 
                            
                            @email-read-state-changed.window="if ($event.detail.emailId == '{{ $rawEmailId }}') isUnread = false"

                            class="relative group transition-all duration-500 {{ $isBounced ? 'bg-rose-50/60 p-3 -ml-3 rounded-xl border border-rose-200 pl-[3.5rem]' : '' }}"
                            :class="isUnread && !{{ $isBounced ? 'true' : 'false' }} ? 'bg-rose-50/40 p-2 -ml-2 rounded-xl border border-rose-100 pl-[3.25rem]' : (!{{ $isBounced ? 'true' : 'false' }} ? 'pl-12' : '')">
                            
                            {{-- Icon Circle --}}
                            <div class="absolute top-0 border-2 rounded-full w-8 h-8 flex items-center justify-center z-10 bg-white transition-all duration-500"
                                :class="isUnread 
                                    ? 'border-rose-500 text-rose-600 shadow-[0_0_12px_rgba(244,63,94,0.6)] animate-pulse ring-2 ring-rose-500/20 left-2 top-2' 
                                    : 'left-0 @if($isBounced) border-rose-500 text-rose-600 shadow-[0_0_8px_rgba(244,63,94,0.4)] ring-2 ring-rose-500/20 top-3 left-3 @elseif($log->type == 'email_sent' || $log->type == 'email_received' || $log->type == 'escalation_sent'){{ $direction === 'inbound' ? 'border-emerald-100 text-emerald-600 shadow-sm' : 'border-blue-100 text-blue-600 shadow-sm' }}@elseif($log->type == 'case_created')border-slate-100 text-slate-500 shadow-sm @elseif($log->type == 'workflow_change')border-purple-100 text-purple-500 shadow-sm @elseif($log->type == 'escalation_sent')border-rose-100 text-rose-600 shadow-sm @else border-slate-100 text-slate-400 shadow-sm @endif'">
                                
                                @if($isBounced) <i data-lucide="alert-circle" class="w-4 h-4"></i>
                                @elseif($direction === 'inbound') <i data-lucide="arrow-down-left" class="w-4 h-4"></i>
                                @elseif($log->type == 'email_sent') <i data-lucide="arrow-up-right" class="w-4 h-4"></i>
                                @elseif($log->type == 'escalation_sent') <i data-lucide="trending-up" class="w-4 h-4"></i>
                                @elseif($log->type == 'case_created') <i data-lucide="flag" class="w-3.5 h-3.5"></i>
                                @elseif($log->type == 'workflow_change') <i data-lucide="git-commit" class="w-3.5 h-3.5"></i>
                                @else <i data-lucide="circle" class="w-3 h-3"></i> @endif
                            </div>

                            <div class="flex flex-col gap-1.5 transition-all duration-500" :class="isUnread || {{ $isBounced ? 'true' : 'false' }} ? 'mt-1' : ''">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-bold text-slate-900 leading-none">
                                            @if($isBounced) Delivery Failed @else {{ $log->readable_type ?? ucfirst(str_replace('_', ' ', $log->type)) }} @endif
                                        </span>
                                        
                                        @if($log->type == 'email_sent' || $log->type == 'email_received' || $log->type == 'escalation_sent')
                                            @if($isBounced)
                                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-600 text-white uppercase tracking-wide shadow-sm flex items-center gap-1">
                                                    <i data-lucide="shield-alert" class="w-3 h-3"></i> Bounced
                                                </span>
                                            @elseif($direction === 'inbound')
                                                {{-- Badges swap dynamically based on Alpine state --}}
                                                <template x-if="isUnread">
                                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-500 text-white uppercase tracking-wide shadow-[0_0_8px_rgba(244,63,94,0.5)] animate-pulse">New / Unread</span>
                                                </template>
                                                <template x-if="!isUnread">
                                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-700 uppercase tracking-wide border border-emerald-200">Received</span>
                                                </template>
                                            @elseif($log->type == 'escalation_sent')
                                                <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-rose-100 text-rose-700 uppercase tracking-wide border border-rose-200">Escalation</span>
                                            @elseif($log->metadata['is_followup'] ?? false)
                                                <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-700 uppercase tracking-wide border border-amber-200">Follow Up</span>
                                            @else
                                                <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-blue-100 text-blue-700 uppercase tracking-wide border border-blue-200">Sent</span>
                                            @endif
                                        @endif

                                        @if(count($attachmentsData) > 0)
                                            <i data-lucide="paperclip" class="w-3 h-3 text-slate-400"></i>
                                        @endif
                                    </div>
                                    <span class="text-[10px] font-semibold text-slate-400 uppercase bg-slate-50 px-1.5 py-0.5 rounded border transition-colors duration-500 whitespace-nowrap"
                                        :class="isUnread || {{ $isBounced ? 'true' : 'false' }} ? 'bg-white border-rose-100' : 'border-slate-100'">
                                        {{ $log->occurred_at ? $log->occurred_at->diffForHumans(null, true) : 'N/A' }}
                                    </span>
                                </div>
                                
                                @if($isBounced)
                                    <p class="text-xs font-semibold text-rose-600 leading-relaxed bg-rose-100/50 p-2 rounded border border-rose-100">
                                        The email address <span class="font-bold underline">{{ $safeRecipient }}</span> rejected this message. Please click "New Email" to try a different contact.
                                    </p>
                                @else
                                    <p class="text-xs text-slate-600 leading-relaxed line-clamp-2">{{ $log->description }}</p>
                                @endif
                                
                                @if(in_array($log->type, ['email_sent', 'email_received', 'escalation_sent']))
                                    <div class="mt-2 flex items-center gap-2">
                                        {{-- DYNAMIC BUTTON: Dispatches to Livewire AND instantly updates Alpine state --}}
                                        <button 
                                            type="button"
                                            @click="$dispatch('open-email', @js($emailPayload)); isUnread = false;"
                                            class="flex items-center gap-2 px-3.5 py-1.5 rounded-lg border transition-all duration-300 text-[11px] font-bold shadow-sm active:scale-95"
                                            :class="isUnread || {{ $isBounced ? 'true' : 'false' }}
                                                ? 'border-rose-500 bg-rose-500 text-white hover:bg-rose-600 shadow-[0_0_12px_rgba(244,63,94,0.4)] ring-2 ring-rose-500/20' 
                                                : 'border-indigo-100 bg-indigo-50/50 text-indigo-600 hover:bg-indigo-100 hover:text-indigo-700 hover:border-indigo-200'"
                                        >
                                            <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                                            <span>View Message</span>
                                        </button>

                                        @if($direction === 'inbound')
                                            <button 
                                                type="button"
                                                @click="$dispatch('open-compose-modal', { 
                                                    subject: 'Re: {{ addslashes($safeSubject) }}', 
                                                    recipient: '{{ addslashes($safeRecipient) }}',
                                                    body: '',
                                                    isEscalation: false,
                                                    isFollowUp: false,
                                                    replyEmailId: '{{ $rawEmailId }}'
                                                })" 
                                                class="flex items-center gap-1.5 px-3 py-1.5 rounded-md border transition-all duration-300 text-xs font-bold shadow-sm"
                                                :class="isUnread 
                                                    ? 'border-rose-200 bg-rose-50 hover:bg-rose-100 hover:border-rose-300 text-rose-700' 
                                                    : 'border-blue-200 bg-blue-50 hover:bg-blue-100 hover:border-blue-300 text-blue-600'">
                                                <i data-lucide="reply" class="w-3.5 h-3.5"></i> Reply
                                            </button>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                    
                    @if($case->timeline->whereNotIn('type', ['ai_guidance', 'system_suggestion'])->isEmpty())
                        <div class="text-center py-8 relative z-10 bg-white">
                            <p class="text-xs text-slate-400 italic">No public activity recorded yet.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>