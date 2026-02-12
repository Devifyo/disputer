<div>
    @if($isOpen)
    <div class="fixed inset-0 z-[100] flex items-center justify-center p-4 sm:p-6" x-cloak>
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" wire:click="close"></div>

        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-3xl max-h-[90vh] flex flex-col overflow-hidden animate-in zoom-in-95 duration-200">
            
            <div class="px-6 py-3 border-b border-slate-100 flex items-center justify-between bg-white">
                <div class="flex items-center gap-2">
                    <div class="w-2 h-2 rounded-full bg-blue-600"></div>
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Message Details</span>
                </div>

                <div class="flex items-center gap-2">
                    <button wire:click="analyze" wire:loading.attr="disabled" 
                            class="flex items-center gap-1.5 px-3 py-1.5 bg-indigo-50 text-indigo-600 rounded-lg text-xs font-bold hover:bg-indigo-100 transition-all border border-indigo-100">
                        <div wire:loading wire:target="analyze" class="animate-spin w-3 h-3 border-2 border-indigo-600 border-t-transparent rounded-full"></div>
                        <i data-lucide="sparkles" class="w-3.5 h-3.5" wire:loading.remove wire:target="analyze"></i>
                        <span wire:loading.remove wire:target="analyze">AI Analysis</span>
                        <span wire:loading wire:target="analyze">Analyzing...</span>
                    </button>

                    <button wire:click="close" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-400 transition-colors">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>
            </div>

            <div class="px-8 py-6 bg-slate-50/50 border-b border-slate-100">
                <h2 class="text-xl font-bold text-slate-900 mb-4 leading-tight">{{ $subject }}</h2>
                
                <div class="space-y-2">
                    <div class="flex items-center text-sm">
                        <span class="w-16 text-slate-400 font-medium">To:</span>
                        <span class="font-bold text-slate-700">{{ $recipient_email ?? 'Support Team' }}</span>
                    </div>
                    <div class="flex items-center text-sm">
                        <span class="w-16 text-slate-400 font-medium">Subject:</span>
                        <span class="text-slate-600">{{ $subject }}</span>
                    </div>
                </div>
            </div>

            <div class="p-0 overflow-y-auto bg-white flex-1">
                @if($isAnalyzing || $analysis)
                    <div class="p-6 bg-indigo-50/30 border-b border-indigo-100 animate-in slide-in-from-top duration-300">
                        <div class="flex items-start gap-3">
                            <div class="p-2 bg-white rounded-lg border border-indigo-100 text-indigo-600 shadow-sm">
                                <i data-lucide="zap" class="w-4 h-4"></i>
                            </div>
                            <div class="flex-1">
                                <h4 class="text-[10px] font-bold text-indigo-400 uppercase tracking-widest mb-1">Copilot Insights</h4>
                                @if($isAnalyzing)
                                    <div class="space-y-2 mt-2">
                                        <div class="h-2 bg-indigo-100 rounded-full w-3/4 animate-pulse"></div>
                                        <div class="h-2 bg-indigo-100 rounded-full w-1/2 animate-pulse"></div>
                                    </div>
                                @else
                                    <p class="text-sm text-indigo-900 leading-relaxed font-medium">{{ $analysis }}</p>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif

                <div class="p-8">
                    <div class="text-slate-700 leading-relaxed prose prose-slate max-w-none 
                                prose-p:my-4 prose-strong:text-slate-900 prose-blockquote:border-l-4 prose-blockquote:border-slate-200 prose-blockquote:pl-4 prose-blockquote:italic">
                        {!! nl2br(e($body)) !!}
                    </div>

                    @if(count($attachments) > 0)
                        <div class="mt-12 pt-8 border-t border-slate-100">
                            <div class="flex items-center gap-2 mb-4">
                                <i data-lucide="paperclip" class="w-4 h-4 text-slate-400"></i>
                                <h4 class="text-xs font-bold text-slate-400 uppercase tracking-widest">Attached Evidence ({{ count($attachments) }})</h4>
                            </div>
                            
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                @foreach($attachments as $file)
                                    <a href="{{ $file['url'] }}" target="_blank" class="group flex items-center gap-3 p-3 rounded-xl border border-slate-200 hover:border-blue-400 hover:bg-blue-50/50 transition-all bg-white shadow-sm">
                                        <div class="w-10 h-10 rounded-lg bg-slate-50 border border-slate-100 flex items-center justify-center group-hover:bg-white transition-colors">
                                            @php
                                                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                                                $isImage = in_array(strtolower($ext), ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                                            @endphp
                                            
                                            @if($isImage)
                                                <i data-lucide="image" class="w-5 h-5 text-blue-500"></i>
                                            @else
                                                <i data-lucide="file-text" class="w-5 h-5 text-slate-400"></i>
                                            @endif
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-xs font-bold text-slate-700 truncate group-hover:text-blue-700">{{ $file['name'] }}</p>
                                            <p class="text-[10px] text-slate-400 uppercase">{{ $ext }} file</p>
                                        </div>
                                        <i data-lucide="external-link" class="w-3.5 h-3.5 text-slate-300 group-hover:text-blue-500"></i>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <div class="px-8 py-4 border-t border-slate-100 bg-white flex justify-end">
                <button wire:click="close" class="px-6 py-2 bg-slate-900 text-white rounded-lg text-xs font-bold hover:bg-slate-800 transition-all shadow-md active:scale-95">
                    Close Message
                </button>
            </div>
        </div>
    </div>
    @endif
</div>