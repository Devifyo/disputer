<div class="lg:col-span-5" 
    x-data="{ 
        // View Modal State
        viewModalOpen: false, 
        viewSubject: '', 
        viewBody: '', 
        viewAttachments: [], // <--- NEW: Array to hold attachments
        
        // Compose Modal State
        composeModalOpen: false,
        replyTo: '',
        replySubject: '',
        replyBody: '',
        replyParentId: null, 
        
        // Actions
        openViewer(subject, body, attachments = []) { 
            this.viewSubject = subject; 
            this.viewBody = body; 
            this.viewAttachments = attachments; // <--- NEW: Set attachments
            this.viewModalOpen = true; 
        },

        openReply(originalSubject, recipient, encryptedParentId = null) {
            this.replyTo = recipient;
            let subjectStr = String(originalSubject || 'Case #{{ $case->case_reference_id }}');
            if (encryptedParentId) {
                let prefix = subjectStr.toLowerCase().startsWith('re:') ? '' : 'Re: ';
                this.replySubject = prefix + subjectStr;
                this.replyBody = '\n\n\n--- Original Message ---\n'; 
            } else {
                this.replySubject = subjectStr;
                this.replyBody = ''; 
            }
            this.replyParentId = encryptedParentId; 
            this.composeModalOpen = true;
        }
    }"
>
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm h-full flex flex-col">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/30">
            <div class="flex items-center gap-2">
                <i data-lucide="activity" class="w-4 h-4 text-slate-400"></i>
                <h3 class="font-bold text-slate-800 text-sm">Activity Log</h3>
            </div>
            
            <button @click="openReply('Case #{{ $case->case_reference_id }}', '', null)" 
                    class="text-xs font-bold text-blue-600 hover:text-blue-700 hover:underline flex items-center gap-1 transition-colors">
                <i data-lucide="plus" class="w-3 h-3"></i> New Email
            </button>
        </div>
        
        <div class="p-6 flex-1">
            <div class="relative space-y-8">
                <div class="absolute top-2 bottom-2 left-4 w-0.5 bg-slate-100"></div>

                @foreach($case->timeline as $log)
                    @php
                        $direction = $log->metadata['direction'] ?? 'outbound'; 
                        if(str_contains($log->type, 'received')) { $direction = 'inbound'; }

                        $rawEmailId = $log->metadata['email_id'] ?? null;
                        $encryptedEmailId = $rawEmailId ? encrypt_id($rawEmailId) : null;

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
                        $attachmentsData = []; // Default empty
                        
                        if ($rawEmailId) {
                            $linkedEmail = \App\Models\Email::find($rawEmailId);
                            if ($linkedEmail) {
                                // Fallback Body
                                if (empty($rawBody)) {
                                    $rawBody = $linkedEmail->body_html ?? $linkedEmail->body_text ?? '';
                                }

                                // Get Attachments
                                // Assuming you have a relationship defined: $email->attachments()
                                $attachments = \App\Models\Attachment::where('email_id', $rawEmailId)->get();
                                foreach($attachments as $att) {
                                    $attachmentsData[] = [
                                        'name' => $att->file_name,
                                        'url'  => asset('storage/' . $att->file_path), // Assumes standard Laravel storage link
                                        'type' => $att->mime_type
                                    ];
                                }
                            }
                        }
                        
                        $safeBody = (string) $rawBody;
                    @endphp

                    <div class="relative pl-12 group">
                        <div class="absolute left-0 top-0 border-2 rounded-full w-8 h-8 flex items-center justify-center z-10 shadow-sm bg-white
                            @if($log->type == 'email_sent' || $log->type == 'email_received')
                                {{ $direction === 'inbound' ? 'border-emerald-100 text-emerald-600' : 'border-blue-100 text-blue-600' }}
                            @elseif($log->type == 'case_created')
                                border-slate-100 text-slate-500
                            @else
                                border-slate-100 text-slate-400
                            @endif">
                            @if($direction === 'inbound') <i data-lucide="arrow-down-left" class="w-4 h-4"></i>
                            @elseif($log->type == 'email_sent') <i data-lucide="arrow-up-right" class="w-4 h-4"></i>
                            @elseif($log->type == 'case_created') <i data-lucide="flag" class="w-3.5 h-3.5"></i>
                            @else <i data-lucide="circle" class="w-3 h-3"></i> @endif
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-bold text-slate-900 leading-none">
                                        {{ $log->readable_type ?? ucfirst(str_replace('_', ' ', $log->type)) }}
                                    </span>
                                    
                                    @if($log->type == 'email_sent' || $log->type == 'email_received')
                                        @if($direction === 'inbound')
                                            <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-700 uppercase tracking-wide border border-emerald-200">Received</span>
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

                            @if($log->type == 'email_sent' || $log->type == 'email_received')
                                <div class="mt-2 flex items-center gap-2">
                                    
                                    <button 
                                        @click="openViewer(
                                            '{{ addslashes($safeSubject) }}', 
                                            {{ json_encode($safeBody ?: 'No content available.') }},
                                            {{ json_encode($attachmentsData) }} 
                                        )"
                                        class="flex items-center gap-1.5 px-3 py-1.5 rounded-md border border-slate-200 bg-white hover:bg-slate-50 hover:border-slate-300 transition-all text-xs font-bold text-slate-600 hover:text-slate-900 shadow-sm">
                                        <i data-lucide="eye" class="w-3.5 h-3.5"></i> View
                                    </button>

                                    @if($direction === 'inbound')
                                        <button 
                                            @click="openReply(
                                                '{{ addslashes($safeSubject) }}', 
                                                '{{ addslashes($safeRecipient) }}',
                                                '{{ $encryptedEmailId }}' 
                                            )"
                                            class="flex items-center gap-1.5 px-3 py-1.5 rounded-md border border-blue-200 bg-blue-50 hover:bg-blue-100 hover:border-blue-300 transition-all text-xs font-bold text-blue-600 shadow-sm">
                                            <i data-lucide="reply" class="w-3.5 h-3.5"></i> Reply
                                        </button>
                                    @else
                                        <div class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold text-slate-400 bg-slate-50 border border-slate-100 rounded-md cursor-default select-none">
                                            <i data-lucide="check-circle" class="w-3.5 h-3.5 text-slate-400"></i> Sent
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
                
                @if($case->timeline->isEmpty())
                    <div class="text-center py-8 relative z-10 bg-white">
                        <p class="text-xs text-slate-400 italic">No activity recorded yet.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    @include('user.cases.partials.modals.view_email')
    @include('user.cases.partials.modals.compose_email')
</div>