@extends('layouts.admin')

@section('title', 'Admin Dashboard')

@section('content')
    <div class="p-6 pb-24 h-full overflow-y-auto custom-scrollbar bg-slate-50/50">
        <x-flash />

        <h1 class="text-2xl font-bold text-slate-900 mb-6 tracking-tight">Dashboard Overview</h1>
        
        {{-- Stats Grid --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            {{-- Total Users --}}
            <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex flex-col transition-transform hover:-translate-y-1">
                <div class="flex justify-between items-start mb-4">
                    <h3 class="text-slate-500 text-xs font-bold uppercase tracking-wider">Total Users</h3>
                    <div class="p-2 bg-blue-50 text-blue-600 rounded-lg"><i data-lucide="users" class="w-4 h-4"></i></div>
                </div>
                <div class="flex items-baseline gap-2 mt-auto">
                    <span class="text-3xl font-bold text-slate-900">{{ number_format($stats['total_users']) }}</span>
                </div>
            </div>

            {{-- Total Cases --}}
            <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex flex-col transition-transform hover:-translate-y-1">
                <div class="flex justify-between items-start mb-4">
                    <h3 class="text-slate-500 text-xs font-bold uppercase tracking-wider">Total Disputes</h3>
                    <div class="p-2 bg-slate-100 text-slate-600 rounded-lg"><i data-lucide="folder-open" class="w-4 h-4"></i></div>
                </div>
                <div class="flex items-baseline gap-2 mt-auto">
                    <span class="text-3xl font-bold text-slate-900">{{ number_format($stats['total_cases']) }}</span>
                </div>
            </div>

            {{-- Pending Action --}}
            <div class="bg-white p-6 rounded-2xl border border-amber-200 bg-gradient-to-br from-amber-50 to-white shadow-sm flex flex-col transition-transform hover:-translate-y-1">
                <div class="flex justify-between items-start mb-4">
                    <h3 class="text-amber-700 text-xs font-bold uppercase tracking-wider">Pending Action</h3>
                    <div class="p-2 bg-amber-100 text-amber-600 rounded-lg"><i data-lucide="clock" class="w-4 h-4"></i></div>
                </div>
                <div class="flex items-baseline gap-2 mt-auto">
                    <span class="text-3xl font-bold text-amber-700">{{ number_format($stats['pending_cases']) }}</span>
                </div>
            </div>

            {{-- Escalated --}}
            <div class="bg-white p-6 rounded-2xl border border-rose-200 bg-gradient-to-br from-rose-50 to-white shadow-sm flex flex-col transition-transform hover:-translate-y-1">
                <div class="flex justify-between items-start mb-4">
                    <h3 class="text-rose-700 text-xs font-bold uppercase tracking-wider">Escalated</h3>
                    <div class="p-2 bg-rose-100 text-rose-600 rounded-lg"><i data-lucide="alert-triangle" class="w-4 h-4"></i></div>
                </div>
                <div class="flex items-baseline gap-2 mt-auto">
                    <span class="text-3xl font-bold text-rose-700">{{ number_format($stats['escalated_cases']) }}</span>
                </div>
            </div>
        </div>

        {{-- Tables Grid --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            
            {{-- RECENT USERS TABLE --}}
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm flex flex-col overflow-hidden">
                <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between bg-slate-50/50 shrink-0">
                    <h2 class="text-base font-bold text-slate-800 flex items-center gap-2">
                        <i data-lucide="user-plus" class="w-4 h-4 text-primary-500"></i> Recent Users
                    </h2>
                    <a href="{{ route('admin.users.index') }}" wire:navigate class="text-[11px] font-bold text-primary-600 hover:text-primary-700 uppercase tracking-wider">View All</a>
                </div>
                <div class="overflow-x-auto flex-1">
                    <table class="w-full text-left text-sm whitespace-nowrap">
                        <thead class="bg-white border-b border-slate-100 text-slate-400 font-bold uppercase text-[10px] tracking-widest">
                            <tr>
                                <th class="px-6 py-3">User</th>
                                <th class="px-6 py-3">Joined</th>
                                <th class="px-6 py-3 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            @forelse($recentUsers as $user)
                                <tr class="hover:bg-slate-50/80 transition-colors group">
                                    <td class="px-6 py-3">
                                        <div class="font-bold text-slate-800">{{ $user->name }}</div>
                                        <div class="text-[11px] text-slate-500">{{ $user->email }}</div>
                                    </td>
                                    <td class="px-6 py-3">
                                        <span class="text-xs text-slate-600 font-medium">{{ $user->created_at->diffForHumans() }}</span>
                                    </td>
                                    <td class="px-6 py-3 text-right">
                                        @if($user->canBeImpersonated())
                                            {{-- FIXED: Removed opacity-0 group-hover:opacity-100 --}}
                                            <a href="{{ route('impersonate', $user->id) }}" title="Impersonate User" class="inline-flex p-2 text-slate-400 hover:text-primary-600 hover:bg-primary-50 rounded-lg transition-all">
                                                <i data-lucide="log-in" class="w-4 h-4"></i>
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="px-6 py-8 text-center text-slate-400 text-xs">No users found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- RECENT CASES TABLE --}}
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm flex flex-col overflow-hidden">
                <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between bg-slate-50/50 shrink-0">
                    <h2 class="text-base font-bold text-slate-800 flex items-center gap-2">
                        <i data-lucide="file-clock" class="w-4 h-4 text-primary-500"></i> Recent Disputes
                    </h2>
                </div>
                <div class="overflow-x-auto flex-1">
                    <table class="w-full text-left text-sm whitespace-nowrap">
                        <thead class="bg-white border-b border-slate-100 text-slate-400 font-bold uppercase text-[10px] tracking-widest">
                            <tr>
                                <th class="px-6 py-3">Case Ref</th>
                                <th class="px-6 py-3">User</th>
                                <th class="px-6 py-3 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            @forelse($recentCases as $case)
                                <tr class="hover:bg-slate-50/80 transition-colors group">
                                    <td class="px-6 py-3">
                                        <div class="font-mono text-xs font-bold text-primary-600">{{ $case->case_reference_id }}</div>
                                        <div class="text-[10px] font-bold uppercase text-slate-400 mt-0.5">{{ $case->status }}</div>
                                    </td>
                                    <td class="px-6 py-3">
                                        <div class="text-sm font-semibold text-slate-700">{{ $case->user->name ?? 'Unknown User' }}</div>
                                    </td>
                                    <td class="px-6 py-3 text-right">
                                        @if($case->user && $case->user->canBeImpersonated())
                                            {{-- FIXED: Removed opacity-0 group-hover:opacity-100 --}}
                                            <a href="{{ route('admin.impersonate.case', $case->id) }}" 
                                               class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-100 hover:bg-primary-50 text-slate-600 hover:text-primary-700 rounded-lg text-xs font-bold transition-all border border-slate-200 hover:border-primary-200">
                                                <i data-lucide="eye" class="w-3.5 h-3.5"></i> View
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="px-6 py-8 text-center text-slate-400 text-xs">No cases found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
@endsection