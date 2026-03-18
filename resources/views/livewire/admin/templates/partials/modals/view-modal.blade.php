{{-- view-modal.blade.php --}}
<div x-data="{ modalOpen: @entangle('showViewModal') }" 
     x-show="modalOpen" 
     x-cloak
     class="fixed inset-0 z-[100] flex items-center justify-center p-4 sm:p-6">
    
    <div x-on:click="modalOpen = false" x-transition.opacity class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm"></div>
    
    <div x-show="modalOpen"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95 translate-y-4"
         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100 translate-y-0"
         x-transition:leave-end="opacity-0 scale-95 translate-y-4"
         class="relative bg-white rounded-[2rem] shadow-2xl w-full max-w-3xl flex flex-col overflow-hidden max-h-full">
        
        {{-- Header --}}
        <div class="px-8 py-6 border-b flex items-center justify-between bg-white shrink-0">
            <div>
                <h2 class="text-xl font-bold text-slate-900">Template Details</h2>
            </div>
            <div class="flex items-center gap-2">
                {{-- Edit Button with Loading State --}}
                <button wire:click="edit({{ $template_id }})" 
                        wire:loading.attr="disabled"
                        class="flex items-center gap-2 px-4 py-2 bg-slate-50 hover:bg-slate-100 text-slate-600 rounded-xl text-xs font-bold transition-all disabled:opacity-70 disabled:cursor-not-allowed" 
                        title="Edit this template">
                    
                    {{-- Default Icon (Hides when loading) --}}
                    <i data-lucide="edit-3" class="w-4 h-4" wire:loading.remove wire:target="edit({{ $template_id }})"></i>
                    
                    {{-- Spinner Icon (Shows when loading) --}}
                    <i data-lucide="loader-2" class="w-4 h-4 animate-spin text-slate-600" wire:loading wire:target="edit({{ $template_id }})"></i>
                    
                    <span wire:loading.remove wire:target="edit({{ $template_id }})">Edit</span>
                    <span wire:loading wire:target="edit({{ $template_id }})">Opening...</span>
                </button>

                {{-- Close Button (Locks while editing is loading) --}}
                <button x-on:click="modalOpen = false" 
                        wire:loading.attr="disabled"
                        wire:target="edit({{ $template_id }})"
                        class="text-slate-400 hover:text-slate-600 transition-colors p-2 bg-slate-50 hover:bg-slate-100 rounded-xl disabled:opacity-50 disabled:cursor-not-allowed">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
        </div>

        {{-- Professional View Layout Body --}}
        <div class="p-8 overflow-y-auto custom-scrollbar flex flex-col gap-8 bg-slate-50/30">
            {{-- Header Info Card --}}
            <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-6">
                <div class="flex items-start gap-5">
                    <div class="w-14 h-14 rounded-2xl flex items-center justify-center shrink-0 bg-{{ $color ?: 'blue' }}-100 text-{{ $color ?: 'blue' }}-600 shadow-sm border border-{{ $color ?: 'blue' }}-200/50">
                        <i data-lucide="{{ $icon ?: 'file-text' }}" class="w-7 h-7"></i>
                    </div>
                    <div class="pt-1">
                        <h3 class="text-2xl font-bold text-slate-900 leading-none mb-3">{{ $title }}</h3>
                        <div class="flex flex-wrap items-center gap-3">
                            <span class="px-3 py-1.5 rounded-lg bg-white border border-slate-200 text-slate-600 text-[10px] font-bold uppercase tracking-wider shadow-sm">
                                {{ collect($categories)->firstWhere('id', $institution_category_id)->name ?? 'Uncategorized' }}
                            </span>
                            <span class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-white border border-slate-200 text-[10px] font-bold uppercase shadow-sm {{ $is_active ? 'text-emerald-600' : 'text-slate-400' }}">
                                <span class="w-2 h-2 rounded-full {{ $is_active ? 'bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.4)]' : 'bg-slate-300' }}"></span>
                                {{ $is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Description & Slug --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="md:col-span-2 space-y-2">
                    <h4 class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Description</h4>
                    <p class="text-sm text-slate-700 leading-relaxed">{{ $description }}</p>
                </div>
                <div class="space-y-2">
                    <h4 class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">URL Slug</h4>
                    <div class="px-4 py-2.5 bg-slate-100/80 border border-slate-200 rounded-xl text-xs font-mono text-slate-600 break-all select-all">
                        /{{ $slug }}
                    </div>
                </div>
            </div>

            {{-- Document Content Preview --}}
            <div>
                <div class="flex items-center justify-between mb-2">
                    <h4 class="text-[11px] font-bold text-slate-400 uppercase tracking-wider flex items-center gap-2">
                        <i data-lucide="file-text" class="w-4 h-4"></i> Document Content
                    </h4>
                </div>
                <textarea wire:model="content" 
                          readonly 
                          rows="12" 
                          class="w-full px-4 py-3 rounded-xl border border-slate-200 text-sm font-mono leading-relaxed outline-none resize-y min-h-[250px] bg-white text-slate-700 custom-scrollbar shadow-sm"></textarea>
            </div>
        </div>

        {{-- Footer --}}
        <div class="px-8 py-5 bg-slate-50 border-t flex justify-end gap-3 shrink-0">
            <button x-on:click="modalOpen = false" class="px-6 py-2.5 text-sm font-bold text-slate-500 hover:text-slate-800 transition-colors">
                Close
            </button>
        </div>
    </div>
</div>