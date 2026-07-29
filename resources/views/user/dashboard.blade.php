@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')

{{-- ── Top bar ──────────────────────────────────────────────────────────────────── --}}
<header class="h-16 bg-white border-b border-slate-200 flex items-center justify-between px-4 sm:px-8 shrink-0 z-10 sticky top-0">
    <h1 class="font-black text-slate-900 text-lg tracking-tight">Dashboard</h1>

    <div class="flex items-center gap-2">
        <a href="{{ url('/flight-disputes/trips') }}"
           class="hidden sm:inline-flex items-center gap-2 border border-slate-200 hover:border-slate-300 text-slate-700 px-4 py-2 rounded-xl text-xs font-bold transition-colors">
            <i data-lucide="shield-check" class="w-3.5 h-3.5"></i> Protect a trip
        </a>
        <a href="{{ url('/flight-disputes/claims/new') }}"
           class="inline-flex items-center gap-2 bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-xl text-xs font-bold shadow-md shadow-primary-600/20 transition-all">
            <i data-lucide="plus" class="w-3.5 h-3.5"></i>
            <span class="hidden sm:inline">New claim</span>
            <span class="sm:hidden">New</span>
        </a>
    </div>
</header>

{{-- ── Page body ────────────────────────────────────────────────────────────────── --}}
<div class="flex-1 overflow-y-auto bg-slate-50">
    <div class="max-w-[1400px] mx-auto px-4 sm:px-8 py-7 space-y-6">

        {{-- Hero: the money is the story, so it leads the page --}}
        <div class="rounded-3xl bg-slate-900 text-white overflow-hidden shadow-xl shadow-slate-900/10">
            <div class="px-6 sm:px-8 py-7">
                <p class="text-[11px] uppercase tracking-[0.2em] font-bold text-slate-400">
                    {{ count($stats['recovered']) ? 'Recovered for you' : 'Compensation being claimed' }}
                </p>

                @php $headline = count($stats['recovered']) ? $stats['recovered'] : $stats['expected']; @endphp
                @if (count($headline))
                    <div class="flex flex-wrap items-baseline gap-x-4 gap-y-1 mt-2">
                        @foreach ($headline as $i => $money)
                            <span class="{{ $i === 0 ? 'text-3xl sm:text-4xl' : 'text-xl sm:text-2xl text-slate-300' }} font-bold tracking-tight">
                                {{ $money['currency'] }} {{ number_format($money['amount'], 2) }}
                            </span>
                        @endforeach
                    </div>
                    <p class="text-[13px] text-slate-400 mt-2">
                        @if (count($stats['recovered']))
                            Paid out to you, after our success fee.
                            @if (count($stats['expected']))
                                <span class="text-slate-300">Another {{ collect($stats['expected'])->map(fn ($m) => $m['currency'] . ' ' . number_format($m['amount'], 2))->implode(' and ') }} still being chased.</span>
                            @endif
                        @else
                            Across {{ $stats['claims_active'] }} open claim{{ $stats['claims_active'] === 1 ? '' : 's' }} - we only take our fee if you win.
                        @endif
                    </p>
                @else
                    <p class="text-2xl font-bold tracking-tight mt-2">Let's find what you're owed</p>
                    <p class="text-[13px] text-slate-400 mt-1.5">Start a claim for a disrupted flight, or add an upcoming trip and we'll watch it for you.</p>
                @endif
            </div>

            <dl class="grid grid-cols-3 divide-x divide-white/10 border-t border-white/10 text-center">
                <div class="px-3 py-3.5">
                    <dt class="text-[10px] uppercase tracking-wider font-bold text-slate-400">Claims</dt>
                    <dd class="text-lg font-bold mt-0.5">{{ $stats['claims_total'] }}</dd>
                </div>
                <div class="px-3 py-3.5">
                    <dt class="text-[10px] uppercase tracking-wider font-bold text-slate-400">In progress</dt>
                    <dd class="text-lg font-bold mt-0.5">{{ $stats['claims_active'] }}</dd>
                </div>
                <div class="px-3 py-3.5">
                    <dt class="text-[10px] uppercase tracking-wider font-bold text-slate-400">Trips watched</dt>
                    <dd class="text-lg font-bold mt-0.5">{{ $stats['trips_watched'] }}</dd>
                </div>
            </dl>
        </div>

        {{-- Waiting on you: each row carries its own flight and amount, so
             three claims never read as the same line three times. --}}
        @if (count($actions))
            <div class="bg-white rounded-2xl ring-1 ring-amber-200/70 overflow-hidden">
                <div class="px-5 py-3 bg-amber-50 border-b border-amber-100 flex items-center gap-2">
                    <span class="relative flex w-2 h-2">
                        <span class="absolute inline-flex w-full h-full rounded-full bg-amber-400 opacity-75 animate-ping"></span>
                        <span class="relative inline-flex w-2 h-2 rounded-full bg-amber-500"></span>
                    </span>
                    <h2 class="font-bold text-amber-900 text-sm">Waiting on you</h2>
                    <span class="text-[11px] font-bold text-amber-700/70">{{ count($actions) }} to complete</span>
                </div>
                <ul class="divide-y divide-slate-50">
                    @foreach ($actions as $action)
                        <li class="flex items-center gap-3.5 px-5 py-3.5 flex-wrap">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-bold text-slate-800">
                                    {{ $action['label'] }}
                                    @if ($action['claim'] ?? null)
                                        <span class="text-slate-400 font-medium">·
                                            @if ($action['claim']->departure_airport){{ $action['claim']->departure_airport }} → {{ $action['claim']->arrival_airport }} @endif
                                            {{ $action['claim']->airline }} {{ $action['claim']->flight_number }}
                                        </span>
                                    @endif
                                </p>
                                <p class="text-[12px] text-slate-500">{{ $action['detail'] }}</p>
                            </div>

                            @if (($action['claim'] ?? null) && (float) $action['claim']->compensation_amount > 0)
                                <span class="shrink-0 text-sm font-bold text-slate-800 whitespace-nowrap">
                                    {{ $action['claim']->compensation_currency }} {{ number_format((float) $action['claim']->compensation_amount * max(1, count($action['claim']->passengerNames())), 2) }}
                                </span>
                            @endif

                            <a href="{{ $action['url'] }}"
                               class="shrink-0 bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold px-4 py-2 rounded-xl transition-colors">{{ $action['cta'] }}</a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">

            {{-- Claims --}}
            <div class="lg:col-span-2 bg-white rounded-2xl ring-1 ring-slate-900/5 overflow-hidden">
                <div class="px-5 py-3.5 border-b border-slate-100 flex items-center justify-between gap-2">
                    <h2 class="font-bold text-slate-900 text-sm">Your claims</h2>
                    <a href="{{ url('/flight-disputes') }}" class="text-[11px] font-bold text-primary-600 hover:underline">View all</a>
                </div>

                @if (!$hasClaims)
                    <div class="px-5 py-10 text-center">
                        <i data-lucide="plane" class="w-7 h-7 text-slate-300 mx-auto mb-3"></i>
                        <p class="text-sm font-medium text-slate-500">No claims yet</p>
                        <p class="text-xs text-slate-400 mt-1">Had a delay, cancellation or denied boarding? Start a claim and we'll check what you're owed.</p>
                        <a href="{{ url('/flight-disputes/claims/new') }}"
                           class="inline-block mt-4 bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold px-5 py-2.5 rounded-xl transition-colors">Start a claim</a>
                    </div>
                @else
                    <ul class="divide-y divide-slate-50">
                        @foreach ($claims as $claim)
                            <li>
                                <a href="{{ url('/flight-disputes/claims/' . encrypt_id($claim->id)) }}"
                                   class="flex items-center gap-3.5 px-5 py-4 hover:bg-slate-50/70 transition-colors">
                                    <span class="w-9 h-9 rounded-xl bg-slate-100 text-slate-500 flex items-center justify-center shrink-0">
                                        <i data-lucide="plane" class="w-4 h-4"></i>
                                    </span>

                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-bold text-slate-800 truncate">
                                            @if ($claim->departure_airport && $claim->arrival_airport)
                                                {{ $claim->departure_airport }} → {{ $claim->arrival_airport }}
                                            @else
                                                Flight details still needed
                                            @endif
                                        </p>
                                        <p class="text-[11px] text-slate-400 truncate">
                                            @if ($claim->airline){{ $claim->airline }} {{ $claim->flight_number }} · @endif
                                            @if ($claim->flight_date){{ $claim->flight_date->format('d M Y') }} · @endif
                                            #{{ $claim->number }}
                                        </p>
                                    </div>

                                    {{-- Money above state, right-aligned: two facts in one column
                                         instead of three items fighting for the same row. --}}
                                    <div class="shrink-0 text-right">
                                        @if ((float) $claim->compensation_amount > 0)
                                            <p class="text-sm font-bold text-slate-800 whitespace-nowrap">
                                                {{ $claim->compensation_currency }} {{ number_format((float) $claim->compensation_amount * max(1, count($claim->passengerNames())), 2) }}
                                            </p>
                                        @endif
                                        @php [$stageLabel, $stageCls] = $claim->customerStage(); @endphp
                                        <span class="inline-block mt-1 text-[10px] font-bold px-2 py-0.5 rounded-full whitespace-nowrap {{ $stageCls }}">{{ $stageLabel }}</span>
                                    </div>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            {{-- Protected trips --}}
            <div class="bg-white rounded-2xl ring-1 ring-slate-900/5 overflow-hidden">
                <div class="px-5 py-3.5 border-b border-slate-100 flex items-center justify-between gap-2">
                    <h2 class="font-bold text-slate-900 text-sm">Protected trips</h2>
                    <a href="{{ url('/flight-disputes/trips') }}" class="text-[11px] font-bold text-primary-600 hover:underline">View all</a>
                </div>

                @if (!$hasTrips)
                    <div class="px-5 py-10 text-center">
                        <i data-lucide="shield-check" class="w-7 h-7 text-slate-300 mx-auto mb-3"></i>
                        <p class="text-sm font-medium text-slate-500">No trips being watched</p>
                        <p class="text-xs text-slate-400 mt-1">Add an upcoming flight and we'll monitor it - if it's disrupted, we tell you what you're owed.</p>
                        <a href="{{ url('/flight-disputes/trips') }}"
                           class="inline-block mt-4 border border-slate-200 hover:border-slate-300 text-slate-700 text-xs font-bold px-5 py-2.5 rounded-xl transition-colors">Protect a trip</a>
                    </div>
                @else
                    <ul class="divide-y divide-slate-50">
                        @foreach ($trips as $trip)
                            <li class="px-5 py-3.5">
                                <div class="flex items-center gap-2">
                                    <p class="text-sm font-bold text-slate-800 truncate flex-1">
                                        {{ $trip->departure_airport }} → {{ $trip->arrival_airport }}
                                    </p>
                                    @php
                                        [$label, $cls] = match (true) {
                                            $trip->eligibility_status === 'eligible' => ['Compensation due', 'bg-emerald-100 text-emerald-700'],
                                            $trip->eligibility_status === 'review'   => ['Under review', 'bg-violet-100 text-violet-700'],
                                            $trip->monitoring_status === \App\Models\Trip::MONITORING_ACTIVE => ['Monitoring', 'bg-blue-100 text-blue-700'],
                                            default => [ucfirst($trip->monitoring_status ?: 'pending'), 'bg-slate-100 text-slate-500'],
                                        };
                                    @endphp
                                    <span class="shrink-0 text-[9px] font-black uppercase px-2 py-0.5 rounded-full {{ $cls }}">{{ $label }}</span>
                                </div>
                                <p class="text-[11px] text-slate-400 mt-0.5">
                                    {{ $trip->airline }} {{ $trip->flight_number }} · {{ $trip->departure_date?->format('d M Y') ?? 'date unknown' }}
                                </p>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

        </div>
    </div>
</div>

{{-- The retired case-management dashboard (stats, active cases, recent
     emails) is kept in resources/views/user/partials for reference:
     @include('user.partials.stats')
     @include('user.partials.active-cases')
     @include('user.partials.recent-emails')
--}}

@endsection
