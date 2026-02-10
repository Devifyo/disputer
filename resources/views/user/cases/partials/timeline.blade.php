<div class="lg:col-span-5" 
    x-data="{ 
        // Viewer Modal State
        viewModalOpen: false, 
        viewSubject: '', 
        viewBody: '', 
        
        // Reply/Compose Modal State
        composeModalOpen: false,
        replyTo: '',
        replySubject: '',
        replyBody: '',
        
        openViewer(subject, body) { 
            this.viewSubject = subject; 
            this.viewBody = body.replace(/\\n/g, '\n').replace(/\\r/g, ''); 
            this.viewModalOpen = true; 
        },

        openReply(originalSubject, recipient) {
            this.replyTo = recipient;
            // Add 'Re:' if not already there
            this.replySubject = originalSubject.startsWith('Re:') ? originalSubject : 'Re: ' + originalSubject;
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
            <button @click="openReply('Case #{{ $case->case_reference_id }}', '')" class="text-xs font-bold text-blue-600 hover:text-blue-700 hover:underline">
                New Email
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
                        
                        <p class="text-xs text-slate-600 leading-relaxed">{{ $log->description }}</p>

                        @if(isset($log->metadata['full_body']))
                            <div class="mt-2 flex gap-2">
                                <button 
                                    @click="openViewer(
                                        '{{ addslashes($log->metadata['subject'] ?? 'System Communication') }}', 
                                        '{{ addslashes($log->metadata['full_body']) }}'
                                    )"
                                    class="flex-1 flex items-center justify-center gap-2 p-2 rounded-lg border border-slate-200 bg-slate-50/50 hover:bg-white hover:border-blue-200 hover:shadow-sm transition-all text-xs font-semibold text-slate-600 hover:text-blue-600">
                                    <i data-lucide="eye" class="w-3.5 h-3.5"></i> View
                                </button>

                                <button 
                                    @click="openReply(
                                        '{{ addslashes($log->metadata['subject'] ?? 'Case Inquiry') }}', 
                                        '{{ $log->metadata['recipient'] ?? '' }}'
                                    )"
                                    class="flex-1 flex items-center justify-center gap-2 p-2 rounded-lg border border-slate-200 bg-slate-50/50 hover:bg-blue-600 hover:border-blue-600 hover:shadow-sm transition-all text-xs font-semibold text-slate-600 hover:text-white group/reply">
                                    <i data-lucide="reply" class="w-3.5 h-3.5 group-hover/reply:text-white"></i> Reply
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

    <div x-show="viewModalOpen" style="display: none;" 
        class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6"
        x-cloak>
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="viewModalOpen = false"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[85vh] flex flex-col overflow-hidden ring-1 ring-slate-900/5">
            <div class="px-6 py-5 border-b border-slate-100 flex items-start justify-between bg-slate-50/80">
                <div class="min-w-0 flex-1 mr-4">
                    <h3 class="text-lg font-bold text-slate-900 leading-tight truncate" x-text="viewSubject"></h3>
                </div>
                <button @click="viewModalOpen = false" class="p-2 rounded-lg hover:bg-slate-200 text-slate-400"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            <div class="p-6 overflow-y-auto bg-white flex-1">
                <div class="p-4 rounded-xl bg-slate-50 border border-slate-100 text-sm text-slate-700 font-mono whitespace-pre-wrap" x-text="viewBody"></div>
            </div>
        </div>
    </div>

    <div x-show="composeModalOpen" style="display: none;" 
        class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6"
        x-cloak>
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="composeModalOpen = false"></div>
        
        <form action="{{ route('user.cases.send_email', $case->id) }}" method="POST" class="relative bg-white rounded-2xl shadow-2xl w-full max-w-2xl flex flex-col overflow-hidden ring-1 ring-slate-900/5">
            @csrf
            
            <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/80 flex justify-between items-center">
                <h3 class="font-bold text-slate-900 flex items-center gap-2">
                    <i data-lucide="mail-plus" class="w-4 h-4 text-blue-600"></i> Compose Reply
                </h3>
                <button type="button" @click="composeModalOpen = false" class="text-slate-400 hover:text-slate-600"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>

            <div class="p-6 space-y-4">
                <div class="grid grid-cols-1 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">To</label>
                        <input type="email" name="recipient" x-model="replyTo" class="w-full rounded-lg border-slate-200 text-sm focus:ring-blue-500 focus:border-blue-500" placeholder="recipient@example.com" required>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Subject</label>
                        <input type="text" name="subject" x-model="replySubject" class="w-full rounded-lg border-slate-200 text-sm focus:ring-blue-500 focus:border-blue-500" required>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Message</label>
                    <textarea name="body" x-model="replyBody" rows="8" class="w-full rounded-lg border-slate-200 text-sm focus:ring-blue-500 focus:border-blue-500" placeholder="Type your reply here..."></textarea>
                </div>
                
                <div class="flex items-center gap-2 text-xs text-slate-500 bg-blue-50 p-2 rounded border border-blue-100">
                    <i data-lucide="info" class="w-4 h-4 text-blue-500"></i>
                    Sending via your configured SMTP server.
                </div>
            </div>

            <div class="px-6 py-4 border-t border-slate-100 bg-slate-50 flex justify-end gap-3">
                <button type="button" @click="composeModalOpen = false" class="px-4 py-2 bg-white border border-slate-300 rounded-lg text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-bold hover:bg-blue-700 shadow-md flex items-center gap-2">
                    <i data-lucide="send" class="w-4 h-4"></i> Send Email
                </button>
            </div>
        </form>
    </div>
</div>