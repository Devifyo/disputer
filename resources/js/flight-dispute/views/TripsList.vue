<template>
    <div class="flex-1 flex flex-col min-h-0">
        <div class="flex-1 overflow-y-auto overflow-x-hidden bg-slate-100/70">
            <div class="max-w-[1400px] mx-auto px-4 sm:px-8 py-8">

                <!-- Heading -->
                <div class="flex items-center justify-between mb-2 flex-wrap gap-3">
                    <div>
                        <h1 class="flex items-center gap-2 text-2xl font-extrabold text-slate-900 tracking-tight">
                            Protect Your Trip
                            <svg class="w-5 h-5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3l7 3v5c0 4.5-3 8.5-7 10-4-1.5-7-5.5-7-10V6l7-3z"/><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4"/></svg>
                        </h1>
                        <p class="text-sm text-slate-500 mt-1 max-w-xl">Register upcoming flights and Unjamm keeps an eye on them. If anything goes wrong, your claim is ready before you land.</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <router-link :to="{ name: 'claims' }" class="inline-flex items-center gap-2 bg-white hover:bg-slate-50 text-slate-600 ring-1 ring-slate-200 px-5 py-3 rounded-xl text-sm font-bold transition-all active:scale-95">
                            My claims
                        </router-link>
                        <router-link :to="{ name: 'add-trip' }" class="inline-flex items-center gap-2 bg-slate-900 hover:bg-slate-800 text-white px-5 py-3 rounded-xl text-sm font-bold shadow-lg shadow-slate-900/10 transition-all active:scale-95">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
                            Add Trip
                        </router-link>
                    </div>
                </div>

                <div v-if="loading" class="text-sm text-slate-400 py-10 text-center">Loading…</div>
                <div v-else-if="error" class="bg-rose-50 border border-rose-100 text-rose-700 px-4 py-3 rounded-xl text-sm">{{ error }}</div>

                <template v-else>
                    <!-- Toolbar: search + status filters -->
                    <div v-if="trips.length" class="flex items-center gap-3 flex-wrap mt-5 mb-5">
                        <div class="relative flex-1 min-w-[220px] max-w-sm">
                            <svg class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="M20 20l-3.5-3.5"/></svg>
                            <input
                                v-model.trim="q"
                                type="search"
                                placeholder="Search flight, route or passenger…"
                                class="w-full bg-white rounded-xl ring-1 ring-slate-200 border-0 pl-10 pr-4 py-2.5 text-sm font-medium text-slate-700 placeholder:text-slate-400 focus:ring-2 focus:ring-primary-500 transition-shadow"
                            />
                        </div>
                        <FilterChips v-model="filter" :chips="chips" />
                    </div>

                    <!-- Column headers (desktop) -->
                    <div v-if="visible.length" class="hidden md:grid md:grid-cols-[1.7fr_1.05fr_1fr_0.8fr_1.35fr_2.5rem] gap-4 px-6 pb-2 text-[11px] uppercase tracking-wider font-bold text-slate-400">
                        <span>Route</span><span>Flight</span><span>Departure</span><span>Travellers</span><span>Status</span><span></span>
                    </div>

                    <!-- Grouped rows -->
                    <template v-for="group in groups" :key="group.title">
                        <div v-if="group.trips.length && groups.length > 1" class="flex items-center gap-3 mt-6 mb-3 first:mt-0">
                            <h2 class="flex items-center gap-2 text-xs uppercase tracking-wider font-bold" :class="group.alert ? 'text-emerald-600' : 'text-slate-400'">
                                {{ group.title }}
                                <!-- Blinking dot: eligible trips still waiting for their claim -->
                                <span v-if="group.alert" class="relative flex w-2 h-2">
                                    <span class="absolute inline-flex w-full h-full rounded-full bg-rose-400 opacity-75 animate-ping"></span>
                                    <span class="relative inline-flex w-2 h-2 rounded-full bg-rose-500"></span>
                                </span>
                            </h2>
                            <span class="h-px flex-1 bg-slate-200"></span>
                        </div>

                        <router-link
                            v-for="t in group.trips"
                            :key="t.id"
                            :to="{ name: 'trip', params: { id: t.id } }"
                            class="grid grid-cols-2 md:grid-cols-[1.7fr_1.05fr_1fr_0.8fr_1.35fr_2.5rem] gap-x-4 gap-y-3 items-center bg-white rounded-2xl shadow-sm ring-1 ring-slate-900/5 px-4 sm:px-6 py-4 mb-3 hover:ring-primary-200 hover:shadow-md transition-all"
                            :class="{ 'opacity-70 hover:opacity-100': !t.upcoming }"
                        >
                            <!-- Route -->
                            <div class="col-span-2 md:col-span-1 flex items-center gap-3 min-w-0">
                                <span class="w-11 h-11 rounded-xl flex items-center justify-center shrink-0" :class="t.upcoming ? 'bg-emerald-50 text-emerald-600' : 'bg-slate-100 text-slate-400'">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M2.5 19h19M3 15l7-1 5-9 2 .8-2.5 8.2L21 12"/></svg>
                                </span>
                                <div class="leading-tight min-w-0">
                                    <div class="font-bold text-slate-900 truncate">
                                        {{ t.departure_city || t.departure_airport || '-' }}
                                        <span class="text-slate-300 font-medium mx-0.5">→</span>
                                        {{ t.arrival_city || t.arrival_airport || '-' }}
                                    </div>
                                    <div class="text-sm text-slate-400 font-medium">{{ t.departure_airport || '-' }} – {{ t.arrival_airport || '-' }}</div>
                                </div>
                                <!-- Delete (mobile position - top right of the card) -->
                                <button
                                    @click.stop.prevent="remove(t)"
                                    class="md:hidden ml-auto w-9 h-9 rounded-full flex items-center justify-center shrink-0 text-slate-400 bg-slate-50 ring-1 ring-slate-200/70 hover:text-rose-600 hover:bg-rose-50 hover:ring-rose-200 active:scale-95 transition-all"
                                    title="Remove trip"
                                    aria-label="Remove trip"
                                >
                                    <svg class="w-[18px] h-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.35 9m-4.78 0L9.26 9m9.97-3.21c.34.05.68.11 1.02.17m-1.02-.17L18.16 19.67a2.25 2.25 0 01-2.24 2.08H8.08a2.25 2.25 0 01-2.24-2.08L4.77 5.79m14.46 0a48 48 0 00-3.48-.4m-12 .56c.34-.06.68-.11 1.02-.16m0 0a48 48 0 013.48-.4m7.5 0v-.92c0-1.18-.91-2.16-2.09-2.2a52 52 0 00-3.32 0c-1.18.04-2.09 1.02-2.09 2.2v.92m7.5 0a49 49 0 00-7.5 0"/>
                                    </svg>
                                </button>
                            </div>

                            <!-- Flight -->
                            <div class="leading-tight min-w-0">
                                <div class="font-bold text-slate-900 text-sm truncate">{{ t.airline || '-' }}</div>
                                <div class="text-sm text-slate-400 font-medium">{{ t.flight_number || '-' }}</div>
                            </div>

                            <!-- Departure -->
                            <div class="leading-tight">
                                <div class="font-bold text-slate-900 text-sm">{{ t.departure_date || '-' }}</div>
                                <div class="text-sm text-slate-400 font-medium">{{ t.departure_time || '-' }}</div>
                            </div>

                            <!-- Travellers -->
                            <div class="leading-tight" :title="(t.passengers || []).join(', ')">
                                <div class="font-bold text-slate-900 text-sm">{{ t.passengers_count || 0 }}</div>
                                <div class="text-sm text-slate-400 font-medium truncate">{{ (t.passengers || [])[0] || '-' }}</div>
                            </div>

                            <!-- Status -->
                            <div class="col-span-2 md:col-span-1 flex md:block items-center justify-between gap-3">
                                <span class="inline-flex items-center gap-2">
                                    <TripStatusBadge :status="t.display_status" :label="t.display_status_label" />
                                    <!-- Claim not filed yet -->
                                    <span v-if="t.can_claim" class="relative flex w-2 h-2" title="Claim not filed yet">
                                        <span class="absolute inline-flex w-full h-full rounded-full bg-rose-400 opacity-75 animate-ping"></span>
                                        <span class="relative inline-flex w-2 h-2 rounded-full bg-rose-500"></span>
                                    </span>
                                </span>
                                <div v-if="t.last_synced_human" class="text-[11px] text-slate-400 font-medium md:mt-1.5 md:pl-1">Updated {{ t.last_synced_human }}</div>
                            </div>

                            <!-- Delete -->
                            <button
                                @click.stop.prevent="remove(t)"
                                class="hidden md:flex w-9 h-9 rounded-full items-center justify-center shrink-0 text-slate-400 bg-slate-50 ring-1 ring-slate-200/70 hover:text-rose-600 hover:bg-rose-50 hover:ring-rose-200 active:scale-95 transition-all justify-self-end"
                                title="Remove trip"
                                aria-label="Remove trip"
                            >
                                <svg class="w-[18px] h-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.35 9m-4.78 0L9.26 9m9.97-3.21c.34.05.68.11 1.02.17m-1.02-.17L18.16 19.67a2.25 2.25 0 01-2.24 2.08H8.08a2.25 2.25 0 01-2.24-2.08L4.77 5.79m14.46 0a48 48 0 00-3.48-.4m-12 .56c.34-.06.68-.11 1.02-.16m0 0a48 48 0 013.48-.4m7.5 0v-.92c0-1.18-.91-2.16-2.09-2.2a52 52 0 00-3.32 0c-1.18.04-2.09 1.02-2.09 2.2v.92m7.5 0a49 49 0 00-7.5 0"/>
                                </svg>
                            </button>
                        </router-link>
                    </template>

                    <!-- No results for current search/filter -->
                    <div v-if="trips.length && !visible.length" class="bg-white rounded-2xl ring-1 ring-slate-900/5 px-6 py-12 text-center">
                        <p class="font-bold text-slate-800">No trips match</p>
                        <p class="text-sm text-slate-500 mt-1">Try a different search or filter.</p>
                        <button @click="clearFilters" class="mt-4 inline-flex items-center gap-2 text-sm font-bold text-primary-600 hover:text-primary-700 hover:bg-primary-50 px-4 py-2 rounded-xl transition-colors">
                            Clear filters
                        </button>
                    </div>

                    <!-- Empty state / funnel entry -->
                    <div v-if="!trips.length" class="bg-white rounded-2xl ring-1 ring-slate-900/5 px-6 py-14 text-center mt-5">
                        <span class="mx-auto w-14 h-14 rounded-2xl bg-emerald-50 text-emerald-500 flex items-center justify-center mb-4">
                            <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3l7 3v5c0 4.5-3 8.5-7 10-4-1.5-7-5.5-7-10V6l7-3z"/><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4"/></svg>
                        </span>
                        <h2 class="font-extrabold text-slate-900 text-lg mb-1">No protected trips yet</h2>
                        <p class="text-sm text-slate-500 mb-6 max-w-md mx-auto">Add an upcoming flight, either manually or by uploading your booking confirmation, and we'll watch it for delays and cancellations.</p>
                        <router-link :to="{ name: 'add-trip' }" class="inline-flex items-center gap-2 bg-slate-900 hover:bg-slate-800 text-white px-6 py-3 rounded-xl text-sm font-bold shadow-lg shadow-slate-900/10 transition-all active:scale-95">
                            Protect your first trip
                        </router-link>
                    </div>
                </template>

            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import api from '../api';
import { confirmRemove } from '../confirm';
import FilterChips from '../components/FilterChips.vue';
import TripStatusBadge from '../components/TripStatusBadge.vue';

const trips = ref([]);
const loading = ref(true);
const error = ref('');

const q = ref('');
const filter = ref('all');

// Statuses that mean "something happened - look at this trip".
const ATTENTION = ['delayed', 'cancelled', 'potentially_eligible', 'eligibility_review_pending'];

const chips = computed(() => [
    { key: 'all',       label: 'All',          count: trips.value.length },
    { key: 'upcoming',  label: 'Upcoming',     count: trips.value.filter((t) => t.upcoming).length },
    { key: 'attention', label: 'Needs review', count: trips.value.filter(matchesAttention).length },
    { key: 'completed', label: 'Completed',    count: trips.value.filter((t) => t.display_status === 'completed').length },
]);

function matchesAttention(t) {
    return ATTENTION.includes(t.display_status);
}

function matchesFilter(t) {
    if (filter.value === 'upcoming') return t.upcoming;
    if (filter.value === 'attention') return matchesAttention(t);
    if (filter.value === 'completed') return t.display_status === 'completed';
    return true;
}

function matchesSearch(t) {
    if (!q.value) return true;
    const needle = q.value.toLowerCase();
    return [
        t.airline, t.flight_number,
        t.departure_airport, t.departure_city, t.arrival_airport, t.arrival_city,
        t.booking_reference, ...(t.passengers || []),
    ].some((v) => v && String(v).toLowerCase().includes(needle));
}

const visible = computed(() => trips.value.filter((t) => matchesFilter(t) && matchesSearch(t)));

// Section order: eligible-for-compensation trips lead (they need action),
// then upcoming (soonest first), then past (most recent first). Separators
// only on the unfiltered view.
const groups = computed(() => {
    const eligible = visible.value.filter((t) => t.display_status === 'eligible').reverse();
    const rest     = visible.value.filter((t) => t.display_status !== 'eligible');
    const upcoming = rest.filter((t) => t.upcoming);
    const past     = rest.filter((t) => !t.upcoming).reverse();

    if (filter.value === 'all' && !q.value) {
        const sections = [
            { title: 'Eligible for compensation', trips: eligible, alert: eligible.some((t) => t.can_claim) },
            { title: 'Upcoming', trips: upcoming },
            { title: 'Past & completed', trips: past },
        ].filter((s) => s.trips.length);

        if (sections.length > 1 || eligible.length) return sections;
    }
    return [{ title: 'Trips', trips: [...eligible, ...upcoming, ...past] }];
});

function clearFilters() {
    q.value = '';
    filter.value = 'all';
}

async function load() {
    loading.value = true;
    error.value = '';
    try {
        trips.value = await api.trips.list();
    } catch (e) {
        error.value = 'Could not load your trips. Please refresh the page.';
    } finally {
        loading.value = false;
    }
}

async function remove(t) {
    const ok = await confirmRemove(
        'Remove this trip?',
        `The ${t.departure_airport} → ${t.arrival_airport} trip and its ticket will be removed. This cannot be undone.`
    );
    if (!ok) return;
    try {
        await api.trips.remove(t.id);
        trips.value = trips.value.filter((x) => x.id !== t.id);
    } catch (e) {
        window.alert('Could not remove the trip. Please try again.');
    }
}

onMounted(load);
</script>
