<div>
    @if($isOpen)
    <div class="fixed inset-0 z-[100] flex items-center justify-center p-4 sm:p-6">
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" wire:click="close"></div>

        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col overflow-hidden animate-in zoom-in-95 duration-200">
            
            <div class="px-6 py-4 border-b border-slate-100 flex items-start justify-between bg-slate-50/50">
                <div class="min-w-0 flex-1 mr-4">
                    <h3 class="text-sm font-bold text-slate-900 leading-tight truncate">{{ $subject }}</h3>
                    <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mt-1">Timeline Archive</p>
                </div>

                <div class="flex items-center gap-2">
                    <button wire:click="analyze" wire:loading.attr="disabled" class="flex items-center gap-1.5 px-3 py-1.5 bg-indigo-50 text-indigo-600 rounded-lg text-xs font-bold hover:bg-indigo-100 transition-all border border-indigo-100">
                        <div wire:loading wire:target="analyze" class="animate-spin w-3 h-3 border-2 border-indigo-600 border-t-transparent rounded-full"></div>
                        <i data-lucide="sparkles" class="w-3.5 h-3.5" wire:loading.remove wire:target="analyze"></i>
                        <span wire:loading.remove wire:target="analyze">AI Insights</span>
                        <span wire:loading wire:target="analyze">Analyzing...</span>
                    </button>

                    <button wire:click="close" class="p-1.5 rounded-lg hover:bg-slate-200 text-slate-400 transition-colors">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
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
                                <h4 class="text-[10px] font-bold text-indigo-400 uppercase tracking-widest mb-1">AI Copilot Analysis</h4>
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
                    <div class="text-sm text-slate-700 leading-relaxed prose prose-sm max-w-none">{!! $body !!}</div>

                    @if(count($attachments) > 0)
                        <div class="mt-10 border-t border-slate-100 pt-6">
                            <h4 class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-4">Files</h4>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                @foreach($attachments as $file)
                                    <a href="{{ $file['url'] }}" target="_blank" class="flex items-center gap-3 p-3 rounded-xl border border-slate-200 hover:bg-slate-50 transition-all">
                                        <div class="w-10 h-10 rounded-lg bg-white border border-slate-100 flex items-center justify-center">
                                            <i data-lucide="file" class="w-5 h-5 text-slate-400"></i>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-xs font-bold text-slate-700 truncate">{{ $file['name'] }}</p>
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <div class="px-6 py-4 border-t border-slate-100 bg-slate-50 flex justify-end">
                <button wire:click="close" class="px-4 py-2 bg-white border border-slate-200 rounded-lg text-xs font-bold text-slate-600 hover:bg-slate-100">
                    Close
                </button>
            </div>
        </div>
    </div>
    @endif
</div>