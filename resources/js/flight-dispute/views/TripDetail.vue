<template>
    <div class="flex-1 flex flex-col min-h-0">
        <header class="h-16 bg-white border-b border-slate-200 flex items-center gap-3 px-4 sm:px-8 shrink-0 z-10 sticky top-0">
            <router-link :to="{ name: 'trips' }" class="p-2 -ml-2 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors" title="Back">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7 7-7m-7 7h18"/></svg>
            </router-link>
            <h1 class="font-black text-slate-900 text-lg tracking-tight">Your Trip</h1>
        </header>

        <div class="flex-1 overflow-y-auto bg-slate-100/70">
            <div class="max-w-[1400px] mx-auto px-4 sm:px-8 py-8">

                <div v-if="loading" class="text-sm text-slate-400 py-10 text-center">Loading…</div>
                <div v-else-if="error" class="bg-rose-50 border border-rose-100 text-rose-700 px-4 py-3 rounded-xl text-sm">{{ error }}</div>

                <div v-else-if="trip" class="grid lg:grid-cols-3 gap-6 items-start">
                    <!-- LEFT -->
                    <div class="lg:col-span-2 space-y-5">
                        <!-- Hero -->
                        <div class="relative overflow-hidden rounded-2xl p-6 sm:p-7 text-white" style="background:linear-gradient(105deg,#064e3b 0%,#059669 55%,#10b981 78%,#2563eb 130%);">
                            <div class="flex flex-col sm:flex-row sm:items-center gap-5">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between text-[11px] font-semibold text-white/70 uppercase tracking-wide mb-1">
                                        <span class="truncate">{{ trip.departure_city || trip.departure_airport }}</span>
                                        <span class="truncate text-right">{{ trip.arrival_city || trip.arrival_airport }}</span>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <span class="text-3xl sm:text-4xl font-black tracking-tight">{{ trip.departure_airport || '—' }}</span>
                                        <span class="flex-1 flex items-center">
                                            <span class="h-px flex-1 bg-white/40"></span>
                                            <span class="w-9 h-9 rounded-full border border-white/50 flex items-center justify-center mx-1 shrink-0">
                                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M21 16v-2l-8-5V3.5a1.5 1.5 0 00-3 0V9l-8 5v2l8-2.5V19l-2 1.5V22l3.5-1 3.5 1v-1.5L13 19v-5.5z"/></svg>
                                            </span>
                                            <span class="h-px flex-1 bg-white/40"></span>
                                        </span>
                                        <span class="text-3xl sm:text-4xl font-black tracking-tight">{{ trip.arrival_airport || '—' }}</span>
                                    </div>
                                </div>
                                <div class="flex gap-3 shrink-0">
                                    <div class="rounded-xl bg-white/10 border border-white/15 px-4 py-3 min-w-[130px]">
                                        <div class="text-[10px] font-bold text-white/70 uppercase tracking-wide">Departure</div>
                                        <div class="text-lg font-black">{{ trip.departure_date || '—' }}</div>
                                        <div v-if="trip.departure_time" class="text-xs font-bold text-white/70">{{ trip.departure_time }}</div>
                                    </div>
                                    <div class="rounded-xl bg-white/10 border border-white/15 px-4 py-3 min-w-[150px]">
                                        <div class="text-[10px] font-bold text-white/70 uppercase tracking-wide">Status</div>
                                        <div class="flex items-center gap-1.5 text-lg font-black">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3l7 3v5c0 4.5-3 8.5-7 10-4-1.5-7-5.5-7-10V6l7-3z"/><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4"/></svg>
                                            Protected
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Monitoring banner -->
                        <div class="flex items-start gap-3 bg-emerald-50 border border-emerald-100 text-emerald-800 px-4 py-3 rounded-xl text-sm">
                            <svg class="w-5 h-5 shrink-0 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3l7 3v5c0 4.5-3 8.5-7 10-4-1.5-7-5.5-7-10V6l7-3z"/><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4"/></svg>
                            <p>This trip is <strong>Protected by Unjamm</strong>. We're watching this flight, and if it's delayed, cancelled, or overbooked, your claim will be ready before you land.</p>
                        </div>

                        <!-- Details -->
                        <div class="bg-white rounded-2xl ring-1 ring-slate-900/5 p-6 sm:p-8">
                            <h2 class="font-bold text-slate-900 mb-5">Trip details</h2>
                            <dl class="grid sm:grid-cols-2 gap-y-4 gap-x-8">
                                <div v-for="row in detailRows" :key="row.label">
                                    <dt class="text-[11px] uppercase tracking-wider font-bold text-slate-400">{{ row.label }}</dt>
                                    <dd class="mt-1 font-medium text-slate-900">{{ row.value || '—' }}</dd>
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
                    </div>

                    <!-- RIGHT: help -->
                    <HelpPanel />
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import api from '../api';
import { confirmRemove } from '../confirm';
import HelpPanel from '../components/HelpPanel.vue';

const props = defineProps({ id: { type: [String, Number], required: true } });
const router = useRouter();

const trip = ref(null);
const loading = ref(true);
const error = ref('');

const detailRows = computed(() => trip.value ? [
    { label: 'Route', value: `${trip.value.departure_airport || '—'} → ${trip.value.arrival_airport || '—'}` },
    { label: 'Airline', value: trip.value.airline },
    { label: 'Flight number', value: trip.value.flight_number },
    { label: 'Departure date', value: trip.value.departure_date },
    { label: 'Departure time', value: trip.value.departure_time },
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
    } catch (e) {
        error.value = e.response?.status === 403 ? 'You do not have access to this trip.' : 'Could not load this trip.';
    } finally {
        loading.value = false;
    }
}

watch(() => props.id, load);
onMounted(load);
</script>
