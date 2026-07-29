<template>
    <div class="flex-1 flex flex-col min-h-0">
        <header class="h-16 bg-white border-b border-slate-200 flex items-center gap-3 px-4 sm:px-8 shrink-0 z-10 sticky top-0">
            <router-link :to="{ name: 'claim', params: { id } }" class="p-2 -ml-2 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors" title="Back">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7 7-7m-7 7h18"/></svg>
            </router-link>
            <h1 class="font-black text-slate-900 text-lg tracking-tight">Confirm Your Claim</h1>
        </header>

        <div class="flex-1 overflow-y-auto overflow-x-hidden bg-slate-100/70">
            <div class="max-w-3xl mx-auto px-4 sm:px-8 py-8">
                <div v-if="loading" class="text-sm text-slate-400 py-10 text-center">Loading…</div>
                <div v-else-if="error" class="bg-rose-50 border border-rose-100 text-rose-700 px-4 py-3 rounded-xl text-sm">{{ error }}</div>

                <template v-else-if="c">
                    <!-- Progress: confirm → sign → filed -->
                    <div class="flex items-center gap-2 mb-6 text-xs font-bold">
                        <span class="flex items-center gap-1.5 text-slate-900"><span class="w-6 h-6 rounded-full bg-slate-900 text-white flex items-center justify-center">1</span> Confirm</span>
                        <span class="h-px flex-1 bg-slate-300"></span>
                        <span class="flex items-center gap-1.5 text-slate-400"><span class="w-6 h-6 rounded-full bg-slate-200 flex items-center justify-center">2</span> Sign</span>
                        <span class="h-px flex-1 bg-slate-300"></span>
                        <span class="flex items-center gap-1.5 text-slate-400"><span class="w-6 h-6 rounded-full bg-slate-200 flex items-center justify-center">3</span> Filed</span>
                    </div>

                    <div class="space-y-5">
                        <!-- 1. Flight summary -->
                        <section class="bg-white rounded-2xl ring-1 ring-slate-900/5 p-6">
                            <div class="flex items-center gap-3 mb-4">
                                <span class="w-11 h-11 rounded-xl bg-slate-900 text-white flex items-center justify-center font-black text-sm">{{ airlineMark }}</span>
                                <div class="min-w-0">
                                    <div class="font-black text-slate-900">{{ c.flight.airline || 'Airline' }} <span class="text-slate-400 font-bold">{{ c.flight.flight_number }}</span></div>
                                    <div class="text-xs text-slate-400">{{ c.flight.travel_date }}<template v-if="c.flight.booking_reference"> - booking {{ c.flight.booking_reference }}</template></div>
                                </div>
                            </div>

                            <div class="flex items-center gap-3 mb-4">
                                <div class="min-w-0">
                                    <div class="text-2xl font-black text-slate-900">{{ c.flight.departure_airport }}</div>
                                    <div class="text-xs text-slate-400 truncate">{{ c.flight.departure_name }}</div>
                                </div>
                                <span class="flex-1 flex items-center min-w-[40px]">
                                    <span class="h-px flex-1 bg-slate-200"></span>
                                    <svg class="w-4 h-4 text-slate-300 mx-1 shrink-0" fill="currentColor" viewBox="0 0 24 24"><path d="M21 16v-2l-8-5V3.5a1.5 1.5 0 00-3 0V9l-8 5v2l8-2.5V19l-2 1.5V22l3.5-1 3.5 1v-1.5L13 19v-5.5z"/></svg>
                                    <span class="h-px flex-1 bg-slate-200"></span>
                                </span>
                                <div class="min-w-0 text-right">
                                    <div class="text-2xl font-black text-slate-900">{{ c.flight.arrival_airport }}</div>
                                    <div class="text-xs text-slate-400 truncate">{{ c.flight.arrival_name }}</div>
                                </div>
                            </div>

                            <div v-if="hasTimes" class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-4">
                                <div v-for="t in times" :key="t.label" class="rounded-xl bg-slate-50 p-3">
                                    <div class="text-[10px] uppercase tracking-wider font-bold text-slate-400">{{ t.label }}</div>
                                    <div class="text-sm font-bold mt-0.5" :class="t.late ? 'text-rose-600' : 'text-slate-800'">{{ t.value || '-' }}</div>
                                </div>
                            </div>

                            <div v-if="c.multi_passenger_locked" class="flex flex-col sm:flex-row sm:items-center gap-3 bg-slate-900 text-white rounded-2xl px-5 py-4 mb-4">
                                <div class="flex-1 text-sm">
                                    <span class="font-bold text-amber-400">★ This booking has {{ c.passengers?.length }} passengers.</span>
                                    Family claims are part of Unjamm Plus - upgrade to claim for everyone in one go.
                                </div>
                                <router-link :to="{ name: 'plus' }" class="shrink-0 bg-emerald-400 hover:bg-emerald-300 text-slate-950 text-sm font-bold px-5 py-2.5 rounded-xl transition-colors text-center">
                                    See Unjamm Plus
                                </router-link>
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-[11px] uppercase tracking-wider font-bold text-slate-400 mr-1">Passengers</span>
                                <span v-for="p in c.passengers" :key="p.name" class="inline-flex items-center gap-1.5 bg-slate-100 text-slate-700 text-xs font-bold px-3 py-1 rounded-full">
                                    {{ p.name }}
                                    <span v-if="p.minor" class="text-[10px] font-bold text-violet-600 bg-violet-100 px-1.5 rounded-full">minor</span>
                                </span>
                            </div>
                        </section>

                        <!-- 2. What happened -->
                        <section class="bg-white rounded-2xl ring-1 ring-slate-900/5 p-6">
                            <p class="text-[11px] uppercase tracking-wider font-bold text-slate-400 mb-3">What happened</p>
                            <div class="flex items-center gap-3 mb-3">
                                <span class="w-10 h-10 rounded-xl bg-rose-100 text-rose-600 flex items-center justify-center shrink-0">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                                </span>
                                <div>
                                    <div class="font-black text-slate-900">{{ c.disruption.headline }}</div>
                                    <div v-if="c.disruption.verified" class="flex items-center gap-1 text-xs text-emerald-600 font-bold mt-0.5">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        Verified against live flight-tracking records
                                    </div>
                                </div>
                            </div>
                            <ol class="space-y-1.5">
                                <li v-for="(ev, i) in c.disruption.timeline" :key="i" class="flex items-center gap-2 text-sm text-slate-600">
                                    <span class="w-1.5 h-1.5 rounded-full shrink-0" :class="ev.status === 'done' ? 'bg-emerald-500' : 'bg-amber-400'"></span>
                                    {{ ev.label }} <span class="text-slate-300 text-xs">{{ ev.date }}</span>
                                </li>
                            </ol>
                        </section>

                        <!-- 3. Why you qualify -->
                        <section class="bg-white rounded-2xl ring-1 ring-slate-900/5 p-6">
                            <p class="text-[11px] uppercase tracking-wider font-bold text-slate-400 mb-3">Why you qualify</p>
                            <div class="flex flex-wrap gap-2 mb-3">
                                <span class="bg-slate-900 text-white text-xs font-bold px-3 py-1 rounded-full">{{ c.eligibility.regulation }}</span>
                                <span class="bg-slate-100 text-slate-700 text-xs font-bold px-3 py-1 rounded-full">{{ c.eligibility.article }}</span>
                                <span class="bg-slate-100 text-slate-700 text-xs font-bold px-3 py-1 rounded-full">{{ c.eligibility.jurisdiction }}</span>
                            </div>
                            <p class="text-sm text-slate-600">{{ c.eligibility.reason }}</p>
                        </section>

                        <!-- 4. Compensation breakdown -->
                        <section v-if="c.payout" class="bg-white rounded-2xl ring-1 ring-slate-900/5 p-6">
                            <p class="text-[11px] uppercase tracking-wider font-bold text-slate-400 mb-3">Your compensation</p>
                            <div class="divide-y divide-slate-100 text-sm">
                                <div v-for="(p, i) in c.passengers" :key="i" class="flex items-center justify-between gap-3 py-2">
                                    <template v-if="editRow === i">
                                        <div class="flex-1 min-w-0 space-y-2">
                                            <div class="flex items-center gap-2">
                                                <input v-model="rowDraft.name" type="text" maxlength="190" @keyup.enter="saveRow(i)" @keyup.esc="editRow = null"
                                                       class="flex-1 min-w-0 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-900/10">
                                                <button type="button" @click="saveRow(i)" :disabled="savingRow"
                                                        class="text-xs font-bold bg-slate-900 hover:bg-slate-800 disabled:opacity-40 text-white px-4 py-2.5 rounded-lg shrink-0 transition-colors">
                                                    {{ savingRow ? 'Saving…' : 'Save' }}
                                                </button>
                                                <button type="button" @click="editRow = null" class="text-xs font-bold text-slate-400 hover:text-slate-600 px-1 shrink-0">Cancel</button>
                                            </div>
                                            <label class="inline-flex items-center gap-2 text-xs font-bold select-none cursor-pointer rounded-full px-3 py-1.5 ring-1 transition-colors"
                                                   :class="rowDraft.minor ? 'bg-violet-100 text-violet-700 ring-violet-200' : 'bg-slate-50 text-slate-500 ring-slate-200 hover:ring-slate-300'">
                                                <input v-model="rowDraft.minor" type="checkbox" class="w-3.5 h-3.5 rounded border-slate-300 text-violet-600 focus:ring-violet-500">
                                                Under 18 - a guardian signs for them
                                            </label>
                                        </div>
                                    </template>
                                    <template v-else>
                                        <span class="text-slate-600 inline-flex items-center gap-1.5 min-w-0">
                                            <span class="truncate">{{ p.name }}</span>
                                            <span v-if="p.minor" class="text-[10px] font-bold text-violet-600 bg-violet-100 px-1.5 rounded-full shrink-0">minor</span>
                                            <button type="button" @click="startRowEdit(i)" title="Correct this passenger"
                                                    class="text-slate-300 hover:text-blue-600 transition-colors shrink-0">
                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 3.5a2.1 2.1 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                                            </button>
                                        </span>
                                        <span class="font-bold text-slate-800 shrink-0">{{ c.payout.currency }} {{ c.payout.per_passenger }}</span>
                                    </template>
                                </div>
                                <p v-if="namesError" class="text-xs font-bold text-rose-600 py-1.5">{{ namesError }}</p>
                                <div class="flex items-center justify-between py-2 font-bold text-slate-900">
                                    <span>Total compensation</span><span>{{ c.payout.currency }} {{ c.payout.gross }}</span>
                                </div>
                                <div class="flex items-center justify-between py-2 text-slate-500">
                                    <span>Success fee ({{ c.payout.fee_percent }}%) - only if we win</span><span>- {{ c.payout.currency }} {{ c.payout.fee }}</span>
                                </div>
                            </div>
                            <div class="mt-3 rounded-xl bg-emerald-50 px-4 py-3 flex items-center justify-between">
                                <span class="text-sm font-bold text-emerald-700">Estimated payout to you</span>
                                <span class="text-2xl font-black text-emerald-700">{{ c.payout.currency }} {{ c.payout.net }}</span>
                            </div>
                            <p class="text-xs text-slate-400 mt-3">Amounts are set by the regulation - they do not depend on your ticket price. No win, no fee.</p>
                        </section>

                        <!-- 5. What happens next -->
                        <section class="bg-white rounded-2xl ring-1 ring-slate-900/5 p-6">
                            <p class="text-[11px] uppercase tracking-wider font-bold text-slate-400 mb-4">What happens next</p>
                            <ol class="relative space-y-5">
                                <li v-for="(step, i) in nextSteps" :key="i" class="flex gap-3">
                                    <div class="flex flex-col items-center">
                                        <span class="w-7 h-7 rounded-full bg-slate-900 text-white text-xs font-black flex items-center justify-center shrink-0">{{ i + 1 }}</span>
                                        <span v-if="i < nextSteps.length - 1" class="w-px flex-1 bg-slate-200 my-1"></span>
                                    </div>
                                    <div class="pb-1">
                                        <div class="font-bold text-slate-800 text-sm">{{ step.title }}</div>
                                        <div class="text-xs text-slate-400 mt-0.5">{{ step.detail }}</div>
                                    </div>
                                </li>
                            </ol>
                        </section>

                        <!-- 6. Social proof -->
                        <section class="bg-white rounded-2xl ring-1 ring-slate-900/5 p-6">
                            <div class="grid grid-cols-2 gap-3 mb-4">
                                <div class="rounded-xl bg-slate-50 p-4 text-center">
                                    <div class="text-2xl font-black text-slate-900">{{ c.social.claims_won }}</div>
                                    <div class="text-xs text-slate-400 font-bold mt-0.5">successful claims</div>
                                </div>
                                <div class="rounded-xl bg-slate-50 p-4 text-center">
                                    <div class="text-2xl font-black text-slate-900">{{ c.social.recovered }}</div>
                                    <div class="text-xs text-slate-400 font-bold mt-0.5">recovered for travellers</div>
                                </div>
                            </div>
                            <div v-if="(c.social.testimonials || []).length" class="space-y-2">
                                <blockquote v-for="t in c.social.testimonials" :key="t.name" class="text-sm text-slate-600 bg-slate-50 rounded-xl px-4 py-3">
                                    "{{ t.story }}" <span class="text-xs font-bold text-slate-400">- {{ t.name }}</span>
                                </blockquote>
                            </div>
                            <div class="flex items-center gap-4 mt-4 text-[11px] font-bold text-slate-400">
                                <span class="flex items-center gap-1"><svg class="w-3.5 h-3.5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4M12 3l7 4v5c0 4.5-3 8-7 9-4-1-7-4.5-7-9V7l7-4z"/></svg> Secure &amp; encrypted</span>
                                <span class="flex items-center gap-1"><svg class="w-3.5 h-3.5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg> No win, no fee</span>
                            </div>
                        </section>

                        <!-- 7. Unjamm Plus (admin toggle: Settings -> Website) -->
                        <section v-if="c.plus_promo_enabled" class="bg-white rounded-2xl ring-1 ring-slate-900/5 p-6">
                            <p class="text-[11px] uppercase tracking-wider font-bold text-slate-400 mb-3">Choose how we process it</p>
                            <div class="grid sm:grid-cols-2 gap-3">
                                <button type="button" @click="plus = false"
                                        class="text-left rounded-xl p-4 ring-2 transition-all"
                                        :class="!plus ? 'ring-slate-900 bg-slate-50' : 'ring-slate-200 hover:ring-slate-300'">
                                    <div class="font-black text-slate-900 mb-1">Free</div>
                                    <ul class="text-xs text-slate-500 space-y-1">
                                        <li>Standard processing queue</li>
                                        <li>No win, no fee</li>
                                    </ul>
                                </button>
                                <button type="button" @click="plus = true"
                                        class="text-left rounded-xl p-4 ring-2 transition-all relative"
                                        :class="plus ? 'ring-slate-900 bg-slate-50' : 'ring-slate-200 hover:ring-slate-300'">
                                    <span class="absolute top-3 right-3 bg-violet-100 text-violet-700 text-[10px] font-black px-2 py-0.5 rounded-full">PLUS</span>
                                    <div class="font-black text-slate-900 mb-1">Unjamm Plus</div>
                                    <ul class="text-xs text-slate-500 space-y-1">
                                        <li>Priority queue</li>
                                        <li>Next-business-day payout</li>
                                        <li>Family / multi-passenger support</li>
                                        <li>Faster support</li>
                                    </ul>
                                </button>
                            </div>
                            <p class="text-xs text-slate-400 mt-2">You can continue on Free - upgrading is entirely optional.</p>
                        </section>

                        <!-- 8. Consent -->
                        <section class="bg-white rounded-2xl ring-1 ring-slate-900/5 p-6">
                            <p class="text-[11px] uppercase tracking-wider font-bold text-slate-400 mb-3">Your consent</p>
                            <label v-for="item in consentItems" :key="item.key" class="flex items-start gap-3 py-2 cursor-pointer select-none">
                                <input type="checkbox" v-model="consents[item.key]" class="mt-0.5 w-4 h-4 rounded border-slate-300 text-slate-900 focus:ring-slate-900">
                                <span class="text-sm text-slate-700" v-html="item.label"></span>
                            </label>
                        </section>

                        <!-- 9. CTA - gated claims upgrade instead of failing on submit -->
                        <router-link v-if="upgradeRequired" :to="{ name: 'plus' }"
                                     class="flex items-center justify-center gap-2 w-full bg-slate-900 hover:bg-slate-800 text-white font-black py-4 rounded-2xl text-base transition-colors shadow-lg shadow-slate-900/10">
                            <span class="text-amber-400">★</span> Upgrade to Unjamm Plus to create this claim
                        </router-link>
                        <button v-else @click="submit" :disabled="!allConsented || submitting"
                                class="w-full bg-slate-900 hover:bg-slate-800 disabled:opacity-40 disabled:cursor-not-allowed text-white font-black py-4 rounded-2xl text-base transition-colors shadow-lg shadow-slate-900/10">
                            {{ submitting ? 'Saving…' : 'Confirm & Continue' }}
                        </button>

                        <p v-if="submitError" class="flex items-start gap-2 bg-rose-50 border border-rose-100 text-rose-700 rounded-xl px-4 py-3 text-sm">
                            <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 8v5M12 16h.01"/></svg>
                            <span>{{ submitError }}</span>
                        </p>

                        <p class="text-center text-xs text-slate-400 pb-6">
                            {{ upgradeRequired ? 'Your claim is saved - it will be filed as soon as you upgrade.' : 'Next: sign your authorisation documents - takes under a minute.' }}
                        </p>
                    </div>
                </template>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import api from '../api';

const props = defineProps({ id: { type: String, required: true } });

// The claim needs Plus - either we knew up front (multi-passenger) or the
// server told us when we tried to confirm.
const gated = ref(false);
const submitError = ref('');
const router = useRouter();

const c = ref(null);
const loading = ref(true);
const error = ref('');
const submitting = ref(false);
const plus = ref(false);
const consents = ref({ accuracy: false, authorization: false, terms: false, privacy: false });
const editRow = ref(null);
const rowDraft = ref({ name: '', minor: false });
const namesError = ref('');
const savingRow = ref(false);

function startRowEdit(i) {
    editRow.value = i;
    rowDraft.value = { name: c.value.passengers[i].name, minor: !!c.value.passengers[i].minor };
    namesError.value = '';
}

async function saveRow(i) {
    const name = rowDraft.value.name.trim();
    if (name.length < 2) {
        namesError.value = 'The passenger needs a name.';
        return;
    }
    savingRow.value = true;
    try {
        const passengers = c.value.passengers.map((p, idx) => idx === i
            ? { name, minor: rowDraft.value.minor }
            : { name: p.name, minor: !!p.minor });
        await api.claims.updatePassengers(props.id, passengers);
        c.value = await api.claims.confirmation(props.id);
        editRow.value = null;
    } catch (e) {
        namesError.value = e.response?.data?.message || 'Could not save the passenger. Please try again.';
    } finally {
        savingRow.value = false;
    }
}

const consentItems = [
    { key: 'accuracy', label: 'I confirm the flight information above is correct.' },
    { key: 'authorization', label: 'I authorize Unjamm to represent me (and the passengers listed) for this claim.' },
    { key: 'terms', label: 'I agree to the <a href="/terms" target="_blank" class="underline font-bold">Terms &amp; Conditions</a>.' },
    { key: 'privacy', label: 'I agree to the <a href="/privacy" target="_blank" class="underline font-bold">Privacy Policy</a>.' },
];

const nextSteps = [
    { title: 'Claim submitted', detail: 'We prepare and file your claim with the airline.' },
    { title: 'Waiting for the airline\'s response', detail: 'Airlines typically respond within 4-8 weeks.' },
    { title: 'Escalation if needed', detail: 'No response within the required period? We can escalate to the enforcement body.' },
    { title: 'You get paid', detail: 'After a successful settlement we transfer your payout, minus the success fee.' },
];

const allConsented = computed(() => consentItems.every((i) => consents.value[i.key]));

// Known before submitting (multi-passenger booking) or learned from a 402.
const upgradeRequired = computed(() => gated.value || !!c.value?.multi_passenger_locked);

const airlineMark = computed(() => {
    const name = c.value?.flight?.airline || '';
    const words = name.split(/\s+/).filter(Boolean);
    return (words.length >= 2 ? words[0][0] + words[1][0] : name.slice(0, 2)).toUpperCase() || '✈';
});

const times = computed(() => c.value ? [
    { label: 'Scheduled departure', value: c.value.flight.scheduled_departure },
    { label: 'Actual departure', value: c.value.flight.actual_departure, late: true },
    { label: 'Scheduled arrival', value: c.value.flight.scheduled_arrival },
    { label: 'Actual arrival', value: c.value.flight.actual_arrival, late: true },
] : []);
const hasTimes = computed(() => times.value.some((t) => t.value));

async function submit() {
    if (!allConsented.value) return;
    submitting.value = true;
    try {
        await api.claims.confirm(props.id, { consents: consents.value, plus: plus.value });
        router.push({ name: 'claim-sign', params: { id: props.id } });
    } catch (e) {
        // A 402 means this claim needs Plus: turn the CTA into the upgrade
        // action rather than interrupting with a dialog.
        if (e.response?.status === 402) {
            gated.value = true;
            submitError.value = '';
        } else {
            submitError.value = e.response?.data?.message || 'Could not save your confirmation. Please try again.';
        }
    } finally {
        submitting.value = false;
    }
}

onMounted(async () => {
    try {
        c.value = await api.claims.confirmation(props.id);
        plus.value = !!c.value.plus_selected;
        if (c.value.confirmed) {
            router.replace({ name: 'claim-sign', params: { id: props.id } });
        }
    } catch (e) {
        error.value = e.response?.data?.message || 'This claim is not ready for confirmation yet.';
    } finally {
        loading.value = false;
    }
});
</script>
