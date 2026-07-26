<div class="h-full overflow-y-auto bg-slate-50/50">
    <div class="max-w-[1280px] mx-auto p-6 pb-24">
        <x-flash />

        <div class="flex items-start justify-between gap-4 flex-wrap mb-6">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Subscriptions</h1>
                <p class="text-sm text-slate-500 mt-1">Unjamm Plus membership - plans, feature access and billing. Independent of claim compensation and success fees.</p>
            </div>

            {{-- Master switch --}}
            <label class="flex items-center gap-3 bg-white rounded-2xl border border-slate-200 shadow-sm px-5 py-3.5 cursor-pointer">
                <div>
                    <span class="block text-sm font-bold text-slate-900">Subscription system</span>
                    <span class="block text-[11px] {{ $systemEnabled ? 'text-emerald-600 font-bold' : 'text-slate-400' }}">
                        {{ $systemEnabled ? 'Enabled - premium features require Plus' : 'Disabled - everything is free for everyone' }}
                    </span>
                </div>
                <input type="checkbox" wire:model.live="systemEnabled" class="sr-only peer">
                <span class="relative w-11 h-6 rounded-full transition-colors {{ $systemEnabled ? 'bg-emerald-500' : 'bg-slate-300' }}">
                    <span class="absolute top-0.5 w-5 h-5 bg-white rounded-full shadow transition-all {{ $systemEnabled ? 'left-[22px]' : 'left-0.5' }}"></span>
                </span>
            </label>
        </div>

        @unless ($stripeReady)
            <div class="flex items-start gap-2.5 bg-amber-50 border border-amber-200 text-amber-800 rounded-2xl px-5 py-3.5 text-sm mb-6">
                <i data-lucide="triangle-alert" class="w-4 h-4 shrink-0 mt-0.5"></i>
                <span>Stripe keys are not configured (<code class="font-mono text-xs">STRIPE_SECRET</code>). Plans can be prepared now; checkout and billing actions activate once the keys are set.</span>
            </div>
        @endunless

        {{-- Tabs --}}
        <div class="inline-flex items-center gap-1 bg-white rounded-xl border border-slate-200 shadow-sm p-1 mb-6">
            @foreach (['overview' => 'Overview', 'plans' => 'Plans', 'features' => 'Feature access', 'subscribers' => 'Subscribers'] as $key => $label)
                <button wire:click="$set('tab', '{{ $key }}')"
                        class="px-4 py-2 rounded-lg text-sm font-bold transition-all {{ $tab === $key ? 'bg-slate-900 text-white shadow' : 'text-slate-500 hover:text-slate-800' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        {{-- OVERVIEW --}}
        @if ($tab === 'overview')
            <div class="grid sm:grid-cols-2 xl:grid-cols-4 gap-4">
                @foreach ([
                    ['Total subscribers', $stats['total'], 'users', 'text-slate-900'],
                    ['Active', $stats['active'], 'badge-check', 'text-emerald-600'],
                    ['Cancelled / cancelling', $stats['cancelled'], 'user-x', 'text-rose-600'],
                    ['Failed payments', $stats['failed'], 'credit-card', 'text-amber-600'],
                ] as [$label, $value, $icon, $cls])
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
                        <div class="flex items-center justify-between">
                            <p class="text-[11px] uppercase tracking-wider font-bold text-slate-400">{{ $label }}</p>
                            <i data-lucide="{{ $icon }}" class="w-4 h-4 text-slate-300"></i>
                        </div>
                        <p class="text-3xl font-bold mt-2 {{ $cls }}">{{ $value }}</p>
                    </div>
                @endforeach

                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 sm:col-span-2">
                    <p class="text-[11px] uppercase tracking-wider font-bold text-slate-400">Monthly recurring revenue</p>
                    <p class="text-2xl font-bold text-slate-900 mt-2">{{ $stats['monthly'] }}</p>
                    <p class="text-[11px] text-slate-400 mt-1">Active monthly-billing subscriptions at current plan prices.</p>
                </div>
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 sm:col-span-2">
                    <p class="text-[11px] uppercase tracking-wider font-bold text-slate-400">Annual recurring revenue</p>
                    <p class="text-2xl font-bold text-slate-900 mt-2">{{ $stats['annual'] }}</p>
                    <p class="text-[11px] text-slate-400 mt-1">Active annual-billing subscriptions at current plan prices. Expired: {{ $stats['expired'] }}.</p>
                </div>
            </div>
        @endif

        {{-- PLANS --}}
        @if ($tab === 'plans')
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between gap-3">
                    <div>
                        <h2 class="font-bold text-slate-900 text-sm">Plans</h2>
                        <p class="text-[11px] text-slate-400 mt-0.5">Prices shown to customers and the Stripe IDs behind them - nothing is hardcoded.</p>
                    </div>
                    <button wire:click="editPlan" class="inline-flex items-center gap-2 bg-slate-900 hover:bg-slate-800 text-white text-sm font-bold px-4 py-2 rounded-xl transition-colors">
                        <i data-lucide="plus" class="w-4 h-4"></i> New plan
                    </button>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-[11px] uppercase tracking-wider text-slate-400 border-b border-slate-100">
                                <th class="px-6 py-3 font-bold">Plan</th>
                                <th class="px-4 py-3 font-bold">Monthly</th>
                                <th class="px-4 py-3 font-bold">Annual</th>
                                <th class="px-4 py-3 font-bold">Trial</th>
                                <th class="px-4 py-3 font-bold">Stripe</th>
                                <th class="px-4 py-3 font-bold">Status</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            @foreach ($plans as $planRow)
                                <tr class="hover:bg-slate-50/60 transition-colors">
                                    <td class="px-6 py-3.5">
                                        <span class="font-bold text-slate-800">{{ $planRow->name }}</span>
                                        <span class="block text-[11px] text-slate-400 font-mono">{{ $planRow->key }}</span>
                                    </td>
                                    <td class="px-4 py-3.5 text-slate-600">{{ $planRow->monthly_price !== null ? $planRow->currency . ' ' . number_format($planRow->monthly_price, 2) : '-' }}</td>
                                    <td class="px-4 py-3.5 text-slate-600">{{ $planRow->annual_price !== null ? $planRow->currency . ' ' . number_format($planRow->annual_price, 2) : '-' }}</td>
                                    <td class="px-4 py-3.5 text-slate-600">{{ $planRow->trial_days ? $planRow->trial_days . ' days' : '-' }}</td>
                                    <td class="px-4 py-3.5">
                                        @php $wired = $planRow->stripe_monthly_price_id || $planRow->stripe_annual_price_id; @endphp
                                        <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-black ring-1 {{ $wired ? 'bg-emerald-50 text-emerald-700 ring-emerald-200' : 'bg-amber-50 text-amber-700 ring-amber-200' }}">
                                            {{ $wired ? 'CONNECTED' : 'SYNCS ON SAVE' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-black ring-1 {{ $planRow->is_active ? 'bg-emerald-50 text-emerald-700 ring-emerald-200' : 'bg-slate-100 text-slate-500 ring-slate-200' }}">
                                            {{ $planRow->is_active ? 'ACTIVE' : 'INACTIVE' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3.5 text-right whitespace-nowrap">
                                        <button wire:click="editPlan({{ $planRow->id }})" class="text-[11px] font-bold text-primary-600 hover:underline mr-3">
                                            <span wire:loading.remove wire:target="editPlan({{ $planRow->id }})">Edit</span>
                                            <span wire:loading wire:target="editPlan({{ $planRow->id }})">…</span>
                                        </button>
                                        <button wire:click="togglePlan({{ $planRow->id }})" class="text-[11px] font-bold {{ $planRow->is_active ? 'text-slate-400 hover:text-rose-600' : 'text-emerald-600' }} hover:underline">
                                            {{ $planRow->is_active ? 'Deactivate' : 'Activate' }}
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- FEATURE ACCESS --}}
        @if ($tab === 'features')
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 max-w-3xl">
                <h2 class="font-bold text-slate-900 text-sm">Which features require Unjamm Plus?</h2>
                <p class="text-xs text-slate-500 mt-1 mb-5">
                    Unticked features stay free for everyone. The whole list is ignored while the subscription system is disabled.
                    @unless ($systemEnabled)
                        <span class="font-bold text-amber-600">System currently disabled - these gates are not being enforced.</span>
                    @endunless
                </p>
                <ul class="space-y-2.5">
                    @foreach ($featureList as $feature => $label)
                        <li>
                            <label class="flex items-start gap-3 rounded-xl border p-3.5 cursor-pointer transition-colors {{ ($features[$feature] ?? false) ? 'border-slate-900 bg-slate-50' : 'border-slate-200 hover:border-slate-300' }}">
                                <input type="checkbox" wire:model.live="features.{{ $feature }}"
                                       class="mt-0.5 rounded border-slate-300 text-slate-900 focus:ring-slate-900">
                                <span>
                                    <span class="block text-sm font-bold text-slate-800">{{ \Illuminate\Support\Str::of($label)->before(' - ') }}</span>
                                    <span class="block text-[11px] text-slate-400">{{ \Illuminate\Support\Str::of($label)->contains(' - ') ? \Illuminate\Support\Str::of($label)->after(' - ') : '' }}</span>
                                </span>
                                <span class="ml-auto shrink-0 inline-flex px-2 py-0.5 rounded-full text-[10px] font-black ring-1 {{ ($features[$feature] ?? false) ? 'bg-violet-50 text-violet-700 ring-violet-200' : 'bg-emerald-50 text-emerald-700 ring-emerald-200' }}">
                                    {{ ($features[$feature] ?? false) ? 'PLUS ONLY' : 'FREE' }}
                                </span>
                            </label>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- SUBSCRIBERS --}}
        @if ($tab === 'subscribers')
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between gap-3 flex-wrap">
                    <h2 class="font-bold text-slate-900 text-sm">Subscribers</h2>
                    <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search name or email…"
                           class="w-64 px-3.5 py-2 rounded-xl border border-slate-200 text-sm focus:border-primary-500 outline-none">
                </div>

                @if ($subscribers->isEmpty())
                    <p class="px-6 py-10 text-sm text-slate-400 text-center">No subscriptions yet. They appear here the moment a customer completes Stripe Checkout.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-left text-[11px] uppercase tracking-wider text-slate-400 border-b border-slate-100">
                                    <th class="px-6 py-3 font-bold">Customer</th>
                                    <th class="px-4 py-3 font-bold">Plan</th>
                                    <th class="px-4 py-3 font-bold">Status</th>
                                    <th class="px-4 py-3 font-bold">Period</th>
                                    <th class="px-4 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                @foreach ($subscribers as $subscription)
                                    <tr class="hover:bg-slate-50/60 transition-colors">
                                        <td class="px-6 py-3.5">
                                            <span class="font-bold text-slate-800">{{ $subscription->user?->name ?? '-' }}</span>
                                            <span class="block text-[11px] text-slate-400">{{ $subscription->user?->email }}</span>
                                        </td>
                                        <td class="px-4 py-3.5 text-slate-600">
                                            {{ $subscription->plan?->name ?? '-' }}
                                            <span class="block text-[11px] text-slate-400">{{ ucfirst($subscription->interval) }}</span>
                                        </td>
                                        <td class="px-4 py-3.5">
                                            <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-black ring-1 {{ $subscription->badgeClasses() }}">
                                                {{ strtoupper($subscription->statusLabel()) }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3.5 text-[12px] text-slate-500 whitespace-nowrap">
                                            {{ $subscription->current_period_start?->format('d M') }} - {{ $subscription->current_period_end?->format('d M Y') }}
                                        </td>
                                        <td class="px-4 py-3.5 text-right whitespace-nowrap">
                                            <button wire:click="showBillingHistory({{ $subscription->user_id }})" class="text-[11px] font-bold text-primary-600 hover:underline mr-3">
                                                <span wire:loading.remove wire:target="showBillingHistory({{ $subscription->user_id }})">Invoices</span>
                                                <span wire:loading wire:target="showBillingHistory({{ $subscription->user_id }})">Loading…</span>
                                            </button>
                                            @if ($subscription->stripe_subscription_id)
                                                <a href="https://dashboard.stripe.com/{{ config('services.stripe.mode', env('STRIPE_MODE')) === 'live' ? '' : 'test/' }}subscriptions/{{ $subscription->stripe_subscription_id }}"
                                                   target="_blank" rel="noopener" class="text-[11px] font-bold text-slate-500 hover:underline mr-3">Stripe ↗</a>
                                            @endif
                                            @if ($subscription->grantsAccess() && !$subscription->cancel_at_period_end)
                                                <button @click="$dispatch('admin-confirm', {
                                                            title: 'Cancel subscription',
                                                            message: 'Cancel {{ $subscription->user?->name }}\'s Unjamm Plus at the end of the current period? They keep access until then.',
                                                            confirmLabel: 'Cancel at period end',
                                                            danger: true,
                                                            method: 'cancelSubscription',
                                                            params: [{{ $subscription->id }}],
                                                        })" class="text-[11px] font-bold text-rose-600 hover:underline">Cancel</button>
                                            @elseif ($subscription->cancel_at_period_end && $subscription->grantsAccess())
                                                <button wire:click="reactivateSubscription({{ $subscription->id }})" class="text-[11px] font-bold text-emerald-600 hover:underline">
                                                    <span wire:loading.remove wire:target="reactivateSubscription({{ $subscription->id }})">Reactivate</span>
                                                    <span wire:loading wire:target="reactivateSubscription({{ $subscription->id }})">…</span>
                                                </button>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="px-6 py-3 border-t border-slate-100">{{ $subscribers->links() }}</div>
                @endif
            </div>
        @endif
    </div>

    {{-- Plan editor --}}
    @if ($showPlanModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" wire:click="$set('showPlanModal', false)"></div>
            <div class="relative bg-white w-full max-w-xl rounded-2xl shadow-2xl max-h-[90vh] overflow-y-auto p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="font-bold text-slate-900">{{ $editingPlanId ? 'Edit plan' : 'New plan' }}</h2>
                    <button wire:click="$set('showPlanModal', false)" class="p-2 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100"><i data-lucide="x" class="w-5 h-5"></i></button>
                </div>

                <div class="grid sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1.5">Plan key</label>
                        <input type="text" wire:model="plan.key" placeholder="plus" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm font-mono focus:border-primary-500 outline-none">
                        @error('plan.key') <span class="text-rose-500 text-[10px] font-bold">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1.5">Display name</label>
                        <input type="text" wire:model="plan.name" placeholder="Unjamm Plus" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:border-primary-500 outline-none">
                        @error('plan.name') <span class="text-rose-500 text-[10px] font-bold">{{ $message }}</span> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1.5">Description</label>
                        <textarea wire:model="plan.description" rows="2" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:border-primary-500 outline-none"></textarea>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1.5">Monthly price</label>
                        <input type="number" step="0.01" min="0" wire:model="plan.monthly_price" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:border-primary-500 outline-none">
                        @error('plan.monthly_price') <span class="text-rose-500 text-[10px] font-bold">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1.5">Annual price</label>
                        <input type="number" step="0.01" min="0" wire:model="plan.annual_price" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:border-primary-500 outline-none">
                        @error('plan.annual_price') <span class="text-rose-500 text-[10px] font-bold">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1.5">Currency</label>
                        <input type="text" maxlength="3" wire:model="plan.currency" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm uppercase focus:border-primary-500 outline-none">
                        @error('plan.currency') <span class="text-rose-500 text-[10px] font-bold">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1.5">Trial days</label>
                        <input type="number" min="0" max="90" wire:model="plan.trial_days" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:border-primary-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1.5">Sort order</label>
                        <input type="number" min="0" wire:model="plan.sort" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:border-primary-500 outline-none">
                    </div>
                    <div class="flex items-end pb-1">
                        <label class="inline-flex items-center gap-2 text-sm font-bold text-slate-700 cursor-pointer">
                            <input type="checkbox" wire:model="plan.is_active" class="rounded border-slate-300 text-slate-900 focus:ring-slate-900"> Active
                        </label>
                    </div>

                    <div class="sm:col-span-2 border-t border-slate-100 pt-3 mt-1">
                        @php $editingPlan = $editingPlanId ? $plans->firstWhere('id', $editingPlanId) : null; @endphp
                        <div class="flex items-start gap-2.5 rounded-xl bg-slate-50 border border-slate-100 px-3.5 py-3">
                            <i data-lucide="{{ $editingPlan?->stripe_product_id ? 'badge-check' : 'zap' }}" class="w-4 h-4 shrink-0 mt-0.5 {{ $editingPlan?->stripe_product_id ? 'text-emerald-500' : 'text-slate-400' }}"></i>
                            <div class="text-[11px] text-slate-500 leading-relaxed">
                                <span class="font-bold text-slate-700">Stripe is managed automatically.</span>
                                Saving creates or updates the Stripe product and prices to match - no IDs to copy.
                                When you change the price, existing subscribers are moved too and renew at the new price from their next billing cycle.
                                @if ($editingPlan?->stripe_product_id)
                                    <span class="block mt-1 font-mono text-[10px] text-slate-400">{{ $editingPlan->stripe_product_id }}</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="sm:col-span-2">
                        <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1.5">Perks (one per line - shown on the customer plan card)</label>
                        <textarea wire:model="plan.perks_text" rows="4" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:border-primary-500 outline-none"></textarea>
                    </div>
                </div>

                <div class="flex justify-end gap-2 mt-5">
                    <button wire:click="$set('showPlanModal', false)" class="px-4 py-2.5 rounded-xl border border-slate-200 bg-white text-slate-700 text-sm font-bold hover:border-slate-300 transition-colors">Cancel</button>
                    <button wire:click="savePlan" wire:loading.attr="disabled" class="px-5 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-sm font-bold transition-colors disabled:opacity-60">
                        <span wire:loading.remove wire:target="savePlan">Save plan</span>
                        <span wire:loading wire:target="savePlan">Saving…</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Billing history --}}
    @if ($historyUserId)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" wire:click="$set('historyUserId', null)"></div>
            <div class="relative bg-white w-full max-w-lg rounded-2xl shadow-2xl max-h-[85vh] overflow-y-auto p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="font-bold text-slate-900">Billing history{{ $historyUser ? ' - ' . $historyUser->name : '' }}</h2>
                    <button wire:click="$set('historyUserId', null)" class="p-2 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100"><i data-lucide="x" class="w-5 h-5"></i></button>
                </div>
                @if (empty($invoices))
                    <p class="text-sm text-slate-400">No invoices found{{ $stripeReady ? '' : ' - Stripe is not configured' }}.</p>
                @else
                    <ul class="divide-y divide-slate-50">
                        @foreach ($invoices as $invoice)
                            <li class="flex items-center gap-3 py-2.5">
                                <div class="min-w-0 flex-1">
                                    <span class="block text-sm font-bold text-slate-800">{{ $invoice['number'] ?? 'Invoice' }}</span>
                                    <span class="block text-[11px] text-slate-400">{{ $invoice['date'] }}</span>
                                </div>
                                <span class="text-sm font-bold text-slate-700">{{ $invoice['total'] }}</span>
                                <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-black ring-1 {{ $invoice['status'] === 'paid' ? 'bg-emerald-50 text-emerald-700 ring-emerald-200' : 'bg-amber-50 text-amber-700 ring-amber-200' }}">{{ strtoupper($invoice['status']) }}</span>
                                @if ($invoice['url'])
                                    <a href="{{ $invoice['url'] }}" target="_blank" rel="noopener" class="text-[11px] font-bold text-primary-600 hover:underline">View</a>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    @endif

    <x-admin.confirm />
</div>
