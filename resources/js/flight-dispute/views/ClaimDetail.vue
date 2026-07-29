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
                                    <span v-if="claim.stage_label"
                                          class="inline-flex items-center gap-1.5 bg-white/15 border border-white/20 rounded-full px-3 py-1 text-[11px] font-bold mb-2">
                                        <span class="w-1.5 h-1.5 rounded-full bg-white/80"></span>{{ claim.stage_label }}
                                    </span>
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
                                        <div class="text-[10px] font-bold text-white/70 uppercase tracking-wide">{{ isBookingTotal ? 'Total compensation' : 'Compensation' }}</div>
                                        <div class="text-lg font-black">{{ heroCompensation }}</div>
                                        <div v-if="isBookingTotal" class="text-[10px] font-bold text-white/70">{{ claim.payout.passenger_count }} passengers × {{ claim.payout.currency }} {{ claim.payout.per_passenger }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Confirm & file CTA: the money moment -->
                        <div v-if="claim.workflow?.can_confirm" class="relative overflow-hidden rounded-2xl p-5 sm:p-6 text-white shadow-lg shadow-emerald-900/20" style="background:linear-gradient(105deg,#065f46 0%,#059669 60%,#10b981 100%);">
                            <div class="absolute -top-10 -right-10 w-40 h-40 rounded-full bg-white/10"></div>
                            <div class="absolute -bottom-14 -right-2 w-28 h-28 rounded-full bg-white/5"></div>
                            <div class="relative flex flex-col sm:flex-row sm:items-center gap-4">
                                <div class="flex items-center gap-4 flex-1 min-w-0">
                                    <span class="w-12 h-12 rounded-2xl bg-white/15 border border-white/25 flex items-center justify-center shrink-0">
                                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><rect x="2.5" y="6" width="19" height="12" rx="2"/><circle cx="12" cy="12" r="2.6"/><path stroke-linecap="round" d="M6 9.5h.01M18 14.5h.01"/></svg>
                                    </span>
                                    <div class="min-w-0">
                                        <div class="text-lg sm:text-xl font-black tracking-tight">You're owed {{ heroCompensation }}</div>
                                        <div class="text-white/80 text-sm mt-0.5">One step left: review your payout and authorise us to claim it. Takes about 2 minutes - no win, no fee.</div>
                                    </div>
                                </div>
                                <router-link :to="{ name: 'claim-confirm', params: { id } }" class="group relative bg-white text-emerald-800 font-black px-6 py-3.5 rounded-xl text-sm text-center shadow-lg hover:shadow-xl hover:-translate-y-0.5 transition-all shrink-0 inline-flex items-center justify-center gap-2">
                                    Claim it now
                                    <svg class="w-4 h-4 transition-transform group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5-5 5M6 12h12"/></svg>
                                </router-link>
                            </div>
                        </div>

                        <!-- Awaiting signatures CTA: progress toward filing -->
                        <div v-else-if="claim.workflow?.awaiting_signatures" class="relative overflow-hidden rounded-2xl p-5 sm:p-6 text-white shadow-lg shadow-violet-900/20" style="background:linear-gradient(105deg,#4c1d95 0%,#6d28d9 60%,#8b5cf6 100%);">
                            <div class="absolute -top-10 -right-10 w-40 h-40 rounded-full bg-white/10"></div>
                            <div class="relative flex flex-col sm:flex-row sm:items-center gap-4">
                                <div class="flex items-center gap-4 flex-1 min-w-0">
                                    <span class="w-12 h-12 rounded-2xl bg-white/15 border border-white/25 flex items-center justify-center shrink-0">
                                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 3.5a2.1 2.1 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <div class="text-lg sm:text-xl font-black tracking-tight">
                                            {{ claim.workflow.signers_signed }} of {{ claim.workflow.signers_total }} signatures collected
                                        </div>
                                        <div class="text-white/80 text-sm mt-0.5">Your claim files the moment the last one is in.</div>
                                        <div class="mt-2.5 h-1.5 rounded-full bg-white/20 overflow-hidden max-w-xs">
                                            <div class="h-full rounded-full bg-white transition-all" :style="{ width: (claim.workflow.signers_total ? (claim.workflow.signers_signed / claim.workflow.signers_total) * 100 : 0) + '%' }"></div>
                                        </div>
                                    </div>
                                </div>
                                <router-link :to="{ name: 'claim-sign', params: { id } }" class="group bg-white text-violet-800 font-black px-6 py-3.5 rounded-xl text-sm text-center shadow-lg hover:shadow-xl hover:-translate-y-0.5 transition-all shrink-0 inline-flex items-center justify-center gap-2">
                                    Finish signing
                                    <svg class="w-4 h-4 transition-transform group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5-5 5M6 12h12"/></svg>
                                </router-link>
                            </div>
                        </div>

                        <!-- Missing info banner -->
                        <div v-if="(claim.missing || []).length || needsBank" class="bg-rose-50 border border-rose-100 rounded-xl px-4 py-3 text-sm">
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
                                <li v-if="needsBank" class="flex items-center gap-2 flex-wrap text-rose-800/90">
                                    <span><strong>Payout bank details</strong> - we can't pay you directly without your account.</span>
                                    <button @click="goToBank" class="text-xs font-bold text-rose-700 underline underline-offset-2 hover:text-rose-900">Add it now</button>
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
                            <!-- The money has moved: one card per airline payment, newest first -->
                            <div v-for="(payment, pi) in (claim.payments || [])" :key="pi" class="mb-8 rounded-2xl bg-slate-900 text-white p-5 sm:p-6">
                                <div class="flex items-center justify-between gap-3 flex-wrap mb-4">
                                    <h2 class="font-bold">
                                        {{ claim.payments.length > 1 ? `Payout ${claim.payments.length - pi} of ${claim.payments.length}` : 'Your payout' }}
                                        <span class="block text-[11px] font-medium text-slate-400 mt-0.5">Airline paid on {{ payment.payment_date }}</span>
                                    </h2>
                                    <span class="flex items-center gap-2">
                                        <a v-if="payment.receipt_url" :href="payment.receipt_url" target="_blank"
                                           class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-white/10 hover:bg-white/20 text-[10px] font-bold text-slate-200 transition-colors">
                                            <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5"/><path d="M12 15V3"/></svg>
                                            Receipt
                                        </a>
                                        <span class="inline-flex px-2.5 py-1 rounded-full text-[10px] font-black"
                                              :class="payment.status === 'paid' ? 'bg-emerald-400/20 text-emerald-300'
                                                    : payment.status === 'failed' ? 'bg-rose-400/20 text-rose-300' : 'bg-amber-400/20 text-amber-300'">
                                            {{ payment.status_label.toUpperCase() }}
                                        </span>
                                    </span>
                                </div>
                                <div class="grid grid-cols-3 gap-3 text-center">
                                    <div class="rounded-xl bg-white/5 px-3 py-2.5">
                                        <p class="text-[10px] uppercase font-black text-slate-400">Airline paid</p>
                                        <p class="text-sm sm:text-base font-bold">{{ payment.gross }}</p>
                                    </div>
                                    <div class="rounded-xl bg-white/5 px-3 py-2.5">
                                        <p class="text-[10px] uppercase font-black text-slate-400">Fee ({{ payment.fee_percent }}%)</p>
                                        <p class="text-sm sm:text-base font-bold">{{ payment.fee }}</p>
                                    </div>
                                    <div class="rounded-xl bg-emerald-400/15 px-3 py-2.5">
                                        <p class="text-[10px] uppercase font-black text-emerald-300">You receive</p>
                                        <p class="text-sm sm:text-base font-bold text-emerald-300">{{ payment.net }}</p>
                                    </div>
                                </div>
                                <p v-if="payment.expenses" class="text-[12px] text-emerald-300/90 mt-3">
                                    Includes {{ payment.expenses }} for your out-of-pocket expenses{{ payment.expenses_fee_free ? ' - reimbursed in full, no fee charged on them.' : '.' }}
                                </p>
                                <p v-if="payment.payout" class="text-[12px] text-slate-400 mt-3">
                                    Transfer {{ payment.payout.status }} · reference <span class="font-mono text-slate-300">{{ payment.payout.reference }}</span>
                                    <template v-if="payment.payout.sent_at"> · sent {{ payment.payout.sent_at }}</template>
                                </p>
                                <div v-if="(payment.timeline || []).length" class="mt-4 border-t border-white/10 pt-3">
                                    <button @click="toggleTimeline(pi)" class="text-[11px] font-bold text-slate-400 hover:text-slate-200 transition-colors">
                                        {{ openTimelines[pi] ? 'Hide history' : `View history (${payment.timeline.length})` }}
                                    </button>
                                    <ul v-if="openTimelines[pi]" class="mt-2.5 space-y-1.5">
                                        <li v-for="(step, i) in payment.timeline" :key="i" class="flex items-center gap-2 text-[12px] text-slate-300">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 shrink-0"></span>
                                            {{ step.label }}<span v-if="step.amount" class="text-slate-400">· {{ step.amount }}</span>
                                            <span class="ml-auto text-slate-500">{{ step.at }}</span>
                                        </li>
                                    </ul>
                                </div>
                            </div>

                            <!-- Payout bank details: where the money should land -->
                            <div v-if="claim.eligibility && claim.eligibility.status !== 'rejected'" id="bank-details-card" class="mb-8 rounded-2xl border p-5 scroll-mt-24"
                                 :class="savedAccount ? 'border-slate-200' : 'border-rose-300 bg-rose-50/60 ring-1 ring-rose-100'">
                                <div class="flex items-center justify-between gap-3 flex-wrap">
                                    <div class="flex items-start gap-2.5">
                                        <span v-if="!savedAccount" class="shrink-0 mt-0.5 w-5 h-5 rounded-full bg-rose-500 text-white text-[11px] font-black flex items-center justify-center animate-pulse">!</span>
                                        <div>
                                            <h3 class="text-sm font-bold" :class="savedAccount ? 'text-slate-900' : 'text-rose-800'">
                                                {{ savedAccount ? 'Payout bank details' : 'Bank details needed for your payout' }}
                                            </h3>
                                            <p class="text-[12px] mt-0.5" :class="savedAccount ? 'text-slate-500' : 'text-rose-700'">
                                                <template v-if="savedAccount">We'll send your payout to <span class="font-mono font-bold">{{ savedAccount.masked }}</span> ({{ savedAccount.currency }}, {{ savedAccount.holder }}).</template>
                                                <template v-else>Without your account we can't pay you directly - add it now so your money isn't held up.</template>
                                            </p>
                                        </div>
                                    </div>
                                    <button @click="bankOpen = !bankOpen"
                                            class="text-[12px] font-bold shrink-0 px-4 py-2 rounded-xl transition-colors"
                                            :class="savedAccount ? 'text-primary-600 hover:underline' : 'bg-rose-600 hover:bg-rose-700 text-white'">
                                        {{ bankOpen ? 'Close' : (savedAccount ? 'Change' : 'Add bank account') }}
                                    </button>
                                </div>

                                <div v-if="bankOpen && bankAccounts.length" class="mt-4 space-y-2">
                                    <p class="text-[11px] uppercase tracking-wider font-bold text-slate-400">Your saved accounts</p>
                                    <div v-for="a in bankAccounts" :key="a.currency"
                                         class="flex flex-wrap items-center gap-x-3 gap-y-1 rounded-xl border px-3.5 py-2.5"
                                         :class="a.is_default ? 'border-emerald-300 bg-emerald-50/60' : 'border-slate-200'">
                                        <span class="font-mono text-sm font-bold text-slate-700">{{ a.currency }} {{ a.masked }}</span>
                                        <span class="text-[12px] text-slate-500">{{ a.holder }}</span>
                                        <span v-if="a.is_default"
                                              class="ml-auto text-[10px] font-black text-emerald-700 bg-emerald-100 px-2 py-0.5 rounded-full">PAYOUTS GO HERE</span>
                                        <span v-else class="ml-auto flex items-center gap-3">
                                            <button @click="makeDefaultBank(a.currency)" class="text-[11px] font-bold text-primary-600 hover:underline">Use for payouts</button>
                                            <button @click="removeBank(a.currency)" class="text-[11px] font-bold text-slate-400 hover:text-rose-600">Remove</button>
                                        </span>
                                    </div>
                                    <p class="text-[11px] text-slate-400">Saved accounts work for all your claims - payouts always go to the one marked above.</p>
                                </div>

                                <div v-if="bankOpen" class="mt-4 grid sm:grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1.5">Currency</label>
                                        <select v-model="bank.currency" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm outline-none">
                                            <option v-for="c in ['CAD', 'USD', 'EUR', 'GBP']" :key="c" :value="c">{{ c }}</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1.5">Account holder name</label>
                                        <input v-model="bank.account_holder_name" type="text" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm outline-none" />
                                    </div>

                                    <template v-if="bank.currency === 'EUR'">
                                        <div class="sm:col-span-2">
                                            <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1.5">IBAN</label>
                                            <input v-model="bank.iban" type="text" placeholder="DE89 3704 0044 0532 0130 00" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm font-mono outline-none" />
                                        </div>
                                    </template>
                                    <template v-else-if="bank.currency === 'GBP'">
                                        <div><label class="block text-[11px] font-bold text-slate-400 uppercase mb-1.5">Sort code</label>
                                            <input v-model="bank.sort_code" type="text" placeholder="231470" maxlength="6" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm font-mono outline-none" /></div>
                                        <div><label class="block text-[11px] font-bold text-slate-400 uppercase mb-1.5">Account number</label>
                                            <input v-model="bank.account_number" type="text" maxlength="8" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm font-mono outline-none" /></div>
                                    </template>
                                    <template v-else-if="bank.currency === 'CAD'">
                                        <div><label class="block text-[11px] font-bold text-slate-400 uppercase mb-1.5">Institution (3)</label>
                                            <input v-model="bank.institution_number" type="text" maxlength="3" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm font-mono outline-none" /></div>
                                        <div><label class="block text-[11px] font-bold text-slate-400 uppercase mb-1.5">Transit (5)</label>
                                            <input v-model="bank.transit_number" type="text" maxlength="5" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm font-mono outline-none" /></div>
                                        <div class="sm:col-span-2"><label class="block text-[11px] font-bold text-slate-400 uppercase mb-1.5">Account number</label>
                                            <input v-model="bank.account_number" type="text" maxlength="12" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm font-mono outline-none" /></div>
                                    </template>
                                    <template v-else>
                                        <div><label class="block text-[11px] font-bold text-slate-400 uppercase mb-1.5">Routing number (9)</label>
                                            <input v-model="bank.routing_number" type="text" maxlength="9" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm font-mono outline-none" /></div>
                                        <div><label class="block text-[11px] font-bold text-slate-400 uppercase mb-1.5">Account number</label>
                                            <input v-model="bank.account_number" type="text" maxlength="17" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm font-mono outline-none" /></div>
                                        <div><label class="block text-[11px] font-bold text-slate-400 uppercase mb-1.5">City</label>
                                            <input v-model="bank.city" type="text" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm outline-none" /></div>
                                        <div><label class="block text-[11px] font-bold text-slate-400 uppercase mb-1.5">ZIP code</label>
                                            <input v-model="bank.post_code" type="text" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm outline-none" /></div>
                                        <div class="sm:col-span-2"><label class="block text-[11px] font-bold text-slate-400 uppercase mb-1.5">Street address</label>
                                            <input v-model="bank.address" type="text" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm outline-none" /></div>
                                    </template>

                                    <p v-if="bankError" class="sm:col-span-2 text-[12px] font-bold text-rose-600">{{ bankError }}</p>
                                    <div class="sm:col-span-2 flex items-center gap-3">
                                        <button :disabled="bankSaving" @click="saveBank"
                                                class="bg-slate-900 hover:bg-slate-800 disabled:opacity-60 text-white text-sm font-bold px-6 py-2.5 rounded-xl transition-colors">
                                            {{ bankSaving ? 'Saving…' : 'Save account' }}
                                        </button>
                                        <span class="text-[11px] text-slate-400">Stored encrypted. We only ever display the last 4 digits.</span>
                                    </div>
                                </div>
                            </div>

                            <template v-if="claim.eligibility">
                                <!-- Not eligible: evaluation result only - no compensation figures -->
                                <template v-if="claim.eligibility.status === 'rejected'">
                                    <p class="text-[11px] uppercase tracking-wider font-bold text-slate-400 mb-3">Claim evaluation result</p>
                                    <div class="flex items-center gap-3 mb-4">
                                        <span class="w-10 h-10 rounded-xl bg-rose-100 text-rose-600 flex items-center justify-center shrink-0">
                                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18"/></svg>
                                        </span>
                                        <div class="font-black text-slate-900 text-lg">Not eligible for compensation</div>
                                    </div>
                                    <div class="flex flex-wrap gap-2 mb-4">
                                        <span v-if="claim.eligibility.regulation" class="bg-slate-900 text-white text-xs font-bold px-3 py-1 rounded-full">{{ claim.eligibility.regulation }}</span>
                                        <span v-if="claim.eligibility.article" class="bg-slate-100 text-slate-700 text-xs font-bold px-3 py-1 rounded-full">{{ claim.eligibility.article }}</span>
                                    </div>
                                    <div class="rounded-xl bg-slate-50 p-4 mb-4">
                                        <p class="text-[11px] uppercase tracking-wider font-bold text-slate-400 mb-1">Why</p>
                                        <p class="text-sm text-slate-700">{{ claim.eligibility.reason }}</p>
                                    </div>
                                    <dl class="space-y-2 text-sm mb-4">
                                        <div class="flex gap-3"><dt class="font-bold text-slate-500 min-w-[110px]">Flight</dt><dd class="text-slate-700">{{ claim.airline }} {{ claim.flight_number }} - {{ claim.departure_airport }} → {{ claim.arrival_airport }}, {{ claim.flight_date }}</dd></div>
                                        <div class="flex gap-3"><dt class="font-bold text-slate-500 min-w-[110px]">Flight data</dt><dd class="text-slate-700">{{ claim.flight_verified ? 'Verified against live flight-tracking records' : 'Could not be verified automatically - judged on the information you provided' }}</dd></div>
                                    </dl>
                                    <p class="text-xs text-slate-400">If you have documents or details that change the picture, add them in the Documents tab and our team will take another look.</p>
                                </template>

                                <template v-else>
                                <!-- Once real payouts exist above, the original assessment is
                                     background reading - folded away so it can't be mistaken
                                     for more money on the way. -->
                                <div :class="hasPayments ? 'rounded-2xl border border-slate-200 bg-slate-50/60 p-4 sm:p-5' : ''">
                                <button v-if="hasPayments" @click="showEstimate = !showEstimate" class="w-full flex items-center justify-between gap-3 text-left">
                                    <span>
                                        <span class="text-sm font-bold text-slate-800">How this claim was assessed</span>
                                        <span class="block text-[11px] text-slate-500 mt-0.5">The legal basis and the original estimate we filed with. The airline has since paid - the money you receive is shown in the payouts above.</span>
                                    </span>
                                    <svg class="w-4 h-4 text-slate-400 shrink-0 transition-transform" :class="showEstimate ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                                </button>
                                <div v-show="!hasPayments || showEstimate" :class="hasPayments ? 'mt-4 border-t border-slate-200 pt-4' : ''">
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
                                    <div class="text-[11px] uppercase tracking-wider font-bold mb-1" :class="claim.eligibility.status === 'eligible' ? 'text-emerald-500' : 'text-slate-400'">
                                        Estimated compensation{{ payoutNames.length > 1 ? ' per passenger' : '' }}
                                    </div>
                                    <div class="text-3xl font-black" :class="claim.eligibility.status === 'eligible' ? 'text-emerald-700' : 'text-slate-700'">{{ claim.compensation.display }}</div>
                                    <div v-if="claim.compensation.basis" class="text-xs text-slate-500 mt-1">{{ claim.compensation.basis }}</div>
                                </div>

                                <!-- The money, step by step: per passenger → total → fee → payout -->
                                <div v-if="claim.payout" class="rounded-xl border border-slate-200 p-4 mb-4">
                                    <p class="text-[11px] uppercase tracking-wider font-bold text-slate-400 mb-3">Your payout, step by step</p>
                                    <div class="divide-y divide-slate-100 text-sm">
                                        <div v-for="(name, i) in payoutNames" :key="i" class="flex items-center justify-between gap-3 py-2">
                                            <span class="text-slate-600 min-w-0 truncate">{{ name }}</span>
                                            <span class="font-bold text-slate-800 shrink-0">{{ claim.payout.currency }} {{ claim.payout.per_passenger }}</span>
                                        </div>
                                        <div v-if="payoutNames.length > 1" class="flex items-center justify-between gap-3 py-2 font-bold text-slate-900">
                                            <span>Total compensation ({{ claim.payout.passenger_count }} passengers)</span>
                                            <span class="shrink-0">{{ claim.payout.currency }} {{ claim.payout.gross }}</span>
                                        </div>
                                        <div class="flex items-center justify-between gap-3 py-2 text-slate-500">
                                            <span>Success fee ({{ claim.payout.fee_percent }}%) - charged only if we win</span>
                                            <span class="shrink-0">- {{ claim.payout.currency }} {{ claim.payout.fee }}</span>
                                        </div>
                                    </div>
                                    <div class="mt-3 rounded-xl bg-emerald-50 px-4 py-3 flex items-center justify-between gap-3">
                                        <span class="text-sm font-bold text-emerald-700">You receive</span>
                                        <span class="text-2xl font-black text-emerald-700">{{ claim.payout.currency }} {{ claim.payout.net }}</span>
                                    </div>
                                    <p class="text-xs text-slate-400 mt-3">No win, no fee - if the airline doesn't pay, you owe nothing. Amounts are set by the regulation, never by your ticket price.</p>
                                </div>

                                <!-- Separate entitlements: compensation, refund, re-routing, expenses -->
                                <div v-if="entitlements.length" class="rounded-xl border border-slate-200 p-4 mb-4">
                                    <p class="text-[11px] uppercase tracking-wider font-bold text-slate-400 mb-1">What you're entitled to</p>
                                    <p class="text-xs text-slate-400 mb-3">These are separate rights - one never replaces another.</p>
                                    <div class="grid sm:grid-cols-2 gap-2">
                                        <div v-for="e in entitlements" :key="e.key" class="rounded-xl p-3 ring-1" :class="entitlementCls(e.state).box">
                                            <div class="flex items-center justify-between gap-2">
                                                <div class="text-[10px] font-bold uppercase tracking-wide" :class="entitlementCls(e.state).label">{{ e.label }}</div>
                                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full shrink-0" :class="entitlementCls(e.state).badge">
                                                    {{ e.state === 'included' ? 'Included' : e.state === 'conditional' ? 'Depends' : 'Not applicable' }}
                                                </span>
                                            </div>
                                            <div class="font-black mt-1 text-sm" :class="entitlementCls(e.state).value">{{ e.value }}</div>
                                            <p class="text-xs mt-1" :class="e.state === 'none' ? 'text-slate-400' : 'text-slate-500'">{{ e.detail }}</p>
                                        </div>
                                    </div>
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
                                </div>
                                </div>
                                </template>
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

                            <!-- Authorisation documents: POAs + Assignment, with signature state -->
                            <template v-if="(claim.legal_documents || []).length">
                                <p class="text-[11px] uppercase tracking-wider font-bold text-slate-400 mt-6 mb-2">Authorisation documents</p>
                                <ul class="space-y-2">
                                    <li v-for="(doc, i) in claim.legal_documents" :key="'legal' + i">
                                        <a :href="doc.url" target="_blank" class="flex items-center gap-3 px-4 py-3 rounded-xl border border-slate-200 hover:border-primary-300 hover:bg-primary-50/40 transition-colors">
                                            <svg class="w-5 h-5 shrink-0" :class="doc.signed ? 'text-emerald-500' : 'text-amber-500'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 4H7a2 2 0 01-2-2V6a2 2 0 012-2h7l5 5v11a2 2 0 01-2 2z"/></svg>
                                            <span class="text-sm font-medium text-slate-700 truncate flex-1">{{ doc.name }}</span>
                                            <span class="text-[10px] font-black px-2 py-0.5 rounded-full shrink-0" :class="doc.signed ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'">
                                                {{ doc.signed ? 'SIGNED' : 'AWAITING SIGNATURE' }}
                                            </span>
                                        </a>
                                    </li>
                                </ul>
                            </template>
                        </div>

                        <!-- Tab: Expenses - receipts the passenger paid out of pocket -->
                        <div v-else-if="activeTab === 'expenses'" class="bg-white rounded-2xl ring-1 ring-slate-900/5 p-6 sm:p-8">
                            <h2 class="font-bold text-slate-900 mb-1">Out-of-pocket expenses</h2>
                            <p class="text-sm text-slate-500 mb-1">
                                Meals, hotel, taxi, replacement tickets - anything the disruption forced you to pay for. Add the receipt and we will claim it back from the airline on top of your compensation.
                            </p>
                            <p class="text-xs font-bold text-emerald-700 mb-5">You keep 100% of expense reimbursements - our fee applies to compensation only.</p>

                            <div v-if="approvedExpenseTotal" class="flex items-center gap-2 bg-emerald-50 border border-emerald-100 text-emerald-800 px-4 py-3 rounded-xl text-sm mb-5">
                                <span class="font-bold">{{ approvedExpenseTotal }}</span> approved and being claimed from the airline.
                            </div>

                            <!-- Add a receipt -->
                            <div class="rounded-2xl border border-slate-200 p-4 mb-5">
                                <p class="text-[11px] uppercase tracking-wider font-bold text-slate-400 mb-3">Add a receipt</p>
                                <div class="grid sm:grid-cols-2 gap-3">
                                    <select v-model="expenseForm.category"
                                            class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:border-primary-500 outline-none">
                                        <option v-for="(label, key) in (claim.expense_categories || {})" :key="key" :value="key">{{ label }}</option>
                                    </select>
                                    <input v-model="expenseForm.expense_date" type="date"
                                           class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:border-primary-500 outline-none" />
                                    <div class="flex gap-2">
                                        <input v-model="expenseForm.amount" type="number" step="0.01" min="0" placeholder="Amount"
                                               class="flex-1 px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:border-primary-500 outline-none" />
                                        <input v-model="expenseForm.currency" type="text" maxlength="3" placeholder="EUR"
                                               class="w-20 px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm uppercase focus:border-primary-500 outline-none" />
                                    </div>
                                    <input v-model="expenseForm.description" type="text" maxlength="190" placeholder="What was it for? (optional)"
                                           class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:border-primary-500 outline-none" />
                                </div>
                                <label class="flex items-center gap-3 mt-3 cursor-pointer">
                                    <span class="inline-flex items-center gap-2 border border-dashed border-slate-300 hover:border-primary-400 rounded-xl px-4 py-2.5 text-sm font-bold text-slate-600 transition-colors">
                                        {{ expenseFile ? expenseFile.name : 'Choose receipt (PDF or photo)' }}
                                    </span>
                                    <input id="expense-file" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp,.heic,.heif" class="hidden" @change="pickExpenseFile" />
                                </label>
                                <p v-if="expenseError" class="text-xs font-bold text-rose-600 mt-2">{{ expenseError }}</p>
                                <button :disabled="uploadingExpense" @click="submitExpense"
                                        class="mt-3 w-full sm:w-auto bg-slate-900 hover:bg-slate-800 disabled:opacity-60 text-white text-sm font-bold px-6 py-2.5 rounded-xl transition-colors">
                                    {{ uploadingExpense ? 'Uploading…' : 'Add receipt' }}
                                </button>
                            </div>

                            <!-- Uploaded receipts -->
                            <p v-if="!(claim.expenses || []).length" class="text-sm text-slate-400">No receipts added yet.</p>
                            <ul v-else class="space-y-2">
                                <li v-for="e in claim.expenses" :key="e.id"
                                    class="flex items-start gap-3 rounded-xl border border-slate-200 p-3">
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-[9px] font-black shrink-0 mt-0.5"
                                          :class="e.reimbursed ? 'bg-emerald-600 text-white'
                                                : e.status === 'approved' ? 'bg-emerald-100 text-emerald-700'
                                                : e.status === 'rejected' ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-700'">
                                        {{ e.reimbursed ? 'PAID BACK' : e.status === 'approved' ? 'APPROVED' : e.status === 'rejected' ? 'NOT ACCEPTED' : 'IN REVIEW' }}
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-bold text-slate-800 truncate">
                                            {{ e.category_label }}
                                            <span v-if="e.display" class="text-slate-500 font-medium">· {{ e.display }}</span>
                                        </p>
                                        <p class="text-[11px] text-slate-400 truncate">
                                            <span v-if="e.date">{{ e.date }} · </span>{{ e.filename }}
                                        </p>
                                        <p v-if="e.reimbursed" class="text-[11px] font-bold text-emerald-600 mt-0.5">Reimbursed to you on {{ e.reimbursed }}</p>
                                        <p v-if="e.description" class="text-[11px] text-slate-500 mt-0.5">{{ e.description }}</p>
                                        <p v-if="e.status === 'rejected' && e.reason" class="text-[11px] text-rose-600 mt-1">{{ e.reason }}</p>
                                    </div>
                                    <a :href="e.url" target="_blank" class="shrink-0 text-[11px] font-bold text-primary-600 hover:underline">View</a>
                                    <button v-if="!e.locked" @click="deleteExpense(e.id)" class="shrink-0 text-[11px] font-bold text-slate-400 hover:text-rose-600">Remove</button>
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

                            <!-- Passengers: correct parsed names, mark minors (guardian signs for them) -->
                            <div class="bg-white rounded-2xl ring-1 ring-slate-900/5 p-6 sm:p-8">
                                <div class="flex items-center justify-between gap-3 mb-1 flex-wrap">
                                    <h2 class="font-bold text-slate-900">Passengers</h2>
                                    <span v-if="claim.passengers_locked" class="text-[10px] font-black bg-slate-100 text-slate-500 px-2 py-0.5 rounded-full">LOCKED AFTER CONFIRMATION</span>
                                </div>
                                <p class="text-xs text-slate-400 mb-4">
                                    {{ claim.passengers_locked
                                        ? 'These details are on your signed authorisation documents - contact support to change them.'
                                        : 'Use each passenger\'s name exactly as on their travel document - these go on the claim papers. Mark anyone under 18 so a guardian signs for them.' }}
                                </p>

                                <div class="space-y-3">
                                    <div v-for="(p, i) in paxDrafts" :key="i"
                                         class="rounded-xl ring-1 p-4 transition-colors"
                                         :class="p.minor ? 'ring-violet-200 bg-violet-50/40' : 'ring-slate-200 bg-white'">
                                        <div class="flex items-center gap-3">
                                            <span class="w-9 h-9 rounded-full flex items-center justify-center font-black text-xs shrink-0"
                                                  :class="p.minor ? 'bg-violet-100 text-violet-700' : 'bg-slate-900 text-white'">
                                                {{ initials(p.name) }}
                                            </span>
                                            <div class="flex-1 min-w-0">
                                                <label class="block text-[10px] uppercase tracking-wider font-bold text-slate-400 mb-1">
                                                    Passenger {{ i + 1 }}<span v-if="i === 0"> - lead</span>
                                                </label>
                                                <input v-model="p.name" type="text" maxlength="190" :disabled="claim.passengers_locked"
                                                       placeholder="Full name as on the travel document"
                                                       class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium focus:outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-900/10 transition-colors disabled:bg-slate-50 disabled:text-slate-500">
                                            </div>
                                        </div>
                                        <label class="mt-3 ml-12 inline-flex items-center gap-2 text-xs font-bold select-none rounded-full px-3 py-1.5 ring-1 transition-colors"
                                               :class="[
                                                   p.minor ? 'bg-violet-100 text-violet-700 ring-violet-200' : 'bg-slate-50 text-slate-500 ring-slate-200',
                                                   claim.passengers_locked ? 'opacity-60' : 'cursor-pointer hover:ring-slate-300',
                                               ]">
                                            <input v-model="p.minor" type="checkbox" :disabled="claim.passengers_locked"
                                                   class="w-3.5 h-3.5 rounded border-slate-300 text-violet-600 focus:ring-violet-500">
                                            Under 18 - a guardian signs for them
                                        </label>
                                    </div>
                                </div>

                                <p v-if="paxError" class="text-xs font-bold text-rose-600 mt-3">{{ paxError }}</p>

                                <div v-if="!claim.passengers_locked" class="flex items-center gap-3 mt-4">
                                    <button @click="savePassengers" :disabled="savingPax || !paxDirty"
                                            class="bg-slate-900 hover:bg-slate-800 disabled:opacity-40 disabled:cursor-not-allowed text-white font-bold px-5 py-2.5 rounded-xl text-sm transition-colors">
                                        {{ savingPax ? 'Saving…' : 'Save changes' }}
                                    </button>
                                    <span v-if="paxSaved" class="inline-flex items-center gap-1 text-xs font-bold text-emerald-600">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                        Saved
                                    </span>
                                    <span v-else-if="!paxDirty" class="text-xs text-slate-400">Everything up to date</span>
                                </div>
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

                            <!-- Flight too old (or unknown) for live tracking records -->
                            <div v-else class="bg-white rounded-2xl ring-1 ring-slate-900/5 p-6 sm:p-8">
                                <h2 class="font-bold text-slate-900 mb-2">Flight tracking data</h2>
                                <p class="text-sm text-slate-600">Live flight-tracking records only reach back about 10 days, so this flight could not be verified automatically. Your claim is assessed on the details and documents you provide - your ticket, boarding pass and any airline emails carry extra weight here.</p>
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
import { computed, nextTick, onMounted, ref, watch } from 'vue';
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
    { key: 'expenses', label: 'Expenses' },
    { key: 'details', label: 'Details' },
    { key: 'emails', label: 'Email History' },
];

// ── Out-of-pocket expense receipts ──────────────────────────
const expenseForm = ref({ category: 'meal', amount: '', currency: 'EUR', expense_date: '', description: '' });
const expenseFile = ref(null);
const uploadingExpense = ref(false);
const expenseError = ref('');

const approvedExpenseTotal = computed(() => {
    const totals = {};
    (claim.value?.expenses || [])
        .filter((e) => e.status === 'approved' && e.amount)
        .forEach((e) => {
            const cur = e.currency || 'EUR';
            totals[cur] = (totals[cur] || 0) + parseFloat(e.amount);
        });
    return Object.entries(totals).map(([cur, amt]) => `${cur} ${amt.toFixed(2)}`).join(' + ');
});

function pickExpenseFile(event) {
    expenseFile.value = event.target.files?.[0] || null;
}

async function submitExpense() {
    if (!expenseFile.value) {
        expenseError.value = 'Attach a photo or PDF of the receipt.';
        return;
    }

    uploadingExpense.value = true;
    expenseError.value = '';
    try {
        const form = new FormData();
        form.append('receipt', expenseFile.value);
        Object.entries(expenseForm.value).forEach(([key, value]) => {
            if (value !== '' && value !== null) form.append(key, value);
        });
        claim.value = await api.claims.addExpense(props.id, form);
        expenseForm.value = { category: 'meal', amount: '', currency: 'EUR', expense_date: '', description: '' };
        expenseFile.value = null;
        if (document.getElementById('expense-file')) document.getElementById('expense-file').value = '';
    } catch (e) {
        expenseError.value = e.response?.data?.message || 'Could not upload the receipt. Please try again.';
    } finally {
        uploadingExpense.value = false;
    }
}

async function deleteExpense(expenseId) {
    try {
        claim.value = await api.claims.removeExpense(props.id, expenseId);
    } catch (e) {
        expenseError.value = e.response?.data?.message || 'Could not remove the receipt.';
    }
}

// ── Payout bank details ─────────────────────────────────────
const bankOpen = ref(false);
const bankSaving = ref(false);
const bankError = ref('');
const bankAccounts = ref([]);
const bank = ref({ currency: 'CAD', account_holder_name: '' });

const needsBank = computed(() =>
    !bankAccounts.value.length
    && claim.value?.eligibility
    && claim.value.eligibility.status !== 'rejected');

const savedAccount = computed(() =>
    bankAccounts.value.find((a) => a.is_default) || bankAccounts.value[0] || null);

const openTimelines = ref({});
function toggleTimeline(i) {
    openTimelines.value[i] = !openTimelines.value[i];
}

const hasPayments = computed(() => (claim.value?.payments || []).length > 0);
const showEstimate = ref(false);

async function loadBankAccounts() {
    try {
        bankAccounts.value = (await api.payoutAccounts.list()).accounts;
    } catch (e) { /* non-blocking */ }
}

function goToBank() {
    activeTab.value = 'compensation';
    bankOpen.value = true;
    // Wait for the tab to render, then bring the card into view.
    nextTick(() => {
        document.getElementById('bank-details-card')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
}

async function removeBank(currency) {
    try {
        bankAccounts.value = (await api.payoutAccounts.remove(currency)).accounts;
    } catch (e) { /* non-blocking */ }
}

async function makeDefaultBank(currency) {
    try {
        bankAccounts.value = (await api.payoutAccounts.makeDefault(currency)).accounts;
    } catch (e) { /* non-blocking */ }
}

async function saveBank() {
    bankSaving.value = true;
    bankError.value = '';
    try {
        bankAccounts.value = (await api.payoutAccounts.save(bank.value)).accounts;
        bankOpen.value = false;
    } catch (e) {
        const errors = e.response?.data?.errors;
        bankError.value = errors ? Object.values(errors)[0][0] : (e.response?.data?.message || 'Could not save the account.');
    } finally {
        bankSaving.value = false;
    }
}

const detailRows = computed(() => claim.value ? [
    { label: 'Route', value: `${claim.value.departure_airport || '-'} → ${claim.value.arrival_airport || '-'}` },
    { label: 'Airline', value: claim.value.airline },
    { label: 'Flight number', value: claim.value.flight_number },
    { label: 'Flight date', value: claim.value.flight_date },
    { label: 'Disruption', value: claim.value.disruption_label },
    { label: 'Booking reference', value: claim.value.booking_reference },
    { label: 'Claim reference', value: claim.value.reference },
    { label: 'Submitted', value: claim.value.submitted_at },
    // The journey is what people mean by "status"; the engine's verdict is a
    // separate fact, so it gets its own row rather than overwriting it.
    { label: 'Status', value: claim.value.stage_label || claim.value.status_label },
    { label: 'Eligibility', value: claim.value.status_label },
] : []);

const entitlements = computed(() => claim.value?.compensation?.breakdown?.entitlements || []);

const payoutNames = computed(() => {
    const names = claim.value?.passengers || [];
    return names.length ? names : [claim.value?.passenger_name].filter(Boolean);
});

const paxDrafts = ref([]);
const paxError = ref('');
const savingPax = ref(false);
const paxSaved = ref(false);

watch(() => claim.value?.passenger_list, (list) => {
    paxDrafts.value = (list || []).map((p) => ({ name: p.name, minor: !!p.minor }));
}, { immediate: true, deep: true });

const paxDirty = computed(() => {
    const saved = claim.value?.passenger_list || [];
    return paxDrafts.value.some((p, i) => p.name !== (saved[i]?.name ?? '') || p.minor !== !!saved[i]?.minor);
});

function initials(name) {
    const words = (name || '').replace(/^(mr|mrs|ms|miss|dr)\.?\s+/i, '').split(/\s+/).filter(Boolean);
    return (words.length >= 2 ? words[0][0] + words[words.length - 1][0] : (words[0] || '?').slice(0, 2)).toUpperCase();
}

async function savePassengers() {
    if (paxDrafts.value.some((p) => (p.name || '').trim().length < 2)) {
        paxError.value = 'Every passenger needs a name.';
        return;
    }
    paxError.value = '';
    savingPax.value = true;
    try {
        claim.value = await api.claims.updatePassengers(props.id, paxDrafts.value.map((p) => ({ name: p.name.trim(), minor: p.minor })));
        paxSaved.value = true;
        setTimeout(() => (paxSaved.value = false), 2500);
    } catch (e) {
        paxError.value = e.response?.data?.message || 'Could not save the passengers. Please try again.';
    } finally {
        savingPax.value = false;
    }
}

const isBookingTotal = computed(() => (claim.value?.payout?.passenger_count || 1) > 1);
const heroCompensation = computed(() => isBookingTotal.value
    ? `${claim.value.payout.currency} ${claim.value.payout.gross}`
    : claim.value?.compensation?.display);

function entitlementCls(state) {
    if (state === 'included') return { box: 'bg-emerald-50 ring-emerald-200', label: 'text-emerald-600', value: 'text-emerald-800', badge: 'bg-emerald-100 text-emerald-700' };
    if (state === 'conditional') return { box: 'bg-amber-50 ring-amber-200', label: 'text-amber-600', value: 'text-amber-800', badge: 'bg-amber-100 text-amber-700' };
    return { box: 'bg-slate-50 ring-slate-200 opacity-70', label: 'text-slate-400', value: 'text-slate-500', badge: 'bg-slate-200 text-slate-500' };
}

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
onMounted(() => {
    load();
    loadBankAccounts();
});
</script>
