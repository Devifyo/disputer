{{-- Wrap everything in one Alpine scope so the modal can overlay the full page --}}
<div x-data="paymentMethodModal()" @keydown.escape.window="closeModal()">

{{-- ===================== CURRENT PLAN CARD ===================== --}}
<div class="p-6 sm:p-8 bg-white shadow-sm ring-1 ring-slate-900/5 sm:rounded-xl border-t-4 border-primary-500">
    <h3 class="text-lg font-bold text-slate-900 mb-4">Current Subscription</h3>

    @if($currentSubscription)
        <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 mb-1.5">
                    <span class="text-xl font-extrabold text-slate-900">{{ $currentSubscription->plan->name }}</span>
                    @if($currentSubscription->canceled_at)
                        <span class="bg-amber-100 text-amber-700 px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider border border-amber-200">Canceling</span>
                    @else
                        <span class="bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider border border-emerald-200">Active</span>
                    @endif
                </div>

                <div class="text-sm text-slate-500">
                    @if($currentSubscription->plan->type === 'recurring_yearly')
                        @if($currentSubscription->canceled_at)
                            <p class="text-slate-600">Access ends on <span class="font-bold text-slate-900">{{ $currentSubscription->expires_at->format('M d, Y') }}</span></p>
                            <p class="text-xs text-amber-600 font-medium mt-0.5">Your plan will not auto-renew.</p>
                        @else
                            <p>Renews on <span class="font-medium text-slate-700">{{ $currentSubscription->expires_at->format('M d, Y') }}</span></p>

                            <div class="mt-3 flex flex-wrap gap-2">
                                {{-- UPDATE PAYMENT METHOD → opens modal --}}
                                <button type="button" @click="openModal()"
                                        class="px-3.5 py-1.5 bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 hover:border-slate-300 rounded-lg text-xs font-bold transition-all flex items-center gap-1.5 shadow-sm">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" x2="22" y1="10" y2="10"/></svg>
                                    Update Payment Method
                                </button>

                                {{-- CANCEL SUBSCRIPTION --}}
                                <button type="button" onclick="confirmCancelSubscription()"
                                        class="px-3.5 py-1.5 bg-white border border-rose-200 text-rose-600 hover:bg-rose-50 hover:border-rose-300 rounded-lg text-xs font-bold transition-all flex items-center gap-1.5 shadow-sm group">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="group-hover:rotate-90 transition-transform"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                                    Cancel Subscription
                                </button>
                            </div>

                            <form id="cancel-subscription-form" action="{{ route('subscription.cancel') }}" method="POST" class="hidden">
                                @csrf
                            </form>
                        @endif
                    @else
                        <p>One-time purchase bundle. Does not expire.</p>
                    @endif
                </div>
            </div>

            {{-- Cases Remaining --}}
            <div class="bg-slate-50 rounded-xl p-4 text-center border border-slate-200 min-w-[140px] shadow-inner">
                <span class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Cases Remaining</span>
                @if($caseStatus->has_unlimited)
                    <span class="text-2xl font-black text-primary-600">Unlimited</span>
                @else
                    <span class="text-3xl font-black text-primary-600 leading-none">{{ $caseStatus->total_remaining }}</span>
                    @if($caseStatus->total_allowed > 0)
                        <span class="text-sm font-bold text-slate-400">/ {{ $caseStatus->total_allowed }}</span>
                    @endif
                @endif
            </div>
        </div>
    @else
        <div class="flex flex-col items-center justify-center py-6 text-center">
            <div class="w-12 h-12 bg-slate-100 rounded-full flex items-center justify-center text-slate-400 mb-3">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" x2="22" y1="10" y2="10"/></svg>
            </div>
            <p class="text-slate-900 font-bold mb-1">No Active Plan</p>
            <p class="text-sm text-slate-500">You are not currently subscribed to any plan. Choose a plan below to start submitting cases.</p>
        </div>
    @endif
</div>

{{-- ===================== AVAILABLE PLANS ===================== --}}
<div class="mt-8">
    <h3 class="text-lg font-bold text-slate-900 mb-4 px-1">Available Plans</h3>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        @foreach($plans as $plan)
            <div class="bg-white rounded-2xl p-6 sm:p-8 shadow-sm ring-1 ring-slate-900/5 flex flex-col relative {{ $plan->type === 'recurring_yearly' ? 'border-2 border-primary-500 shadow-md' : '' }}">

                @if($plan->type === 'recurring_yearly')
                    <span class="absolute -top-3 left-1/2 -translate-x-1/2 bg-primary-500 text-white px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider shadow-sm">Best Value</span>
                @endif

                <div class="mb-6">
                    <h4 class="text-xl font-bold text-slate-900">{{ $plan->name }}</h4>
                    <div class="mt-2 flex items-baseline gap-1">
                        <span class="text-3xl font-black text-slate-900">${{ number_format($plan->price, 2) }}</span>
                        <span class="text-sm font-medium text-slate-500">{{ $plan->type === 'recurring_yearly' ? '/ year' : 'one-time' }}</span>
                    </div>
                </div>

                <ul class="space-y-3 mb-8 flex-1">
                    @if($plan->features)
                        @foreach($plan->features as $feature)
                            <li class="flex items-start gap-3 text-sm text-slate-600">
                                <svg class="w-5 h-5 text-emerald-500 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                {{ $feature }}
                            </li>
                        @endforeach
                    @endif
                </ul>

                @if($caseStatus->has_unlimited)
                    @if($plan->type === 'recurring_yearly')
                        @if($currentSubscription && $currentSubscription->plan_id === $plan->id && $currentSubscription->canceled_at)
                            <form action="{{ route('subscription.resume') }}" method="POST" class="w-full">
                                @csrf
                                <button type="submit" class="w-full block text-center py-3 px-4 rounded-xl text-sm font-bold transition-all bg-emerald-600 text-white hover:bg-emerald-700 shadow-lg shadow-emerald-600/20">
                                    Resume Subscription
                                </button>
                            </form>
                        @else
                            <button disabled class="w-full py-3 px-4 rounded-xl text-sm font-bold bg-slate-100 text-slate-400 cursor-not-allowed border border-slate-200">
                                Current Plan
                            </button>
                        @endif
                    @else
                        <button disabled title="You already have unlimited cases." class="w-full py-3 px-4 rounded-xl text-sm font-bold bg-slate-50 text-slate-400 cursor-not-allowed border border-slate-200">
                            Not needed (Unlimited active)
                        </button>
                    @endif
                @else
                    <a href="{{ route('checkout', $plan->slug) }}" class="w-full block text-center py-3 px-4 rounded-xl text-sm font-bold transition-all {{ $plan->type === 'recurring_yearly' ? 'bg-primary-600 text-white hover:bg-primary-700 shadow-lg shadow-primary-600/20' : 'bg-slate-900 text-white hover:bg-slate-800 shadow-md' }}">
                        {{ $plan->type === 'recurring_yearly' ? 'Subscribe Now' : 'Buy Now' }}
                    </a>
                @endif

            </div>
        @endforeach
    </div>
</div>

{{-- ===================== PAYMENT METHODS MODAL ===================== --}}
<div x-show="modalOpen"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="fixed inset-0 z-50 flex items-center justify-center p-4"
     style="display: none;">

    {{-- Backdrop --}}
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="closeModal()"></div>

    {{-- Modal Panel --}}
    <div x-show="modalOpen"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95 translate-y-2"
         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100 translate-y-0"
         x-transition:leave-end="opacity-0 scale-95 translate-y-2"
         class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md z-10 overflow-hidden">

        {{-- Modal Header --}}
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 bg-primary-50 rounded-lg flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-primary-600"><rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" x2="22" y1="10" y2="10"/></svg>
                </div>
                <h2 class="text-base font-bold text-slate-900">Payment Methods</h2>
            </div>
            <button type="button" @click="closeModal()" class="text-slate-400 hover:text-slate-600 transition-colors p-1 rounded-lg hover:bg-slate-100">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
            </button>
        </div>

        {{-- Modal Body --}}
        <div class="px-6 py-5 max-h-[70vh] overflow-y-auto">

            {{-- Global success --}}
            <div x-show="successMsg"
                 class="mb-4 p-3 bg-emerald-50 border border-emerald-200 rounded-xl flex items-center gap-2 text-sm text-emerald-700 font-medium">
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                <span x-text="successMsg"></span>
            </div>

            {{-- Global error --}}
            <div x-show="errorMsg && !saving"
                 class="mb-4 p-3 bg-rose-50 border border-rose-200 rounded-xl flex items-center gap-2 text-sm text-rose-700 font-medium">
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="12"/><line x1="12" x2="12.01" y1="16" y2="16"/></svg>
                <span x-text="errorMsg"></span>
            </div>

            {{-- Loading skeleton --}}
            <div x-show="loading" class="space-y-3">
                <div class="h-16 bg-slate-100 rounded-xl animate-pulse"></div>
                <div class="h-16 bg-slate-100 rounded-xl animate-pulse opacity-70"></div>
            </div>

            {{-- ---- Saved cards ---- --}}
            <div x-show="!loading">

                <template x-if="paymentMethods.length > 0">
                    <div>
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-3">Saved Cards</p>
                        <div class="space-y-2">
                            <template x-for="pm in paymentMethods" :key="pm.id">
                                <div class="flex items-center justify-between gap-3 p-3.5 rounded-xl border transition-all"
                                     :class="pm.is_default ? 'border-primary-200 bg-primary-50' : 'border-slate-200 bg-white hover:border-slate-300'">

                                    <div class="flex items-center gap-3 min-w-0">
                                        {{-- Brand icon --}}
                                        <div class="w-10 h-7 rounded-md flex items-center justify-center text-[10px] font-black uppercase tracking-wide shrink-0 border"
                                             :class="{
                                                'bg-blue-600 text-white border-blue-600': pm.brand === 'visa',
                                                'bg-slate-900 text-white border-slate-900': pm.brand === 'mastercard',
                                                'bg-sky-500 text-white border-sky-500': pm.brand === 'amex',
                                                'bg-amber-500 text-white border-amber-500': pm.brand === 'discover',
                                                'bg-slate-100 text-slate-600 border-slate-200': !['visa','mastercard','amex','discover'].includes(pm.brand)
                                             }"
                                             x-text="pm.brand === 'mastercard' ? 'MC' : pm.brand.substring(0, 4)">
                                        </div>
                                        <div class="min-w-0">
                                            <p class="text-sm font-bold text-slate-800 capitalize" x-text="pm.brand + ' ···· ' + pm.last4"></p>
                                            <p class="text-xs text-slate-400" x-text="'Expires ' + pm.exp_month + '/' + pm.exp_year"></p>
                                        </div>
                                    </div>

                                    <div class="shrink-0 flex items-center gap-1.5">
                                        <template x-if="pm.is_default">
                                            <span class="px-2 py-1 bg-primary-100 text-primary-700 text-[10px] font-bold uppercase tracking-wide rounded-lg border border-primary-200">
                                                Default
                                            </span>
                                        </template>
                                        <template x-if="!pm.is_default">
                                            <div class="flex items-center gap-1.5">
                                                <button type="button"
                                                        @click="setDefault(pm.id)"
                                                        :disabled="settingDefaultId === pm.id || removingId === pm.id"
                                                        class="px-3 py-1.5 bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 hover:border-primary-300 hover:text-primary-700 rounded-lg text-xs font-bold transition-all flex items-center gap-1.5 disabled:opacity-50">
                                                    <svg x-show="settingDefaultId === pm.id" class="animate-spin w-3 h-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                                    </svg>
                                                    Use this card
                                                </button>
                                                <button type="button"
                                                        @click="removeCard(pm.id)"
                                                        :disabled="removingId === pm.id || settingDefaultId === pm.id"
                                                        class="p-1.5 text-slate-400 hover:text-rose-500 hover:bg-rose-50 rounded-lg transition-all disabled:opacity-50"
                                                        title="Remove card">
                                                    <svg x-show="removingId !== pm.id" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                                                    <svg x-show="removingId === pm.id" class="animate-spin w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                                    </svg>
                                                </button>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>

                {{-- ---- Divider ---- --}}
                <div class="flex items-center gap-3 my-5">
                    <div class="flex-1 h-px bg-slate-200"></div>
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-widest">Add New Card</span>
                    <div class="flex-1 h-px bg-slate-200"></div>
                </div>

                {{-- ---- New card form ---- --}}
                <div>
                    <div class="border border-slate-300 rounded-xl px-4 py-3.5 bg-white focus-within:ring-2 focus-within:ring-primary-500 focus-within:border-primary-500 transition-all">
                        <div id="pm-card-element"></div>
                    </div>

                    <p x-show="cardError" x-text="cardError" class="mt-2 text-xs font-bold text-rose-600"></p>

                    <button type="button"
                            @click="saveNewCard()"
                            :disabled="saving || !cardReady"
                            class="mt-4 w-full py-2.5 px-4 bg-primary-600 text-white text-sm font-bold rounded-xl hover:bg-primary-700 transition-all shadow-md disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2">
                        <svg x-show="saving" class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        <span x-text="saving ? 'Saving...' : 'Save New Card'"></span>
                    </button>
                </div>

            </div>
        </div>
    </div>
</div>

</div>{{-- end x-data --}}

@push('scripts')
<script src="https://js.stripe.com/v3/"></script>
<script>
    function confirmCancelSubscription() {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Cancel Subscription?',
                text: "Your plan will remain active until the end of your current billing cycle. You will not be charged again.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Yes, cancel it',
                cancelButtonText: 'Keep it',
                customClass: { popup: 'rounded-2xl', confirmButton: 'rounded-lg font-bold', cancelButton: 'rounded-lg font-bold' }
            }).then((result) => {
                if (result.isConfirmed) document.getElementById('cancel-subscription-form').submit();
            });
        } else {
            if (confirm("Are you sure you want to cancel your subscription?")) {
                document.getElementById('cancel-subscription-form').submit();
            }
        }
    }

    function paymentMethodModal() {
        return {
            modalOpen: false,
            loading: false,
            saving: false,
            settingDefaultId: null,
            removingId: null,
            paymentMethods: [],
            stripeInstance: null,
            cardElement: null,
            clientSecret: null,
            cardReady: false,
            cardError: '',
            errorMsg: '',
            successMsg: '',

            async openModal() {
                this.modalOpen = true;
                this.loading = true;
                this.errorMsg = '';
                this.successMsg = '';
                this.cardError = '';
                this.cardReady = false;
                document.body.style.overflow = 'hidden';

                try {
                    // Fetch saved payment methods and a fresh SetupIntent in parallel
                    const [pmRes, siRes] = await Promise.all([
                        fetch('{{ route('billing.payment-methods') }}', {
                            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
                        }),
                        fetch('{{ route('billing.setup-intent') }}', {
                            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
                        })
                    ]);

                    const pmData = await pmRes.json();
                    const siData = await siRes.json();

                    if (pmData.error) { this.errorMsg = pmData.error; return; }
                    if (siData.error) { this.errorMsg = siData.error; return; }

                    this.paymentMethods = pmData.payment_methods;
                    this.clientSecret   = siData.client_secret;

                    // Init Stripe card element (no postal code)
                    this.stripeInstance = Stripe('{{ config('services.stripe.key') }}');
                    const elements = this.stripeInstance.elements();
                    this.cardElement = elements.create('card', {
                        hidePostalCode: true,
                        style: {
                            base: {
                                fontSize: '15px',
                                color: '#1e293b',
                                fontFamily: 'Inter, sans-serif',
                                '::placeholder': { color: '#94a3b8' }
                            },
                            invalid: { color: '#ef4444' }
                        }
                    });

                    await this.$nextTick();
                    this.cardElement.mount('#pm-card-element');
                    this.cardElement.on('change', (event) => {
                        this.cardError = event.error ? event.error.message : '';
                        this.cardReady = event.complete;
                    });

                } catch (e) {
                    this.errorMsg = 'Failed to load payment methods. Please try again.';
                } finally {
                    this.loading = false;
                }
            },

            closeModal() {
                this.modalOpen = false;
                document.body.style.overflow = '';
                if (this.cardElement) {
                    this.cardElement.unmount();
                    this.cardElement = null;
                }
                this.cardReady  = false;
                this.cardError  = '';
                this.errorMsg   = '';
                this.successMsg = '';
            },

            async setDefault(pmId) {
                this.settingDefaultId = pmId;
                this.errorMsg = '';

                try {
                    const res = await fetch('{{ route('billing.set-default-payment-method') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify({ payment_method_id: pmId })
                    });
                    const data = await res.json();

                    if (data.error) { this.errorMsg = data.error; return; }

                    // Update local state so UI reflects new default instantly
                    this.paymentMethods = this.paymentMethods.map(pm => ({
                        ...pm,
                        is_default: pm.id === pmId
                    }));
                    this.successMsg = 'Default payment method updated.';
                    setTimeout(() => { this.successMsg = ''; }, 3000);

                } catch (e) {
                    this.errorMsg = 'An error occurred. Please try again.';
                } finally {
                    this.settingDefaultId = null;
                }
            },

            async removeCard(pmId) {
                this.removingId = pmId;
                this.errorMsg = '';

                try {
                    const res = await fetch('{{ route('billing.remove-payment-method') }}', {
                        method: 'DELETE',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify({ payment_method_id: pmId })
                    });
                    const data = await res.json();

                    if (data.error) { this.errorMsg = data.error; return; }

                    this.paymentMethods = this.paymentMethods.filter(pm => pm.id !== pmId);
                    this.successMsg = 'Card removed.';
                    setTimeout(() => { this.successMsg = ''; }, 3000);

                } catch (e) {
                    this.errorMsg = 'An error occurred. Please try again.';
                } finally {
                    this.removingId = null;
                }
            },

            async saveNewCard() {
                this.saving = true;
                this.errorMsg = '';
                this.cardError = '';

                try {
                    const result = await this.stripeInstance.confirmCardSetup(this.clientSecret, {
                        payment_method: { card: this.cardElement }
                    });

                    if (result.error) {
                        this.cardError = result.error.message;
                        return;
                    }

                    const res = await fetch('{{ route('billing.update-payment-method') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify({ payment_method_id: result.setupIntent.payment_method })
                    });
                    const data = await res.json();

                    if (data.error) { this.cardError = data.error; return; }

                    this.successMsg = 'New card saved and set as default.';

                    // Refresh the saved cards list
                    const pmRes = await fetch('{{ route('billing.payment-methods') }}', {
                        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
                    });
                    const pmData = await pmRes.json();
                    if (!pmData.error) this.paymentMethods = pmData.payment_methods;

                    // Clear the card element and get a fresh SetupIntent for next time
                    this.cardElement.clear();
                    this.cardReady = false;

                    setTimeout(() => { this.successMsg = ''; }, 3000);

                } catch (e) {
                    this.cardError = 'An unexpected error occurred. Please try again.';
                } finally {
                    this.saving = false;
                }
            }
        }
    }
</script>
@endpush
