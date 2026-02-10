<div class="lg:col-span-5" 
    x-data="{ 
        // View Modal State
        viewModalOpen: false, 
        viewSubject: '', 
        viewBody: '', 
        
        // Compose Modal State
        composeModalOpen: false,
        replyTo: '',
        replySubject: '',
        replyBody: '',
        
        // Actions
        openViewer(subject, body) { 
            this.viewSubject = subject; 
            this.viewBody = body; 
            this.viewModalOpen = true; 
        },

        openReply(originalSubject, recipient) {
            this.replyTo = recipient;
            let prefix = originalSubject.toLowerCase().startsWith('re:') ? '' : 'Re: ';
            this.replySubject = prefix + originalSubject;
            this.replyBody = '\n\n\n--- Original Message ---\n'; 
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
            
            <button @click="openReply('Case #{{ $case->case_reference_id }}', '')" class="text-xs font-bold text-blue-600 hover:text-blue-700 hover:underline flex items-center gap-1 transition-colors">
                <i data-lucide="plus" class="w-3 h-3"></i> New Email
            </button>
        </div>
        
        <div class="p-6 flex-1">
            <div class="relative space-y-8">
                <div class="absolute top-2 bottom-2 left-4 w-0.5 bg-slate-100"></div>

                @foreach($case->timeline as $log)
                <div class="relative pl-12 group">
                    <div class="absolute left-0 top-0 bg-white border-2 border-slate-100 rounded-full w-8 h-8 flex items-center justify-center z-10 shadow-sm
                        {{ $log->type == 'email_sent' ? 'text-blue-500 border-blue-100' : 'text-slate-400' }}">
                        @if($log->type == 'email_sent') <i data-lucide="mail" class="w-3.5 h-3.5"></i>
                        @elseif($log->type == 'case_created') <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                        @else <i data-lucide="circle" class="w-3 h-3"></i> @endif
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="text-sm font-bold text-slate-900 leading-none">
                                {{ $log->readable_type ?? ucfirst(str_replace('_', ' ', $log->type)) }}
                            </span>
                            <span class="text-[10px] font-semibold text-slate-400 uppercase bg-slate-50 px-1.5 py-0.5 rounded border border-slate-100 whitespace-nowrap">
                                {{ $log->occurred_at ? $log->occurred_at->diffForHumans(null, true) : 'N/A' }}
                            </span>
                        </div>
                        
                        <p class="text-xs text-slate-600 leading-relaxed line-clamp-2">{{ $log->description }}</p>

                        @if(isset($log->metadata['full_body']))
                            <div class="mt-2 flex gap-2">
                                <button 
                                    @click="openViewer(
                                        {{ json_encode($log->metadata['subject'] ?? 'System Msg') }}, 
                                        {{ json_encode($log->metadata['full_body'] ?? '') }}
                                    )"
                                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-md border border-slate-200 bg-white hover:bg-slate-50 hover:border-blue-300 transition-all text-xs font-semibold text-slate-600 hover:text-blue-600 shadow-sm">
                                    <i data-lucide="eye" class="w-3.5 h-3.5"></i> View
                                </button>

                                <button 
                                    @click="openReply(
                                        {{ json_encode($log->metadata['subject'] ?? 'Case Inquiry') }}, 
                                        {{ json_encode($log->metadata['recipient'] ?? '') }}
                                    )"
                                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-md border border-slate-200 bg-white hover:bg-blue-50 hover:border-blue-300 transition-all text-xs font-semibold text-slate-600 hover:text-blue-600 shadow-sm">
                                    <i data-lucide="reply" class="w-3.5 h-3.5"></i> Reply
                                </button>
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