<template>
    <div class="flex-1 flex flex-col min-h-0">
        <!-- Top bar -->
        <header class="h-16 bg-white border-b border-slate-200 flex items-center gap-3 px-4 sm:px-8 shrink-0 z-10 sticky top-0">
            <router-link :to="{ name: 'claims' }" class="p-2 -ml-2 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors" title="Back">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7 7-7m-7 7h18"/></svg>
            </router-link>
            <h1 class="font-black text-slate-900 text-lg tracking-tight">Add a claim</h1>
        </header>

        <div class="flex-1 overflow-y-auto overflow-x-hidden bg-slate-100/70">
            <div class="max-w-[820px] mx-auto px-4 sm:px-8 py-8">

                <!-- Method choice: one OR the other -->
                <p class="text-sm font-bold text-slate-600 mb-3">How would you like to start? <span class="font-medium text-slate-400">Pick one - you don't need both.</span></p>
                <div class="grid sm:grid-cols-2 gap-3 mb-6">
                    <button
                        v-for="m in modes" :key="m.key"
                        type="button"
                        @click="mode = m.key"
                        class="text-left p-4 rounded-2xl ring-1 transition-all active:scale-[0.99]"
                        :class="mode === m.key ? 'bg-slate-900 ring-slate-900 shadow-lg shadow-slate-900/15' : 'bg-white ring-slate-200 hover:ring-slate-300'"
                    >
                        <span class="flex items-center gap-2">
                            <span class="w-4 h-4 rounded-full border-2 flex items-center justify-center shrink-0"
                                  :class="mode === m.key ? 'border-white' : 'border-slate-300'">
                                <span v-if="mode === m.key" class="w-2 h-2 rounded-full bg-white"></span>
                            </span>
                            <span class="text-sm font-bold" :class="mode === m.key ? 'text-white' : 'text-slate-800'">{{ m.label }}</span>
                            <span v-if="m.badge" class="ml-auto text-[10px] font-black uppercase tracking-wide px-2 py-0.5 rounded-full"
                                  :class="mode === m.key ? 'bg-white/20 text-white' : 'bg-emerald-50 text-emerald-600'">{{ m.badge }}</span>
                        </span>
                        <span class="block text-xs mt-1.5 pl-6" :class="mode === m.key ? 'text-white/70' : 'text-slate-400'">{{ m.hint }}</span>
                    </button>
                </div>

                <!-- ── Manual entry: step funnel ── -->
                <div v-if="mode === 'manual'" class="bg-white rounded-2xl ring-1 ring-slate-900/5 p-6 sm:p-8">
                    <p v-if="topError" class="bg-rose-50 border border-rose-100 text-rose-700 px-4 py-3 rounded-xl text-sm font-medium mb-5">{{ topError }}</p>

                    <!-- Step indicator -->
                    <div class="flex items-center gap-2 mb-7">
                        <template v-for="(label, i) in steps" :key="label">
                            <span class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-black shrink-0"
                                  :class="i + 1 < step ? 'bg-emerald-500 text-white' : i + 1 === step ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-400'">
                                <svg v-if="i + 1 < step" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                <template v-else>{{ i + 1 }}</template>
                            </span>
                            <span class="text-xs font-bold hidden sm:inline" :class="i + 1 === step ? 'text-slate-900' : 'text-slate-400'">{{ label }}</span>
                            <span v-if="i < steps.length - 1" class="h-px flex-1 bg-slate-200"></span>
                        </template>
                    </div>

                    <!-- Step 1: Flight -->
                    <div v-show="step === 1" class="space-y-5">
                        <h2 class="font-bold text-slate-900">Which flight was it?</h2>
                        <div class="grid sm:grid-cols-2 gap-5">
                            <div>
                                <label class="fd-label">Departure airport (IATA) *</label>
                                <input v-model="form.departure_airport" type="text" maxlength="8" placeholder="LGA" class="fd-input uppercase" />
                                <p v-if="err('departure_airport')" class="fd-err">{{ err('departure_airport') }}</p>
                            </div>
                            <div>
                                <label class="fd-label">Arrival airport (IATA) *</label>
                                <input v-model="form.arrival_airport" type="text" maxlength="8" placeholder="LHR" class="fd-input uppercase" />
                                <p v-if="err('arrival_airport')" class="fd-err">{{ err('arrival_airport') }}</p>
                            </div>
                            <div>
                                <label class="fd-label">Departure city</label>
                                <input v-model="form.departure_city" type="text" placeholder="New York" class="fd-input" />
                            </div>
                            <div>
                                <label class="fd-label">Arrival city</label>
                                <input v-model="form.arrival_city" type="text" placeholder="London" class="fd-input" />
                            </div>
                            <div>
                                <label class="fd-label">Airline *</label>
                                <input v-model="form.airline" type="text" placeholder="British Airways" class="fd-input" />
                                <p v-if="err('airline')" class="fd-err">{{ err('airline') }}</p>
                            </div>
                            <div>
                                <label class="fd-label">Flight number</label>
                                <input v-model="form.flight_number" type="text" placeholder="BA178" class="fd-input" />
                            </div>
                            <div>
                                <label class="fd-label">Flight date *</label>
                                <input v-model="form.flight_date" type="date" class="fd-input" />
                                <p v-if="err('flight_date')" class="fd-err">{{ err('flight_date') }}</p>
                            </div>
                            <div>
                                <label class="fd-label">Booking reference</label>
                                <input v-model="form.booking_reference" type="text" placeholder="ABC123" class="fd-input" />
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: What happened -->
                    <div v-show="step === 2" class="space-y-4">
                        <h2 class="font-bold text-slate-900">What went wrong?</h2>
                        <p class="text-xs text-slate-400">We verify this against the flight's tracking records automatically.</p>
                        <div class="space-y-2">
                            <button
                                v-for="d in disruptions" :key="d.value" type="button"
                                @click="form.disruption_type = d.value"
                                class="w-full text-left px-4 py-3 rounded-xl ring-1 text-sm font-medium transition-all"
                                :class="form.disruption_type === d.value ? 'bg-slate-900 text-white ring-slate-900' : 'bg-white text-slate-700 ring-slate-200 hover:ring-slate-300 hover:bg-slate-50'"
                            >{{ d.label }}</button>
                        </div>
                        <p v-if="err('disruption_type')" class="fd-err">{{ err('disruption_type') }}</p>

                        <div v-if="form.disruption_type === 'other'">
                            <label class="fd-label">What happened? *</label>
                            <textarea v-model.trim="form.disruption_note" rows="4" maxlength="1000"
                                      placeholder="Describe what went wrong in your own words - our team reviews this directly."
                                      class="fd-input"></textarea>
                            <p class="text-[11px] text-slate-400 mt-1">Required - at least 10 characters.</p>
                            <p v-if="err('disruption_note')" class="fd-err">{{ err('disruption_note') }}</p>
                        </div>
                    </div>

                    <!-- Step 3: Passenger & fare -->
                    <div v-show="step === 3" class="space-y-5">
                        <h2 class="font-bold text-slate-900">Who travelled, and what did the ticket cost?</h2>
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
                            <div>
                                <label class="fd-label">Ticket price per person</label>
                                <input v-model="form.ticket_price" type="number" min="0" step="0.01" placeholder="450.00" class="fd-input" />
                                <p class="text-[11px] text-slate-400 mt-1">Some compensation (downgrades, US denied boarding, refunds) is a percentage of the fare.</p>
                                <p v-if="err('ticket_price')" class="fd-err">{{ err('ticket_price') }}</p>
                            </div>
                            <div>
                                <label class="fd-label">Currency</label>
                                <select v-model="form.ticket_currency" class="fd-input">
                                    <option v-for="c in ['USD', 'EUR', 'GBP', 'CAD', 'AED', 'INR', 'CHF']" :key="c" :value="c">{{ c }}</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Step 4: Documents -->
                    <div v-show="step === 4" class="space-y-4">
                        <h2 class="font-bold text-slate-900">Ticket &amp; supporting documents</h2>
                        <div class="rounded-xl bg-slate-50 p-4">
                            <p class="text-[11px] uppercase tracking-wider font-bold text-slate-400 mb-2">What helps your case</p>
                            <ul class="space-y-1.5">
                                <li v-for="ex in documentExamples" :key="ex" class="flex gap-2 text-sm text-slate-600">
                                    <svg class="w-4 h-4 text-emerald-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4"/></svg>
                                    {{ ex }}
                                </li>
                            </ul>
                        </div>

                        <label class="flex flex-col items-center justify-center gap-2 border-2 border-dashed border-slate-200 hover:border-primary-300 rounded-xl px-4 py-6 cursor-pointer transition-colors">
                            <svg class="w-6 h-6 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16V4m0 0L8 8m4-4l4 4M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2"/></svg>
                            <span class="text-sm font-bold text-slate-600">Add documents</span>
                            <span class="text-xs text-slate-400">PDF or photos - up to 6 files, 12 MB each</span>
                            <input type="file" multiple accept=".pdf,.jpg,.jpeg,.png,.webp,.heic,.heif" class="hidden" @change="addDocuments" />
                        </label>

                        <ul v-if="documents.length" class="space-y-2">
                            <li v-for="(f, i) in documents" :key="i" class="flex items-center gap-2 px-3 py-2 rounded-lg bg-slate-50 text-sm">
                                <svg class="w-4 h-4 text-primary-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 4H7a2 2 0 01-2-2V6a2 2 0 012-2h7l5 5v11a2 2 0 01-2 2z"/></svg>
                                <span class="truncate flex-1 text-slate-700">{{ f.name }}</span>
                                <button type="button" @click="documents.splice(i, 1)" class="text-slate-300 hover:text-rose-500 transition-colors" aria-label="Remove file">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M6 6l12 12M18 6L6 18"/></svg>
                                </button>
                            </li>
                        </ul>
                        <p v-if="err('documents')" class="fd-err">{{ err('documents') }}</p>
                    </div>

                    <!-- Nav -->
                    <div class="flex items-center justify-between gap-3 mt-7 pt-5 border-t border-slate-100">
                        <button v-if="step > 1" type="button" @click="step--" class="px-4 py-2.5 rounded-xl text-sm font-bold text-slate-500 hover:text-slate-700 hover:bg-slate-100 transition-colors">Back</button>
                        <span v-else></span>

                        <button v-if="step < steps.length" type="button" @click="step++" :disabled="!stepValid"
                                class="px-6 py-2.5 bg-slate-900 hover:bg-slate-800 text-white rounded-xl text-sm font-bold shadow-lg shadow-slate-900/10 transition-all active:scale-95 disabled:opacity-40">
                            Next
                        </button>
                        <button v-else type="button" @click="submit" :disabled="saving"
                                class="inline-flex items-center gap-2 bg-slate-900 hover:bg-slate-800 text-white px-6 py-2.5 rounded-xl text-sm font-bold shadow-lg shadow-slate-900/10 transition-all active:scale-95 disabled:opacity-60">
                            <svg v-if="saving" class="w-4 h-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z"/></svg>
                            {{ documents.length ? 'Submit claim' : 'Submit without documents' }}
                        </button>
                    </div>
                </div>

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
                    <p class="text-sm text-slate-500 mb-5">Drop a booking confirmation PDF - or a photo of your itinerary - and we'll pull out the flights and passengers and create your claims.</p>

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
import { computed, ref } from 'vue';
import { useRouter } from 'vue-router';
import api from '../api';
import { showProcessing } from '../confirm';

const router = useRouter();

const modes = [
    { key: 'upload', label: 'Upload itinerary', badge: 'Fastest', hint: "Drop your booking PDF or a photo - we read the flights and passengers automatically." },
    { key: 'manual', label: 'Enter details', hint: "No document handy? Answer a few quick questions instead." },
];
const mode = ref('upload');

const disruptions = [
    { value: 'delayed', label: 'Delayed 3h+' },
    { value: 'cancelled', label: 'Cancelled' },
    { value: 'denied_boarding', label: 'Denied boarding' },
    { value: 'downgrade', label: 'Downgraded to a lower class' },
    { value: 'missed_connection', label: 'Missed connection' },
    { value: 'other', label: 'Other' },
];

const steps = ['Flight', 'What happened', 'Passenger & fare', 'Documents'];
const step = ref(1);
const documents = ref([]);

const documentExamples = [
    'Your ticket or booking confirmation',
    'Boarding passes',
    'Any airline communication about the disruption (email, SMS)',
    'Receipts for extra costs (meals, hotel, transport)',
];

const form = ref({
    departure_city: '', departure_airport: '', arrival_city: '', arrival_airport: '',
    airline: '', flight_number: '', flight_date: '', disruption_type: '', disruption_note: '',
    passenger_name: '', booking_reference: '', contact_email: '',
    ticket_price: '', ticket_currency: 'USD',
});

// Gate each step on its required fields so Next can't skip them.
const stepValid = computed(() => {
    const f = form.value;
    if (step.value === 1) return f.departure_airport && f.arrival_airport && f.airline && f.flight_date;
    if (step.value === 2) return f.disruption_type && (f.disruption_type !== 'other' || (f.disruption_note || '').length >= 10);
    if (step.value === 3) return !!f.passenger_name;
    return true;
});

function addDocuments(event) {
    const incoming = Array.from(event.target.files || []);
    documents.value = [...documents.value, ...incoming].slice(0, 6);
    event.target.value = '';
}
const errors = ref({});
const topError = ref('');
const saving = ref(false);

const err = (f) => errors.value[f]?.[0];

async function submit() {
    saving.value = true;
    errors.value = {};
    topError.value = '';
    const done = showProcessing(
        'Checking your eligibility…',
        "We're verifying the flight against tracking records and reviewing your claim under EU261, UK261, APPR and US DOT rules."
    );
    try {
        const payload = new FormData();
        Object.entries(form.value).forEach(([key, value]) => {
            if (value !== '' && value !== null) payload.append(key, value);
        });
        documents.value.forEach((file, i) => payload.append(`documents[${i}]`, file));

        const claim = await api.claims.create(payload);
        router.push({ name: 'claim', params: { id: claim.id } });
    } catch (e) {
        if (e.response?.status === 422) {
            errors.value = e.response.data.errors || {};
            topError.value = 'Please fix the highlighted fields.';
            step.value = 1;
        } else {
            topError.value = 'Something went wrong. Please try again.';
        }
    } finally {
        done();
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
