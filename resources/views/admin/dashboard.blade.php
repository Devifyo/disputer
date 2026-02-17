@extends('layouts.admin')

@section('title', 'Admin Dashboard')

@section('content')
    <div class="p-6">
        <h1 class="text-2xl font-bold text-slate-900 mb-6">Dashboard Overview</h1>
        
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            
            {{-- Total Users --}}
            <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm flex flex-col">
                <h3 class="text-slate-500 text-xs font-bold uppercase tracking-wider">Total Users</h3>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="text-3xl font-bold text-slate-900">{{ number_format($stats['total_users']) }}</span>
                </div>
            </div>

            {{-- Total Cases --}}
            <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm flex flex-col">
                <h3 class="text-slate-500 text-xs font-bold uppercase tracking-wider">Total Disputes</h3>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="text-3xl font-bold text-slate-900">{{ number_format($stats['total_cases']) }}</span>
                </div>
            </div>

            {{-- Pending Action --}}
            <div class="bg-white p-6 rounded-xl border border-amber-100 bg-amber-50/50 shadow-sm flex flex-col">
                <h3 class="text-amber-600/70 text-xs font-bold uppercase tracking-wider">Pending Action</h3>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="text-3xl font-bold text-amber-700">{{ number_format($stats['pending_cases']) }}</span>
                    <span class="text-xs text-amber-600 font-medium">Requires attention</span>
                </div>
            </div>

            {{-- Escalated (New) --}}
            <div class="bg-white p-6 rounded-xl border border-purple-100 bg-purple-50/50 shadow-sm flex flex-col">
                <h3 class="text-purple-600/70 text-xs font-bold uppercase tracking-wider">Escalated</h3>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="text-3xl font-bold text-purple-700">{{ number_format($stats['escalated_cases']) }}</span>
                    <span class="text-xs text-purple-600 font-medium">High Priority</span>
                </div>
            </div>

        </div>
    </div>
@endsection