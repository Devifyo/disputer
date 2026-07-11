<template>
    <div class="flex-1 flex flex-col min-h-0">
        <header class="h-16 bg-white border-b border-slate-200 flex items-center gap-3 px-4 sm:px-8 shrink-0 z-10 sticky top-0">
            <router-link :to="{ name: 'trips' }" class="p-2 -ml-2 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors" title="Back">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7 7-7m-7 7h18"/></svg>
            </router-link>
            <h1 class="font-black text-slate-900 text-lg tracking-tight">Your Trip</h1>
        </header>

        <div class="flex-1 overflow-y-auto overflow-x-hidden bg-slate-100/70">
            <div class="max-w-[1400px] mx-auto px-4 sm:px-8 py-8">

                <div v-if="loading" class="text-sm text-slate-400 py-10 text-center">Loading…</div>
                <div v-else-if="error" class="bg-rose-50 border border-rose-100 text-rose-700 px-4 py-3 rounded-xl text-sm">{{ error }}</div>

                <div v-else-if="trip" class="grid lg:grid-cols-3 gap-6 items-start">
                    <!-- LEFT -->
                    <div class="lg:col-span-2 min-w-0 space-y-5">
                        <!-- Hero -->
                        <div class="relative overflow-hidden rounded-2xl p-6 sm:p-7 text-white" style="background:linear-gradient(105deg,#064e3b 0%,#059669 55%,#10b981 78%,#2563eb 130%);">
                            <div class="flex flex-col xl:flex-row xl:items-center gap-5">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between text-[11px] font-semibold text-white/70 uppercase tracking-wide mb-1">
                                        <span class="truncate">{{ trip.departure_city || trip.departure_airport }}</span>
                                        <span class="truncate text-right">{{ trip.arrival_city || trip.arrival_airport }}</span>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <span class="text-3xl sm:text-4xl font-black tracking-tight">{{ trip.departure_airport || '-' }}</span>
                                        <span class="flex-1 flex items-center">
                                            <span class="h-px flex-1 bg-white/40"></span>
                                            <span class="w-9 h-9 rounded-full border border-white/50 flex items-center justify-center mx-1 shrink-0">
                                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M21 16v-2l-8-5V3.5a1.5 1.5 0 00-3 0V9l-8 5v2l8-2.5V19l-2 1.5V22l3.5-1 3.5 1v-1.5L13 19v-5.5z"/></svg>
                                            </span>
                                            <span class="h-px flex-1 bg-white/40"></span>
                                        </span>
                                        <span class="text-3xl sm:text-4xl font-black tracking-tight">{{ trip.arrival_airport || '-' }}</span>
                                    </div>
                                    <div class="text-xs font-bold text-white/70 mt-2">{{ trip.airline || '' }} {{ trip.flight_number || '' }}</div>
                                </div>
                                <div class="flex gap-3 shrink-0 flex-wrap">
                                    <div class="rounded-xl bg-white/10 border border-white/15 px-4 py-3 min-w-[130px]">
                                        <div class="text-[10px] font-bold text-white/70 uppercase tracking-wide">Departure</div>
                                        <div class="text-lg font-black">{{ trip.departure_date || '-' }}</div>
                                        <div v-if="trip.departure_time" class="text-xs font-bold text-white/70">{{ trip.departure_time }}</div>
                                    </div>
                                    <div class="rounded-xl bg-white/10 border border-white/15 px-4 py-3 min-w-[150px]">
                                        <div class="text-[10px] font-bold text-white/70 uppercase tracking-wide">Status</div>
                                        <div class="flex items-center gap-1.5 text-lg font-black">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3l7 3v5c0 4.5-3 8.5-7 10-4-1.5-7-5.5-7-10V6l7-3z"/><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4"/></svg>
                                            {{ trip.display_status_label || 'Protected' }}
                                        </div>
                                        <div v-if="trip.last_synced_human" class="text-[10px] font-bold text-white/60 mt-0.5">Updated {{ trip.last_synced_human }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Monitoring banner -->
                        <div v-if="verdict === 'eligible'" class="flex items-center gap-3 bg-emerald-50 border border-emerald-100 text-emerald-800 px-4 py-3 rounded-xl text-sm flex-wrap">
                            <svg class="w-5 h-5 shrink-0 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <p class="flex-1 min-w-[220px]">
                                Good news - this trip is <strong>eligible for compensation</strong> under {{ trip.eligibility.regulation }} ({{ trip.eligibility.article }}).
                                <template v-if="trip.claims?.length">Your claim{{ trip.claims.length > 1 ? 's are' : ' is' }} underway.</template>
                            </p>
                            <button
                                v-if="trip.can_claim"
                                @click="startClaim"
                                :disabled="claiming"
                                class="inline-flex items-center gap-2 bg-slate-900 hover:bg-slate-800 text-white px-5 py-2.5 rounded-xl text-sm font-bold shadow-lg shadow-slate-900/10 transition-all active:scale-95 disabled:opacity-60"
                            >
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                {{ claiming ? 'Creating claim…' : 'Start your claim' }}
                            </button>
                            <router-link
                                v-for="c in trip.claims || []"
                                :key="c.id"
                                :to="{ name: 'claim', params: { id: c.id } }"
                                class="inline-flex items-center gap-1.5 bg-white text-emerald-700 ring-1 ring-emerald-200 hover:bg-emerald-100 px-4 py-2 rounded-xl text-xs font-bold transition-all"
                            >
                                View claim #{{ c.number }}
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                            </router-link>
                        </div>
                        <div v-else-if="verdict === 'review'" class="flex items-start gap-3 bg-violet-50 border border-violet-100 text-violet-800 px-4 py-3 rounded-xl text-sm">
                            <svg class="w-5 h-5 shrink-0 text-violet-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l2.5 2.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <p>Good news - you may be <strong>eligible for compensation</strong> under {{ trip.eligibility.regulation }}. <strong>Our team is verifying the details</strong> and will confirm your claim shortly - no action is needed from you.</p>
                        </div>
                        <div v-else-if="verdict === 'rejected'" class="flex items-start gap-3 bg-slate-50 border border-slate-200 text-slate-600 px-4 py-3 rounded-xl text-sm">
                            <svg class="w-5 h-5 shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M12 8v4m0 4h.01"/></svg>
                            <p>{{ trip.eligibility.reason }}</p>
                        </div>
                        <div v-else-if="trip.no_claim_reason" class="bg-slate-50 border border-slate-200 text-slate-600 rounded-xl text-sm">
                            <div class="flex items-start gap-3 px-4 py-3">
                                <svg class="w-5 h-5 shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M12 8v4m0 4h.01"/></svg>
                                <p>{{ trip.no_claim_reason }}</p>
                            </div>
                            <!-- The disruptions flight data can't see - right where the user is looking -->
                            <div v-if="canReport" class="flex items-center gap-2 flex-wrap px-4 pb-3 sm:pl-12">
                                <span class="text-xs font-bold text-slate-500">Something we couldn't see?</span>
                                <button
                                    v-for="option in reportOptions"
                                    :key="option.type"
                                    @click="reportProblem(option)"
                                    :disabled="!!reportingOption"
                                    class="inline-flex items-center bg-white text-slate-700 ring-1 ring-slate-200 hover:ring-slate-300 hover:bg-slate-100 px-3 py-1.5 rounded-lg text-xs font-bold transition-all active:scale-95 disabled:opacity-50"
                                >
                                    {{ option.label }}
                                </button>
                            </div>
                        </div>
                        <div v-else-if="trip.potentially_eligible" class="flex items-start gap-3 bg-violet-50 border border-violet-100 text-violet-800 px-4 py-3 rounded-xl text-sm">
                            <svg class="w-5 h-5 shrink-0 text-violet-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.3 4.3L2.8 18a2 2 0 001.7 3h15a2 2 0 001.7-3L13.7 4.3a2 2 0 00-3.4 0z"/></svg>
                            <p>This trip was <strong>disrupted</strong> and may be eligible for compensation. <strong>We're reviewing your eligibility</strong> - no action is needed from you right now.</p>
                        </div>
                        <div v-else class="flex items-start gap-3 bg-emerald-50 border border-emerald-100 text-emerald-800 px-4 py-3 rounded-xl text-sm">
                            <svg class="w-5 h-5 shrink-0 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3l7 3v5c0 4.5-3 8.5-7 10-4-1.5-7-5.5-7-10V6l7-3z"/><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4"/></svg>
                            <p>This trip is <strong>Protected by Unjamm</strong>. We're watching this flight, and if it's delayed, cancelled, or overbooked, your claim will be ready before you land.</p>
                        </div>

                        <!-- Tabs (scrolls within itself on narrow screens) -->
                        <div class="flex items-center gap-1 bg-white rounded-2xl ring-1 ring-slate-900/5 p-1.5 overflow-x-auto">
                            <button
                                v-for="t in tabs"
                                :key="t.key"
                                @click="tab = t.key"
                                class="flex-1 shrink-0 inline-flex items-center justify-center gap-2 px-3 sm:px-4 py-2.5 rounded-xl text-sm font-bold whitespace-nowrap transition-colors"
                                :class="tab === t.key ? 'bg-slate-900 text-white shadow-lg shadow-slate-900/10' : 'text-slate-500 hover:text-slate-800 hover:bg-slate-50'"
                            >
                                <!-- Flight status: live-activity pulse · Compensation: banknote · Trip details: ticket -->
                                <svg v-if="t.key === 'status'" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.5 12h3.5l2.5-6.5 5 13 2.5-6.5h3.5"/></svg>
                                <svg v-else-if="t.key === 'compensation'" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><rect x="2.5" y="6" width="19" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path stroke-linecap="round" d="M6 9.5v.01M18 14.5v.01"/></svg>
                                <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 6v.75m0 3v.75m0 3v.75m0 3V18m2.25-13.5h-15A2.25 2.25 0 001.5 6.75v2.25a.75.75 0 00.75.75 2.25 2.25 0 010 4.5.75.75 0 00-.75.75v2.25a2.25 2.25 0 002.25 2.25h15a2.25 2.25 0 002.25-2.25v-2.25a.75.75 0 00-.75-.75 2.25 2.25 0 010-4.5.75.75 0 00.75-.75V6.75a2.25 2.25 0 00-2.25-2.25z"/></svg>
                                {{ t.label }}
                                <span v-if="t.key === 'status' && monitoring.events.length" class="ml-0.5 min-w-[20px] h-5 px-1.5 rounded-full text-[11px] font-black flex items-center justify-center" :class="tab === t.key ? 'bg-white/25 text-white' : 'bg-slate-100 text-slate-500'">{{ monitoring.events.length }}</span>
                                <!-- Blinking dot: an eligible verdict is waiting for its claim -->
                                <span v-if="t.key === 'compensation' && trip.can_claim" class="relative flex w-2.5 h-2.5 ml-0.5">
                                    <span class="absolute inline-flex w-full h-full rounded-full bg-rose-400 opacity-75 animate-ping"></span>
                                    <span class="relative inline-flex w-2.5 h-2.5 rounded-full bg-rose-500"></span>
                                </span>
                            </button>
                        </div>

                        <!-- ═══ TAB: Flight status ═══ -->
                        <template v-if="tab === 'status'">
                            <!-- Live flight status (FlightAware) -->
                            <div class="bg-white rounded-2xl ring-1 ring-slate-900/5 p-6 sm:p-8">
                                <div class="flex items-center justify-between gap-3 mb-5 flex-wrap">
                                    <h2 class="font-bold text-slate-900">Live flight status</h2>
                                    <div class="flex items-center gap-3">
                                        <TripStatusBadge :status="trip.display_status" :label="trip.display_status_label" />
                                        <button
                                            @click="refresh"
                                            :disabled="refreshing"
                                            class="inline-flex items-center gap-1.5 text-xs font-bold text-primary-600 hover:text-primary-700 hover:bg-primary-50 px-3 py-2 rounded-lg transition-colors disabled:opacity-50"
                                        >
                                            <svg class="w-3.5 h-3.5" :class="refreshing && 'animate-spin'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h5M20 20v-5h-5M5.5 15a7 7 0 0011.4 2.5L20 15M18.5 9A7 7 0 007.1 6.5L4 9"/></svg>
                                            {{ refreshing ? 'Refreshing…' : 'Refresh' }}
                                        </button>
                                    </div>
                                </div>

                                <template v-if="trip.fa_flight_id">
                                    <!-- Where is the plane right now? -->
                                    <div v-if="phaseInfo" class="rounded-xl px-4 py-3.5 mb-5" :class="phaseInfo.box">
                                        <div class="flex items-center gap-3">
                                            <span class="text-xl leading-none">{{ phaseInfo.icon }}</span>
                                            <div class="min-w-0">
                                                <div class="font-bold text-sm">{{ phaseInfo.headline }}</div>
                                                <div v-if="phaseInfo.sub" class="text-xs opacity-80 mt-0.5">{{ phaseInfo.sub }}</div>
                                                <div v-if="phaseInfo.detail" class="text-xs opacity-80 mt-0.5">{{ phaseInfo.detail }}</div>
                                            </div>
                                        </div>
                                        <!-- In-flight progress: origin → destination -->
                                        <div v-if="phaseInfo.progress !== null" class="mt-3">
                                            <div class="flex items-center gap-3">
                                                <span class="text-[11px] font-black">{{ trip.departure_airport }}</span>
                                                <div class="flex-1 h-1.5 rounded-full bg-black/10 overflow-hidden">
                                                    <div class="h-full rounded-full bg-current transition-all" :style="{ width: phaseInfo.progress + '%' }"></div>
                                                </div>
                                                <span class="text-[11px] font-black">{{ trip.arrival_airport }}</span>
                                            </div>
                                            <div v-if="phaseInfo.miles" class="flex justify-between text-[11px] font-medium opacity-70 mt-1">
                                                <span>{{ phaseInfo.miles.flown.toLocaleString() }} mi flown</span>
                                                <span>{{ phaseInfo.miles.togo.toLocaleString() }} mi to go</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="grid sm:grid-cols-2 gap-5">
                                        <div v-for="side in ['departure', 'arrival']" :key="side" class="rounded-xl border border-slate-200 p-4">
                                            <div class="text-[11px] uppercase tracking-wider font-bold text-slate-400 mb-2">
                                                {{ side === 'departure' ? 'Departure' : 'Arrival' }}
                                                <span class="text-slate-300 normal-case font-medium">- {{ airportLabel(side) }}{{ tzFor(side) ? ' · local time' : '' }}</span>
                                            </div>
                                            <dl class="space-y-1.5 text-sm">
                                                <div class="flex justify-between gap-3">
                                                    <dt class="text-slate-400 font-medium">Scheduled</dt>
                                                    <dd class="font-bold text-slate-800">{{ fmtSide(trip[`scheduled_${side}`], side) || '-' }}</dd>
                                                </div>
                                                <div class="flex justify-between gap-3">
                                                    <dt class="text-slate-400 font-medium">Estimated</dt>
                                                    <dd class="font-bold" :class="delayFor(side) > 0 ? 'text-amber-600' : 'text-slate-800'">{{ fmtSide(trip[`estimated_${side}`], side) || '-' }}</dd>
                                                </div>
                                                <div class="flex justify-between gap-3">
                                                    <dt class="text-slate-400 font-medium">Actual</dt>
                                                    <dd class="font-bold text-slate-800">{{ fmtSide(trip[`actual_${side}`], side) || '-' }}</dd>
                                                </div>
                                                <div class="flex justify-between gap-3">
                                                    <dt class="text-slate-400 font-medium">Delay</dt>
                                                    <dd class="font-bold" :class="delayFor(side) > 0 ? 'text-amber-600' : 'text-emerald-600'">
                                                        {{ delayFor(side) > 0 ? `+${delayFor(side)} min` : 'None' }}
                                                    </dd>
                                                </div>
                                                <div class="flex justify-between gap-3">
                                                    <dt class="text-slate-400 font-medium">Gate</dt>
                                                    <dd class="font-bold text-slate-800">{{ side === 'departure' ? (trip.origin_gate || '-') : (trip.destination_gate || '-') }}</dd>
                                                </div>
                                                <div class="flex justify-between gap-3">
                                                    <dt class="text-slate-400 font-medium">Terminal</dt>
                                                    <dd class="font-bold text-slate-800">{{ side === 'departure' ? (trip.origin_terminal || '-') : (trip.destination_terminal || '-') }}</dd>
                                                </div>
                                            </dl>
                                        </div>
                                    </div>
                                    <!-- Trust line: monitoring is alive, no technical noise -->
                                    <div class="flex items-center gap-2 mt-4 text-xs text-slate-400 font-medium flex-wrap">
                                        <svg class="w-3.5 h-3.5 text-emerald-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        <span v-if="trip.last_synced_human">Last checked {{ trip.last_synced_human }}.</span>
                                        <span v-if="trip.next_poll_at">Next automatic check {{ fmt(trip.next_poll_at) }}.</span>
                                        <span v-else-if="trip.monitoring_status === 'completed'">Monitoring complete.</span>
                                    </div>
                                </template>

                                <p v-else-if="trip.monitoring_status === 'failed'" class="text-sm text-slate-400">
                                    We couldn't find live tracking for this flight. Please check that the flight number and departure date are correct.
                                </p>
                                <p v-else class="text-sm text-slate-400">
                                    We're setting up live tracking for this flight. Its status will appear here shortly.
                                </p>
                            </div>

                            <!-- Route reliability (informational) -->
                            <div v-if="trip.route_stats" class="bg-white rounded-2xl ring-1 ring-slate-900/5 p-6 sm:p-8">
                                <h2 class="font-bold text-slate-900 mb-1">Route reliability</h2>
                                <p class="text-xs text-slate-400 mb-5">Based on the last {{ trip.route_stats.sample_size }} operations of {{ trip.fa_ident || trip.flight_number }}. Informational only.</p>
                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                                    <div v-for="tile in reliabilityTiles" :key="tile.label" class="rounded-xl bg-slate-50 p-4">
                                        <div class="text-2xl font-black" :class="tile.valueClass || 'text-slate-900'">
                                            {{ tile.value }}<span v-if="tile.suffix" class="text-sm font-bold text-slate-400"> {{ tile.suffix }}</span>
                                        </div>
                                        <div class="flex items-center gap-1 text-[11px] uppercase tracking-wider font-bold text-slate-400 mt-1">
                                            {{ tile.label }}
                                            <InfoTip :text="tile.tip" />
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Passenger-reported disruptions (invisible to flight data);
                                 completed no-claim trips get these buttons in the top banner instead -->
                            <div v-if="canReport && !trip.no_claim_reason" class="bg-white rounded-2xl ring-1 ring-slate-900/5 p-6 sm:p-8">
                                <h2 class="font-bold text-slate-900 mb-1">Something went wrong that we couldn't see?</h2>
                                <p class="text-xs text-slate-400 mb-5">Denied boarding, downgrades and missed connections don't show up in flight data. Report it and we'll review your eligibility right away.</p>
                                <div class="flex gap-2 flex-wrap">
                                    <button
                                        v-for="option in reportOptions"
                                        :key="option.type"
                                        @click="reportProblem(option)"
                                        :disabled="!!reportingOption"
                                        class="inline-flex items-center gap-2 bg-white text-slate-700 ring-1 ring-slate-200 hover:ring-slate-300 hover:bg-slate-50 px-4 py-2.5 rounded-xl text-sm font-bold transition-all active:scale-95 disabled:opacity-50"
                                    >
                                        {{ option.label }}
                                    </button>
                                </div>
                            </div>

                            <!-- Flight events -->
                            <div class="bg-white rounded-2xl ring-1 ring-slate-900/5 p-6 sm:p-8">
                                <h2 class="font-bold text-slate-900 mb-5">Flight events</h2>
                                <ul v-if="monitoring.events.length" class="space-y-3">
                                    <li v-for="e in monitoring.events" :key="e.id" class="flex items-start gap-3">
                                        <span class="mt-0.5 w-7 h-7 rounded-full flex items-center justify-center shrink-0" :class="e.qualifying ? 'bg-violet-50 text-violet-600' : 'bg-slate-100 text-slate-500'">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l2.5 2.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        </span>
                                        <div class="min-w-0">
                                            <p class="text-sm font-medium text-slate-800">{{ e.description }}</p>
                                            <p class="text-xs text-slate-400 mt-0.5">
                                                {{ e.detected_human }}
                                                <span v-if="e.qualifying" class="ml-2 inline-flex items-center text-violet-600 font-bold">Eligibility review triggered</span>
                                            </p>
                                        </div>
                                    </li>
                                </ul>
                                <p v-else class="text-sm text-slate-400">
                                    Nothing to report - no delays, cancellations or gate changes so far.
                                    If anything happens to this flight, we'll record it here and let you know.
                                </p>
                            </div>
                        </template>

                        <!-- ═══ TAB: Compensation ═══ -->
                        <template v-else-if="tab === 'compensation'">
                            <!-- Eligibility verdict -->
                            <div v-if="trip.eligibility" class="bg-white rounded-2xl ring-1 ring-slate-900/5 p-6 sm:p-8">
                                <div class="flex items-center justify-between gap-3 mb-5 flex-wrap">
                                    <h2 class="font-bold text-slate-900">Compensation eligibility</h2>
                                    <TripStatusBadge :status="verdict === 'eligible' ? 'eligible' : verdict === 'review' ? 'eligibility_review_pending' : 'not_eligible'" />
                                </div>
                                <div class="grid sm:grid-cols-3 gap-4 mb-5">
                                    <div class="rounded-xl bg-slate-50 p-4">
                                        <div class="text-[11px] uppercase tracking-wider font-bold text-slate-400 mb-1">Regulation</div>
                                        <div class="font-black text-slate-900">{{ regulationLabel(trip.eligibility.regulation) }}</div>
                                    </div>
                                    <div class="rounded-xl bg-slate-50 p-4">
                                        <div class="text-[11px] uppercase tracking-wider font-bold text-slate-400 mb-1">Legal basis</div>
                                        <div class="font-black text-slate-900">{{ trip.eligibility.article || '-' }}</div>
                                    </div>
                                    <!-- Confidence only makes sense on the engine's own final
                                         verdicts; human decisions and pending reviews say so instead. -->
                                    <div v-if="verdict === 'review'" class="rounded-xl bg-violet-50 p-4">
                                        <div class="text-[11px] uppercase tracking-wider font-bold text-violet-400 mb-1">What happens next</div>
                                        <div class="font-black text-violet-700">Our team is on it</div>
                                    </div>
                                    <div v-else-if="trip.eligibility.decided_by === 'team'" class="rounded-xl bg-emerald-50 p-4">
                                        <div class="text-[11px] uppercase tracking-wider font-bold text-emerald-500 mb-1">Decision</div>
                                        <div class="flex items-center gap-1.5 font-black text-emerald-700">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4"/></svg>
                                            Verified by our team
                                        </div>
                                    </div>
                                    <div v-else class="rounded-xl bg-slate-50 p-4">
                                        <div class="flex items-center gap-1 text-[11px] uppercase tracking-wider font-bold text-slate-400 mb-1">
                                            Confidence
                                            <InfoTip text="How certain our automated check is about this verdict, based on data quality, jurisdiction and how clearly the disruption crosses the legal threshold." />
                                        </div>
                                        <div class="flex items-center gap-3">
                                            <div class="font-black" :class="confidenceColor">{{ trip.eligibility.confidence }}%</div>
                                            <div class="flex-1 h-1.5 rounded-full bg-slate-200 overflow-hidden">
                                                <div class="h-full rounded-full transition-all" :class="confidenceBar" :style="{ width: trip.eligibility.confidence + '%' }"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <p class="text-sm text-slate-600">{{ trip.eligibility.reason }}</p>

                                <div v-if="trip.can_claim" class="mt-5 pt-5 border-t border-slate-100 flex items-center justify-between gap-3 flex-wrap">
                                    <p class="text-sm text-slate-500">
                                        {{ (trip.passengers || []).length > 1 ? `We'll create one claim per passenger (${trip.passengers.length}).` : 'Create your claim in one click - everything is pre-filled from this trip.' }}
                                    </p>
                                    <button
                                        @click="startClaim"
                                        :disabled="claiming"
                                        class="inline-flex items-center gap-2 bg-slate-900 hover:bg-slate-800 text-white px-5 py-2.5 rounded-xl text-sm font-bold shadow-lg shadow-slate-900/10 transition-all active:scale-95 disabled:opacity-60"
                                    >
                                        {{ claiming ? 'Creating claim…' : 'Start your claim' }}
                                    </button>
                                </div>
                            </div>

                            <!-- Filed claims -->
                            <div v-if="(trip.claims || []).length" class="bg-white rounded-2xl ring-1 ring-slate-900/5 p-6 sm:p-8">
                                <h2 class="font-bold text-slate-900 mb-5">Your claims</h2>
                                <router-link
                                    v-for="c in trip.claims"
                                    :key="c.id"
                                    :to="{ name: 'claim', params: { id: c.id } }"
                                    class="flex items-center gap-3 px-4 py-3 rounded-xl border border-slate-200 hover:border-primary-300 hover:bg-primary-50/40 transition-colors mb-2 last:mb-0"
                                >
                                    <span class="w-9 h-9 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    </span>
                                    <span class="min-w-0">
                                        <span class="block text-sm font-bold text-slate-800">Claim #{{ c.number }} - {{ c.passenger_name }}</span>
                                        <span class="block text-xs text-slate-400 font-medium">{{ c.status_label }}</span>
                                    </span>
                                    <svg class="w-4 h-4 text-slate-300 ml-auto shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                                </router-link>
                            </div>
                        </template>

                        <!-- ═══ TAB: Trip details ═══ -->
                        <template v-else>
                            <!-- Details -->
                            <div class="bg-white rounded-2xl ring-1 ring-slate-900/5 p-6 sm:p-8">
                                <h2 class="font-bold text-slate-900 mb-5">Trip details</h2>
                                <dl class="grid sm:grid-cols-2 gap-y-4 gap-x-8">
                                    <div v-for="row in detailRows" :key="row.label">
                                        <dt class="text-[11px] uppercase tracking-wider font-bold text-slate-400">{{ row.label }}</dt>
                                        <dd class="mt-1 font-medium text-slate-900">{{ row.value || '-' }}</dd>
                                    </div>
                                </dl>
                            </div>

                            <!-- Passengers -->
                            <div class="bg-white rounded-2xl ring-1 ring-slate-900/5 p-6 sm:p-8">
                                <h2 class="font-bold text-slate-900 mb-5">Passengers</h2>
                                <ul v-if="(trip.passengers || []).length" class="space-y-2">
                                    <li v-for="(p, i) in trip.passengers" :key="i" class="flex items-center gap-3 px-4 py-3 rounded-xl border border-slate-200">
                                        <span class="w-9 h-9 rounded-full bg-primary-50 text-primary-600 flex items-center justify-center shrink-0 text-sm font-bold">{{ initials(p) }}</span>
                                        <span class="text-sm font-medium text-slate-700">{{ p }}</span>
                                        <span class="ml-auto inline-flex items-center gap-1 text-emerald-600 text-xs font-bold">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3l7 3v5c0 4.5-3 8.5-7 10-4-1.5-7-5.5-7-10V6l7-3z"/><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4"/></svg>
                                            Protected
                                        </span>
                                    </li>
                                </ul>
                                <p v-else class="text-sm text-slate-400">No passengers recorded.</p>
                            </div>

                            <!-- Ticket -->
                            <div class="bg-white rounded-2xl ring-1 ring-slate-900/5 p-6 sm:p-8">
                                <h2 class="font-bold text-slate-900 mb-5">Ticket</h2>
                                <a v-if="trip.has_ticket" :href="trip.ticket_url" target="_blank" class="flex items-center gap-3 px-4 py-3 rounded-xl border border-slate-200 hover:border-primary-300 hover:bg-primary-50/40 transition-colors">
                                    <svg class="w-5 h-5 text-primary-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 4H7a2 2 0 01-2-2V6a2 2 0 012-2h7l5 5v11a2 2 0 01-2 2z"/></svg>
                                    <span class="text-sm font-medium text-slate-700">View uploaded ticket</span>
                                    <svg class="w-4 h-4 text-slate-300 ml-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                </a>
                                <p v-else class="text-sm text-slate-400">No ticket attached.</p>
                                <div v-if="trip.ticket_price" class="mt-4 text-sm text-slate-500">Ticket price: <span class="font-bold text-slate-800">{{ trip.ticket_price }} {{ trip.ticket_currency }}</span></div>
                            </div>

                            <!-- Danger zone -->
                            <div class="flex justify-end">
                                <button @click="removeTrip" class="inline-flex items-center gap-2 text-sm font-bold text-rose-500 hover:text-rose-600 hover:bg-rose-50 px-4 py-2.5 rounded-xl transition-colors">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6 7h12M9 7V5a1 1 0 011-1h4a1 1 0 011 1v2m-8 0l1 13a1 1 0 001 1h6a1 1 0 001-1l1-13"/></svg>
                                    Remove this trip
                                </button>
                            </div>
                        </template>
                    </div>

                    <!-- RIGHT: help -->
                    <HelpPanel />

                    <!-- Disruption report funnel -->
                    <ReportWizard
                        v-if="reportingOption"
                        :trip-id="props.id"
                        :option="reportingOption"
                        @close="reportingOption = null"
                        @submitted="onReportSubmitted"
                    />
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import api from '../api';
import { confirmAction, confirmRemove, showProcessing } from '../confirm';
import { formatDateTime, formatDuration, formatTime } from '../datetime';
import HelpPanel from '../components/HelpPanel.vue';
import ReportWizard from '../components/ReportWizard.vue';
import InfoTip from '../components/InfoTip.vue';
import TripStatusBadge from '../components/TripStatusBadge.vue';

const props = defineProps({ id: { type: [String, Number], required: true } });
const router = useRouter();

const trip = ref(null);
const loading = ref(true);
const error = ref('');
const monitoring = ref({ events: [] });
const refreshing = ref(false);

// The Compensation tab appears once the Eligibility Engine has ruled,
// and leads when the trip is eligible - that's what the user came for.
const tabs = computed(() => {
    const compensation = trip.value?.eligibility ? [{ key: 'compensation', label: 'Compensation' }] : [];
    const status = [{ key: 'status', label: 'Flight status' }];
    const details = [{ key: 'details', label: 'Trip details' }];

    return trip.value?.eligibility?.status === 'eligible'
        ? [...compensation, ...status, ...details]
        : [...status, ...compensation, ...details];
});
const tab = ref('status');

const fmt = formatDateTime;

function tzFor(side) {
    return side === 'departure' ? trip.value?.origin_timezone : trip.value?.destination_timezone;
}

// Airport-local time - what's printed on tickets and departure boards.
function fmtSide(iso, side) {
    return formatDateTime(iso, tzFor(side) || undefined);
}

// "Frankfurt" / "JFK" - city if known, else the airport code.
function placeName(side) {
    const t = trip.value || {};
    return side === 'departure'
        ? (t.departure_city || t.departure_airport || 'local')
        : (t.arrival_city || t.arrival_airport || 'local');
}

// "Dubai Int'l (DXB)" - full airport name with its code.
function airportLabel(side) {
    const t = trip.value || {};
    const code = side === 'departure' ? t.departure_airport : t.arrival_airport;
    const name = side === 'departure'
        ? (t.origin_airport_name || t.departure_city)
        : (t.destination_airport_name || t.arrival_city);

    return name ? `${name} (${code})` : code || '';
}

// Ticks every 30s so the "arriving in…" countdown and progress bar stay live.
const now = ref(Date.now());
const clock = setInterval(() => (now.value = Date.now()), 30_000);
onUnmounted(() => clearInterval(clock));

// One glance answer to "where is the plane right now?"
function joinFacts(parts) {
    const line = parts.filter(Boolean).join(' · ');
    return line || null;
}

const phaseInfo = computed(() => {
    const t = trip.value;
    if (!t?.flight_phase) return null;

    const arrival = t.estimated_arrival || t.scheduled_arrival;

    switch (t.flight_phase) {
        case 'cancelled':
            return {
                icon: '⚠️', box: 'bg-rose-50 text-rose-700', progress: null,
                headline: 'This flight was cancelled',
                sub: 'See your compensation eligibility below.',
            };
        case 'landed':
            return {
                icon: '🛬', box: 'bg-slate-100 text-slate-700', progress: null,
                headline: `Landed at ${airportLabel('arrival')}`,
                sub: `Arrived ${fmtSide(t.actual_arrival, 'arrival')} local time`
                    + (t.arrival_delay_minutes > 15 ? ` - ${formatDuration(t.arrival_delay_minutes * 60000)} late` : ' - on time'),
                detail: joinFacts([
                    t.destination_terminal && `Terminal ${t.destination_terminal}`,
                    t.destination_gate && `Gate ${t.destination_gate}`,
                ]),
            };
        case 'enroute': {
            const dep = new Date(t.actual_departure).getTime();
            const arr = arrival ? new Date(arrival).getTime() : null;
            const landsInMs = arr ? arr - now.value : null;

            // FlightAware's own progress figure when it has one; otherwise
            // interpolate between departure and estimated arrival.
            const timeBased = arr && arr > dep ? Math.round(((now.value - dep) / (arr - dep)) * 100) : null;
            const pct = Number.isFinite(t.progress_percent) ? t.progress_percent : timeBased;
            const progress = pct === null ? null : Math.min(98, Math.max(2, pct));

            const dist = t.route_distance_miles;
            const flown = dist && progress !== null ? Math.round((dist * progress) / 100) : null;

            return {
                icon: '✈️', box: 'bg-sky-50 text-sky-800', progress,
                headline: landsInMs !== null && landsInMs > 0
                    ? `In the air - arriving in ${formatDuration(landsInMs)}`
                    : 'In the air - landing shortly',
                sub: arr ? `Estimated arrival ${formatTime(arrival, tzFor('arrival') || undefined)} local time at ${airportLabel('arrival')}` : null,
                detail: joinFacts([
                    t.origin_gate && `Left gate ${t.origin_gate}`,
                    t.destination_terminal && `Arriving at Terminal ${t.destination_terminal}`,
                ]),
                miles: flown !== null ? { flown, togo: dist - flown } : null,
            };
        }
        default: {
            const dep = t.estimated_departure || t.scheduled_departure;
            const depMs = dep ? new Date(dep).getTime() - now.value : null;

            return {
                icon: '🛫', box: 'bg-emerald-50 text-emerald-800', progress: null,
                headline: `On the ground at ${airportLabel('departure')}`,
                sub: dep
                    ? `Departs ${fmtSide(dep, 'departure')} local time` + (depMs > 0 ? ` - in ${formatDuration(depMs)}` : '')
                    : 'Departure time not confirmed yet.',
                detail: joinFacts([
                    t.origin_terminal && `Terminal ${t.origin_terminal}`,
                    t.origin_gate && `Gate ${t.origin_gate}`,
                ]),
            };
        }
    }
});

function delayFor(side) {
    const min = side === 'departure' ? trip.value?.departure_delay_minutes : trip.value?.arrival_delay_minutes;
    return Math.max(0, min || 0);
}

function scoreColor(score) {
    if (score >= 60) return 'text-rose-600';
    if (score >= 30) return 'text-amber-600';
    return 'text-emerald-600';
}

// ── Eligibility Engine verdict ─────────────────────────────
const verdict = computed(() => trip.value?.eligibility?.status ?? null);

const REGULATIONS = {
    EU261:  'EU261 (EC 261/2004)',
    UK261:  'UK261 (retained EU law)',
    APPR:   'APPR (Canada)',
    US_DOT: 'US DOT',
};

function regulationLabel(code) {
    return REGULATIONS[code] || code || '-';
}

const confidenceColor = computed(() => {
    const c = trip.value?.eligibility?.confidence ?? 0;
    return c >= 70 ? 'text-emerald-600' : c >= 40 ? 'text-amber-600' : 'text-rose-600';
});

const confidenceBar = computed(() => {
    const c = trip.value?.eligibility?.confidence ?? 0;
    return c >= 70 ? 'bg-emerald-500' : c >= 40 ? 'bg-amber-500' : 'bg-rose-500';
});

// Route reliability stat tiles, each with a plain-language explanation
// shown behind an ⓘ icon.
const reliabilityTiles = computed(() => {
    const s = trip.value?.route_stats;
    if (!s) return [];
    const flight = trip.value.fa_ident || trip.value.flight_number || 'this flight';
    return [
        {
            label: 'On time',
            value: `${s.on_time_percentage}%`,
            tip: `How often ${flight} arrived within 15 minutes of schedule on its recent flights. Higher is better.`,
        },
        {
            label: 'Avg arrival delay',
            value: s.avg_arrival_delay_min,
            suffix: 'min',
            tip: 'On average, how many minutes late this flight arrived recently. On-time or early arrivals count as 0.',
        },
        {
            label: 'Avg departure delay',
            value: s.avg_departure_delay_min,
            suffix: 'min',
            tip: 'On average, how many minutes late this flight left the gate recently.',
        },
        {
            label: 'Delay score',
            value: s.delay_score,
            suffix: '/100',
            valueClass: scoreColor(s.delay_score),
            tip: 'Overall disruption risk for this flight: 0 means very reliable, 100 means frequently late or cancelled. It blends lateness with how often the schedule is missed.',
        },
    ];
});

async function loadMonitoring() {
    try {
        monitoring.value = await api.trips.monitoring(props.id);
    } catch (e) {
        // Non-fatal: the trip page still works without history.
    }
}

const claiming = ref(false);
const reportOptions = [
    { type: 'denied_boarding',   label: 'I was denied boarding' },
    { type: 'downgrade',         label: 'I was downgraded' },
    { type: 'missed_connection', label: 'I missed my connection' },
    { type: 'other',             label: 'Something else' },
];

// Reportable once the flight has departed, until a report or claim exists.
const canReport = computed(() => {
    const t = trip.value;
    return t && t.flight_phase !== 'scheduled' && !t.reported_disruption && !(t.claims || []).length;
});

const reportingOption = ref(null); // opens the funnel wizard

function reportProblem(option) {
    reportingOption.value = option;
}

async function onReportSubmitted(updatedTrip) {
    reportingOption.value = null;
    trip.value = updatedTrip;
    await loadMonitoring();
    if (trip.value.eligibility) tab.value = 'compensation';
}

async function startClaim() {
    const count = (trip.value?.passengers || []).length || 1;
    const ok = await confirmAction(
        'Start your claim?',
        count > 1
            ? `We'll file ${count} compensation claims - one per passenger - using this trip's verified flight data. Check that the passenger names are correct before continuing, as the claims will be submitted for review.`
            : "We'll file a compensation claim using this trip's verified flight data. Check that the passenger name is correct before continuing, as the claim will be submitted for review.",
        count > 1 ? `Yes, file ${count} claims` : 'Yes, file my claim'
    );
    if (!ok) return;

    claiming.value = true;
    const done = showProcessing('Creating your claim…', 'We\'re preparing your claim from this trip\'s verified flight data.');
    try {
        const res = await api.trips.createClaim(props.id);
        const first = res.data?.[0];
        if (first) {
            router.push({ name: 'claim', params: { id: first.id } });
        } else {
            trip.value = await api.trips.get(props.id);
        }
    } catch (e) {
        window.alert(e.response?.data?.message || 'Could not create the claim. Please try again.');
    } finally {
        done();
        claiming.value = false;
    }
}

async function refresh() {
    refreshing.value = true;
    try {
        trip.value = await api.trips.sync(props.id);
        await loadMonitoring();
    } catch (e) {
        window.alert('Could not refresh the flight status. Please try again.');
    } finally {
        refreshing.value = false;
    }
}

const detailRows = computed(() => trip.value ? [
    { label: 'Route', value: `${trip.value.departure_airport || '-'} → ${trip.value.arrival_airport || '-'}` },
    { label: 'Airline', value: trip.value.airline },
    { label: 'Flight number', value: trip.value.flight_number },
    { label: 'Departure date', value: trip.value.departure_date },
    { label: `Departure time (${placeName('departure')} time)`, value: trip.value.departure_time },
    { label: `Departure (live, ${placeName('departure')} time)`, value: fmtSide(trip.value.actual_departure || trip.value.estimated_departure || trip.value.scheduled_departure, 'departure') },
    { label: `Arrival (live, ${placeName('arrival')} time)`, value: fmtSide(trip.value.actual_arrival || trip.value.estimated_arrival || trip.value.scheduled_arrival, 'arrival') },
    { label: 'Booking reference', value: trip.value.booking_reference },
    { label: 'Added', value: trip.value.created_at_human },
    { label: 'Source', value: trip.value.source === 'upload' ? 'Uploaded itinerary' : 'Entered manually' },
] : []);

function initials(name) {
    return name.split(/\s+/).map((w) => w[0]).filter(Boolean).slice(0, 2).join('').toUpperCase();
}

async function removeTrip() {
    const ok = await confirmRemove(
        'Remove this trip?',
        `The ${trip.value.departure_airport} → ${trip.value.arrival_airport} trip and its ticket will be removed. This cannot be undone.`
    );
    if (!ok) return;
    try {
        await api.trips.remove(trip.value.id);
        router.push({ name: 'trips' });
    } catch (e) {
        window.alert('Could not remove the trip. Please try again.');
    }
}

async function load() {
    loading.value = true;
    error.value = '';
    try {
        trip.value = await api.trips.get(props.id);
        // Eligible trips open on Compensation - the claim is the headline.
        tab.value = trip.value.eligibility?.status === 'eligible' ? 'compensation' : 'status';
        await loadMonitoring();
    } catch (e) {
        error.value = e.response?.status === 403 ? 'You do not have access to this trip.' : 'Could not load this trip.';
    } finally {
        loading.value = false;
    }
}

watch(() => props.id, load);
onMounted(load);
</script>
