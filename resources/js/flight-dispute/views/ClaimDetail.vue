<template>
    <div class="flex-1 flex flex-col min-h-0">
        <header class="h-16 bg-white border-b border-slate-200 flex items-center gap-3 px-4 sm:px-8 shrink-0 z-10 sticky top-0">
            <router-link :to="{ name: 'claims' }" class="p-2 -ml-2 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors" title="Back">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7 7-7m-7 7h18"/></svg>
            </router-link>
            <h1 class="font-black text-slate-900 text-lg tracking-tight">Your Claim</h1>
        </header>

        <div class="flex-1 overflow-y-auto overflow-x-hidden bg-slate-100/70">
            <div class="max-w-[1400px] mx-auto px-4 sm:px-8 py-8">

                <div v-if="loading" class="text-sm text-slate-400 py-10 text-center">Loading…</div>
                <div v-else-if="error" class="bg-rose-50 border border-rose-100 text-rose-700 px-4 py-3 rounded-xl text-sm">{{ error }}</div>

                <div v-else-if="claim" class="grid lg:grid-cols-3 gap-6 items-start">
                    <!-- LEFT -->
                    <div class="lg:col-span-2 min-w-0 space-y-5">
                        <!-- Hero -->
                        <div class="relative overflow-hidden rounded-2xl p-6 sm:p-7 text-white" style="background:linear-gradient(105deg,#064e3b 0%,#059669 55%,#10b981 78%,#2563eb 130%);">
                            <div class="flex flex-col xl:flex-row xl:items-center gap-5">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between text-[11px] font-semibold text-white/70 uppercase tracking-wide mb-1">
                                        <span class="truncate">{{ claim.departure_city || claim.departure_airport }}</span>
                                        <span class="truncate text-right">{{ claim.arrival_city || claim.arrival_airport }}</span>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <span class="text-3xl sm:text-4xl font-black tracking-tight">{{ claim.departure_airport || '-' }}</span>
                                        <span class="flex-1 flex items-center">
                                            <span class="h-px flex-1 bg-white/40"></span>
                                            <span class="w-9 h-9 rounded-full border border-white/50 flex items-center justify-center mx-1 shrink-0">
                                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M21 16v-2l-8-5V3.5a1.5 1.5 0 00-3 0V9l-8 5v2l8-2.5V19l-2 1.5V22l3.5-1 3.5 1v-1.5L13 19v-5.5z"/></svg>
                                            </span>
                                            <span class="h-px flex-1 bg-white/40"></span>
                                        </span>
                                        <span class="text-3xl sm:text-4xl font-black tracking-tight">{{ claim.arrival_airport || '-' }}</span>
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

                        <!-- Missing info banner -->
                        <div v-if="(claim.missing || []).length" class="bg-rose-50 border border-rose-100 rounded-xl px-4 py-3 text-sm">
                            <div class="flex items-center gap-2 font-bold text-rose-700 mb-1.5">
                                <span class="relative flex w-2.5 h-2.5">
                                    <span class="absolute inline-flex w-full h-full rounded-full bg-rose-400 opacity-75 animate-ping"></span>
                                    <span class="relative inline-flex w-2.5 h-2.5 rounded-full bg-rose-500"></span>
                                </span>
                                Your claim is missing information
                            </div>
                            <ul class="space-y-1">
                                <li v-for="m in claim.missing" :key="m.key" class="flex items-center gap-2 flex-wrap text-rose-800/90">
                                    <span><strong>{{ m.label }}</strong> - {{ m.hint }}</span>
                                    <button @click="activeTab = m.tab" class="text-xs font-bold text-rose-700 underline underline-offset-2 hover:text-rose-900">Add it now</button>
                                </li>
                            </ul>
                        </div>

                        <!-- Tabs -->
                        <div class="flex flex-wrap gap-1 bg-white rounded-xl ring-1 ring-slate-900/5 p-1">
                            <button
                                v-for="t in tabs" :key="t.key" @click="activeTab = t.key"
                                class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-bold transition-colors"
                                :class="activeTab === t.key ? 'bg-slate-900 text-white shadow-lg shadow-slate-900/10' : 'text-slate-500 hover:text-slate-700'"
                            >
                                {{ t.label }}
                                <!-- Blinking dot: this tab has missing info the user can supply -->
                                <span v-if="missingForTab(t.key).length" class="relative flex w-2 h-2">
                                    <span class="absolute inline-flex w-full h-full rounded-full bg-rose-400 opacity-75 animate-ping"></span>
                                    <span class="relative inline-flex w-2 h-2 rounded-full bg-rose-500"></span>
                                </span>
                            </button>
                        </div>

                        <!-- Tab: Progress -->
                        <div v-if="activeTab === 'progress'" class="bg-white rounded-2xl ring-1 ring-slate-900/5 p-6 sm:p-8">
                            <ol class="relative">
                                <li v-for="(ev, i) in claim.events" :key="i" class="flex gap-4 pb-8 last:pb-0">
                                    <div class="flex flex-col items-center">
                                        <span class="w-6 h-6 rounded-full flex items-center justify-center shrink-0 z-10" :class="dotCls(ev.status)">
                                            <svg v-if="ev.status === 'done'" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                            <svg v-else-if="ev.status === 'failed'" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18"/></svg>
                                            <svg v-else class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 7v5l3 2"/></svg>
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
                            <template v-if="claim.eligibility">
                                <div class="grid sm:grid-cols-3 gap-4 mb-5">
                                    <div class="rounded-xl bg-slate-50 p-4">
                                        <div class="text-[11px] uppercase tracking-wider font-bold text-slate-400 mb-1">Regulation</div>
                                        <div class="font-black text-slate-900">{{ claim.eligibility.regulation || '-' }}</div>
                                    </div>
                                    <div class="rounded-xl bg-slate-50 p-4">
                                        <div class="text-[11px] uppercase tracking-wider font-bold text-slate-400 mb-1">Legal basis</div>
                                        <div class="font-black text-slate-900">{{ claim.eligibility.article || '-' }}</div>
                                    </div>
                                    <div v-if="claim.eligibility.status === 'review'" class="rounded-xl bg-violet-50 p-4">
                                        <div class="text-[11px] uppercase tracking-wider font-bold text-violet-400 mb-1">What happens next</div>
                                        <div class="font-black text-violet-700">Our team is on it</div>
                                    </div>
                                    <div v-else-if="claim.eligibility.decided_by === 'team'" class="rounded-xl bg-emerald-50 p-4">
                                        <div class="text-[11px] uppercase tracking-wider font-bold text-emerald-500 mb-1">Decision</div>
                                        <div class="font-black text-emerald-700">Verified by our team</div>
                                    </div>
                                    <div v-else class="rounded-xl bg-slate-50 p-4">
                                        <div class="text-[11px] uppercase tracking-wider font-bold text-slate-400 mb-1">Confidence</div>
                                        <div class="font-black" :class="claim.eligibility.confidence >= 70 ? 'text-emerald-600' : 'text-amber-600'">{{ claim.eligibility.confidence }}%</div>
                                    </div>
                                </div>

                                <div class="rounded-xl p-5 mb-4" :class="claim.eligibility.status === 'eligible' ? 'bg-emerald-50' : 'bg-slate-50'">
                                    <div class="text-[11px] uppercase tracking-wider font-bold mb-1" :class="claim.eligibility.status === 'eligible' ? 'text-emerald-500' : 'text-slate-400'">Estimated compensation</div>
                                    <div class="text-3xl font-black" :class="claim.eligibility.status === 'eligible' ? 'text-emerald-700' : 'text-slate-700'">{{ claim.compensation.display }}</div>
                                    <div v-if="claim.compensation.basis" class="text-xs text-slate-500 mt-1">{{ claim.compensation.basis }}</div>
                                </div>

                                <!-- Why this amount: the law's tier table + your facts -->
                                <div v-if="claim.compensation.breakdown" class="rounded-xl border border-slate-200 p-4 mb-4">
                                    <p class="text-[11px] uppercase tracking-wider font-bold text-slate-400 mb-3">How this amount is set</p>

                                    <div v-if="(claim.compensation.breakdown.tiers || []).length" class="grid grid-cols-3 gap-2 mb-4">
                                        <div v-for="tier in claim.compensation.breakdown.tiers" :key="tier.label"
                                             class="rounded-xl p-3 text-center ring-1 relative"
                                             :class="tier.active ? 'bg-emerald-50 ring-emerald-300' : 'bg-slate-50 ring-slate-200 opacity-60'">
                                            <svg v-if="tier.active" class="w-4 h-4 text-emerald-500 absolute top-2 right-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                            <div class="text-[10px] font-bold uppercase tracking-wide" :class="tier.active ? 'text-emerald-600' : 'text-slate-400'">{{ tier.label }}</div>
                                            <div class="font-black mt-1" :class="tier.active ? 'text-emerald-700' : 'text-slate-500'">{{ tier.amount }}</div>
                                        </div>
                                    </div>

                                    <dl class="space-y-2">
                                        <div v-for="fact in claim.compensation.breakdown.facts || []" :key="fact.label" class="flex gap-3 text-sm">
                                            <dt class="font-bold text-slate-500 shrink-0 min-w-[110px]">{{ fact.label }}</dt>
                                            <dd class="text-slate-700">{{ fact.value }}</dd>
                                        </div>
                                    </dl>

                                    <p v-if="claim.compensation.breakdown.note" class="text-xs text-slate-400 mt-3 pt-3 border-t border-slate-100">{{ claim.compensation.breakdown.note }}</p>
                                    <p class="text-xs mt-2" :class="claim.compensation.ticket_price ? 'text-slate-400' : 'text-amber-600 font-medium'">
                                        <template v-if="claim.compensation.ticket_price">
                                            Ticket price on file: {{ claim.compensation.ticket_currency }} {{ claim.compensation.ticket_price }}
                                        </template>
                                        <template v-else>
                                            No ticket price on file - fare-based cases (downgrades, refunds, US denied boarding) need it.
                                        </template>
                                    </p>
                                </div>

                                <p class="text-sm text-slate-600">{{ claim.eligibility.reason }}</p>
                                <p v-if="claim.flight_verified" class="flex items-center gap-1.5 text-xs text-slate-400 mt-3">
                                    <svg class="w-3.5 h-3.5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    Flight facts verified against live flight-tracking records.
                                </p>
                            </template>

                            <template v-else>
                                <div class="flex items-start gap-3 bg-primary-50 border border-primary-100 text-primary-800 px-4 py-3 rounded-xl text-sm">
                                    <svg class="w-5 h-5 shrink-0 text-primary-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
                                    <p>We're verifying this flight and assessing your eligibility - this usually completes within a few minutes of submitting your claim.</p>
                                </div>
                                <div class="mt-4 text-sm text-slate-500">Current estimate: <span class="font-bold text-slate-800">{{ claim.compensation.display }}</span></div>
                            </template>
                        </div>

                        <!-- Tab: Documents -->
                        <div v-else-if="activeTab === 'documents'" class="bg-white rounded-2xl ring-1 ring-slate-900/5 p-6 sm:p-8">
                            <div v-if="isMissing('documents')" class="flex items-start gap-2 bg-rose-50 border border-rose-100 text-rose-700 px-4 py-3 rounded-xl text-sm mb-4">
                                <span class="relative flex w-2 h-2 mt-1.5 shrink-0">
                                    <span class="absolute inline-flex w-full h-full rounded-full bg-rose-400 opacity-75 animate-ping"></span>
                                    <span class="relative inline-flex w-2 h-2 rounded-full bg-rose-500"></span>
                                </span>
                                <p>No supporting documents yet - your ticket, boarding pass or any airline emails strengthen the case with the airline.</p>
                            </div>

                            <label class="flex flex-col items-center justify-center gap-2 border-2 border-dashed border-slate-200 hover:border-primary-300 rounded-xl px-4 py-6 cursor-pointer transition-colors mb-4">
                                <svg class="w-6 h-6 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16V4m0 0L8 8m4-4l4 4M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2"/></svg>
                                <span class="text-sm font-bold text-slate-600">{{ uploadingDocs ? 'Uploading…' : 'Add documents' }}</span>
                                <span class="text-xs text-slate-400">PDF or photos - up to 6 at a time, 12 MB each</span>
                                <input type="file" multiple accept=".pdf,.jpg,.jpeg,.png,.webp,.heic,.heif" class="hidden" :disabled="uploadingDocs" @change="uploadDocuments" />
                            </label>

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
                        <div v-else-if="activeTab === 'details'" class="space-y-5">
                            <!-- Supply missing facts -->
                            <div v-if="missingForTab('details').length" class="bg-white rounded-2xl ring-1 ring-rose-200 p-6 sm:p-8">
                                <h2 class="flex items-center gap-2 font-bold text-slate-900 mb-1">
                                    Missing information
                                    <span class="relative flex w-2 h-2">
                                        <span class="absolute inline-flex w-full h-full rounded-full bg-rose-400 opacity-75 animate-ping"></span>
                                        <span class="relative inline-flex w-2 h-2 rounded-full bg-rose-500"></span>
                                    </span>
                                </h2>
                                <p class="text-xs text-slate-400 mb-5">Filling these in updates your compensation estimate immediately.</p>

                                <div class="grid sm:grid-cols-2 gap-5">
                                    <div v-if="isMissing('ticket_price')">
                                        <label class="block text-xs font-bold text-slate-500 mb-1.5">Ticket price per person</label>
                                        <div class="flex gap-2">
                                            <input v-model="infoForm.ticket_price" type="number" min="0" step="0.01" placeholder="450.00"
                                                   class="flex-1 px-3.5 py-2.5 rounded-xl ring-1 ring-slate-200 border-0 text-sm focus:ring-2 focus:ring-primary-500" />
                                            <select v-model="infoForm.ticket_currency" class="px-2 py-2.5 rounded-xl ring-1 ring-slate-200 border-0 text-sm">
                                                <option v-for="c in ['USD', 'EUR', 'GBP', 'CAD', 'AED', 'INR', 'CHF']" :key="c" :value="c">{{ c }}</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div v-if="isMissing('rebooking_delay')">
                                        <label class="block text-xs font-bold text-slate-500 mb-1.5">How late did you finally arrive?</label>
                                        <select v-model="infoForm.arrival_delay_minutes" class="w-full px-3.5 py-2.5 rounded-xl ring-1 ring-slate-200 border-0 text-sm">
                                            <option :value="null" disabled>Select…</option>
                                            <option :value="240">3 - 6 hours late</option>
                                            <option :value="420">6 - 9 hours late</option>
                                            <option :value="600">More than 9 hours late</option>
                                            <option :value="1440">I travelled the next day</option>
                                            <option value="refund">I never travelled - I chose a refund</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="flex justify-end mt-5">
                                    <button @click="saveInfo" :disabled="savingInfo || !infoDirty"
                                            class="px-5 py-2.5 bg-slate-900 hover:bg-slate-800 text-white rounded-xl text-sm font-bold shadow-lg shadow-slate-900/10 transition-all active:scale-95 disabled:opacity-40">
                                        {{ savingInfo ? 'Updating…' : 'Save & update estimate' }}
                                    </button>
                                </div>
                            </div>

                            <div class="bg-white rounded-2xl ring-1 ring-slate-900/5 p-6 sm:p-8">
                                <dl class="grid sm:grid-cols-2 gap-y-4 gap-x-8">
                                    <div v-for="row in detailRows" :key="row.label">
                                        <dt class="text-[11px] uppercase tracking-wider font-bold text-slate-400">{{ row.label }}</dt>
                                        <dd class="mt-1 font-medium text-slate-900">{{ row.value || '-' }}</dd>
                                    </div>
                                </dl>
                            </div>

                            <!-- Flight tracking record (FlightAware snapshot from the flight day) -->
                            <div v-if="claim.flight_tracking" class="bg-white rounded-2xl ring-1 ring-slate-900/5 p-6 sm:p-8">
                                <div class="flex items-center justify-between gap-3 mb-5 flex-wrap">
                                    <h2 class="font-bold text-slate-900">What the flight actually did</h2>
                                    <span v-if="claim.flight_tracking.cancelled" class="inline-flex px-3 py-1.5 rounded-full text-xs font-bold bg-rose-50 text-rose-700 ring-1 ring-rose-200">Cancelled</span>
                                    <span v-else-if="claim.flight_tracking.diverted" class="inline-flex px-3 py-1.5 rounded-full text-xs font-bold bg-amber-50 text-amber-700 ring-1 ring-amber-200">Diverted</span>
                                </div>

                                <div class="grid sm:grid-cols-2 gap-5">
                                    <div v-for="side in ['departure', 'arrival']" :key="side" class="rounded-xl border border-slate-200 p-4">
                                        <div class="text-[11px] uppercase tracking-wider font-bold text-slate-400 mb-2">
                                            {{ side === 'departure' ? 'Departure' : 'Arrival' }}
                                            <span class="text-slate-300 normal-case font-medium">- {{ trackingAirport(side) }} · local time</span>
                                        </div>
                                        <dl class="space-y-1.5 text-sm">
                                            <div class="flex justify-between gap-3">
                                                <dt class="text-slate-400 font-medium">Scheduled</dt>
                                                <dd class="font-bold text-slate-800">{{ trackingTime(`scheduled_${side}`, side) || '-' }}</dd>
                                            </div>
                                            <div class="flex justify-between gap-3">
                                                <dt class="text-slate-400 font-medium">Actual</dt>
                                                <dd class="font-bold text-slate-800">{{ trackingTime(`actual_${side}`, side) || '-' }}</dd>
                                            </div>
                                            <div class="flex justify-between gap-3">
                                                <dt class="text-slate-400 font-medium">Delay</dt>
                                                <dd class="font-bold" :class="trackingDelay(side) > 0 ? 'text-amber-600' : 'text-emerald-600'">
                                                    {{ trackingDelay(side) > 0 ? `+${trackingDelay(side)} min` : 'None' }}
                                                </dd>
                                            </div>
                                            <div class="flex justify-between gap-3">
                                                <dt class="text-slate-400 font-medium">Gate</dt>
                                                <dd class="font-bold text-slate-800">{{ (side === 'departure' ? claim.flight_tracking.origin_gate : claim.flight_tracking.destination_gate) || '-' }}</dd>
                                            </div>
                                            <div class="flex justify-between gap-3">
                                                <dt class="text-slate-400 font-medium">Terminal</dt>
                                                <dd class="font-bold text-slate-800">{{ (side === 'departure' ? claim.flight_tracking.origin_terminal : claim.flight_tracking.destination_terminal) || '-' }}</dd>
                                            </div>
                                        </dl>
                                    </div>
                                </div>

                                <p class="flex items-center gap-1.5 text-xs text-slate-400 mt-4">
                                    <svg class="w-3.5 h-3.5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    Verified against live flight-tracking records for {{ claim.flight_date }}.
                                </p>
                            </div>
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
import { formatDateTime } from '../datetime';
import HelpPanel from '../components/HelpPanel.vue';

// Flight tracking snapshot helpers (airport-local times, like the trip page).
function useTracking(claim) {
    const t = () => claim.value?.flight_tracking;

    return {
        trackingTime: (key, side) => formatDateTime(
            t()?.[key],
            (side === 'departure' ? t()?.origin_timezone : t()?.destination_timezone) || undefined
        ),
        trackingDelay: (side) => Math.max(0, t()?.[side === 'departure' ? 'departure_delay_minutes' : 'arrival_delay_minutes'] || 0),
        trackingAirport: (side) => {
            const name = side === 'departure' ? t()?.origin_airport_name : t()?.destination_airport_name;
            const code = side === 'departure' ? claim.value?.departure_airport : claim.value?.arrival_airport;
            return name ? `${name} (${code})` : code || '';
        },
    };
}

const props = defineProps({ id: { type: [String, Number], required: true } });

const claim = ref(null);
const { trackingTime, trackingDelay, trackingAirport } = useTracking(claim);

// ── Missing-info handling ──
const infoForm = ref({ ticket_price: '', ticket_currency: 'USD', arrival_delay_minutes: null });
const savingInfo = ref(false);
const uploadingDocs = ref(false);

const missingForTab = (tab) => (claim.value?.missing || []).filter((m) => m.tab === tab);
const isMissing = (key) => (claim.value?.missing || []).some((m) => m.key === key);
const infoDirty = computed(() => infoForm.value.ticket_price !== '' || infoForm.value.arrival_delay_minutes !== null);

async function saveInfo() {
    savingInfo.value = true;
    try {
        const payload = {};
        if (infoForm.value.ticket_price !== '') {
            payload.ticket_price = infoForm.value.ticket_price;
            payload.ticket_currency = infoForm.value.ticket_currency;
        }
        if (infoForm.value.arrival_delay_minutes === 'refund') {
            payload.did_not_travel = true;
        } else if (infoForm.value.arrival_delay_minutes !== null) {
            payload.arrival_delay_minutes = infoForm.value.arrival_delay_minutes;
        }

        claim.value = await api.claims.updateInfo(props.id, payload);
        infoForm.value = { ticket_price: '', ticket_currency: 'USD', arrival_delay_minutes: null };
        activeTab.value = 'compensation'; // show the refreshed estimate
    } catch (e) {
        window.alert(e.response?.data?.message || 'Could not save. Please try again.');
    } finally {
        savingInfo.value = false;
    }
}

async function uploadDocuments(event) {
    const files = Array.from(event.target.files || []).slice(0, 6);
    event.target.value = '';
    if (!files.length) return;

    uploadingDocs.value = true;
    try {
        const form = new FormData();
        files.forEach((f, i) => form.append(`documents[${i}]`, f));
        claim.value = await api.claims.addDocuments(props.id, form);
    } catch (e) {
        window.alert(e.response?.data?.message || 'Could not upload the documents. Please try again.');
    } finally {
        uploadingDocs.value = false;
    }
}
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
    { label: 'Route', value: `${claim.value.departure_airport || '-'} → ${claim.value.arrival_airport || '-'}` },
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
    return 'bg-amber-100 text-amber-600 border border-amber-300';
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
