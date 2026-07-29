@extends('layouts.admin')

@use('App\Livewire\Admin\FlightClaims\Claims')

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

            {{-- Total Claims --}}
            <a href="{{ route('admin.flight-claims.claims') }}" wire:navigate
               class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex flex-col transition-transform hover:-translate-y-1">
                <div class="flex justify-between items-start mb-4">
                    <h3 class="text-slate-500 text-xs font-bold uppercase tracking-wider">Total Claims</h3>
                    <div class="p-2 bg-slate-100 text-slate-600 rounded-lg"><i data-lucide="plane" class="w-4 h-4"></i></div>
                </div>
                <div class="flex items-baseline gap-2 mt-auto">
                    <span class="text-3xl font-bold text-slate-900">{{ number_format($stats['total_claims']) }}</span>
                    @if ($stats['claims_review'])
                        <span class="text-xs font-bold text-amber-600">{{ $stats['claims_review'] }} need review</span>
                    @endif
                </div>
            </a>

            {{-- Pending Action --}}
            {{-- <div class="bg-white p-6 rounded-2xl border border-amber-200 bg-gradient-to-br from-amber-50 to-white shadow-sm flex flex-col transition-transform hover:-translate-y-1">
                <div class="flex justify-between items-start mb-4">
                    <h3 class="text-amber-700 text-xs font-bold uppercase tracking-wider">Pending Cases</h3>
                    <div class="p-2 bg-amber-100 text-amber-600 rounded-lg"><i data-lucide="clock" class="w-4 h-4"></i></div>
                </div>
                <div class="flex items-baseline gap-2 mt-auto">
                    <span class="text-3xl font-bold text-amber-700">{{ number_format($stats['pending_cases']) }}</span>
                </div>
            </div> --}}

            {{-- Protected Trips --}}
            <a href="{{ route('admin.flight-claims.trips') }}" wire:navigate
               class="bg-white p-6 rounded-2xl border border-sky-200 bg-gradient-to-br from-sky-50 to-white shadow-sm flex flex-col transition-transform hover:-translate-y-1">
                <div class="flex justify-between items-start mb-4">
                    <h3 class="text-sky-700 text-xs font-bold uppercase tracking-wider">Protected Trips</h3>
                    <div class="p-2 bg-sky-100 text-sky-600 rounded-lg"><i data-lucide="shield-check" class="w-4 h-4"></i></div>
                </div>
                <div class="flex items-baseline gap-2 mt-auto">
                    <span class="text-3xl font-bold text-sky-700">{{ number_format($stats['protected_trips']) }}</span>
                    <span class="text-xs font-bold text-sky-600/70">{{ $stats['trips_watching'] }} monitored now</span>
                </div>
            </a>
            
            {{-- Fees Earned: the success-fee share of payments already PAID out --}}
            <a href="{{ route('admin.flight-claims.payments') }}" wire:navigate
               class="bg-white p-6 rounded-2xl border border-emerald-200 bg-gradient-to-br from-emerald-50 to-white shadow-sm flex flex-col transition-transform hover:-translate-y-1">
                <div class="flex justify-between items-start mb-4">
                    <h3 class="text-emerald-700 text-xs font-bold uppercase tracking-wider">Fees Earned</h3>
                    <div class="p-2 bg-emerald-100 text-emerald-600 rounded-lg">
                        <i data-lucide="banknote" class="w-4 h-4"></i>
                    </div>
                </div>
                <div class="flex flex-col mt-auto">
                    <span class="text-2xl font-bold text-emerald-700 break-words">{{ $stats['fees_earned'] ?: '-' }}</span>
                    <span class="text-xs font-medium text-emerald-600/70 mt-1">success fees on settled claims</span>
                </div>
            </a>
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

            {{-- RECENT CLAIMS TABLE --}}
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm flex flex-col overflow-hidden">
                <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between bg-slate-50/50 shrink-0">
                    <h2 class="text-base font-bold text-slate-800 flex items-center gap-2">
                        <i data-lucide="plane" class="w-4 h-4 text-primary-500"></i> Recent Claims
                    </h2>
                    <a href="{{ route('admin.flight-claims.claims') }}" wire:navigate class="text-[11px] font-bold text-primary-600 hover:text-primary-700 uppercase tracking-wider">View All</a>
                </div>
                @if ($recentClaims->isEmpty())
                    <div class="flex-1 flex flex-col items-center justify-center text-center px-8 py-14">
                        <span class="w-14 h-14 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center mb-4">
                            <i data-lucide="plane" class="w-6 h-6"></i>
                        </span>
                        <p class="text-sm font-bold text-slate-700">No claims yet</p>
                        <p class="text-xs text-slate-400 mt-1.5 max-w-xs leading-relaxed">
                            New claims land here the moment a customer files one - by funnel, ticket upload or email to claims@unjamm.com.
                        </p>
                        <a href="{{ route('admin.flight-claims.claims') }}" wire:navigate
                           class="mt-5 inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-600 hover:border-slate-300 hover:text-slate-900 transition-colors">
                            Open the claims list
                        </a>
                    </div>
                @else
                <div class="overflow-x-auto flex-1">
                    <table class="w-full text-left text-sm whitespace-nowrap">
                        <thead class="bg-white border-b border-slate-100 text-slate-400 font-bold uppercase text-[10px] tracking-widest">
                            <tr>
                                <th class="px-6 py-3">Claim</th>
                                <th class="px-6 py-3">Customer</th>
                                <th class="px-6 py-3">Stage</th>
                                <th class="px-6 py-3 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            @forelse($recentClaims as $claim)
                                @php [$stageLabel, $stageCls] = Claims::stage($claim); @endphp
                                <tr class="hover:bg-slate-50/80 transition-colors">
                                    <td class="px-6 py-3">
                                        <div class="font-bold text-slate-800">{{ $claim->departure_airport }} → {{ $claim->arrival_airport }}</div>
                                        <div class="text-[11px] text-slate-500">{{ $claim->airline }} {{ $claim->flight_number }} · #{{ $claim->number }}</div>
                                    </td>
                                    <td class="px-6 py-3">
                                        <div class="text-sm font-semibold text-slate-700">{{ $claim->user->name ?? '-' }}</div>
                                    </td>
                                    <td class="px-6 py-3">
                                        <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-black ring-1 {{ $stageCls }}">{{ $stageLabel }}</span>
                                    </td>
                                    <td class="px-6 py-3 text-right">
                                        <a href="{{ route('admin.flight-claims.claims.show', $claim) }}" wire:navigate
                                           class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-100 hover:bg-primary-50 text-slate-600 hover:text-primary-700 rounded-lg text-xs font-bold transition-all border border-slate-200 hover:border-primary-200">
                                            <i data-lucide="eye" class="w-3.5 h-3.5"></i> Open
                                        </a>
                                    </td>
                                </tr>
                            @empty
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @endif
            </div>

        </div>
    </div>
@endsection