@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')

    <header class="h-16 bg-white border-b border-slate-200 flex items-center justify-between px-4 sm:px-8 z-10 sticky top-0 shrink-0">
        <div class="flex items-center gap-4">
            <h1 class="font-bold text-slate-900 text-lg tracking-tight flex items-center gap-2">
                Dashboard
            </h1>
            
            @if($isEmailConfigured)
                <div class="hidden md:flex items-center gap-2 text-[11px] font-bold bg-emerald-50 text-emerald-700 px-2.5 py-1 rounded-full border border-emerald-100">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    System Active
                </div>
            @else
                <div class="hidden md:flex items-center gap-2 text-[11px] font-bold bg-rose-50 text-rose-700 px-2.5 py-1 rounded-full border border-rose-100 animate-pulse">
                    <i data-lucide="alert-octagon" class="w-3 h-3"></i>
                    Configuration Missing
                </div>
            @endif
        </div>

        <div class="flex items-center gap-3 sm:gap-4">
            <a href="{{ route('user.cases.create') }}">
                <button class="bg-slate-900 hover:bg-slate-800 text-white px-3 sm:px-4 py-2 rounded-lg text-xs font-bold shadow-lg shadow-slate-900/20 transition-all flex items-center gap-2 hover:scale-105 active:scale-95">
                    <i data-lucide="plus" class="w-3 h-3"></i>
                    <span class="hidden sm:inline">New Dispute</span>
                    <span class="sm:hidden">New</span>
                </button>
            </a>
        </div>
    </header>

    <div class="flex-1 overflow-y-auto bg-slate-50 p-4 sm:p-8">
        <div class="max-w-[1600px] mx-auto space-y-8">

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                
                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm flex justify-between items-start group hover:border-blue-300 transition-all cursor-default">
                    <div>
                        <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Active Cases</p>
                        <h3 class="text-2xl font-extrabold text-slate-800 mt-1">{{ $stats['active'] ?? 0 }}</h3>
                    </div>
                    <div class="bg-slate-50 p-2.5 rounded-lg text-slate-400 group-hover:bg-blue-50 group-hover:text-blue-600 transition-colors">
                        <i data-lucide="layers" class="w-5 h-5"></i>
                    </div>
                </div>

                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm flex justify-between items-start relative overflow-hidden group hover:border-blue-300 transition-all cursor-default">
                    <div class="relative z-10">
                        <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Unread Replies</p>
                        <h3 class="text-2xl font-extrabold text-blue-600 mt-1">{{ $stats['replies'] ?? 0 }}</h3>
                    </div>
                    <div class="bg-blue-50 p-2.5 rounded-lg text-blue-600">
                        <i data-lucide="mail" class="w-5 h-5"></i>
                    </div>
                    @if(($stats['replies'] ?? 0) > 0)
                        <div class="absolute bottom-0 left-0 w-full h-1 bg-blue-100"><div class="w-1/4 h-full bg-blue-500"></div></div>
                    @endif
                </div>

                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm flex justify-between items-start group hover:border-amber-300 transition-all cursor-default">
                    <div>
                        <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Drafts Pending</p>
                        <h3 class="text-2xl font-extrabold text-amber-600 mt-1">{{ $stats['drafts'] ?? 0 }}</h3>
                    </div>
                    <div class="bg-amber-50 p-2.5 rounded-lg text-amber-600 group-hover:bg-amber-100 transition-colors">
                        <i data-lucide="edit-3" class="w-5 h-5"></i>
                    </div>
                </div>

                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm flex justify-between items-start group hover:border-emerald-300 transition-all cursor-default">
                    <div>
                        <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Resolved</p>
                        <h3 class="text-2xl font-extrabold text-emerald-600 mt-1">{{ $stats['resolved'] ?? 0 }}</h3>
                    </div>
                    <div class="bg-emerald-50 p-2.5 rounded-lg text-emerald-600 group-hover:bg-emerald-100 transition-colors">
                        <i data-lucide="check-circle-2" class="w-5 h-5"></i>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

                <div class="lg:col-span-2 space-y-5">

                    @if($latestUnread)
                    <div class="bg-gradient-to-r from-blue-50 via-white to-white border border-blue-100 rounded-xl p-4 shadow-sm flex flex-col sm:flex-row items-center justify-between gap-4">
                        <div class="flex items-center gap-4 w-full">
                            <div class="w-10 h-10 bg-white rounded-full flex items-center justify-center text-blue-600 shadow-sm border border-blue-100 shrink-0">
                                <i data-lucide="bell-ring" class="w-5 h-5"></i>
                            </div>
                            <div class="flex-1">
                                <h3 class="text-sm font-bold text-slate-800">{{ $latestUnread->subject ?? 'New Reply Received' }}</h3>
                                <div class="flex items-center gap-2 mt-0.5">
                                    <span class="text-xs text-slate-500">Case #{{ $latestUnread->case_id }}</span>
                                    <span class="text-[10px] bg-blue-100 text-blue-700 px-1.5 rounded font-bold">NEW</span>
                                </div>
                            </div>
                        </div>
                        <a href="{{ route('user.cases.show', $latestUnread->case_id) }}" class="whitespace-nowrap w-full sm:w-auto px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-lg shadow-sm transition-all text-center">
                            View Reply
                        </a>
                    </div>
                    @endif

                    <div class="flex items-center justify-between">
                        <h2 class="text-base font-bold text-slate-800 flex items-center gap-2">
                            <i data-lucide="list" class="w-4 h-4 text-slate-400"></i> Active Disputes
                        </h2>
                    </div>

                    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden flex flex-col min-h-[400px]">
                        @if(count($activeCases) > 0)
                            <div class="overflow-x-auto">
                                <table class="w-full text-left whitespace-nowrap">
                                    <thead class="bg-slate-50/50 border-b border-slate-100">
                                        <tr>
                                            <th class="px-6 py-3 text-[10px] font-bold text-slate-500 uppercase tracking-wider">Case Info</th>
                                            <th class="px-6 py-3 text-[10px] font-bold text-slate-500 uppercase tracking-wider">Status</th>
                                            <th class="px-6 py-3 text-right text-[10px] font-bold text-slate-500 uppercase tracking-wider">Last Update</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        @foreach($activeCases as $case)
                                        <tr onclick="window.location='{{ route('user.cases.show', $case->case_reference_id) }}'" class="hover:bg-slate-50 transition-colors cursor-pointer group">
                                            <td class="px-6 py-4">
                                                <div class="flex items-center gap-3">
                                                    <div class="w-9 h-9 rounded-lg bg-slate-100 flex items-center justify-center text-xs font-bold text-slate-600 border border-slate-200 shrink-0 uppercase group-hover:bg-white group-hover:border-blue-200 group-hover:text-blue-600 transition-colors">
                                                        {{ substr($case->institution_name, 0, 2) }}
                                                    </div>
                                                    <div>
                                                        <p class="text-sm font-bold text-slate-800">{{ $case->institution_name }}</p>
                                                        <p class="text-[10px] text-slate-400 font-mono">ID: #{{ $case->id }}</p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                @if($case->status->value === 'waiting_user')
                                                    <span class="bg-amber-50 text-amber-700 border border-amber-100 text-[10px] px-2.5 py-1 rounded-full font-bold flex items-center w-fit gap-1.5">
                                                        <span class="w-1.5 h-1.5 bg-amber-500 rounded-full animate-pulse"></span> Action Required
                                                    </span>
                                                @elseif(strtolower($case->status->value) === 'sent')
                                                    <span class="bg-blue-50 text-blue-700 border border-blue-100 text-[10px] px-2.5 py-1 rounded-full font-bold">
                                                        In Progress
                                                    </span>
                                                @else
                                                    <span class="bg-slate-100 text-slate-600 border border-slate-200 text-[10px] px-2.5 py-1 rounded-full font-bold">
                                                        {{ ucfirst(str_replace('_', ' ', $case->status->value)) }}
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 text-right text-xs text-slate-500">{{ $case->updated_at->diffForHumans(null, true) }}</td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="flex-1 flex flex-col items-center justify-center text-center p-8">
                                <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center mb-4 border border-slate-100">
                                    <i data-lucide="folder-search" class="w-8 h-8 text-slate-300"></i>
                                </div>
                                <h3 class="text-sm font-bold text-slate-900">No active disputes</h3>
                                <p class="text-xs text-slate-500 mt-1 mb-6 max-w-xs mx-auto">You don't have any cases in progress. Start a new dispute to begin tracking.</p>
                                <a href="{{ route('user.cases.create') }}" class="text-blue-600 font-bold text-sm hover:underline flex items-center gap-1">
                                    Start new dispute <i data-lucide="arrow-right" class="w-3 h-3"></i>
                                </a>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="lg:col-span-1 space-y-6">

                    @if(!$isEmailConfigured)
                        <div class="bg-white rounded-xl border border-rose-200 shadow-sm overflow-hidden">
                            <div class="bg-rose-50 p-4 border-b border-rose-100 flex items-center gap-3">
                                <div class="bg-rose-100 p-1.5 rounded-lg text-rose-600 shrink-0">
                                    <i data-lucide="alert-triangle" class="w-4 h-4"></i>
                                </div>
                                <div>
                                    <h3 class="text-sm font-bold text-rose-800">Setup Required</h3>
                                    <p class="text-[10px] text-rose-600 font-medium">Email not connected</p>
                                </div>
                            </div>
                            <div class="p-4">
                                <p class="text-xs text-slate-600 mb-3 leading-relaxed">
                                    We cannot send or track disputes until you connect your email.
                                </p>
                                <a href="{{ route('profile.edit') }}#email-settings" class="w-full flex items-center justify-center gap-2 bg-rose-600 hover:bg-rose-700 text-white py-2 rounded-lg text-xs font-bold transition-all shadow-sm shadow-rose-600/20">
                                    Connect Email Now <i data-lucide="arrow-right" class="w-3 h-3"></i>
                                </a>
                            </div>
                        </div>
                    @else
                         <div class="bg-gradient-to-br from-emerald-500 to-emerald-600 rounded-xl shadow-lg p-5 text-white relative overflow-hidden">
                            <div class="absolute top-0 right-0 p-4 opacity-20">
                                <i data-lucide="shield-check" class="w-20 h-20"></i>
                            </div>
                            <div class="relative z-10">
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="bg-white/20 p-1 rounded">
                                        <i data-lucide="check" class="w-3 h-3 text-white"></i>
                                    </span>
                                    <h3 class="font-bold text-sm">System Operational</h3>
                                </div>
                                <p class="text-[11px] text-emerald-50 leading-relaxed mb-3">
                                    Your email is connected. We are ready to send and track disputes automatically.
                                </p>
                                <div class="w-full bg-black/20 rounded-full h-1.5">
                                    <div class="bg-white h-1.5 rounded-full" style="width: 100%"></div>
                                </div>
                                <p class="text-[10px] text-right text-emerald-100 font-bold mt-1">100% Configured</p>
                            </div>
                        </div>
                    @endif

                    <div>
                        <div class="flex items-center justify-between mb-3 px-1">
                            <h2 class="text-sm font-bold text-slate-800 flex items-center gap-2">
                                <i data-lucide="history" class="w-4 h-4 text-slate-400"></i> Recent Emails
                            </h2>
                        </div>

                        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden flex flex-col h-[400px]">
                            @if(count($recentEmails) > 0)
                                <div class="overflow-y-auto p-2 space-y-1">
                                    @foreach($recentEmails as $email)
                                        <div class="group p-3 rounded-lg hover:bg-slate-50 border border-transparent hover:border-slate-100 transition-all cursor-default">
                                            <div class="flex justify-between items-start mb-1">
                                                <div class="flex items-center gap-2">
                                                    @if($email->direction === 'inbound')
                                                        <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span>
                                                        <span class="text-[10px] font-bold text-slate-700">INBOUND</span>
                                                    @else
                                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                                        <span class="text-[10px] font-bold text-slate-700">OUTBOUND</span>
                                                    @endif
                                                </div>
                                                <span class="text-[10px] text-slate-400 font-mono">{{ $email->created_at->format('M d') }}</span>
                                            </div>
                                            
                                            <p class="text-xs font-semibold text-slate-800 truncate pr-2">{{ $email->subject ?? 'No Subject' }}</p>
                                            
                                            <div class="flex justify-between items-end mt-1.5">
                                                <p class="text-[10px] text-slate-500 truncate max-w-[140px]">
                                                    {{ $email->direction === 'inbound' ? 'From: ' . $email->sender : 'To: ' . $email->recipient }}
                                                </p>
                                                <span class="text-[9px] font-mono text-slate-400 bg-slate-100 px-1.5 py-0.5 rounded">
                                                    #{{ $email->case_id }}
                                                </span>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="flex-1 flex flex-col items-center justify-center text-center p-6">
                                    <div class="w-12 h-12 bg-slate-50 rounded-full flex items-center justify-center mb-3">
                                        <i data-lucide="mail-open" class="w-6 h-6 text-slate-300"></i>
                                    </div>
                                    <p class="text-xs font-bold text-slate-800">No recent emails</p>
                                    <p class="text-[10px] text-slate-500 mt-1 max-w-[150px]">Email activity will appear here once disputes are started.</p>
                                </div>
                            @endif
                        </div>
                    </div>

                </div>
            </div>

        </div>
    </div>

@endsection