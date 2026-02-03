@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <header class="h-16 bg-surface border-b border-slate-200 flex items-center justify-between px-4 sm:px-8 z-10 sticky top-0 shrink-0">
        <div class="flex items-center gap-4">
            <button id="open-sidebar" class="lg:hidden text-slate-500 hover:text-slate-800"><i data-lucide="menu" class="w-6 h-6"></i></button>
            <span class="font-semibold text-slate-800 text-sm">Dashboard</span>
        </div>
        <div class="flex items-center gap-3 sm:gap-4">
            <a href="{{ route('user.cases.create') }}">
                <button class="bg-primary hover:bg-slate-800 text-white px-3 sm:px-4 py-2 rounded-md text-xs font-semibold shadow-lg shadow-slate-900/10 transition-all flex items-center gap-2">
                    <i data-lucide="plus" class="w-3 h-3"></i>
                    <span class="hidden sm:inline">New Dispute</span>
                    <span class="sm:hidden">New</span>
                </button>
            </a>
        </div>
    </header>

    <div class="flex-1 overflow-y-auto bg-slate-50/50 p-4 sm:p-8">
        <div class="max-w-7xl mx-auto space-y-6 sm:space-y-8">

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-surface p-5 rounded-xl border border-slate-200 shadow-sm flex justify-between items-start">
                    <div><p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Active Cases</p><h3 class="text-2xl font-bold text-slate-800 mt-1">4</h3></div>
                    <span class="bg-slate-50 p-2 rounded-lg text-slate-400"><i data-lucide="layers" class="w-4 h-4"></i></span>
                </div>
                <div class="bg-surface p-5 rounded-xl border border-slate-200 shadow-sm flex justify-between items-start relative overflow-hidden">
                    <div class="relative z-10"><p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Unread Replies</p><h3 class="text-2xl font-bold text-blue-600 mt-1">1</h3></div>
                    <span class="bg-blue-50 p-2 rounded-lg text-blue-600"><i data-lucide="mail" class="w-4 h-4"></i></span>
                    <div class="absolute bottom-0 left-0 w-full h-1 bg-blue-100"><div class="w-1/4 h-full bg-blue-500"></div></div>
                </div>
                <div class="bg-surface p-5 rounded-xl border border-slate-200 shadow-sm flex justify-between items-start">
                    <div><p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Drafts Pending</p><h3 class="text-2xl font-bold text-amber-600 mt-1">1</h3></div>
                    <span class="bg-amber-50 p-2 rounded-lg text-amber-600"><i data-lucide="edit-3" class="w-4 h-4"></i></span>
                </div>
                <div class="bg-surface p-5 rounded-xl border border-slate-200 shadow-sm flex justify-between items-start">
                    <div><p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Resolved</p><h3 class="text-2xl font-bold text-emerald-600 mt-1">8</h3></div>
                    <span class="bg-emerald-50 p-2 rounded-lg text-emerald-600"><i data-lucide="check-circle" class="w-4 h-4"></i></span>
                </div>
            </div>

            <div class="bg-white border border-slate-200 rounded-xl p-6 shadow-sm flex flex-col md:flex-row items-center justify-between gap-6">
                <div class="flex items-start gap-4">
                    <div class="w-12 h-12 bg-blue-50 rounded-full flex items-center justify-center text-blue-600 shadow-sm border border-blue-100 shrink-0"><i data-lucide="inbox" class="w-6 h-6"></i></div>
                    <div>
                        <h3 class="text-lg font-bold text-slate-800">New Reply from Chase Bank</h3>
                        <p class="text-sm text-slate-600 mt-1 max-w-xl">We received an email regarding <span class="font-medium text-slate-900">Case #9221</span>. The institution has requested additional documents.</p>
                        <p class="text-xs text-slate-400 mt-2 font-mono">Captured via System Route ID: 9221-x8a</p>
                    </div>
                </div>
                <div class="flex gap-3 w-full md:w-auto">
                    <button class="flex-1 md:flex-none px-4 py-2 bg-white border border-slate-200 text-slate-600 text-sm font-medium rounded-lg hover:bg-slate-50 transition-colors">Dismiss</button>
                    <button class="flex-1 md:flex-none px-4 py-2 bg-accent hover:bg-blue-700 text-white text-sm font-medium rounded-lg shadow-md transition-all flex items-center justify-center gap-2">View Thread <i data-lucide="arrow-right" class="w-4 h-4"></i></button>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

                <div class="lg:col-span-2 space-y-4">
                    <div class="flex items-center justify-between"><h2 class="text-base font-bold text-slate-800">Active Disputes</h2></div>
                    <div class="bg-surface rounded-xl border border-slate-200 shadow-card overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left min-w-[600px]">
                                <thead class="bg-slate-50 border-b border-slate-100">
                                    <tr>
                                        <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Case ID</th>
                                        <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Institution</th>
                                        <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Status</th>
                                        <th class="px-6 py-4 text-right text-xs font-semibold text-slate-500 uppercase">Updated</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr class="hover:bg-slate-50 transition-colors cursor-pointer">
                                        <td class="px-6 py-4 text-xs font-mono text-slate-500">#9221</td>
                                        <td class="px-6 py-4">
                                            <div class="flex items-center gap-3">
                                                <div class="w-8 h-8 rounded bg-slate-100 flex items-center justify-center text-xs font-bold text-slate-600 border border-slate-200 shrink-0">CH</div>
                                                <div><p class="text-sm font-semibold text-slate-900">Chase Bank</p></div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4"><span class="bg-blue-50 text-blue-700 border border-blue-100 text-xs px-2 py-1 rounded-full font-medium flex items-center w-fit gap-1"><span class="w-1.5 h-1.5 bg-blue-500 rounded-full animate-pulse"></span> Reply Received</span></td>
                                        <td class="px-6 py-4 text-right text-xs text-slate-500">20 min ago</td>
                                    </tr>
                                    <tr class="hover:bg-slate-50 transition-colors cursor-pointer">
                                        <td class="px-6 py-4 text-xs font-mono text-slate-500">#8840</td>
                                        <td class="px-6 py-4">
                                            <div class="flex items-center gap-3">
                                                <div class="w-8 h-8 rounded bg-slate-100 flex items-center justify-center text-xs font-bold text-slate-600 border border-slate-200 shrink-0">UA</div>
                                                <div><p class="text-sm font-semibold text-slate-900">United Airlines</p></div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4"><span class="bg-slate-100 text-slate-600 border border-slate-200 text-xs px-2 py-1 rounded-full font-medium">Sent / Waiting</span></td>
                                        <td class="px-6 py-4 text-right text-xs text-slate-500">2 days ago</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="lg:col-span-1 space-y-4">
                    <div class="flex items-center justify-between">
                        <h2 class="text-base font-bold text-slate-800 flex items-center gap-2">
                            <i data-lucide="activity" class="w-4 h-4 text-slate-400"></i> Activity Log
                        </h2>
                    </div>
                    <div class="bg-surface rounded-xl border border-slate-200 shadow-card p-0 overflow-hidden h-auto lg:h-[400px] overflow-y-auto">
                        <div class="p-5 space-y-0 relative">
                            <div class="absolute left-[39px] top-8 bottom-8 w-px bg-slate-200 z-0"></div>

                            <div class="relative pl-12 pb-6 group">
                                <div class="absolute left-0 top-0 z-10 bg-surface p-1">
                                    <div class="w-8 h-8 rounded-lg bg-blue-50 border border-blue-200 flex items-center justify-center text-blue-600 shadow-sm"><i data-lucide="mail" class="w-4 h-4"></i></div>
                                </div>
                                <div class="bg-slate-50/50 group-hover:bg-blue-50/30 rounded-lg p-3 border border-slate-100 group-hover:border-blue-100 transition-all">
                                    <div class="flex justify-between items-start mb-1"><span class="text-[10px] font-bold text-blue-700 bg-blue-100 px-1.5 py-0.5 rounded tracking-wide">INBOUND</span><span class="text-[10px] text-slate-400 font-mono">10:42 AM</span></div>
                                    <p class="text-xs font-semibold text-slate-800">Reply Received</p>
                                    <p class="text-[11px] text-slate-500 mt-1">From: disputes@chase.com</p>
                                </div>
                            </div>

                            <div class="relative pl-12 pb-2 group">
                                <div class="absolute left-0 top-0 z-10 bg-surface p-1">
                                    <div class="w-8 h-8 rounded-lg bg-emerald-50 border border-emerald-200 flex items-center justify-center text-emerald-600 shadow-sm"><i data-lucide="send" class="w-4 h-4"></i></div>
                                </div>
                                <div class="bg-slate-50/50 group-hover:bg-emerald-50 rounded-lg p-3 border border-slate-100 transition-all">
                                    <div class="flex justify-between items-start mb-1"><span class="text-[10px] font-bold text-emerald-700 bg-emerald-100 px-1.5 py-0.5 rounded tracking-wide">OUTBOUND</span><span class="text-[10px] text-slate-400 font-mono">Yesterday</span></div>
                                    <p class="text-xs font-semibold text-slate-800">Email Sent</p>
                                    <p class="text-[11px] text-slate-500 mt-1">To: disputes@chase.com</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
