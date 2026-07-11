<template>
    <div class="flex-1 flex flex-col min-h-0">
        <div class="flex-1 overflow-y-auto overflow-x-hidden bg-slate-100/70">
            <div class="max-w-[1400px] mx-auto px-4 sm:px-8 py-8">

                <!-- Heading -->
                <div class="flex items-center justify-between mb-2 flex-wrap gap-3">
                    <div>
                        <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Your submitted claims</h1>
                        <p class="text-sm text-slate-500 mt-1 max-w-xl">Track every compensation claim you've started, from draft to payout.</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <router-link :to="{ name: 'trips' }" class="inline-flex items-center gap-2 bg-white hover:bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200 px-5 py-3 rounded-xl text-sm font-bold transition-all active:scale-95">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3l7 3v5c0 4.5-3 8.5-7 10-4-1.5-7-5.5-7-10V6l7-3z"/><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4"/></svg>
                            Protect Your Trip
                        </router-link>
                        <router-link :to="{ name: 'add-claim' }" class="inline-flex items-center gap-2 bg-slate-900 hover:bg-slate-800 text-white px-5 py-3 rounded-xl text-sm font-bold shadow-lg shadow-slate-900/10 transition-all active:scale-95">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
                            Add Claim
                        </router-link>
                    </div>
                </div>

                <div v-if="loading" class="text-sm text-slate-400 py-10 text-center">Loading…</div>
                <div v-else-if="error" class="bg-rose-50 border border-rose-100 text-rose-700 px-4 py-3 rounded-xl text-sm">{{ error }}</div>

                <template v-else>
                    <!-- Toolbar: search + status filters -->
                    <div v-if="claims.length" class="flex items-center gap-3 flex-wrap mt-5 mb-5">
                        <div class="relative flex-1 min-w-[220px] max-w-sm">
                            <svg class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="M20 20l-3.5-3.5"/></svg>
                            <input
                                v-model.trim="q"
                                type="search"
                                placeholder="Search claim, flight or route…"
                                class="w-full bg-white rounded-xl ring-1 ring-slate-200 border-0 pl-10 pr-4 py-2.5 text-sm font-medium text-slate-700 placeholder:text-slate-400 focus:ring-2 focus:ring-primary-500 transition-shadow"
                            />
                        </div>
                        <FilterChips v-model="filter" :chips="chips" />
                    </div>

                    <!-- Column headers (desktop) -->
                    <div v-if="visible.length" class="hidden md:grid md:grid-cols-[1.7fr_1.05fr_0.9fr_1.1fr_1.2fr_2.5rem] gap-4 px-6 pb-2 text-[11px] uppercase tracking-wider font-bold text-slate-400">
                        <span>Route</span><span>Flight</span><span>Flight date</span><span>Claim</span><span>Status</span><span></span>
                    </div>

                    <!-- Rows -->
                    <router-link
                        v-for="c in visible"
                        :key="c.id"
                        :to="{ name: 'claim', params: { id: c.id } }"
                        class="group grid grid-cols-2 md:grid-cols-[1.7fr_1.05fr_0.9fr_1.1fr_1.2fr_2.5rem] gap-x-4 gap-y-3 items-center bg-white rounded-2xl shadow-sm ring-1 ring-slate-900/5 px-4 sm:px-6 py-4 mb-3 hover:ring-primary-200 hover:shadow-md transition-all"
                    >
                        <!-- Route -->
                        <div class="col-span-2 md:col-span-1 flex items-center gap-3 min-w-0">
                            <span class="w-11 h-11 rounded-xl bg-primary-50 text-primary-600 flex items-center justify-center shrink-0">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M2.5 19h19M3 15l7-1 5-9 2 .8-2.5 8.2L21 12"/></svg>
                            </span>
                            <div class="leading-tight min-w-0">
                                <div class="font-bold text-slate-900 truncate">
                                    {{ c.departure_city || c.departure_airport || '-' }}
                                    <span class="text-slate-300 font-medium mx-0.5">→</span>
                                    {{ c.arrival_city || c.arrival_airport || '-' }}
                                </div>
                                <div class="text-sm text-slate-400 font-medium">{{ c.departure_airport || '-' }} – {{ c.arrival_airport || '-' }}</div>
                            </div>
                        </div>

                        <!-- Flight -->
                        <div class="leading-tight min-w-0">
                            <div class="font-bold text-slate-900 text-sm truncate">{{ c.airline || '-' }}</div>
                            <div class="text-sm text-slate-400 font-medium">{{ c.flight_number || '-' }}</div>
                        </div>

                        <!-- Flight date -->
                        <div class="leading-tight">
                            <div class="font-bold text-slate-900 text-sm">{{ c.flight_date || '-' }}</div>
                            <div v-if="c.disruption_label" class="text-sm text-slate-400 font-medium truncate">{{ c.disruption_label }}</div>
                        </div>

                        <!-- Claim -->
                        <div class="leading-tight">
                            <div class="font-bold text-slate-900 text-sm">#{{ c.number }}</div>
                            <div class="text-sm text-slate-400 font-medium">Created {{ c.created_at }}</div>
                        </div>

                        <!-- Status -->
                        <div class="col-span-2 md:col-span-1">
                            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold ring-1" :class="statusTheme(c.status)">
                                <span class="w-1.5 h-1.5 rounded-full bg-current opacity-70"></span>
                                {{ c.status_label }}
                            </span>
                        </div>

                        <!-- Chevron -->
                        <span class="hidden md:flex w-9 h-9 rounded-full bg-primary-50 text-primary-600 items-center justify-center shrink-0 justify-self-end group-hover:bg-primary-100 transition-colors">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        </span>
                    </router-link>

                    <!-- No results for current search/filter -->
                    <div v-if="claims.length && !visible.length" class="bg-white rounded-2xl ring-1 ring-slate-900/5 px-6 py-12 text-center">
                        <p class="font-bold text-slate-800">No claims match</p>
                        <p class="text-sm text-slate-500 mt-1">Try a different search or filter.</p>
                        <button @click="clearFilters" class="mt-4 inline-flex items-center gap-2 text-sm font-bold text-primary-600 hover:text-primary-700 hover:bg-primary-50 px-4 py-2 rounded-xl transition-colors">
                            Clear filters
                        </button>
                    </div>

                    <!-- Empty -->
                    <div v-if="!claims.length" class="bg-white rounded-2xl ring-1 ring-slate-900/5 px-6 py-16 text-center mt-5">
                        <div class="w-12 h-12 mx-auto rounded-2xl bg-primary-50 text-primary-500 flex items-center justify-center mb-3">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M2.5 19h19M3 15l7-1 5-9 2 .8-2.5 8.2L21 12"/></svg>
                        </div>
                        <p class="font-bold text-slate-800">No claims yet</p>
                        <p class="text-sm text-slate-500 mt-1">Add your first airline claim to get started.</p>
                        <router-link :to="{ name: 'add-claim' }" class="inline-flex items-center gap-2 mt-5 bg-slate-900 hover:bg-slate-800 text-white px-5 py-2.5 rounded-xl text-sm font-bold transition-all">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
                            Add Claim
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
import FilterChips from '../components/FilterChips.vue';

const claims = ref([]);
const loading = ref(true);
const error = ref('');

const q = ref('');
const filter = ref('all');

// Status chips are built from whatever statuses actually exist in the
// user's claims, so new backend statuses appear without a frontend change.
const chips = computed(() => {
    const byStatus = new Map();
    for (const c of claims.value) {
        if (!byStatus.has(c.status)) {
            byStatus.set(c.status, { key: c.status, label: c.status_label, count: 0 });
        }
        byStatus.get(c.status).count++;
    }
    return [{ key: 'all', label: 'All', count: claims.value.length }, ...byStatus.values()];
});

function matchesSearch(c) {
    if (!q.value) return true;
    const needle = q.value.toLowerCase();
    return [
        c.number, c.reference, c.airline, c.flight_number,
        c.departure_airport, c.departure_city, c.arrival_airport, c.arrival_city,
        c.status_label, c.disruption_label,
    ].some((v) => v && String(v).toLowerCase().includes(needle));
}

const visible = computed(() =>
    claims.value.filter((c) => (filter.value === 'all' || c.status === filter.value) && matchesSearch(c))
);

function statusTheme(status) {
    switch (status) {
        case 'draft':                       return 'bg-slate-50 text-slate-600 ring-slate-200';
        case 'pending_eligibility_review':  return 'bg-amber-50 text-amber-700 ring-amber-200';
        case 'eligible':
        case 'approved':
        case 'paid':                        return 'bg-emerald-50 text-emerald-700 ring-emerald-200';
        case 'rejected':                    return 'bg-rose-50 text-rose-700 ring-rose-200';
        default:                            return 'bg-sky-50 text-sky-700 ring-sky-200';
    }
}

function clearFilters() {
    q.value = '';
    filter.value = 'all';
}

async function load() {
    loading.value = true;
    try {
        claims.value = await api.claims.list();
    } catch (e) {
        error.value = 'Could not load your claims.';
    } finally {
        loading.value = false;
    }
}

onMounted(load);
</script>
