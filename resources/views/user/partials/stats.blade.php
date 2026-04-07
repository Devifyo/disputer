<div class="grid grid-cols-2 lg:grid-cols-4 gap-4">

    {{-- Total Cases --}}
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 flex flex-col gap-3 hover:shadow-md hover:border-slate-300 transition-all">
        <div class="flex items-center justify-between">
            <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Total Cases</span>
            <div class="w-8 h-8 bg-slate-100 rounded-xl flex items-center justify-center text-slate-500">
                <i data-lucide="layers" class="w-4 h-4"></i>
            </div>
        </div>
        <div>
            <p class="text-3xl font-black text-slate-900 leading-none">{{ $stats['total_cases'] ?? 0 }}</p>
            <p class="text-xs text-slate-400 mt-1 font-medium">All time</p>
        </div>
    </div>

    {{-- Active Cases --}}
    <div class="bg-white rounded-2xl border border-amber-100 shadow-sm p-5 flex flex-col gap-3 hover:shadow-md hover:border-amber-200 transition-all">
        <div class="flex items-center justify-between">
            <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Active</span>
            <div class="w-8 h-8 bg-amber-50 rounded-xl flex items-center justify-center text-amber-600">
                <i data-lucide="scale" class="w-4 h-4"></i>
            </div>
        </div>
        <div>
            <p class="text-3xl font-black text-amber-600 leading-none">{{ $stats['active_cases'] ?? 0 }}</p>
            <div class="flex items-center gap-1.5 mt-1">
                @if(($stats['active_cases'] ?? 0) > 0)
                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                    <p class="text-xs text-amber-600 font-semibold">In progress</p>
                @else
                    <p class="text-xs text-slate-400 font-medium">None open</p>
                @endif
            </div>
        </div>
    </div>

    {{-- Unread Replies --}}
    <div class="bg-white rounded-2xl border {{ ($stats['replies'] ?? 0) > 0 ? 'border-blue-200' : 'border-slate-200' }} shadow-sm p-5 flex flex-col gap-3 hover:shadow-md transition-all">
        <div class="flex items-center justify-between">
            <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Unread</span>
            <div class="relative w-8 h-8 bg-blue-50 rounded-xl flex items-center justify-center text-blue-600">
                <i data-lucide="mail" class="w-4 h-4"></i>
                @if(($stats['replies'] ?? 0) > 0)
                    <span class="absolute -top-1 -right-1 w-3.5 h-3.5 bg-blue-600 rounded-full flex items-center justify-center text-[8px] font-black text-white leading-none">{{ min($stats['replies'], 9) }}</span>
                @endif
            </div>
        </div>
        <div>
            <p class="text-3xl font-black {{ ($stats['replies'] ?? 0) > 0 ? 'text-blue-600' : 'text-slate-900' }} leading-none">{{ $stats['replies'] ?? 0 }}</p>
            <p class="text-xs {{ ($stats['replies'] ?? 0) > 0 ? 'text-blue-500 font-semibold' : 'text-slate-400 font-medium' }} mt-1">
                {{ ($stats['replies'] ?? 0) > 0 ? 'Need attention' : 'All read' }}
            </p>
        </div>
    </div>

    {{-- Resolved --}}
    <div class="bg-white rounded-2xl border border-emerald-100 shadow-sm p-5 flex flex-col gap-3 hover:shadow-md hover:border-emerald-200 transition-all">
        <div class="flex items-center justify-between">
            <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Resolved</span>
            <div class="w-8 h-8 bg-emerald-50 rounded-xl flex items-center justify-center text-emerald-600">
                <i data-lucide="check-circle-2" class="w-4 h-4"></i>
            </div>
        </div>
        <div>
            <p class="text-3xl font-black text-emerald-600 leading-none">{{ $stats['resolved'] ?? 0 }}</p>
            @if(($stats['total_cases'] ?? 0) > 0)
                @php $rate = round(($stats['resolved'] / $stats['total_cases']) * 100) @endphp
                <div class="mt-2">
                    <div class="flex justify-between items-center mb-1">
                        <p class="text-[10px] text-slate-400 font-medium">Success rate</p>
                        <p class="text-[10px] font-bold text-emerald-600">{{ $rate }}%</p>
                    </div>
                    <div class="w-full bg-slate-100 rounded-full h-1">
                        <div class="bg-emerald-500 h-1 rounded-full transition-all" style="width: {{ $rate }}%"></div>
                    </div>
                </div>
            @else
                <p class="text-xs text-slate-400 font-medium mt-1">No cases yet</p>
            @endif
        </div>
    </div>

</div>
