@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')

{{-- ── Top bar ──────────────────────────────────────────────────────────────────── --}}
<header class="h-16 bg-white border-b border-slate-200 flex items-center justify-between px-4 sm:px-8 shrink-0 z-10 sticky top-0">
    <div class="flex items-center gap-3">
        <h1 class="font-black text-slate-900 text-lg tracking-tight">Dashboard</h1>
        @if($isEmailConfigured)
            <span class="hidden sm:inline-flex items-center gap-1.5 text-[10px] font-bold bg-emerald-50 text-emerald-700 px-2.5 py-1 rounded-full border border-emerald-100">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                System Active
            </span>
        @else
            <span class="hidden sm:inline-flex items-center gap-1.5 text-[10px] font-bold bg-rose-50 text-rose-700 px-2.5 py-1 rounded-full border border-rose-100">
                <i data-lucide="alert-octagon" class="w-3 h-3"></i>
                Setup Needed
            </span>
        @endif
    </div>

    <a href="{{ route('user.cases.create') }}"
       class="inline-flex items-center gap-2 bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-xl text-xs font-bold shadow-md shadow-primary-600/20 transition-all hover:scale-105 active:scale-95">
        <i data-lucide="plus" class="w-3.5 h-3.5"></i>
        <span class="hidden sm:inline">New Case</span>
        <span class="sm:hidden">New</span>
    </a>
</header>

{{-- ── Page body ────────────────────────────────────────────────────────────────── --}}
<div class="flex-1 overflow-y-auto bg-slate-50">
    <div class="max-w-[1400px] mx-auto px-4 sm:px-8 py-7 space-y-6">

        @include('user.cases.partials.alerts')

        {{-- Stat cards --}}
        @include('user.partials.stats')

        {{-- Main grid — items-stretch so both columns match height --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-stretch">

            {{-- Left: active cases (2/3) --}}
            <div class="lg:col-span-2 flex flex-col">
                @include('user.partials.active-cases')
            </div>

            {{-- Right: recent emails (1/3) --}}
            <div class="lg:col-span-1 flex flex-col">
                @include('user.partials.recent-emails')
            </div>

        </div>
    </div>
</div>

@endsection
