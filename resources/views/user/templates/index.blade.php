@extends('layouts.app')

@section('title', 'Letter Library')

@section('content')
    <header class="h-16 sticky top-0 z-30 flex items-center justify-between px-4 sm:px-6 lg:px-8 bg-white/80 backdrop-blur-md border-b border-slate-200 transition-all">
        <div class="flex items-center gap-4">
            {{-- <button id="open-sidebar" class="lg:hidden p-2 -ml-2 text-slate-400 hover:text-slate-700 hover:bg-slate-100 rounded-lg transition-colors">
                <i data-lucide="menu" class="w-5 h-5"></i>
            </button> --}}
            <h1 class="font-bold text-slate-900 text-lg tracking-tight flex items-center gap-2">
                Letter Templates
                <span class="hidden sm:flex h-5 px-2 items-center justify-center rounded-full bg-slate-100 text-[10px] font-bold text-slate-600 border border-slate-200">
                    {{ $templates->count() }} Available
                </span>
            </h1>
        </div>
    </header>

    <div class="flex-1 overflow-y-auto bg-slate-50 p-4 sm:p-6 lg:p-8" 
         x-data="{ 
             modalOpen: false, 
             activeTemplate: null,
             copyToClipboard(text) {
                 if (!text) return;
                 // Replace placeholders like [CURRENT_DATE] with real data dynamically if needed
                 // const final = text.replace('[CURRENT_DATE]', new Date().toLocaleDateString());
                 
                 navigator.clipboard.writeText(text);
                 
                 // Simple Toast Notification
                 const toast = document.createElement('div');
                 toast.className = 'fixed bottom-4 right-4 bg-slate-900 text-white px-4 py-2 rounded-lg text-sm font-bold shadow-xl animate-bounce';
                 toast.innerText = 'Template copied to clipboard!';
                 document.body.appendChild(toast);
                 setTimeout(() => toast.remove(), 2000);
             }
         }">
        
        <div class="max-w-7xl mx-auto space-y-8">

            <div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
                <div class="max-w-2xl">
                    <h2 class="text-2xl font-bold text-slate-900 tracking-tight">Professional Dispute Blueprints</h2>
                    <p class="text-slate-500 mt-2 text-sm leading-relaxed">
                        Don't know what to write? Use these attorney-crafted templates to communicate effectively with banks, bureaus, and merchants.
                    </p>
                </div>

                <form action="{{ route('user.templates.index') }}" method="GET" class="relative group w-full md:w-72">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i data-lucide="search" class="w-4 h-4 text-slate-400 group-focus-within:text-blue-500 transition-colors"></i>
                    </div>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search templates..." 
                           class="block w-full pl-10 pr-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm text-slate-900 placeholder:text-slate-400 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all shadow-sm">
                </form>
            </div>

            @if($templates->count() > 0)
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                @foreach($templates as $template)
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm hover:shadow-md transition-all duration-300 group flex flex-col h-full">
                    
                    <div class="p-6 pb-4">
                        <div class="flex justify-between items-start mb-4">
                            @php
                                $colors = [
                                    'blue' => 'bg-blue-50 text-blue-600',
                                    'red' => 'bg-red-50 text-red-600',
                                    'emerald' => 'bg-emerald-50 text-emerald-600',
                                    'slate' => 'bg-slate-100 text-slate-600',
                                    'orange' => 'bg-orange-50 text-orange-600',
                                    'sky' => 'bg-sky-50 text-sky-600',
                                    'violet' => 'bg-violet-50 text-violet-600',
                                ];
                                $style = $colors[$template->color] ?? $colors['slate'];
                            @endphp
                            <div class="w-10 h-10 rounded-lg {{ $style }} flex items-center justify-center shrink-0">
                                <i data-lucide="{{ $template->icon }}" class="w-5 h-5"></i>
                            </div>
                            
                            <span class="inline-flex items-center px-2 py-1 rounded-md bg-slate-50 border border-slate-100 text-[10px] font-bold text-slate-500 uppercase tracking-wide">
                                {{ $template->category->name ?? 'General' }}
                            </span>
                        </div>

                        <h3 class="font-bold text-slate-900 text-base mb-2 group-hover:text-blue-600 transition-colors">
                            {{ $template->title }}
                        </h3>
                        <p class="text-sm text-slate-500 leading-relaxed line-clamp-2">
                            {{ $template->description }}
                        </p>
                    </div>

                    <div class="h-px bg-slate-50 w-full mt-auto"></div>

                    <div class="p-4 pt-4 flex items-center gap-2">
                        <button 
                            @click="
                                // 1. Load the template data safely using Laravel's json_encode
                                activeTemplate = {{ json_encode($template) }}; 
                                
                                // 2. Add the category name
                                activeTemplate.category_name = '{{ $template->category->name ?? 'General' }}'; 
                                
                                // 3. Create a pristine copy of the content immediately using JS (Safest way)
                                activeTemplate.original_content = activeTemplate.content; 
                                
                                // 4. Open Modal
                                modalOpen = true;
                            "
                            class="flex-1 py-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 rounded-lg text-xs font-bold transition-all flex items-center justify-center gap-2">
                            <i data-lucide="eye" class="w-3.5 h-3.5 text-slate-400"></i> Preview
                        </button>
                        
                        <button 
                            @click="copyToClipboard({{ json_encode($template->content) }})"
                            class="flex-1 py-2 bg-slate-900 hover:bg-slate-800 text-white border border-transparent rounded-lg text-xs font-bold transition-all flex items-center justify-center gap-2 active:scale-95">
                            <i data-lucide="copy" class="w-3.5 h-3.5"></i> Copy Text
                        </button>
                    </div>
                </div>
                @endforeach
            </div>
            {{-- pagination --}}
            <div class="mt-8">
                    {{ $templates->withQueryString()->links() }}
            </div>
            @else
                <div class="text-center py-20 bg-white rounded-xl border border-dashed border-slate-200">
                    <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i data-lucide="file-question" class="w-8 h-8 text-slate-400"></i>
                    </div>
                    <h3 class="text-slate-900 font-bold">No templates found</h3>
                    <p class="text-slate-500 text-sm mt-1">Try adjusting your search terms.</p>
                </div>
            @endif
        </div>

        {{-- modal --}}
        {{-- modal --}}
        <div x-show="modalOpen" style="display: none;" 
             class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
        >
            
            <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="modalOpen = false"></div>
            
            <template x-if="activeTemplate">
                
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-3xl flex flex-col overflow-hidden ring-1 ring-slate-900/5 h-[85vh]"
                     x-transition:enter="transition ease-out duration-300"
                     x-transition:enter-start="opacity-0 translate-y-8 scale-95"
                     x-transition:enter-end="opacity-100 translate-y-0 scale-100">
                    
                    <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/80 shrink-0">
                        <div>
                            <h3 class="font-bold text-slate-900" x-text="activeTemplate.title"></h3>
                            <div class="flex items-center gap-2 mt-1">
                                <span class="text-xs text-slate-500" x-text="activeTemplate.category_name"></span>
                                <span class="text-[10px] bg-blue-50 text-blue-600 px-1.5 py-0.5 rounded font-bold uppercase tracking-wide border border-blue-100">Editable Mode</span>
                            </div>
                        </div>
                        <button @click="modalOpen = false" class="p-2 rounded-lg hover:bg-slate-200 text-slate-400 transition-colors">
                            <i data-lucide="x" class="w-5 h-5"></i>
                        </button>
                    </div>

                    <div class="flex-1 bg-slate-100/50 p-4 sm:p-6 overflow-hidden flex flex-col">
                        
                        <div class="flex justify-between items-center mb-2 px-1">
                            <label class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                                <i data-lucide="pen-line" class="w-3 h-3 inline mr-1"></i> Edit text before copying
                            </label>
                            <button @click="activeTemplate.content = activeTemplate.original_content" class="text-[10px] text-slate-400 hover:text-red-500 underline decoration-dotted transition-colors">
                                Reset to Original
                            </button>
                        </div>

                        <textarea 
                            x-model="activeTemplate.content"
                            class="flex-1 w-full p-6 rounded-xl border-0 shadow-sm ring-1 ring-slate-200 text-slate-700 font-mono text-sm leading-relaxed focus:ring-2 focus:ring-blue-500/20 focus:outline-none resize-none bg-white placeholder:text-slate-300"
                            placeholder="Template content..."
                            spellcheck="false"
                        ></textarea>
                    </div>

                    <div class="px-6 py-4 border-t border-slate-100 bg-white flex justify-between items-center shrink-0">
                        <span class="text-xs text-slate-400 hidden sm:inline">Changes are temporary.</span>
                        
                        <div class="flex gap-3 ml-auto">
                            <button @click="modalOpen = false" class="px-4 py-2 text-slate-500 hover:text-slate-700 text-sm font-medium transition-colors">Close</button>
                            
                            <button @click="copyToClipboard(activeTemplate.content); modalOpen = false" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-bold shadow-lg shadow-blue-500/20 transition-all flex items-center gap-2 transform active:scale-95">
                                <i data-lucide="copy" class="w-4 h-4"></i> Copy Text
                            </button>
                        </div>
                    </div>
                </div>

            </template> 
        </div>
        {{-- end of modal --}}

    </div>
@endsection