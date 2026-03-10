{{-- VIEW STORY MODAL --}}
@if($showStoryModal && $selectedStory)
    <div class="fixed inset-0 z-[100] flex items-center justify-center p-4 sm:p-6">
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm animate-in fade-in" wire:click="$set('showStoryModal', false)"></div>
        
        <div class="relative bg-white rounded-[2rem] shadow-2xl w-full max-w-3xl flex flex-col overflow-hidden animate-in zoom-in-95 max-h-[90vh]">
            
            {{-- Header --}}
            <div class="px-8 py-6 border-b border-slate-100 flex items-center justify-between bg-white shrink-0">
                <div>
                    <h2 class="text-xl font-bold text-slate-900">Success Story Details</h2>
                    <p class="text-xs text-slate-500 mt-1">Submitted on {{ $selectedStory->created_at->format('M d, Y h:i A') }}</p>
                </div>
                <button wire:click="$set('showStoryModal', false)" class="p-2 text-slate-400 hover:text-slate-700 bg-slate-50 hover:bg-slate-100 rounded-full transition-colors">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            {{-- Content Body (Scrollable) --}}
            <div class="p-8 overflow-y-auto bg-white flex-1">
                
                {{-- Submitter Info Card --}}
                <div class="bg-slate-50 border border-slate-100 rounded-2xl p-5 mb-8 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full bg-white shadow-sm flex items-center justify-center text-primary-600 font-bold text-lg border border-slate-200">
                        {{ substr($selectedStory->first_name, 0, 1) }}
                    </div>
                    <div>
                        <div class="font-bold text-slate-900">{{ $selectedStory->first_name }}</div>
                        <div class="text-sm text-slate-500">{{ $selectedStory->email ?? 'No email provided' }}</div>
                    </div>
                    @if($selectedStory->user_id)
                        <div class="ml-auto bg-primary-100 text-primary-700 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider">
                            Registered User
                        </div>
                    @endif
                </div>

                {{-- The Story (Formatted cleanly for reading) --}}
                <div>
                    <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3">The Story</h3>
                    <div class="bg-white border border-slate-200 rounded-2xl p-6 sm:p-8 text-slate-800 text-[15px] leading-loose whitespace-pre-line shadow-sm">
                        {{ $selectedStory->story }}
                    </div>
                </div>

                {{-- Attached Media --}}
                @if(is_array($selectedStory->media_files) && count($selectedStory->media_files) > 0)
                    <div class="mt-8">
                        <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3">Attached Media</h3>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                            @foreach($selectedStory->media_files as $file)
                                <a href="{{ Storage::url($file) }}" target="_blank" class="group block border border-slate-200 rounded-xl overflow-hidden hover:border-primary-500 hover:shadow-md transition-all">
                                    @if(in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp']))
                                        <div class="aspect-video w-full bg-slate-100 relative">
                                            <img src="{{ Storage::url($file) }}" class="w-full h-full object-cover absolute inset-0">
                                        </div>
                                    @else
                                        <div class="aspect-video w-full bg-slate-50 flex flex-col items-center justify-center text-slate-400 group-hover:text-primary-600 transition-colors">
                                            <i data-lucide="file-text" class="w-8 h-8 mb-2"></i>
                                            <span class="text-[10px] font-bold uppercase">View Document</span>
                                        </div>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

            </div>
        </div>
    </div>
@endif