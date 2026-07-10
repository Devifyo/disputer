<template>
    <div class="flex-1 flex flex-col min-h-0">
        <div class="flex-1 overflow-y-auto bg-slate-100/70">
            <div class="max-w-[1400px] mx-auto px-4 sm:px-8 py-8">

                <!-- Heading -->
                <div class="flex items-center justify-between mb-6">
                    <h1 class="flex items-center gap-2 text-2xl font-extrabold text-slate-900 tracking-tight">
                        Your submitted claims
                        <svg class="w-4 h-4 text-primary-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
                    </h1>
                    <div class="flex items-center gap-3">
                    <router-link :to="{ name: 'trips' }" class="inline-flex items-center gap-2 bg-white hover:bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200 px-5 py-3 rounded-xl text-sm font-bold transition-all active:scale-95">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3l7 3v5c0 4.5-3 8.5-7 10-4-1.5-7-5.5-7-10V6l7-3z"/><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4"/></svg>
                        Protect Your Trip
                    </router-link>
                    <router-link :to="{ name: 'add-claim' }" class="inline-flex items-center gap-2 bg-primary-600 hover:bg-primary-700 text-white px-5 py-3 rounded-xl text-sm font-bold shadow-md shadow-primary-600/25 transition-all active:scale-95">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
                        Add Claim
                    </router-link>
                    </div>
                </div>

                <div v-if="loading" class="text-sm text-slate-400 py-10 text-center">Loading…</div>
                <div v-else-if="error" class="bg-rose-50 border border-rose-100 text-rose-700 px-4 py-3 rounded-xl text-sm">{{ error }}</div>

                <template v-else>
                    <!-- Rows -->
                    <router-link
                        v-for="c in claims"
                        :key="c.id"
                        :to="{ name: 'claim', params: { id: c.id } }"
                        class="group flex items-center gap-5 sm:gap-8 bg-white rounded-2xl shadow-sm ring-1 ring-slate-900/5 px-4 sm:px-6 py-4 mb-4 hover:ring-primary-200 hover:shadow-md transition-all flex-wrap"
                    >
                        <!-- Departure -->
                        <div class="flex items-center gap-3 min-w-[150px]">
                            <span class="w-11 h-11 rounded-xl bg-primary-50 text-primary-600 flex items-center justify-center shrink-0">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M2.5 19h19M3 15l7-1 5-9 2 .8-2.5 8.2L21 12"/></svg>
                            </span>
                            <div class="leading-tight">
                                <div class="font-bold text-slate-900">{{ c.departure_city || c.departure_airport || '—' }}</div>
                                <div class="text-sm text-slate-400 font-medium">{{ c.departure_airport || '—' }}</div>
                            </div>
                        </div>

                        <!-- Arrival -->
                        <div class="flex items-center gap-3 min-w-[150px]">
                            <span class="w-11 h-11 rounded-xl bg-primary-50 text-primary-600 flex items-center justify-center shrink-0">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M2.5 19h19M20.5 15.5l-8-1.5-6-7.5-2 .5 3 8-4.5 1"/></svg>
                            </span>
                            <div class="leading-tight">
                                <div class="font-bold text-slate-900">{{ c.arrival_city || c.arrival_airport || '—' }}</div>
                                <div class="text-sm text-slate-400 font-medium">{{ c.arrival_airport || '—' }}</div>
                            </div>
                        </div>

                        <!-- Airline -->
                        <div class="min-w-[110px] flex-1">
                            <div class="text-slate-500 font-medium truncate">{{ c.airline || 'Airline…' }}</div>
                        </div>

                        <!-- Flight date -->
                        <div class="min-w-[130px]">
                            <div class="font-bold text-slate-900 text-sm">Flight date</div>
                            <div class="flex items-center gap-1.5 text-slate-500 text-sm mt-0.5">
                                <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4.5" width="18" height="16" rx="2"/><path d="M3 9h18M8 3v3M16 3v3"/></svg>
                                {{ c.flight_date || '—' }}
                            </div>
                        </div>

                        <!-- Claim id -->
                        <div class="min-w-[160px]">
                            <div class="font-bold text-slate-900 text-sm">Claim ID: #{{ c.number }}</div>
                            <div class="text-slate-400 text-sm mt-0.5">Created {{ c.created_at }}</div>
                        </div>

                        <!-- Chevron -->
                        <span class="w-9 h-9 rounded-full bg-primary-50 text-primary-600 flex items-center justify-center shrink-0 ml-auto group-hover:bg-primary-100 transition-colors">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        </span>
                    </router-link>

                    <!-- Empty -->
                    <div v-if="!claims.length" class="bg-white rounded-2xl ring-1 ring-slate-900/5 px-6 py-16 text-center">
                        <div class="w-12 h-12 mx-auto rounded-2xl bg-primary-50 text-primary-500 flex items-center justify-center mb-3">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M2.5 19h19M3 15l7-1 5-9 2 .8-2.5 8.2L21 12"/></svg>
                        </div>
                        <p class="font-bold text-slate-800">No claims yet</p>
                        <p class="text-sm text-slate-500 mt-1">Add your first airline claim to get started.</p>
                        <router-link :to="{ name: 'add-claim' }" class="inline-flex items-center gap-2 mt-5 bg-primary-600 hover:bg-primary-700 text-white px-5 py-2.5 rounded-xl text-sm font-bold transition-all">
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
import { ref, onMounted } from 'vue';
import api from '../api';

const claims = ref([]);
const loading = ref(true);
const error = ref('');

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
