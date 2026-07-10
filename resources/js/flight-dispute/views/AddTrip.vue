<template>
    <div class="flex-1 flex flex-col min-h-0">
        <!-- Top bar -->
        <header class="h-16 bg-white border-b border-slate-200 flex items-center gap-3 px-4 sm:px-8 shrink-0 z-10 sticky top-0">
            <router-link :to="{ name: 'trips' }" class="p-2 -ml-2 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors" title="Back">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7 7-7m-7 7h18"/></svg>
            </router-link>
            <h1 class="font-black text-slate-900 text-lg tracking-tight">Protect a trip</h1>
        </header>

        <div class="flex-1 overflow-y-auto overflow-x-hidden bg-slate-100/70">
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

                <!-- ── Manual funnel ── -->
                <div v-if="mode === 'manual'" class="bg-white rounded-2xl ring-1 ring-slate-900/5 p-6 sm:p-8">

                    <!-- Stepper -->
                    <ol class="flex items-center gap-2 mb-8">
                        <li v-for="(s, i) in steps" :key="s" class="flex items-center gap-2 flex-1 last:flex-none">
                            <button
                                type="button"
                                @click="i + 1 < step && (step = i + 1)"
                                class="flex items-center gap-2"
                                :class="i + 1 < step ? 'cursor-pointer' : 'cursor-default'"
                            >
                                <span
                                    class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold shrink-0 transition-colors"
                                    :class="i + 1 < step ? 'bg-emerald-500 text-white' : i + 1 === step ? 'bg-primary-600 text-white' : 'bg-slate-100 text-slate-400'"
                                >
                                    <svg v-if="i + 1 < step" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                    <template v-else>{{ i + 1 }}</template>
                                </span>
                                <span class="text-xs font-bold hidden sm:block" :class="i + 1 === step ? 'text-slate-900' : 'text-slate-400'">{{ s }}</span>
                            </button>
                            <span v-if="i < steps.length - 1" class="h-px flex-1 bg-slate-200"></span>
                        </li>
                    </ol>

                    <p v-if="topError" class="bg-rose-50 border border-rose-100 text-rose-700 px-4 py-3 rounded-xl text-sm font-medium mb-6">{{ topError }}</p>

                    <!-- Step 1 · Route -->
                    <div v-if="step === 1" class="space-y-5">
                        <h2 class="font-bold text-slate-900">Where are you flying?</h2>
                        <div class="grid sm:grid-cols-2 gap-5">
                            <div>
                                <label class="fd-label">Departure city</label>
                                <input v-model="form.departure_city" type="text" placeholder="London" class="fd-input" />
                            </div>
                            <div>
                                <label class="fd-label">Departure airport (IATA) *</label>
                                <input v-model="form.departure_airport" type="text" maxlength="8" placeholder="LHR" class="fd-input uppercase" />
                                <p v-if="stepError.departure_airport" class="fd-err">{{ stepError.departure_airport }}</p>
                            </div>
                            <div>
                                <label class="fd-label">Arrival city</label>
                                <input v-model="form.arrival_city" type="text" placeholder="New York" class="fd-input" />
                            </div>
                            <div>
                                <label class="fd-label">Arrival airport (IATA) *</label>
                                <input v-model="form.arrival_airport" type="text" maxlength="8" placeholder="JFK" class="fd-input uppercase" />
                                <p v-if="stepError.arrival_airport" class="fd-err">{{ stepError.arrival_airport }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2 · Flight -->
                    <div v-else-if="step === 2" class="space-y-5">
                        <h2 class="font-bold text-slate-900">Your flight</h2>
                        <div class="grid sm:grid-cols-2 gap-5">
                            <div>
                                <label class="fd-label">Airline</label>
                                <input v-model="form.airline" type="text" placeholder="British Airways" class="fd-input" />
                            </div>
                            <div>
                                <label class="fd-label">Flight number *</label>
                                <input v-model="form.flight_number" type="text" maxlength="12" placeholder="BA175" class="fd-input uppercase" />
                                <p v-if="stepError.flight_number" class="fd-err">{{ stepError.flight_number }}</p>
                            </div>
                            <div>
                                <label class="fd-label">Departure date *</label>
                                <input v-model="form.departure_date" type="date" :min="today" class="fd-input" />
                                <p v-if="stepError.departure_date" class="fd-err">{{ stepError.departure_date }}</p>
                            </div>
                            <div>
                                <label class="fd-label">Departure time</label>
                                <input v-model="form.departure_time" type="time" class="fd-input" />
                            </div>
                            <div>
                                <label class="fd-label">Booking reference</label>
                                <input v-model="form.booking_reference" type="text" maxlength="32" placeholder="ABC123" class="fd-input uppercase" />
                            </div>
                        </div>
                    </div>

                    <!-- Step 3 · Passengers -->
                    <div v-else-if="step === 3" class="space-y-5">
                        <div>
                            <h2 class="font-bold text-slate-900">Who's travelling?</h2>
                            <p class="text-sm text-slate-500 mt-1">Every passenger on the booking is protected, so add them all.</p>
                        </div>
                        <div v-for="(p, i) in passengers" :key="i" class="flex items-center gap-3">
                            <input
                                v-model="passengers[i]"
                                type="text"
                                :placeholder="i === 0 ? 'John Smith' : 'Another passenger'"
                                class="fd-input flex-1"
                            />
                            <button
                                v-if="passengers.length > 1"
                                type="button"
                                @click="passengers.splice(i, 1)"
                                class="p-2.5 rounded-lg text-slate-300 hover:text-rose-500 hover:bg-rose-50 transition-colors shrink-0"
                                title="Remove passenger"
                            >
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6 7h12M9 7V5a1 1 0 011-1h4a1 1 0 011 1v2m-8 0l1 13a1 1 0 001 1h6a1 1 0 001-1l1-13"/></svg>
                            </button>
                        </div>
                        <p v-if="stepError.passengers" class="fd-err">{{ stepError.passengers }}</p>
                        <button type="button" @click="passengers.push('')" class="inline-flex items-center gap-2 text-sm font-bold text-primary-600 hover:text-primary-700">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
                            Add another passenger
                        </button>
                    </div>

                    <!-- Step 4 · Ticket -->
                    <div v-else class="space-y-5">
                        <div>
                            <h2 class="font-bold text-slate-900">Attach your ticket *</h2>
                            <p class="text-sm text-slate-500 mt-1">Your ticket or booking confirmation shows the price you paid. We need it to value your claim if this flight is disrupted.</p>
                        </div>

                        <label
                            class="group flex flex-col items-center justify-center gap-2 border-2 border-dashed rounded-xl px-6 py-10 text-center cursor-pointer transition-colors"
                            :class="dragActive ? 'border-primary-400 bg-primary-50/60' : 'border-slate-300 hover:border-primary-400 hover:bg-primary-50/40'"
                            @dragover.prevent="dragActive = true" @dragleave.prevent="dragActive = false" @drop.prevent="onTicketDrop"
                        >
                            <svg class="w-8 h-8 text-slate-400 group-hover:text-primary-500 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.9 5 5 0 019.72-1.4A4 4 0 0117 16H7z"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 12v6m0-6l-2 2m2-2l2 2"/></svg>
                            <div class="text-sm"><span class="font-semibold text-primary-600">Choose a PDF or photo</span><span class="text-slate-500"> or drag it here</span></div>
                            <span class="text-xs text-slate-400">PDF or image (JPG, PNG, HEIC) · up to 12 MB</span>
                            <input type="file" accept="application/pdf,image/*" class="hidden" @change="onTicketSelect" />
                        </label>

                        <div v-if="ticketFile" class="flex flex-wrap items-center justify-between gap-3 bg-slate-50 border border-slate-200 rounded-xl px-4 py-3">
                            <span class="text-sm font-medium text-slate-700 truncate">{{ ticketFile.name }}</span>
                            <button type="button" @click="ticketFile = null" class="text-xs font-semibold text-slate-500 hover:text-slate-700 px-3 py-2 rounded-lg hover:bg-slate-100">Remove</button>
                        </div>
                        <p v-if="stepError.ticket" class="fd-err">{{ stepError.ticket }}</p>
                    </div>

                    <!-- Funnel nav -->
                    <div class="flex items-center justify-between pt-8">
                        <button
                            v-if="step > 1"
                            type="button"
                            @click="step--"
                            class="inline-flex items-center gap-2 text-sm font-bold text-slate-500 hover:text-slate-700 px-4 py-3 rounded-xl hover:bg-slate-100 transition-colors"
                        >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                            Back
                        </button>
                        <span v-else></span>

                        <button
                            v-if="step < steps.length"
                            type="button"
                            @click="next"
                            class="inline-flex items-center gap-2 bg-slate-900 hover:bg-slate-800 text-white px-6 py-3 rounded-xl text-sm font-bold shadow-lg shadow-slate-900/10 transition-all active:scale-95"
                        >
                            Continue
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        </button>
                        <button
                            v-else
                            type="button"
                            @click="submit"
                            :disabled="saving"
                            class="inline-flex items-center gap-2 bg-slate-900 hover:bg-slate-800 text-white px-6 py-3 rounded-xl text-sm font-bold shadow-lg shadow-slate-900/10 transition-all disabled:opacity-60"
                        >
                            <svg v-if="saving" class="w-4 h-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z"/></svg>
                            <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3l7 3v5c0 4.5-3 8.5-7 10-4-1.5-7-5.5-7-10V6l7-3z"/></svg>
                            Protect this trip
                        </button>
                    </div>
                </div>

                <!-- ── PDF / photo upload ── -->
                <div v-else class="relative bg-white rounded-2xl ring-1 ring-slate-900/5 p-6 sm:p-8">
                    <div v-if="uploading" class="absolute inset-0 z-20 rounded-2xl bg-white/85 backdrop-blur-sm flex flex-col items-center justify-center text-center gap-3">
                        <svg class="w-9 h-9 text-primary-600 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z"/></svg>
                        <div>
                            <p class="font-bold text-slate-900">Reading your itinerary…</p>
                            <p class="text-sm text-slate-500">We'll protect every upcoming flight on it automatically.</p>
                        </div>
                    </div>

                    <h2 class="font-bold text-slate-900 mb-1">Upload your itinerary</h2>
                    <p class="text-sm text-slate-500 mb-5">Drop a booking confirmation PDF or a photo of your itinerary, and we'll register each upcoming flight and every passenger for protection.</p>

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

                    <div v-if="uploadNotice" class="mt-4 flex items-center justify-between gap-3 bg-emerald-50 border border-emerald-100 text-emerald-800 px-4 py-3 rounded-xl text-sm">
                        <span>{{ uploadNotice }}</span>
                        <router-link :to="{ name: 'trips' }" class="font-bold whitespace-nowrap hover:underline">View my trips →</router-link>
                    </div>

                    <div v-if="selectedFile" class="mt-4 flex flex-wrap items-center justify-between gap-3 bg-slate-50 border border-slate-200 rounded-xl px-4 py-3">
                        <span class="text-sm font-medium text-slate-700 truncate">{{ selectedFile.name }}</span>
                        <div class="flex items-center gap-2">
                            <button type="button" @click="selectedFile = null" class="text-xs font-semibold text-slate-500 hover:text-slate-700 px-3 py-2 rounded-lg hover:bg-slate-100">Remove</button>
                            <button type="button" @click="upload" :disabled="uploading" class="inline-flex items-center gap-2 bg-slate-900 hover:bg-slate-800 text-white px-4 py-2 rounded-lg text-xs font-bold disabled:opacity-60">Process &amp; protect trip</button>
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

// Local date, not UTC: toISOString() would block same-day trips for users
// west of UTC and allow already-past dates east of it.
const now = new Date();
const today = [
    now.getFullYear(),
    String(now.getMonth() + 1).padStart(2, '0'),
    String(now.getDate()).padStart(2, '0'),
].join('-');

// ── Manual funnel ──
const steps = ['Route', 'Flight', 'Passengers', 'Ticket'];
const step = ref(1);

const form = ref({
    departure_city: '', departure_airport: '', arrival_city: '', arrival_airport: '',
    airline: '', flight_number: '', departure_date: '', departure_time: '',
    booking_reference: '',
});
const passengers = ref(['']);
const ticketFile = ref(null);

const stepError = ref({});
const topError = ref('');
const saving = ref(false);

function validateStep() {
    const e = {};
    if (step.value === 1) {
        if (!form.value.departure_airport.trim()) e.departure_airport = 'Required.';
        if (!form.value.arrival_airport.trim()) e.arrival_airport = 'Required.';
    } else if (step.value === 2) {
        if (!form.value.flight_number.trim()) e.flight_number = 'Required.';
        if (!form.value.departure_date) e.departure_date = 'Required.';
        else if (form.value.departure_date < today) e.departure_date = 'Trips can only be protected before you fly. Please pick a future date.';
    } else if (step.value === 3) {
        if (!passengers.value.some((p) => p.trim())) e.passengers = 'Add at least one passenger.';
    } else if (step.value === 4) {
        if (!ticketFile.value) e.ticket = 'Please attach your ticket. We need it to know your ticket price if a claim arises.';
    }
    stepError.value = e;
    return Object.keys(e).length === 0;
}

function next() {
    if (validateStep()) {
        stepError.value = {};
        step.value++;
    }
}

function onTicketSelect(e) {
    ticketFile.value = e.target.files?.[0] || null;
    stepError.value = {};
}

function onTicketDrop(e) {
    dragActive.value = false;
    ticketFile.value = e.dataTransfer?.files?.[0] || null;
    stepError.value = {};
}

async function submit() {
    if (!validateStep()) return;
    saving.value = true;
    topError.value = '';
    try {
        const data = new FormData();
        Object.entries(form.value).forEach(([k, v]) => v && data.append(k, v));
        passengers.value.filter((p) => p.trim()).forEach((p) => data.append('passengers[]', p.trim()));
        data.append('ticket', ticketFile.value);
        await api.trips.create(data);
        router.push({ name: 'trips' });
    } catch (e) {
        if (e.response?.status === 422) {
            const errs = e.response.data.errors || {};
            topError.value = Object.values(errs)[0]?.[0] || 'Please review your details.';
            // Send the user back to the step containing the first server error.
            const key = Object.keys(errs)[0] || '';
            if (['departure_airport', 'arrival_airport', 'departure_city', 'arrival_city'].some((f) => key.startsWith(f))) step.value = 1;
            else if (['airline', 'flight_number', 'departure_date', 'departure_time', 'booking_reference'].some((f) => key.startsWith(f))) step.value = 2;
            else if (key.startsWith('passengers')) step.value = 3;
            else if (key.startsWith('ticket')) step.value = 4;
        } else {
            topError.value = 'Something went wrong. Please try again.';
        }
    } finally {
        saving.value = false;
    }
}

// ── Upload mode ──
const selectedFile = ref(null);
const uploading = ref(false);
const uploadError = ref('');
const uploadNotice = ref('');
const dragActive = ref(false);

function onSelect(e) {
    selectedFile.value = e.target.files?.[0] || null;
    uploadError.value = '';
    uploadNotice.value = '';
}

function onDrop(e) {
    dragActive.value = false;
    selectedFile.value = e.dataTransfer?.files?.[0] || null;
    uploadError.value = '';
    uploadNotice.value = '';
}

async function upload() {
    if (!selectedFile.value) return;
    uploading.value = true;
    uploadError.value = '';
    uploadNotice.value = '';
    try {
        const res = await api.trips.upload(selectedFile.value);
        if (res.duplicate) {
            uploadNotice.value = res.message;
            selectedFile.value = null;
        } else {
            router.push({ name: 'trips' });
        }
    } catch (e) {
        uploadError.value = e.response?.data?.message
            || e.response?.data?.errors?.file?.[0]
            || 'Upload failed. Please try again.';
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
