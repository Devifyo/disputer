<template>
    <div class="max-w-3xl mx-auto px-4 sm:px-6 py-8">
        <div v-if="loading" class="text-center py-20 text-slate-400 text-sm">Loading…</div>

        <template v-else>
            <!-- Checkout outcome banners -->
            <div v-if="checkoutState === 'success' && !billing.is_plus"
                 class="flex items-center gap-3 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-2xl px-5 py-3.5 text-sm mb-6">
                <svg class="w-5 h-5 shrink-0 animate-spin text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                    <path d="M21 12a9 9 0 1 1-6.219-8.56"/>
                </svg>
                <span><span class="font-bold">Payment received</span> - activating your membership, just a moment…</span>
            </div>
            <div v-else-if="checkoutState === 'cancelled'" class="bg-slate-100 border border-slate-200 text-slate-600 rounded-2xl px-5 py-4 text-sm mb-6">
                Checkout cancelled - no charge was made.
            </div>

            <!-- Already a member -->
            <div v-if="billing.is_plus" class="space-y-5">
                <div class="rounded-3xl bg-slate-900 text-white overflow-hidden shadow-xl shadow-slate-900/10">
                    <div class="px-6 sm:px-8 pt-8 pb-7 text-center">
                        <span class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-white/10 text-amber-400 text-2xl ring-1 ring-white/15">★</span>
                        <h1 class="text-2xl font-bold tracking-tight mt-4">
                            {{ justUpgraded ? "Welcome to Unjamm Plus" : "You're an Unjamm Plus member" }}
                        </h1>
                        <p class="text-sm text-slate-300 mt-1.5">
                            {{ justUpgraded ? 'Your membership is active - every perk below is switched on.' : 'Thanks for flying with us in your corner.' }}
                        </p>
                    </div>
                    <dl class="grid grid-cols-3 divide-x divide-white/10 border-t border-white/10 text-center">
                        <div class="px-3 py-4">
                            <dt class="text-[10px] uppercase tracking-wider font-bold text-slate-400">Plan</dt>
                            <dd class="text-sm font-bold mt-1 truncate">{{ billing.subscription?.plan || 'Unjamm Plus' }}</dd>
                        </div>
                        <div class="px-3 py-4">
                            <dt class="text-[10px] uppercase tracking-wider font-bold text-slate-400">Status</dt>
                            <dd class="text-sm font-bold mt-1 capitalize text-emerald-400">{{ billing.subscription?.status || 'active' }}</dd>
                        </div>
                        <div class="px-3 py-4">
                            <dt class="text-[10px] uppercase tracking-wider font-bold text-slate-400">
                                {{ billing.subscription?.cancelling ? 'Access until' : 'Renews' }}
                            </dt>
                            <dd class="text-sm font-bold mt-1">{{ billing.subscription?.renews_at || '-' }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="bg-white rounded-2xl ring-1 ring-slate-900/5 p-6 sm:p-7">
                    <p class="text-[11px] uppercase tracking-wider font-bold text-slate-400 mb-3">What your membership includes</p>
                    <ul class="grid sm:grid-cols-2 gap-x-6 gap-y-2.5">
                        <li v-for="item in plusItems" :key="item" class="flex items-start gap-2.5 text-[13px] text-slate-700">
                            <span class="mt-0.5 w-4 h-4 rounded-full bg-emerald-100 text-emerald-600 text-[10px] font-black flex items-center justify-center shrink-0">✓</span>
                            {{ item }}
                        </li>
                    </ul>
                </div>

                <div class="flex flex-wrap items-center gap-3 bg-white rounded-2xl ring-1 ring-slate-900/5 p-5 sm:px-7">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-bold text-slate-900">Billing</p>
                        <p class="text-[13px] text-slate-500">Cards, invoices and cancellation are handled securely by Stripe.</p>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <router-link :to="{ name: 'claims' }" class="text-sm font-bold text-slate-500 hover:text-slate-800 px-4 py-3">My claims</router-link>
                        <button :disabled="redirecting" @click="openPortal"
                                class="bg-slate-900 hover:bg-slate-800 disabled:opacity-60 text-white text-sm font-bold px-6 py-3 rounded-xl transition-colors">
                            {{ redirecting ? 'Opening…' : 'Manage billing' }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- System off: nothing to sell -->
            <div v-else-if="!billing.enabled" class="bg-white rounded-2xl ring-1 ring-slate-900/5 p-6 sm:p-8 text-center">
                <h1 class="text-xl font-bold text-slate-900 mb-2">Everything is included right now</h1>
                <p class="text-sm text-slate-500">All Unjamm features are currently free while we're in our launch period. No membership needed.</p>
                <router-link :to="{ name: 'claims' }" class="inline-block mt-5 bg-slate-900 hover:bg-slate-800 text-white text-sm font-bold px-6 py-3 rounded-xl transition-colors">Back to my claims</router-link>
            </div>

            <!-- Upgrade -->
            <template v-else>
                <div class="text-center mb-8">
                    <span class="inline-flex items-center gap-2 bg-slate-900 text-amber-400 text-[11px] font-black uppercase tracking-wider px-3.5 py-1.5 rounded-full mb-4">
                        <span class="text-sm leading-none">★</span> Unjamm Plus
                    </span>
                    <h1 class="text-3xl sm:text-4xl font-bold text-slate-900 tracking-tight">Never fly unprotected again</h1>
                    <p class="text-sm text-slate-500 mt-3 max-w-md mx-auto">Priority handling for every disruption. Cancel anytime - billing is handled securely by Stripe.</p>

                    <div v-if="hasAnnual" class="inline-flex items-center gap-1 bg-slate-100 rounded-xl p-1 mt-6">
                        <button v-for="opt in ['monthly', 'annual']" :key="opt" @click="interval = opt"
                                class="relative px-5 py-2 rounded-lg text-sm font-bold transition-all"
                                :class="interval === opt ? 'bg-white text-slate-900 shadow' : 'text-slate-500 hover:text-slate-700'">
                            {{ opt === 'monthly' ? 'Monthly' : 'Annual' }}
                            <span v-if="opt === 'annual' && savingsPercent" class="ml-1.5 text-[10px] font-black text-emerald-600">-{{ savingsPercent }}%</span>
                        </button>
                    </div>
                </div>

                <div class="grid md:grid-cols-2 gap-5 items-stretch max-w-3xl mx-auto">
                    <!-- Free: where they are today -->
                    <div class="bg-white rounded-2xl ring-1 ring-slate-900/5 p-6 sm:p-7 flex flex-col">
                        <h2 class="text-base font-bold text-slate-900">Free</h2>
                        <p class="text-[13px] text-slate-500 mt-1">What you have now</p>
                        <div class="mt-4 mb-5">
                            <span class="text-3xl font-bold text-slate-900">{{ symbol(billing.plans[0]) }}0</span>
                            <span class="text-xs text-slate-400 ml-1">forever</span>
                        </div>
                        <ul class="space-y-3 flex-1">
                            <li v-for="item in freeItems" :key="item" class="flex items-start gap-2.5 text-[13px] text-slate-600">
                                <span class="mt-0.5 w-4 h-4 rounded-full bg-slate-100 text-slate-400 text-[10px] font-black flex items-center justify-center shrink-0">✓</span>
                                {{ item }}
                            </li>
                        </ul>
                        <p class="text-[11px] text-slate-400 mt-5 mb-4">Everything else - claims, monitoring, automatic filing - is part of Plus.</p>
                        <div class="text-center text-[12px] font-bold text-slate-400 border border-slate-200 rounded-xl py-3">Your current plan</div>
                    </div>

                    <!-- Plus: the upgrade -->
                    <div v-for="plan in billing.plans" :key="plan.id"
                         class="relative bg-slate-900 rounded-2xl p-6 sm:p-7 flex flex-col text-white shadow-2xl shadow-slate-900/25 ring-1 ring-slate-900 overflow-hidden">
                        <div class="absolute -top-16 -right-16 w-48 h-48 rounded-full bg-emerald-400/10 blur-2xl pointer-events-none"></div>

                        <span class="absolute top-5 right-5 bg-emerald-400/15 text-emerald-300 text-[10px] font-black uppercase tracking-wider px-2.5 py-1 rounded-full ring-1 ring-emerald-400/30">Recommended</span>

                        <h2 class="text-base font-bold">{{ plan.name }}</h2>
                        <p class="text-[13px] text-slate-400 mt-1">Everything in Free, and:</p>
                        <div class="mt-4 mb-1">
                            <span class="text-4xl font-bold tracking-tight">{{ symbol(plan) }}{{ price(plan) }}</span>
                            <span class="text-xs text-slate-400 ml-1.5">/ {{ interval === 'annual' ? 'year' : 'month' }}</span>
                        </div>
                        <p class="text-[11px] mb-5" :class="interval === 'annual' ? 'text-emerald-400 font-bold' : 'text-slate-500'">
                            <template v-if="interval === 'annual' && monthlyEquivalent">That's {{ symbol(plan) }}{{ monthlyEquivalent }} a month</template>
                            <template v-else-if="hasAnnual">or {{ symbol(plan) }}{{ Number(plan.annual_price).toFixed(0) }} a year</template>
                            <template v-else>Billed monthly - cancel anytime</template>
                        </p>
                        <ul class="space-y-3 flex-1">
                            <li v-for="perk in plan.perks" :key="perk" class="flex items-start gap-2.5 text-[13px] text-slate-100">
                                <span class="mt-0.5 w-4 h-4 rounded-full bg-emerald-400/20 text-emerald-300 text-[10px] font-black flex items-center justify-center shrink-0">✓</span>
                                {{ perk }}
                            </li>
                        </ul>
                        <p v-if="plan.trial_days" class="text-[12px] font-bold text-emerald-300 mt-4">Try it free for {{ plan.trial_days }} days</p>
                        <button :disabled="redirecting || !available(plan)" @click="subscribe(plan)"
                                class="mt-5 w-full bg-emerald-400 hover:bg-emerald-300 disabled:opacity-60 text-slate-950 text-sm font-bold px-6 py-3.5 rounded-xl transition-all active:scale-[.98]">
                            {{ redirecting ? 'Redirecting to Stripe…' : (available(plan) ? 'Upgrade to ' + plan.name : 'Coming soon') }}
                        </button>
                        <p class="text-center text-[10px] text-slate-500 mt-3">Secure checkout by Stripe · Cancel anytime</p>
                    </div>
                </div>
                <p v-if="error" class="text-center text-sm font-bold text-rose-600 mt-4">{{ error }}</p>
            </template>
        </template>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import api from '../api';

const loading = ref(true);
const billing = ref({ enabled: false, is_plus: false, plans: [] });
const interval = ref('monthly');
const redirecting = ref(false);
const error = ref('');
const checkoutState = ref(new URLSearchParams(window.location.search).get('checkout'));
let poller = null;

function price(plan) {
    const value = interval.value === 'annual' ? plan.annual_price : plan.monthly_price;
    return value === null ? '-' : Number(value).toFixed(2);
}

// "C$9.99" reads better than "CAD 9.99" on a price tag.
const SYMBOLS = { CAD: 'C$', USD: '$', EUR: '€', GBP: '£', AUD: 'A$' };

function symbol(plan) {
    const currency = plan?.currency || 'CAD';
    return SYMBOLS[currency] || currency + ' ';
}

const hasAnnual = computed(() => (billing.value.plans || []).some((plan) => plan.annual_available && plan.annual_price));

// "-18%" chip on the annual toggle, from the real plan prices.
const savingsPercent = computed(() => {
    const plan = billing.value.plans?.[0];
    if (!plan?.monthly_price || !plan?.annual_price) return null;
    const yearAtMonthly = plan.monthly_price * 12;
    return Math.round(((yearAtMonthly - plan.annual_price) / yearAtMonthly) * 100) || null;
});

const monthlyEquivalent = computed(() => {
    const plan = billing.value.plans?.[0];
    return plan?.annual_price ? (plan.annual_price / 12).toFixed(2) : null;
});

// The Free column lists only what free accounts actually get - the always-on
// basics plus any catalogue feature the admin has left ungated. No
// strikethrough clutter: what is missing is simply not listed.
const freeItems = computed(() => {
    const locked = billing.value.locked || [];
    const catalogue = [
        { key: 'flight_claims', label: 'Compensation claims' },
        { key: 'flight_monitoring', label: 'Live flight monitoring' },
        { key: 'priority_processing', label: 'Priority filing queue' },
        { key: 'multi_passenger', label: 'Multi-passenger / family accounts' },
        { key: 'auto_filing', label: 'Fully automatic claim filing' },
    ];
    return [
        'Flight status lookups',
        'Expense receipt storage',
        ...catalogue.filter((f) => !locked.includes(f.key)).map((f) => f.label),
    ];
});

// A member's perks: the features the admin has actually gated (those are the
// ones membership unlocks), always with the headline promises.
const plusItems = computed(() => {
    const locked = billing.value.locked || [];
    const labels = {
        flight_claims: 'Compensation claims',
        flight_monitoring: 'Live flight monitoring',
        priority_processing: 'Priority filing queue',
        multi_passenger: 'Multi-passenger / family accounts',
        auto_filing: 'Fully automatic claim filing',
        ai_claim_drafting: 'AI-written claim letters',
        ai_follow_up_drafts: 'AI follow-ups to the airline',
        ai_regulator_drafts: 'AI regulator complaints',
    };
    const unlocked = locked.map((key) => labels[key]).filter(Boolean);

    return unlocked.length ? unlocked : ['Priority filing queue', 'Multi-passenger / family accounts', 'Fully automatic claim filing'];
});

// Landed here straight from Stripe: greet them rather than just informing.
const justUpgraded = computed(() => checkoutState.value === 'success');

function available(plan) {
    return interval.value === 'annual' ? plan.annual_available : plan.monthly_available;
}

async function load() {
    billing.value = await api.billing.overview();
    loading.value = false;
}

async function subscribe(plan) {
    redirecting.value = true;
    error.value = '';
    try {
        const { url } = await api.billing.checkout(plan.id, interval.value);
        window.location.href = url;
    } catch (e) {
        error.value = e.response?.data?.message || 'Could not start checkout. Please try again.';
        redirecting.value = false;
    }
}

async function openPortal() {
    redirecting.value = true;
    try {
        const { url } = await api.billing.portal();
        window.location.href = url;
    } catch (e) {
        error.value = e.response?.data?.message || 'Could not open the billing portal.';
        redirecting.value = false;
    }
}

onMounted(async () => {
    await load();

    // After checkout, the webhook activates the membership moments later -
    // poll briefly so the page flips to "member" without a manual refresh.
    if (checkoutState.value === 'success' && !billing.value.is_plus) {
        let attempts = 0;
        poller = setInterval(async () => {
            attempts += 1;
            await load();
            if (billing.value.is_plus || attempts >= 10) {
                clearInterval(poller);
                poller = null;
            }
        }, 3000);
    }
});

onBeforeUnmount(() => poller && clearInterval(poller));
</script>
