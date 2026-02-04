@extends('layouts.app')

@section('title', 'My Disputes')

@section('content')
    <header class="h-16 sticky top-0 z-30 flex items-center justify-between px-4 sm:px-6 lg:px-8 bg-white/90 backdrop-blur-md border-b border-slate-200 transition-all">
        <div class="flex items-center gap-3">
            <button id="open-sidebar" class="lg:hidden p-2 -ml-2 text-slate-500 hover:bg-slate-100 rounded-lg transition-colors">
                <i data-lucide="menu" class="w-5 h-5"></i>
            </button>
            <h1 class="font-bold text-slate-900 text-lg tracking-tight flex items-center gap-2">
                Disputes 
                <span class="hidden sm:flex h-5 w-5 items-center justify-center rounded-full bg-slate-100 text-[10px] font-bold text-slate-600 border border-slate-200">
                    {{ $cases->total() }}
                </span>
            </h1>
        </div>
        <div>
            <a href="{{ route('user.cases.create') }}" class="group">
                <button class="bg-slate-900 hover:bg-slate-800 text-white px-4 py-2 rounded-lg text-xs font-semibold shadow-lg shadow-slate-900/10 transition-all flex items-center gap-2 transform active:scale-95">
                    <i data-lucide="plus" class="w-4 h-4 transition-transform group-hover:rotate-90"></i>
                    <span>New Dispute</span>
                </button>
            </a>
        </div>
    </header>

    <div class="flex-1 overflow-y-auto bg-slate-50 p-4 sm:p-6 lg:p-8">
        <div class="max-w-7xl mx-auto space-y-6">

            <form id="filterForm" method="GET" action="{{ route('user.cases.index') }}" class="bg-white p-2 rounded-xl border border-slate-200 shadow-sm flex flex-col sm:flex-row gap-2">
                <div class="relative flex-1 group">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i data-lucide="search" class="w-4 h-4 text-slate-400 group-focus-within:text-blue-500 transition-colors"></i>
                    </div>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search disputes..." 
                           class="block w-full pl-10 pr-3 py-2 bg-transparent border-0 text-sm text-slate-900 placeholder:text-slate-400 focus:ring-0 focus:outline-none h-10">
                </div>
                
                <div class="h-px sm:h-10 sm:w-px bg-slate-100 mx-1"></div>

                <div class="relative min-w-[160px]">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i data-lucide="filter" class="w-3.5 h-3.5 text-slate-400"></i>
                    </div>
                    <select name="status" onchange="document.getElementById('filterForm').submit()" 
                            class="block w-full pl-9 pr-8 py-2 bg-slate-50 hover:bg-slate-100 border-0 rounded-lg text-xs font-medium text-slate-700 focus:ring-0 cursor-pointer h-10 transition-colors appearance-none">
                        <option value="">All Statuses</option>
                        <option value="draft" {{ request('status') == 'draft' ? 'selected' : '' }}>Drafts</option>
                        <option value="sent" {{ request('status') == 'sent' ? 'selected' : '' }}>Sent</option>
                        <option value="waiting_reply" {{ request('status') == 'waiting_reply' ? 'selected' : '' }}>Waiting Reply</option>
                        <option value="escalated" {{ request('status') == 'escalated' ? 'selected' : '' }}>Escalated</option>
                        <option value="resolved" {{ request('status') == 'resolved' ? 'selected' : '' }}>Resolved</option>
                    </select>
                    <div class="absolute inset-y-0 right-0 pr-2 flex items-center pointer-events-none">
                        <i data-lucide="chevron-down" class="w-3 h-3 text-slate-400"></i>
                    </div>
                </div>
            </form>

            @if($cases->count() > 0)
                
                <div class="hidden sm:block bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50/50 border-b border-slate-100 text-[11px] uppercase tracking-wider text-slate-500 font-bold">
                                <th class="px-6 py-4 pl-8 w-[30%]">Case Reference</th>
                                <th class="px-6 py-4 w-[25%]">Institution</th>
                                <th class="px-6 py-4 w-[15%]">Status</th>
                                <th class="px-6 py-4 w-[20%]">Next Deadline</th>
                                <th class="px-6 py-4 text-right pr-8 w-[10%]"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            @foreach($cases as $case)
                            <tr class="group hover:bg-slate-50/80 transition-colors cursor-pointer" onclick="window.location='{{ route('user.cases.show', $case->case_reference_id) }}'">
                                
                                <td class="px-6 py-4 pl-8">
                                    <div class="flex items-start gap-3">
                                        <div class="mt-1.5 h-2 w-2 rounded-full shrink-0 {{ in_array($case->status?->value, ['sent', 'escalated']) ? 'bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.4)]' : 'bg-slate-300' }}"></div>
                                        <div>
                                            <span class="block text-sm font-bold text-slate-900 font-mono tracking-tight group-hover:text-blue-600 transition-colors">
                                                #{{ $case->case_reference_id }}
                                            </span>
                                            <span class="block text-xs text-slate-400 mt-0.5">
                                                Created {{ $case->created_at->format('M d') }}
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-lg bg-white border border-slate-200 flex items-center justify-center text-[10px] font-bold text-slate-600 shadow-sm shrink-0">
                                            {{ substr($case->institution_name, 0, 1) }}
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium text-slate-900">{{ $case->institution_name }}</p>
                                            <p class="text-[10px] text-slate-500">{{ $case->institution->category->name ?? 'General' }}</p>
                                        </div>
                                    </div>
                                </td>

                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold border {{ $case->status_color }}">
                                        {{ ucfirst(str_replace('_', ' ', $case->status?->value ?? $case->status)) }}
                                    </span>
                                </td>

                                <td class="px-6 py-4">
                                    @if($case->next_action_at)
                                        <div class="flex items-center gap-1.5">
                                            <i data-lucide="clock" class="w-3.5 h-3.5 {{ $case->next_action_at->isPast() ? 'text-red-500' : 'text-slate-400' }}"></i>
                                            <span class="text-xs font-semibold font-mono {{ $case->next_action_at->isPast() ? 'text-red-600' : 'text-slate-600' }}">
                                                {{ $case->next_action_at->format('M d, Y') }}
                                            </span>
                                        </div>
                                    @else
                                        <span class="text-xs text-slate-300 font-mono pl-5">--</span>
                                    @endif
                                </td>

                                <td class="px-6 py-4 text-right pr-8">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center hover:bg-slate-200 transition-colors ml-auto text-slate-300 group-hover:text-slate-600">
                                        <i data-lucide="chevron-right" class="w-4 h-4"></i>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="sm:hidden space-y-4">
                    @foreach($cases as $case)
                    <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm active:scale-[0.98] transition-transform cursor-pointer" onclick="window.location='{{ route('user.cases.show', $case->case_reference_id) }}'">
                        <div class="flex justify-between items-start mb-3">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-lg bg-slate-50 border border-slate-100 flex items-center justify-center text-sm font-bold text-slate-700">
                                    {{ substr($case->institution_name, 0, 1) }}
                                </div>
                                <div>
                                    <h3 class="font-bold text-slate-900 text-sm">{{ $case->institution_name }}</h3>
                                    <p class="text-xs text-slate-500 font-mono">#{{ $case->case_reference_id }}</p>
                                </div>
                            </div>
                            <span class="inline-flex px-2 py-1 rounded text-[10px] font-bold border {{ $case->status_color }}">
                                {{ ucfirst($case->status?->value ?? 'Processing') }}
                            </span>
                        </div>
                        
                        <div class="flex items-center justify-between pt-3 border-t border-slate-50 mt-3">
                            <div class="flex items-center gap-1.5">
                                <i data-lucide="clock" class="w-3.5 h-3.5 text-slate-400"></i>
                                <span class="text-xs font-medium {{ $case->next_action_at && $case->next_action_at->isPast() ? 'text-red-600' : 'text-slate-500' }}">
                                    {{ $case->next_action_at ? $case->next_action_at->format('M d') : 'No deadline' }}
                                </span>
                            </div>
                            <span class="text-xs font-semibold text-blue-600 flex items-center gap-1">
                                Details <i data-lucide="arrow-right" class="w-3 h-3"></i>
                            </span>
                        </div>
                    </div>
                    @endforeach
                </div>

                <div class="mt-6">
                    {{ $cases->withQueryString()->links() }}
                </div>

            @else
                <div class="flex flex-col items-center justify-center py-16 bg-white rounded-xl border border-slate-200 border-dashed text-center">
                    <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center mb-4">
                        <i data-lucide="folder-open" class="w-8 h-8 text-slate-300"></i>
                    </div>
                    <h3 class="text-slate-900 font-bold text-lg">No disputes found</h3>
                    <p class="text-slate-500 text-sm mt-1 max-w-xs mx-auto">It looks quiet here. Start a new case to begin tracking your disputes.</p>
                    <a href="{{ route('user.cases.create') }}" class="mt-6 inline-flex items-center gap-2 text-sm font-semibold text-white bg-slate-900 hover:bg-slate-800 px-5 py-2.5 rounded-lg transition-all shadow-md">
                        <i data-lucide="plus" class="w-4 h-4"></i> Create Case
                    </a>
                </div>
            @endif

        </div>
    </div>
@endsection