<template>
    <div class="flex-1 flex flex-col min-h-0">
        <header class="h-16 bg-white border-b border-slate-200 flex items-center gap-3 px-4 sm:px-8 shrink-0 z-10 sticky top-0">
            <router-link :to="{ name: 'claims' }" class="p-2 -ml-2 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors" title="Back">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7 7-7m-7 7h18"/></svg>
            </router-link>
            <h1 class="font-black text-slate-900 text-lg tracking-tight">Your Claim</h1>
        </header>

        <div class="flex-1 overflow-y-auto bg-slate-100/70">
            <div class="max-w-[1400px] mx-auto px-4 sm:px-8 py-8">

                <div v-if="loading" class="text-sm text-slate-400 py-10 text-center">Loading…</div>
                <div v-else-if="error" class="bg-rose-50 border border-rose-100 text-rose-700 px-4 py-3 rounded-xl text-sm">{{ error }}</div>

                <div v-else-if="claim" class="grid lg:grid-cols-3 gap-6 items-start">
                    <!-- LEFT -->
                    <div class="lg:col-span-2 space-y-5">
                        <!-- Hero -->
                        <div class="relative overflow-hidden rounded-2xl p-6 sm:p-7 text-white" style="background:linear-gradient(105deg,#064e3b 0%,#059669 55%,#10b981 78%,#2563eb 130%);">
                            <div class="flex flex-col lg:flex-row lg:items-center gap-5">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between text-[11px] font-semibold text-white/70 uppercase tracking-wide mb-1">
                                        <span class="truncate">{{ claim.departure_city || claim.departure_airport }}</span>
                                        <span class="truncate text-right">{{ claim.arrival_city || claim.arrival_airport }}</span>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <span class="text-3xl sm:text-4xl font-black tracking-tight">{{ claim.departure_airport || '—' }}</span>
                                        <span class="flex-1 flex items-center">
                                            <span class="h-px flex-1 bg-white/40"></span>
                                            <span class="w-9 h-9 rounded-full border border-white/50 flex items-center justify-center mx-1 shrink-0">
                                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M21 16v-2l-8-5V3.5a1.5 1.5 0 00-3 0V9l-8 5v2l8-2.5V19l-2 1.5V22l3.5-1 3.5 1v-1.5L13 19v-5.5z"/></svg>
                                            </span>
                                            <span class="h-px flex-1 bg-white/40"></span>
                                        </span>
                                        <span class="text-3xl sm:text-4xl font-black tracking-tight">{{ claim.arrival_airport || '—' }}</span>
                                    </div>
                                </div>
                                <div class="flex gap-3 shrink-0 flex-wrap">
                                    <div class="rounded-xl bg-white/10 border border-white/15 px-4 py-3 min-w-[120px]">
                                        <div class="text-[10px] font-bold text-white/70 uppercase tracking-wide">Claim ID</div>
                                        <div class="text-lg font-black">{{ claim.number }}</div>
                                    </div>
                                    <div class="rounded-xl bg-white/10 border border-white/15 px-4 py-3 min-w-[140px]">
                                        <div class="text-[10px] font-bold text-white/70 uppercase tracking-wide">Compensation</div>
                                        <div class="text-lg font-black">{{ claim.compensation.display }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tabs -->
                        <div class="flex flex-wrap gap-1 bg-white rounded-xl ring-1 ring-slate-900/5 p-1">
                            <button
                                v-for="t in tabs" :key="t.key" @click="activeTab = t.key"
                                class="px-4 py-2 rounded-lg text-sm font-bold transition-colors"
                                :class="activeTab === t.key ? 'bg-slate-900 text-white shadow-lg shadow-slate-900/10' : 'text-slate-500 hover:text-slate-700'"
                            >{{ t.label }}</button>
                        </div>

                        <!-- Tab: Progress -->
                        <div v-if="activeTab === 'progress'" class="bg-white rounded-2xl ring-1 ring-slate-900/5 p-6 sm:p-8">
                            <ol class="relative">
                                <li v-for="(ev, i) in claim.events" :key="i" class="flex gap-4 pb-8 last:pb-0">
                                    <div class="flex flex-col items-center">
                                        <span class="w-6 h-6 rounded-full flex items-center justify-center shrink-0 z-10" :class="dotCls(ev.status)">
                                            <svg v-if="ev.status === 'done'" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                            <svg v-else-if="ev.status === 'failed'" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18"/></svg>
                                        </span>
                                        <span v-if="i < claim.events.length - 1" class="w-px flex-1 bg-slate-200 my-1"></span>
                                    </div>
                                    <div class="-mt-0.5">
                                        <div class="font-bold text-slate-900">{{ ev.label }}</div>
                                        <div class="text-sm text-slate-400 mt-0.5">{{ ev.date }}</div>
                                    </div>
                                </li>
                                <li v-if="!claim.events.length" class="text-sm text-slate-400">No status updates yet.</li>
                            </ol>
                            <button @click="activeTab = 'details'" class="mt-4 inline-flex items-center gap-2 border border-primary-300 text-primary-700 hover:bg-primary-50 px-5 py-2.5 rounded-xl text-sm font-bold transition-colors">View Status Details</button>
                        </div>

                        <!-- Tab: Compensation -->
                        <div v-else-if="activeTab === 'compensation'" class="bg-white rounded-2xl ring-1 ring-slate-900/5 p-6 sm:p-8">
                            <div class="flex items-start gap-3 bg-primary-50 border border-primary-100 text-primary-800 px-4 py-3 rounded-xl text-sm">
                                <svg class="w-5 h-5 shrink-0 text-primary-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
                                <p>Your eligibility and compensation amount will be assessed after your claim is reviewed. We'll update this section once it's ready.</p>
                            </div>
                            <div class="mt-4 text-sm text-slate-500">Current estimate: <span class="font-bold text-slate-800">{{ claim.compensation.display }}</span></div>
                        </div>

                        <!-- Tab: Documents -->
                        <div v-else-if="activeTab === 'documents'" class="bg-white rounded-2xl ring-1 ring-slate-900/5 p-6 sm:p-8">
                            <p v-if="!claim.documents.length" class="text-sm text-slate-400">No documents attached yet.</p>
                            <ul v-else class="space-y-2">
                                <li v-for="(doc, i) in claim.documents" :key="i">
                                    <a :href="doc.url" target="_blank" class="flex items-center gap-3 px-4 py-3 rounded-xl border border-slate-200 hover:border-primary-300 hover:bg-primary-50/40 transition-colors">
                                        <svg class="w-5 h-5 text-primary-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 4H7a2 2 0 01-2-2V6a2 2 0 012-2h7l5 5v11a2 2 0 01-2 2z"/></svg>
                                        <span class="text-sm font-medium text-slate-700 truncate">{{ doc.name }}</span>
                                    </a>
                                </li>
                            </ul>
                        </div>

                        <!-- Tab: Details -->
                        <div v-else-if="activeTab === 'details'" class="bg-white rounded-2xl ring-1 ring-slate-900/5 p-6 sm:p-8">
                            <dl class="grid sm:grid-cols-2 gap-y-4 gap-x-8">
                                <div v-for="row in detailRows" :key="row.label">
                                    <dt class="text-[11px] uppercase tracking-wider font-bold text-slate-400">{{ row.label }}</dt>
                                    <dd class="mt-1 font-medium text-slate-900">{{ row.value || '—' }}</dd>
                                </div>
                            </dl>
                        </div>

                        <!-- Tab: Email History -->
                        <div v-else class="bg-white rounded-2xl ring-1 ring-slate-900/5 p-6 sm:p-8">
                            <p class="text-sm text-slate-400">No emails have been exchanged on this claim yet.</p>
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
import api from '../api';
import HelpPanel from '../components/HelpPanel.vue';

const props = defineProps({ id: { type: [String, Number], required: true } });

const claim = ref(null);
const loading = ref(true);
const error = ref('');
const activeTab = ref('progress');

const tabs = [
    { key: 'progress', label: 'Progress' },
    { key: 'compensation', label: 'Compensation' },
    { key: 'documents', label: 'Documents' },
    { key: 'details', label: 'Details' },
    { key: 'emails', label: 'Email History' },
];

const detailRows = computed(() => claim.value ? [
    { label: 'Route', value: `${claim.value.departure_airport || '—'} → ${claim.value.arrival_airport || '—'}` },
    { label: 'Airline', value: claim.value.airline },
    { label: 'Flight number', value: claim.value.flight_number },
    { label: 'Flight date', value: claim.value.flight_date },
    { label: 'Disruption', value: claim.value.disruption_label },
    { label: 'Passenger', value: claim.value.passenger_name },
    { label: 'Booking reference', value: claim.value.booking_reference },
    { label: 'Claim reference', value: claim.value.reference },
    { label: 'Submitted', value: claim.value.submitted_at },
    { label: 'Status', value: claim.value.status_label },
] : []);

function dotCls(status) {
    if (status === 'done') return 'bg-emerald-500 text-white';
    if (status === 'failed') return 'bg-rose-100 text-rose-500 border border-rose-300';
    return 'bg-slate-200 text-slate-400';
}

async function load() {
    loading.value = true;
    error.value = '';
    try {
        claim.value = await api.claims.get(props.id);
    } catch (e) {
        error.value = e.response?.status === 403 ? 'You do not have access to this claim.' : 'Could not load this claim.';
    } finally {
        loading.value = false;
    }
}

watch(() => props.id, load);
onMounted(load);
</script>
