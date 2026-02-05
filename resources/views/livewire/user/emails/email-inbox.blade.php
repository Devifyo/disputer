<div class="h-[calc(100vh-4rem)] flex flex-col bg-slate-50/50">

    <div class="px-6 py-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white/50 backdrop-blur-sm border-b border-slate-200/60 sticky top-0 z-20">
        
        <div class="flex items-center gap-6 flex-1">
            <button id="open-sidebar" class="lg:hidden p-2 -ml-2 text-slate-500 hover:bg-slate-100 rounded-lg transition-colors">
                <i data-lucide="menu" class="w-5 h-5"></i>
            </button>
            <h1 class="text-xl font-bold text-slate-900 tracking-tight">Inbox</h1>
            
            <div class="relative w-full max-w-md group hidden sm:block">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <i data-lucide="search" class="w-4 h-4 text-slate-400 group-focus-within:text-blue-500 transition-colors"></i>
                </div>
                <input type="text" 
                       wire:model.live.debounce.300ms="search"
                       class="block w-full pl-10 pr-4 py-2 bg-white border border-slate-200 rounded-lg text-sm font-medium text-slate-700 placeholder:text-slate-400 focus:ring-2 focus:ring-blue-500/10 focus:border-blue-500 transition-all shadow-sm group-hover:border-slate-300" 
                       placeholder="Search disputing letters...">
            </div>
        </div>

        <div class="flex items-center gap-3 self-end sm:self-auto">
            <div class="flex bg-slate-100 p-1 rounded-lg border border-slate-200">
                
                <button wire:click="$set('filter', 'all')" 
                        class="px-3 py-1.5 rounded-md text-xs font-bold transition-all {{ $filter === 'all' ? 'bg-white text-slate-900 shadow-sm ring-1 ring-slate-900/5' : 'text-slate-500 hover:text-slate-700 hover:bg-white/50' }}">
                    All
                </button>
                
                <button wire:click="$set('filter', 'unread')" 
                        class="px-3 py-1.5 rounded-md text-xs font-bold transition-all {{ $filter === 'unread' ? 'bg-white text-slate-900 shadow-sm ring-1 ring-slate-900/5' : 'text-slate-500 hover:text-slate-700 hover:bg-white/50' }}">
                    Unread
                </button>
                
                <button wire:click="$set('filter', 'sent')" 
                        class="px-3 py-1.5 rounded-md text-xs font-bold transition-all {{ $filter === 'sent' ? 'bg-white text-slate-900 shadow-sm ring-1 ring-slate-900/5' : 'text-slate-500 hover:text-slate-700 hover:bg-white/50' }}">
                    Sent
                </button>
            </div>
            
            <a href="{{ route('user.emails.create') }}" class="flex items-center gap-2 bg-slate-900 hover:bg-slate-800 text-white px-4 py-2 rounded-lg text-sm font-bold shadow-lg shadow-slate-900/10 transition-all hover:scale-[1.02] active:scale-95">
                <i data-lucide="plus" class="w-4 h-4"></i>
                <span>Compose</span>
            </a>
        </div>

        <div class="relative w-full sm:hidden mt-2">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <i data-lucide="search" class="w-4 h-4 text-slate-400"></i>
            </div>
            <input type="text" wire:model.live.debounce.300ms="search" class="block w-full pl-10 pr-4 py-2 bg-white border border-slate-200 rounded-lg text-sm" placeholder="Search...">
        </div>
    </div>

    <div class="flex-1 overflow-y-auto p-4 sm:p-6">
        <div class="max-w-6xl mx-auto bg-white rounded-2xl border border-slate-200 shadow-sm divide-y divide-slate-100 overflow-hidden relative min-h-[200px]">
            
            <div wire:loading.flex 
                 wire:target="search, filter" 
                 style="display: none;" 
                 class="absolute inset-0 bg-white/80 z-10 items-center justify-center backdrop-blur-[1px]">
                
                <div class="flex flex-col items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-8 h-8 text-blue-500 animate-spin">
                        <path d="M21 12a9 9 0 1 1-6.219-8.56"/>
                    </svg>
                </div>
            </div>

            @forelse($threads as $email)
            <a href="{{ route('user.emails.show', $email->id) }}" class="group block relative hover:bg-blue-50/30 transition-all duration-200">
                
                @if($email->unread)
                    <div class="absolute left-3 top-1/2 -translate-y-1/2 w-2 h-2 rounded-full bg-blue-600 shadow-sm shadow-blue-500/30"></div>
                @endif

                <div class="flex items-start sm:items-center gap-5 p-5 pl-8"> 
                    <div class="shrink-0 relative">
                        @php
                            $colors = ['bg-blue-100 text-blue-700', 'bg-emerald-100 text-emerald-700', 'bg-purple-100 text-purple-700', 'bg-amber-100 text-amber-700', 'bg-slate-100 text-slate-700'];
                            $initial = substr($email->recipient, 0, 1);
                            $colorClass = $colors[ord($initial) % count($colors)];
                        @endphp
                        
                        <div class="w-12 h-12 rounded-xl {{ $colorClass }} flex items-center justify-center font-bold text-lg border border-white shadow-sm group-hover:scale-105 transition-transform">
                            {{ $initial }}
                        </div>

                        <div class="absolute -bottom-1 -right-1 w-5 h-5 bg-white rounded-full flex items-center justify-center border border-slate-100 shadow-sm">
                            @if($email->status == 'reply_received')
                                <i data-lucide="reply" class="w-3 h-3 text-emerald-500"></i>
                            @elseif($email->status == 'sent')
                                <i data-lucide="arrow-up-right" class="w-3 h-3 text-slate-400"></i>
                            @elseif($email->status == 'failed')
                                <i data-lucide="alert-circle" class="w-3 h-3 text-red-500"></i>
                            @endif
                        </div>
                    </div>

                    <div class="flex-1 min-w-0 grid grid-cols-1 md:grid-cols-12 gap-2 md:gap-6 items-center">
                        <div class="md:col-span-3 min-w-0">
                            <h3 class="text-sm font-bold text-slate-900 truncate {{ $email->unread ? 'text-black' : 'text-slate-700' }}">
                                {{ $email->recipient }}
                            </h3>
                            <div class="flex items-center gap-1.5 mt-0.5">
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-slate-100 text-slate-500 border border-slate-200 truncate max-w-full">
                                    Case #{{ $email->case_id }}
                                </span>
                            </div>
                        </div>

                        <div class="md:col-span-7 min-w-0">
                            <div class="flex items-center gap-2 mb-0.5">
                                <p class="text-sm font-semibold text-slate-900 truncate group-hover:text-blue-700 transition-colors">
                                    {{ $email->subject }}
                                </p>
                                @if($email->has_attachment)
                                    <i data-lucide="paperclip" class="w-3.5 h-3.5 text-slate-400 shrink-0"></i>
                                @endif
                            </div>
                            <p class="text-sm text-slate-500 truncate group-hover:text-slate-600">
                                <span class="text-slate-400 font-normal">—</span> {{ $email->last_message }}
                            </p>
                        </div>

                        <div class="md:col-span-2 text-right flex flex-row md:flex-col items-center md:items-end justify-between md:justify-center gap-2 mt-2 md:mt-0">
                            <span class="text-xs font-medium {{ $email->unread ? 'text-blue-600' : 'text-slate-400' }} whitespace-nowrap">
                                {{ $email->date }}
                            </span>
                            
                            @if($email->status == 'failed')
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-red-50 text-red-600 text-[10px] font-bold uppercase tracking-wide border border-red-100">
                                    Failed
                                </span>
                            @elseif($email->unread)
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-blue-50 text-blue-600 text-[10px] font-bold uppercase tracking-wide border border-blue-100">
                                    New Reply
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
            </a>
            @empty
            <div class="flex flex-col items-center justify-center py-20 text-center">
                <div class="w-20 h-20 bg-slate-50 rounded-2xl flex items-center justify-center mb-4">
                    <i data-lucide="inbox" class="w-10 h-10 text-slate-300"></i>
                </div>
                <h3 class="text-lg font-bold text-slate-900">No emails found</h3>
                <p class="text-slate-500 max-w-sm mt-1">
                    @if($search)
                        No results for "{{ $search }}".
                    @else
                        Your inbox is currently empty.
                    @endif
                </p>
            </div>
            @endforelse

        </div>
    </div>
</div>