@props(['emails', 'case'])

@if($emails->isNotEmpty())
    <div class="mb-8" x-data="{ expanded: false }">
        {{-- Stack Header --}}
        <div class="flex items-center justify-between mb-3 px-1">
            <div class="flex items-center gap-2">
                <span class="flex h-2 w-2 rounded-full bg-rose-500 animate-pulse"></span>
                <h4 class="text-[11px] font-bold text-slate-400 uppercase tracking-widest">Inbox Action Items</h4>
            </div>
            
            @if($emails->count() > 2)
                <button @click="expanded = !expanded" 
                        class="text-[11px] font-extrabold text-rose-600 uppercase tracking-tight hover:text-rose-700 transition-colors flex items-center gap-1">
                    <span x-text="expanded ? 'Show Less' : 'View All ({{ $emails->count() }})'"></span>
                    <svg :class="expanded ? 'rotate-180' : ''" class="w-3 h-3 transition-transform duration-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M19 9l-7 7-7-7" /></svg>
                </button>
            @endif
        </div>

        <div class="space-y-3">
            {{-- 1. Show first 2 --}}
            @foreach($emails->take(2) as $unreadEmail)
                <x-case.unread-email-alert :unreadEmail="$unreadEmail" :case="$case" />
            @endforeach

            {{-- 2. Collapsible rest --}}
            @if($emails->count() > 2)
                <div x-show="expanded" x-collapse x-cloak class="space-y-3 pt-1">
                    @foreach($emails->skip(2) as $unreadEmail)
                        <x-case.unread-email-alert :unreadEmail="$unreadEmail" :case="$case" />
                    @endforeach
                </div>

                {{-- 3. Visual Stack Hint --}}
                <div x-show="!expanded" 
                     @click="expanded = true"
                     class="relative cursor-pointer group">
                    <div class="absolute inset-x-4 -bottom-1.5 h-4 bg-white border border-slate-200 rounded-xl z-0 transition-all group-hover:-bottom-2"></div>
                    <div class="absolute inset-x-8 -bottom-3 h-4 bg-white border border-slate-100 rounded-xl -z-10 transition-all group-hover:-bottom-4"></div>
                    
                    <div class="bg-slate-50 border border-slate-200 rounded-xl p-3 flex items-center justify-center gap-2 text-xs font-bold text-slate-500 hover:bg-slate-100 transition-colors relative z-10">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/></svg>
                        <span>+{{ $emails->count() - 2 }} more unread emails</span>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endif