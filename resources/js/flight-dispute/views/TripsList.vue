<template>
    <div class="flex-1 flex flex-col min-h-0">
        <div class="flex-1 overflow-y-auto bg-slate-100/70">
            <div class="max-w-[1400px] mx-auto px-4 sm:px-8 py-8">

                <!-- Heading -->
                <div class="flex items-center justify-between mb-2 flex-wrap gap-3">
                    <h1 class="flex items-center gap-2 text-2xl font-extrabold text-slate-900 tracking-tight">
                        Protect Your Trip
                        <svg class="w-5 h-5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3l7 3v5c0 4.5-3 8.5-7 10-4-1.5-7-5.5-7-10V6l7-3z"/><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4"/></svg>
                    </h1>
                    <div class="flex items-center gap-3">
                        <router-link :to="{ name: 'claims' }" class="inline-flex items-center gap-2 bg-white hover:bg-slate-50 text-slate-600 ring-1 ring-slate-200 px-5 py-3 rounded-xl text-sm font-bold transition-all active:scale-95">
                            My claims
                        </router-link>
                        <router-link :to="{ name: 'add-trip' }" class="inline-flex items-center gap-2 bg-primary-600 hover:bg-primary-700 text-white px-5 py-3 rounded-xl text-sm font-bold shadow-md shadow-primary-600/25 transition-all active:scale-95">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
                            Add Trip
                        </router-link>
                    </div>
                </div>
                <p class="text-sm text-slate-500 mb-6 max-w-xl">Register upcoming flights and Unjamm keeps an eye on them. If anything goes wrong, your claim is ready before you land.</p>

                <div v-if="loading" class="text-sm text-slate-400 py-10 text-center">Loading…</div>
                <div v-else-if="error" class="bg-rose-50 border border-rose-100 text-rose-700 px-4 py-3 rounded-xl text-sm">{{ error }}</div>

                <template v-else>
                    <!-- Rows -->
                    <router-link
                        v-for="t in trips"
                        :key="t.id"
                        :to="{ name: 'trip', params: { id: t.id } }"
                        class="group flex items-center gap-5 sm:gap-8 bg-white rounded-2xl shadow-sm ring-1 ring-slate-900/5 px-4 sm:px-6 py-4 mb-4 hover:ring-primary-200 hover:shadow-md transition-all flex-wrap"
                    >
                        <!-- Departure -->
                        <div class="flex items-center gap-3 min-w-[150px]">
                            <span class="w-11 h-11 rounded-xl bg-primary-50 text-primary-600 flex items-center justify-center shrink-0">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M2.5 19h19M3 15l7-1 5-9 2 .8-2.5 8.2L21 12"/></svg>
                            </span>
                            <div class="leading-tight">
                                <div class="font-bold text-slate-900">{{ t.departure_city || t.departure_airport || '—' }}</div>
                                <div class="text-sm text-slate-400 font-medium">{{ t.departure_airport || '—' }}</div>
                            </div>
                        </div>

                        <!-- Arrival -->
                        <div class="flex items-center gap-3 min-w-[150px]">
                            <span class="w-11 h-11 rounded-xl bg-primary-50 text-primary-600 flex items-center justify-center shrink-0">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M2.5 19h19M20.5 15.5l-8-1.5-6-7.5-2 .5 3 8-4.5 1"/></svg>
                            </span>
                            <div class="leading-tight">
                                <div class="font-bold text-slate-900">{{ t.arrival_city || t.arrival_airport || '—' }}</div>
                                <div class="text-sm text-slate-400 font-medium">{{ t.arrival_airport || '—' }}</div>
                            </div>
                        </div>

                        <!-- Airline / flight -->
                        <div class="min-w-[110px] flex-1">
                            <div class="text-slate-500 font-medium truncate">{{ t.airline || 'Airline…' }}</div>
                            <div class="text-sm text-slate-400 font-medium">{{ t.flight_number }}</div>
                        </div>

                        <!-- Passengers -->
                        <div class="min-w-[110px]" :title="(t.passengers || []).join(', ')">
                            <div class="font-bold text-slate-900 text-sm">{{ t.passengers_count || 0 }} passenger{{ (t.passengers_count || 0) === 1 ? '' : 's' }}</div>
                            <div class="text-sm text-slate-400 font-medium truncate max-w-[160px]">{{ (t.passengers || []).join(', ') || '—' }}</div>
                        </div>

                        <!-- Departure date -->
                        <div class="min-w-[130px]">
                            <div class="font-bold text-slate-900 text-sm">Departure</div>
                            <div class="flex items-center gap-1.5 text-slate-500 text-sm mt-0.5">
                                <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4.5" width="18" height="16" rx="2"/><path d="M3 9h18M8 3v3M16 3v3"/></svg>
                                {{ t.departure_date || '—' }}<span v-if="t.departure_time" class="text-slate-400">· {{ t.departure_time }}</span>
                            </div>
                        </div>

                        <!-- Protected badge -->
                        <div class="min-w-[170px]">
                            <span v-if="t.protected" class="inline-flex items-center gap-1.5 bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200 px-3 py-1.5 rounded-full text-xs font-bold">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3l7 3v5c0 4.5-3 8.5-7 10-4-1.5-7-5.5-7-10V6l7-3z"/><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4"/></svg>
                                Protected by Unjamm
                            </span>
                        </div>

                        <!-- Delete -->
                        <button
                            @click.stop.prevent="remove(t)"
                            class="p-2 rounded-lg text-slate-300 hover:text-rose-500 hover:bg-rose-50 transition-colors"
                            title="Remove trip"
                        >
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6 7h12M9 7V5a1 1 0 011-1h4a1 1 0 011 1v2m-8 0l1 13a1 1 0 001 1h6a1 1 0 001-1l1-13"/></svg>
                        </button>
                    </router-link>

                    <!-- Empty state / funnel entry -->
                    <div v-if="!trips.length" class="bg-white rounded-2xl ring-1 ring-slate-900/5 px-6 py-14 text-center">
                        <span class="mx-auto w-14 h-14 rounded-2xl bg-emerald-50 text-emerald-500 flex items-center justify-center mb-4">
                            <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3l7 3v5c0 4.5-3 8.5-7 10-4-1.5-7-5.5-7-10V6l7-3z"/><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4"/></svg>
                        </span>
                        <h2 class="font-extrabold text-slate-900 text-lg mb-1">No protected trips yet</h2>
                        <p class="text-sm text-slate-500 mb-6 max-w-md mx-auto">Add an upcoming flight, either manually or by uploading your booking confirmation, and we'll watch it for delays and cancellations.</p>
                        <router-link :to="{ name: 'add-trip' }" class="inline-flex items-center gap-2 bg-primary-600 hover:bg-primary-700 text-white px-6 py-3 rounded-xl text-sm font-bold shadow-md shadow-primary-600/25 transition-all active:scale-95">
                            Protect your first trip
                        </router-link>
                    </div>
                </template>

            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import api from '../api';
import { confirmRemove } from '../confirm';

const trips = ref([]);
const loading = ref(true);
const error = ref('');

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
