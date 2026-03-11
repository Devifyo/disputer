{{-- Current Plan Status Card --}}
<div class="p-6 sm:p-8 bg-white shadow-sm ring-1 ring-slate-900/5 sm:rounded-xl border-t-4 border-primary-500">
    <h3 class="text-lg font-bold text-slate-900 mb-4">Current Subscription</h3>
    
    @if($currentSubscription)
        <div class="flex flex-col sm:flex-row sm:items-start sm:items-center justify-between gap-4">
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
                            {{-- EDGE CASE: Canceled but still active until period end --}}
                            <p class="text-slate-600">Access ends on <span class="font-bold text-slate-900">{{ $currentSubscription->expires_at->format('M d, Y') }}</span></p>
                            <p class="text-xs text-amber-600 font-medium mt-0.5">Your plan will not auto-renew.</p>
                        @else
                            {{-- EDGE CASE: Active and auto-renewing --}}
                            <p>Renews on <span class="font-medium text-slate-700">{{ $currentSubscription->expires_at->format('M d, Y') }}</span></p>
                            
                            {{-- BEAUTIFUL CANCEL SUBSCRIPTION BUTTON --}}
                            <button type="button" onclick="confirmCancelSubscription()" class="mt-3 px-3.5 py-1.5 bg-white border border-rose-200 text-rose-600 hover:bg-rose-50 hover:border-rose-300 rounded-lg text-xs font-bold transition-all flex items-center gap-1.5 shadow-sm group">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="group-hover:rotate-90 transition-transform"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                                Cancel Subscription
                            </button>

                            {{-- HIDDEN FORM TO SUBMIT CANCELLATION --}}
                            <form id="cancel-subscription-form" action="{{ route('subscription.cancel') }}" method="POST" class="hidden">
                                @csrf
                            </form>
                        @endif

                    @else
                        {{-- EDGE CASE: One-time bundle --}}
                        <p>One-time purchase bundle. Does not expire.</p>
                    @endif
                </div>
            </div>
            
            {{-- Remaining Cases Display --}}
            <div class="bg-slate-50 rounded-xl p-4 text-center border border-slate-200 min-w-[140px] shadow-inner">
                <span class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Cases Remaining</span>
                @if($currentSubscription->plan->type === 'recurring_yearly')
                    <span class="text-2xl font-black text-primary-600">Unlimited</span>
                @else
                    <span class="text-3xl font-black text-primary-600 leading-none">
                        {{ max(0, $currentSubscription->cases_allowed - $currentSubscription->cases_used) }}
                    </span>
                    <span class="text-sm font-bold text-slate-400">/ {{ $currentSubscription->cases_allowed }}</span>
                @endif
            </div>
        </div>
    @else
        {{-- EDGE CASE: Completely empty state (No plan) --}}
        <div class="flex flex-col items-center justify-center py-6 text-center">
            <div class="w-12 h-12 bg-slate-100 rounded-full flex items-center justify-center text-slate-400 mb-3">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" x2="22" y1="10" y2="10"/></svg>
            </div>
            <p class="text-slate-900 font-bold mb-1">No Active Plan</p>
            <p class="text-sm text-slate-500">You are not currently subscribed to any plan. Choose a plan below to start submitting cases.</p>
        </div>
    @endif
</div>

{{-- Available Plans Grid --}}
<div class="mt-8">
    <h3 class="text-lg font-bold text-slate-900 mb-4 px-1">Available Plans</h3>
    
    @php
        // Helper booleans to manage grid logic cleanly
        $hasActiveYearlyPlan = $currentSubscription && $currentSubscription->plan->type === 'recurring_yearly' && !$currentSubscription->canceled_at;
    @endphp

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

                {{-- The Absolute Edge-Case-Proof Purchase Logic Engine --}}
                @if($currentSubscription && $currentSubscription->plan_id === $plan->id && !$currentSubscription->canceled_at)
                    {{-- Scenario A: They own this plan and it is happily active --}}
                    <button disabled class="w-full py-3 px-4 rounded-xl text-sm font-bold bg-slate-100 text-slate-400 cursor-not-allowed border border-slate-200">
                        Current Plan
                    </button>
                    
                @elseif($currentSubscription && $currentSubscription->plan_id === $plan->id && $currentSubscription->canceled_at)
                    {{-- Scenario B: They own this plan but canceled it. Let them safely resume without checking out again! --}}
                    <form action="{{ route('subscription.resume') }}" method="POST" class="w-full">
                        @csrf
                        <button type="submit" class="w-full block text-center py-3 px-4 rounded-xl text-sm font-bold transition-all bg-emerald-600 text-white hover:bg-emerald-700 shadow-lg shadow-emerald-600/20">
                            Resume Subscription
                        </button>
                    </form>
                    
                @elseif($hasActiveYearlyPlan && $plan->type !== 'recurring_yearly')
                    {{-- Scenario C: They have an active yearly plan. Do not let them waste money on 1-case bundles. --}}
                    <button disabled title="You already have unlimited cases." class="w-full py-3 px-4 rounded-xl text-sm font-bold bg-slate-50 text-slate-400 cursor-not-allowed border border-slate-200">
                        Not needed (Unlimited active)
                    </button>
                    
                @else
                    {{-- Scenario D: Standard Buy/Subscribe Link (For upgrades, first-time buys, or buying more one-time cases) --}}
                    <a href="{{ route('checkout', $plan->slug) }}" class="w-full block text-center py-3 px-4 rounded-xl text-sm font-bold transition-all {{ $plan->type === 'recurring_yearly' ? 'bg-primary-600 text-white hover:bg-primary-700 shadow-lg shadow-primary-600/20' : 'bg-slate-900 text-white hover:bg-slate-800 shadow-md' }}">
                        {{ $plan->type === 'recurring_yearly' ? 'Subscribe Now' : 'Buy Now' }}
                    </a>
                @endif

            </div>
        @endforeach
    </div>
</div>

{{-- SweetAlert Logic for Cancellation --}}
@push('scripts')
<script>
    function confirmCancelSubscription() {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Cancel Subscription?',
                text: "Your plan will remain active until the end of your current billing cycle. You will not be charged again.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444', // Tailwind rose-500
                cancelButtonColor: '#64748b',  // Tailwind slate-500
                confirmButtonText: 'Yes, cancel it',
                cancelButtonText: 'Keep it',
                customClass: {
                    popup: 'rounded-2xl',
                    confirmButton: 'rounded-lg font-bold',
                    cancelButton: 'rounded-lg font-bold'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('cancel-subscription-form').submit();
                }
            });
        } else {
            // Fallback for native browser confirm if SweetAlert fails to load
            if (confirm("Are you sure you want to cancel your subscription? It will remain active until the end of your billing cycle.")) {
                document.getElementById('cancel-subscription-form').submit();
            }
        }
    }
</script>
@endpush