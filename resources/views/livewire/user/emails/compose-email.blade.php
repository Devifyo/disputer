<div class="min-h-screen bg-[#F8FAFC] flex flex-col">

<header class="px-6 py-4 flex items-center justify-between sticky top-0 z-30 bg-[#F8FAFC]/90 backdrop-blur-sm">
        <div class="flex items-center gap-4">
            <a href="{{ route('user.emails.index') }}" class="p-2 -ml-2 rounded-full text-slate-400 hover:text-slate-600 hover:bg-white transition-all">
                <i data-lucide="x" class="w-6 h-6"></i>
            </a>
            <h1 class="text-lg font-bold text-slate-900 tracking-tight">New Message</h1>
        </div>
        
        <div class="text-xs font-medium flex items-center justify-end min-w-[120px]">
            
            <span wire:loading wire:target="send" class="text-blue-600 flex items-center gap-1">
                <i data-lucide="loader" class="w-3 h-3 animate-spin"></i> Sending...
            </span>

            <span wire:dirty wire:loading.remove wire:target="send" class="text-amber-600 flex items-center gap-1">
                <div class="w-1.5 h-1.5 rounded-full bg-amber-500"></div> Unsaved changes
            </span>

            <span wire:dirty.remove wire:loading.remove wire:target="send" class="text-slate-400 flex items-center gap-1">
                <i data-lucide="check" class="w-3 h-3 text-emerald-500"></i> Draft saved
            </span>

        </div>
    </header>

    <div class="flex-1 overflow-y-auto pb-20">
        <div class="max-w-3xl mx-auto px-6">
            
            <form wire:submit.prevent="send" class="bg-white rounded-2xl shadow-xl shadow-slate-200/40 border border-slate-200 overflow-hidden relative">
                
                <div class="bg-slate-50/50 border-b border-slate-100 p-6 space-y-5">
                    
                    <div class="relative group z-20">
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5">Regarding Case</label>
                        <div class="relative">
                            <i data-lucide="briefcase" class="absolute left-3.5 top-3.5 w-4 h-4 text-slate-400"></i>
                            <select wire:model="case_id" class="w-full bg-white border border-slate-200 rounded-xl pl-10 pr-10 py-3 text-sm font-semibold text-slate-700 focus:border-blue-500 focus:ring-0 shadow-sm">
                                <option value="" disabled>Select a dispute case...</option>
                                <option value="1023">Case #1023 • Chase Bank (Billing Dispute)</option>
                                <option value="1045">Case #1045 • American Airlines (Refund)</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5">To</label>
                            <div class="relative">
                                <i data-lucide="at-sign" class="absolute left-3.5 top-3 w-4 h-4 text-slate-400"></i>
                                <input type="email" wire:model="recipient" class="w-full bg-white border border-slate-200 rounded-lg pl-10 pr-3 py-2.5 text-sm font-semibold text-slate-900 focus:border-blue-500 focus:ring-0 shadow-sm" placeholder="recipient@institution.com">
                            </div>
                            @error('recipient') <span class="text-xs text-red-500 font-bold mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5">Subject</label>
                            <input type="text" wire:model="subject" class="w-full bg-white border border-slate-200 rounded-lg px-3 py-2.5 text-sm font-semibold text-slate-900 focus:border-blue-500 focus:ring-0 shadow-sm" placeholder="Subject line...">
                            @error('subject') <span class="text-xs text-red-500 font-bold mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>

                <div class="px-6 py-3 bg-gradient-to-r from-blue-50 to-indigo-50 border-b border-blue-100 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="p-1.5 bg-blue-100 text-blue-600 rounded-md"><i data-lucide="sparkles" class="w-4 h-4"></i></div>
                        <div>
                            <p class="text-xs font-bold text-blue-900">Need help writing?</p>
                            <p class="text-[10px] text-blue-600/80 font-medium">Use an attorney-crafted template.</p>
                        </div>
                    </div>
                    <button type="button" wire:click="$set('showTemplateModal', true)" class="px-3 py-1.5 bg-white text-blue-700 text-xs font-bold rounded-md shadow-sm border border-blue-100 hover:bg-blue-50 transition-all">
                        Browse Library
                    </button>
                </div>

                <div class="p-6">
                    <textarea wire:model="body" rows="12" class="w-full text-sm text-slate-700 leading-relaxed border-0 focus:ring-0 p-0 resize-none placeholder:text-slate-300 bg-transparent" placeholder="Type your message here..."></textarea>
                    @error('body') <span class="text-xs text-red-500 font-bold mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div class="px-6 pb-6 border-t border-slate-50 pt-6 mx-6 mt-2">
                    <label class="group flex items-center justify-center gap-4 p-5 border-2 border-dashed border-slate-300 rounded-xl hover:bg-slate-50 hover:border-blue-400 cursor-pointer transition-all relative overflow-hidden mb-4">
                        <div class="flex flex-col items-center gap-2 text-center">
                            <div class="w-10 h-10 rounded-full bg-slate-100 group-hover:bg-blue-100 text-slate-400 group-hover:text-blue-500 flex items-center justify-center transition-colors">
                                <i data-lucide="cloud-upload" class="w-5 h-5"></i>
                            </div>
                            <p class="text-sm font-bold text-slate-700 group-hover:text-blue-700 transition-colors">
                                <span wire:loading.remove wire:target="attachments">Click to upload evidence</span>
                                <span wire:loading wire:target="attachments">Uploading...</span>
                            </p>
                        </div>
                        <input type="file" wire:model="attachments" multiple class="hidden">
                    </label>

                    @if(count($attachments) > 0)
                    <div class="space-y-3">
                        @foreach($attachments as $index => $file)
                        <div class="flex items-center gap-3 p-3 bg-white border border-slate-200 rounded-lg shadow-sm hover:shadow-md transition-all group relative">
                            <div class="w-10 h-10 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center shrink-0 border border-indigo-100">
                                <i data-lucide="file-text" class="w-5 h-5"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-bold text-slate-700 truncate">{{ $file->getClientOriginalName() }}</p>
                                <p class="text-[10px] text-slate-400 font-medium">{{ round($file->getSize() / 1024, 2) }} KB</p>
                            </div>
                            <button type="button" wire:click="removeAttachment({{ $index }})" class="p-2 text-slate-300 hover:text-red-500 hover:bg-red-50 rounded-lg transition-colors cursor-pointer">
                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                            </button>
                        </div>
                        @endforeach
                    </div>
                    @endif
                </div>

                <div class="px-6 py-4 bg-slate-50 border-t border-slate-200 flex items-center justify-between sticky bottom-0">
                    
                    <button type="button" wire:click="discard" class="text-slate-500 hover:text-red-600 text-xs font-bold transition-colors flex items-center gap-1.5">
                        <i data-lucide="trash-2" class="w-3.5 h-3.5"></i> Discard
                    </button>

                    <button type="submit" class="bg-slate-900 hover:bg-slate-800 text-white px-6 py-2 rounded-lg text-sm font-bold shadow-lg shadow-slate-900/20 transition-all flex items-center gap-2" wire:loading.attr="disabled">
                        
                        <span wire:loading.remove wire:target="send">Send Message</span>
                        <i data-lucide="send" class="w-3.5 h-3.5" wire:loading.remove wire:target="send"></i>
                        
                        <span wire:loading wire:target="send" class="flex items-center gap-2">
                            <i data-lucide="loader" class="w-3.5 h-3.5 animate-spin"></i> Sending...
                        </span>
                    </button>
                </div>

            </form>
        </div>
    </div>

    @if($showTemplateModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6">
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" wire:click="$set('showTemplateModal', false)"></div>
        
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-2xl flex flex-col overflow-hidden max-h-[80vh]">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/80">
                <h3 class="font-bold text-slate-900">Select a Template</h3>
                <button wire:click="$set('showTemplateModal', false)" class="p-2 rounded-lg hover:bg-slate-200 text-slate-400 transition-colors"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>

            <div class="flex-1 overflow-y-auto p-4 space-y-3 bg-slate-50">
                <div class="relative mb-4">
                    <i data-lucide="search" class="absolute left-3 top-2.5 w-4 h-4 text-slate-400"></i>
                    <input type="text" wire:model.live.debounce.300ms="searchQuery" placeholder="Search templates..." class="w-full bg-white border border-slate-200 rounded-lg pl-9 pr-4 py-2 text-sm focus:border-blue-500 focus:ring-0">
                </div>

                @forelse($this->templates as $template)
                <div wire:click="applyTemplate({{ $template->id }})" class="bg-white p-4 rounded-xl border border-slate-200 hover:border-blue-400 hover:shadow-md cursor-pointer transition-all group">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center shrink-0 group-hover:bg-blue-600 group-hover:text-white transition-colors">
                            <i data-lucide="file-text" class="w-5 h-5"></i>
                        </div>
                        <div>
                            <h4 class="text-sm font-bold text-slate-900 group-hover:text-blue-700">{{ $template->title }}</h4>
                            <p class="text-xs text-slate-500 mt-0.5">{{ $template->category->name ?? 'General' }}</p>
                        </div>
                    </div>
                </div>
                @empty
                    <div class="text-center py-8 text-slate-400 text-sm">No templates found.</div>
                @endforelse
            </div>
        </div>
    </div>
    @endif
</div>