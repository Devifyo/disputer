{{-- Unread reply notification banner --}}
@if($latestUnread)
<div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 bg-blue-600 rounded-2xl p-4 shadow-lg shadow-blue-600/20 mb-4">
    <div class="flex items-center gap-3">
        <div class="w-9 h-9 bg-white/20 rounded-xl flex items-center justify-center shrink-0">
            <i data-lucide="bell-ring" class="w-4 h-4 text-white"></i>
        </div>
        <div>
            <p class="text-xs font-black text-white">{{ $latestUnread->subject ?? 'New Reply Received' }}</p>
            <div class="flex items-center gap-2 mt-0.5">
                <span class="text-[11px] text-blue-200">Case #{{ $latestUnread->case_reference_id }}</span>
                <span class="text-[9px] bg-white/20 text-white px-1.5 py-0.5 rounded font-bold uppercase tracking-wide">New</span>
            </div>
        </div>
    </div>
    <a href="{{ route('user.cases.show', $latestUnread->case->case_reference_id) }}"
       class="shrink-0 w-full sm:w-auto px-4 py-2 bg-white text-blue-700 text-xs font-black rounded-xl hover:bg-blue-50 transition-all text-center shadow-sm">
        View Reply →
    </a>
</div>
@endif

{{-- Section header --}}
<div class="flex items-center justify-between mb-3">
    <h2 class="text-sm font-black text-slate-900 flex items-center gap-2">
        <span class="w-5 h-5 bg-slate-100 rounded-lg flex items-center justify-center">
            <i data-lucide="list" class="w-3 h-3 text-slate-500"></i>
        </span>
        Active Cases
        @if(count($activeCases) > 0)
            <span class="bg-slate-100 text-slate-600 text-[10px] font-bold px-2 py-0.5 rounded-full">{{ count($activeCases) }}</span>
        @endif
    </h2>
    @if(count($activeCases) > 0)
        <a href="{{ route('user.cases.index') }}" class="text-[11px] font-bold text-primary-600 hover:text-primary-700 flex items-center gap-1 transition-colors">
            View all <i data-lucide="arrow-right" class="w-3 h-3"></i>
        </a>
    @endif
</div>

{{-- Cases card — flex-1 so it fills remaining column height --}}
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden flex flex-col flex-1">

    @if(count($activeCases) > 0)
        {{-- Has cases: scrollable table --}}
        <div class="overflow-x-auto flex-1">
            <table class="w-full text-left">
                <thead>
                    <tr class="border-b border-slate-100 bg-slate-50/80">
                        <th class="px-5 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest">Institution</th>
                        <th class="px-5 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest">Status</th>
                        <th class="px-5 py-3 text-right text-[10px] font-black text-slate-400 uppercase tracking-widest">Updated</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($activeCases as $case)
                    <tr onclick="window.location='{{ route('user.cases.show', $case->case_reference_id) }}'"
                        class="hover:bg-slate-50/80 transition-colors cursor-pointer group">
                        <td class="px-5 py-3.5">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-xl bg-slate-100 flex items-center justify-center text-[11px] font-black text-slate-600 border border-slate-200 shrink-0 uppercase group-hover:bg-primary-50 group-hover:border-primary-200 group-hover:text-primary-700 transition-colors">
                                    {{ substr($case->institution_name, 0, 2) }}
                                </div>
                                <div class="min-w-0">
                                    <p class="text-sm font-bold text-slate-800 truncate">{{ $case->institution_name }}</p>
                                    <p class="text-[10px] text-slate-400 font-mono">#{{ $case->case_reference_id }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-3.5">
                            @if($case->status->value === 'waiting_user')
                                <span class="inline-flex items-center gap-1.5 bg-amber-50 text-amber-700 border border-amber-100 text-[10px] px-2.5 py-1 rounded-full font-bold">
                                    <span class="w-1.5 h-1.5 bg-amber-500 rounded-full animate-pulse"></span>
                                    Action Required
                                </span>
                            @elseif(strtolower($case->status->value) === 'sent')
                                <span class="inline-flex items-center gap-1.5 bg-blue-50 text-blue-700 border border-blue-100 text-[10px] px-2.5 py-1 rounded-full font-bold">
                                    <span class="w-1.5 h-1.5 bg-blue-500 rounded-full"></span>
                                    In Progress
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 bg-slate-100 text-slate-600 border border-slate-200 text-[10px] px-2.5 py-1 rounded-full font-bold">
                                    {{ ucfirst(str_replace('_', ' ', $case->status->value)) }}
                                </span>
                            @endif
                        </td>
                        <td class="px-5 py-3.5 text-right">
                            <span class="text-[11px] text-slate-400 font-medium">{{ $case->updated_at->diffForHumans(null, true) }} ago</span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

    @else
        {{-- Empty state: professional getting-started guide --}}
        <div class="flex-1 flex flex-col">

            {{-- Top section: welcome message --}}
            <div class="flex-1 flex flex-col items-center justify-center text-center px-8 py-10">
                <div class="w-14 h-14 bg-primary-50 rounded-2xl flex items-center justify-center mb-5 border border-primary-100">
                    <i data-lucide="scale" class="w-7 h-7 text-primary-500"></i>
                </div>
                <h3 class="text-base font-black text-slate-900 mb-1">No active cases</h3>
                <p class="text-sm text-slate-500 max-w-xs leading-relaxed mb-6">
                    You haven't opened any cases yet. Follow the steps below to get started.
                </p>
                <a href="{{ route('user.cases.create') }}"
                   class="inline-flex items-center gap-2 px-5 py-2.5 bg-primary-600 text-white text-xs font-black rounded-xl hover:bg-primary-700 transition-all shadow-md shadow-primary-600/20">
                    <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                    Open Your First Case
                </a>
            </div>

        </div>
    @endif

</div>
