<template>
    <div class="flex-1 flex flex-col min-h-0">
        <!-- Top bar -->
        <header class="h-16 bg-white border-b border-slate-200 flex items-center gap-3 px-4 sm:px-8 shrink-0 z-10 sticky top-0">
            <router-link :to="{ name: 'claims' }" class="p-2 -ml-2 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors" title="Back">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7 7-7m-7 7h18"/></svg>
            </router-link>
            <h1 class="font-black text-slate-900 text-lg tracking-tight">Add a claim</h1>
        </header>

        <div class="flex-1 overflow-y-auto bg-slate-100/70">
            <div class="max-w-[820px] mx-auto px-4 sm:px-8 py-8">

                <!-- Mode toggle -->
                <div class="inline-flex p-1 bg-white rounded-xl ring-1 ring-slate-900/5 mb-6">
                    <button
                        v-for="m in modes" :key="m.key"
                        @click="mode = m.key"
                        class="px-4 py-2 rounded-lg text-sm font-bold transition-colors"
                        :class="mode === m.key ? 'bg-slate-900 text-white shadow-lg shadow-slate-900/10' : 'text-slate-500 hover:text-slate-700'"
                    >{{ m.label }}</button>
                </div>

                <!-- ── Manual entry ── -->
                <form v-if="mode === 'manual'" @submit.prevent="submit" class="bg-white rounded-2xl ring-1 ring-slate-900/5 p-6 sm:p-8 space-y-6">
                    <p v-if="topError" class="bg-rose-50 border border-rose-100 text-rose-700 px-4 py-3 rounded-xl text-sm font-medium">{{ topError }}</p>

                    <!-- Route -->
                    <div class="grid sm:grid-cols-2 gap-5">
                        <div>
                            <label class="fd-label">Departure city</label>
                            <input v-model="form.departure_city" type="text" placeholder="New York" class="fd-input" />
                            <p v-if="err('departure_city')" class="fd-err">{{ err('departure_city') }}</p>
                        </div>
                        <div>
                            <label class="fd-label">Departure airport (IATA) *</label>
                            <input v-model="form.departure_airport" type="text" maxlength="8" placeholder="LGA" class="fd-input uppercase" />
                            <p v-if="err('departure_airport')" class="fd-err">{{ err('departure_airport') }}</p>
                        </div>
                        <div>
                            <label class="fd-label">Arrival city</label>
                            <input v-model="form.arrival_city" type="text" placeholder="London" class="fd-input" />
                            <p v-if="err('arrival_city')" class="fd-err">{{ err('arrival_city') }}</p>
                        </div>
                        <div>
                            <label class="fd-label">Arrival airport (IATA) *</label>
                            <input v-model="form.arrival_airport" type="text" maxlength="8" placeholder="LHR" class="fd-input uppercase" />
                            <p v-if="err('arrival_airport')" class="fd-err">{{ err('arrival_airport') }}</p>
                        </div>
                    </div>

                    <!-- Flight -->
                    <div class="grid sm:grid-cols-2 gap-5">
                        <div>
                            <label class="fd-label">Airline *</label>
                            <input v-model="form.airline" type="text" placeholder="British Airways" class="fd-input" />
                            <p v-if="err('airline')" class="fd-err">{{ err('airline') }}</p>
                        </div>
                        <div>
                            <label class="fd-label">Flight number</label>
                            <input v-model="form.flight_number" type="text" placeholder="BA178" class="fd-input" />
                            <p v-if="err('flight_number')" class="fd-err">{{ err('flight_number') }}</p>
                        </div>
                        <div>
                            <label class="fd-label">Flight date *</label>
                            <input v-model="form.flight_date" type="date" class="fd-input" />
                            <p v-if="err('flight_date')" class="fd-err">{{ err('flight_date') }}</p>
                        </div>
                        <div>
                            <label class="fd-label">Booking reference</label>
                            <input v-model="form.booking_reference" type="text" placeholder="ABC123" class="fd-input" />
                            <p v-if="err('booking_reference')" class="fd-err">{{ err('booking_reference') }}</p>
                        </div>
                    </div>

                    <!-- Disruption -->
                    <div>
                        <label class="fd-label">What went wrong? *</label>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="d in disruptions" :key="d.value" type="button"
                                @click="form.disruption_type = d.value"
                                class="px-4 py-2 rounded-xl text-sm font-semibold border transition-colors"
                                :class="form.disruption_type === d.value ? 'bg-primary-50 border-primary-300 text-primary-700' : 'bg-white border-slate-200 text-slate-600 hover:border-slate-300'"
                            >{{ d.label }}</button>
                        </div>
                        <p v-if="err('disruption_type')" class="fd-err">{{ err('disruption_type') }}</p>
                    </div>

                    <!-- Passenger / contact -->
                    <div class="grid sm:grid-cols-2 gap-5">
                        <div>
                            <label class="fd-label">Passenger full name *</label>
                            <input v-model="form.passenger_name" type="text" placeholder="John Smith" class="fd-input" />
                            <p v-if="err('passenger_name')" class="fd-err">{{ err('passenger_name') }}</p>
                        </div>
                        <div>
                            <label class="fd-label">Contact email</label>
                            <input v-model="form.contact_email" type="email" placeholder="you@example.com" class="fd-input" />
                            <p v-if="err('contact_email')" class="fd-err">{{ err('contact_email') }}</p>
                        </div>
                    </div>

                    <div class="flex justify-end pt-2">
                        <button type="submit" :disabled="saving" class="inline-flex items-center gap-2 bg-slate-900 hover:bg-slate-800 text-white px-6 py-3 rounded-xl text-sm font-bold shadow-lg shadow-slate-900/10 transition-all disabled:opacity-60">
                            <svg v-if="saving" class="w-4 h-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z"/></svg>
                            Submit claim
                        </button>
                    </div>
                </form>

                <!-- ── PDF / photo upload ── -->
                <div v-else class="relative bg-white rounded-2xl ring-1 ring-slate-900/5 p-6 sm:p-8">
                    <div v-if="uploading" class="absolute inset-0 z-20 rounded-2xl bg-white/85 backdrop-blur-sm flex flex-col items-center justify-center text-center gap-3">
                        <svg class="w-9 h-9 text-primary-600 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z"/></svg>
                        <div>
                            <p class="font-bold text-slate-900">Reading your itinerary…</p>
                            <p class="text-sm text-slate-500">We'll create a claim for each passenger automatically.</p>
                        </div>
                    </div>

                    <h2 class="font-bold text-slate-900 mb-1">Upload your itinerary</h2>
                    <p class="text-sm text-slate-500 mb-5">Drop a booking confirmation PDF — or a photo of your itinerary — and we'll pull out the flights and passengers and create your claims.</p>

                    <label
                        class="group flex flex-col items-center justify-center gap-2 border-2 border-dashed rounded-xl px-6 py-10 text-center cursor-pointer transition-colors"
                        :class="dragActive ? 'border-primary-400 bg-primary-50/60' : 'border-slate-300 hover:border-primary-400 hover:bg-primary-50/40'"
                        @dragover.prevent="dragActive = true" @dragleave.prevent="dragActive = false" @drop.prevent="onDrop"
                    >
                        <svg class="w-8 h-8 text-slate-400 group-hover:text-primary-500 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.9 5 5 0 019.72-1.4A4 4 0 0117 16H7z"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 12v6m0-6l-2 2m2-2l2 2"/></svg>
                        <div class="text-sm"><span class="font-semibold text-primary-600">Choose a PDF or photo</span><span class="text-slate-500"> or drag it here</span></div>
                        <span class="text-xs text-slate-400">PDF or image (JPG, PNG, HEIC) · up to 12 MB</span>
                        <input type="file" accept="application/pdf,image/*" class="hidden" @change="onSelect" />
                    </label>

                    <div v-if="uploadNotice" class="mt-4 flex items-center justify-between gap-3 bg-primary-50 border border-primary-100 text-primary-800 px-4 py-3 rounded-xl text-sm">
                        <span>{{ uploadNotice }}</span>
                        <router-link :to="{ name: 'claims' }" class="font-bold whitespace-nowrap hover:underline">View my claims →</router-link>
                    </div>

                    <div v-if="selectedFile" class="mt-4 flex flex-wrap items-center justify-between gap-3 bg-slate-50 border border-slate-200 rounded-xl px-4 py-3">
                        <span class="text-sm font-medium text-slate-700 truncate">{{ selectedFile.name }}</span>
                        <div class="flex items-center gap-2">
                            <button type="button" @click="selectedFile = null" class="text-xs font-semibold text-slate-500 hover:text-slate-700 px-3 py-2 rounded-lg hover:bg-slate-100">Remove</button>
                            <button type="button" @click="upload" :disabled="uploading" class="inline-flex items-center gap-2 bg-slate-900 hover:bg-slate-800 text-white px-4 py-2 rounded-lg text-xs font-bold disabled:opacity-60">Process &amp; create claims</button>
                        </div>
                    </div>
                    <p v-if="uploadError" class="mt-3 text-xs font-semibold text-rose-600">{{ uploadError }}</p>
                </div>

            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import api from '../api';

const router = useRouter();

const modes = [
    { key: 'upload', label: 'Upload itinerary' },
    { key: 'manual', label: 'Enter details' },
];
const mode = ref('upload');

const disruptions = [
    { value: 'delayed', label: 'Delayed 3h+' },
    { value: 'cancelled', label: 'Cancelled' },
    { value: 'denied_boarding', label: 'Denied boarding' },
    { value: 'missed_connection', label: 'Missed connection' },
    { value: 'other', label: 'Other' },
];

const form = ref({
    departure_city: '', departure_airport: '', arrival_city: '', arrival_airport: '',
    airline: '', flight_number: '', flight_date: '', disruption_type: '',
    passenger_name: '', booking_reference: '', contact_email: '',
});
const errors = ref({});
const topError = ref('');
const saving = ref(false);

const err = (f) => errors.value[f]?.[0];

async function submit() {
    saving.value = true;
    errors.value = {};
    topError.value = '';
    try {
        const claim = await api.claims.create(form.value);
        router.push({ name: 'claim', params: { id: claim.id } });
    } catch (e) {
        if (e.response?.status === 422) {
            errors.value = e.response.data.errors || {};
            topError.value = 'Please fix the highlighted fields.';
        } else {
            topError.value = 'Something went wrong. Please try again.';
        }
    } finally {
        saving.value = false;
    }
}

// PDF upload
const selectedFile = ref(null);
const dragActive = ref(false);
const uploading = ref(false);
const uploadError = ref('');
const uploadNotice = ref('');

const onSelect = (e) => { uploadError.value = ''; uploadNotice.value = ''; selectedFile.value = e.target.files?.[0] || null; };
const onDrop = (e) => { dragActive.value = false; uploadError.value = ''; uploadNotice.value = ''; selectedFile.value = e.dataTransfer.files?.[0] || null; };

async function upload() {
    if (!selectedFile.value) return;
    uploading.value = true;
    uploadError.value = '';
    uploadNotice.value = '';
    try {
        const res = await api.uploadItinerary(selectedFile.value);
        if (res.data?.duplicate) {
            uploadNotice.value = res.data.message || 'You already uploaded this itinerary.';
            selectedFile.value = null;
            return;
        }
        router.push({ name: 'claims' });
    } catch (e) {
        uploadError.value = e.response?.data?.message || 'We could not process that file.';
    } finally {
        uploading.value = false;
    }
}
</script>

<style scoped>
.fd-label { display: block; font-size: 0.8rem; font-weight: 600; color: #334155; margin-bottom: 0.4rem; }
.fd-input {
    width: 100%; padding: 0.65rem 0.85rem; border: 1px solid #e2e8f0; border-radius: 0.7rem;
    font-size: 0.95rem; color: #0f172a; outline: none; transition: border-color .15s, box-shadow .15s; background: #fff;
}
.fd-input:focus { border-color: #2563eb; box-shadow: 0 0 0 4px rgba(37,99,235,0.1); }
.fd-input::placeholder { color: #94a3b8; }
.fd-err { color: #e11d48; font-size: 0.72rem; font-weight: 600; margin-top: 0.3rem; }
</style>
