<div class="lg:col-span-5 h-full">
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm h-full flex flex-col">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/30">
            <div class="flex items-center gap-2">
                <i data-lucide="activity" class="w-4 h-4 text-slate-400"></i>
                <h3 class="font-bold text-slate-800 text-sm">Activity Log</h3>
            </div>
            
            {{-- NEW EMAIL BUTTON: Dispatch to Parent --}}
            <button type="button" 
                    @click="$dispatch('open-compose-modal', { 
                        subject: 'Case #{{ $case->case_reference_id }}', 
                        recipient: '',
                        body: '',
                        isEscalation: false ,
                        isFollowUp: false
                    })" 
                    class="text-xs font-bold text-blue-600 hover:text-blue-700 hover:underline flex items-center gap-1 transition-colors">
                <i data-lucide="plus" class="w-3 h-3"></i> New Email
            </button>
        </div>
        
        <div class="p-6 flex-1">
            <div class="relative space-y-8">
                <div class="absolute top-2 bottom-2 left-4 w-0.5 bg-slate-100"></div>

                @foreach($case->timeline as $log)
                    @php
                        // --- FILTER: HIDE INTERNAL LOGS ---
                        if (in_array($log->type, ['Ai_guidance_workflow', 'system_suggestion', 'debug_log'])) {
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

                        // 1. Get Body
                        $rawBody = $log->metadata['full_body'] ?? $log->metadata['body'] ?? '';
                        if (is_array($rawBody)) { $rawBody = reset($rawBody); }
                        
                        // 2. Fetch Attachments & Fallback Body
                        $attachmentsData = []; 
                        
                        if ($rawEmailId) {
                            $linkedEmail = \App\Models\Email::find($rawEmailId);
                            if ($linkedEmail) {
                                if (empty($rawBody)) {
                                    $rawBody = $linkedEmail->body_html ?? $linkedEmail->body_text ?? '';
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
                    @endphp

                    <div class="relative pl-12 group">
                        <div class="absolute left-0 top-0 border-2 rounded-full w-8 h-8 flex items-center justify-center z-10 shadow-sm bg-white
                            @if($log->type == 'email_sent' || $log->type == 'email_received' || $log->type == 'escalation_sent')
                                {{ $direction === 'inbound' ? 'border-emerald-100 text-emerald-600' : 'border-blue-100 text-blue-600' }}
                            @elseif($log->type == 'case_created')
                                border-slate-100 text-slate-500
                            @elseif($log->type == 'workflow_change')
                                border-purple-100 text-purple-500
                            @elseif($log->type == 'escalation_sent')
                                border-rose-100 text-rose-600
                            @else
                                border-slate-100 text-slate-400
                            @endif">
                            
                            @if($direction === 'inbound') <i data-lucide="arrow-down-left" class="w-4 h-4"></i>
                            @elseif($log->type == 'email_sent') <i data-lucide="arrow-up-right" class="w-4 h-4"></i>
                            @elseif($log->type == 'escalation_sent') <i data-lucide="trending-up" class="w-4 h-4"></i>
                            @elseif($log->type == 'case_created') <i data-lucide="flag" class="w-3.5 h-3.5"></i>
                            @elseif($log->type == 'workflow_change') <i data-lucide="git-commit" class="w-3.5 h-3.5"></i>
                            @else <i data-lucide="circle" class="w-3 h-3"></i> @endif
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-bold text-slate-900 leading-none">
                                        {{ $log->readable_type ?? ucfirst(str_replace('_', ' ', $log->type)) }}
                                    </span>
                                    
                                    @if($log->type == 'email_sent' || $log->type == 'email_received' || $log->type == 'escalation_sent')
                                        @if($direction === 'inbound')
                                            <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-700 uppercase tracking-wide border border-emerald-200">Received</span>
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
                                <span class="text-[10px] font-semibold text-slate-400 uppercase bg-slate-50 px-1.5 py-0.5 rounded border border-slate-100 whitespace-nowrap">
                                    {{ $log->occurred_at ? $log->occurred_at->diffForHumans(null, true) : 'N/A' }}
                                </span>
                            </div>
                            <p class="text-xs text-slate-600 leading-relaxed line-clamp-2">{{ $log->description }}</p>
                            @if(in_array($log->type, ['email_sent', 'email_received', 'escalation_sent']))
                                <div class="mt-2 flex items-center gap-2">
                                    <button 
                                        type="button"
                                        @click="$dispatch('open-email', {
                                            emailId: '{{ $rawEmailId ?? '' }}',
                                            subject: '{{ addslashes($safeSubject) }}', 
                                            body: {{ json_encode($safeBody) }},
                                            direction: '{{ $direction }}', 
                                            attachments: {{ json_encode($attachmentsData) }},
                                            recipient: '{{ $safeRecipient }}'
                                        })"
                                        class="flex items-center gap-2 px-3.5 py-1.5 rounded-lg border border-indigo-100 bg-indigo-50/50 text-indigo-600 hover:bg-indigo-100 hover:text-indigo-700 hover:border-indigo-200 transition-all text-[11px] font-bold shadow-sm active:scale-95"
                                    >
                                        <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                                        <span>View Message</span>
                                    </button>

                                    @if($direction === 'inbound')
                                        {{-- REPLY BUTTON: Dispatches to Parent --}}
                                        <button 
                                            type="button"
                                            @click="$dispatch('open-compose-modal', { 
                                                subject: 'Re: {{ addslashes($safeSubject) }}', 
                                                recipient: '{{ addslashes($safeRecipient) }}',
                                                body: '\n\n\n--- Original Message ---\n',
                                                isEscalation: false
                                            })" 
                                            class="flex items-center gap-1.5 px-3 py-1.5 rounded-md border border-blue-200 bg-blue-50 hover:bg-blue-100 hover:border-blue-300 transition-all text-xs font-bold text-blue-600 shadow-sm">
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