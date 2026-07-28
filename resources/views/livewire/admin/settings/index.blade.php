<div class="h-full overflow-y-auto p-6 pb-24 relative bg-slate-50/50" x-data="{ activeTab: 'profile' }">
    <x-flash />

    {{-- Header --}}
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Admin Settings</h1>
        <p class="text-sm text-slate-500">Manage your personal admin account and global system preferences.</p>
    </div>

    <div class="flex flex-col md:flex-row gap-6">
        
        {{-- Settings Sidebar Navigation --}}
        <div class="w-full md:w-64 shrink-0">
            <nav class="flex flex-col gap-1 sticky top-6">
                <button @click="activeTab = 'profile'" 
                        :class="activeTab === 'profile' ? 'bg-white shadow-sm border-slate-200 text-primary-600' : 'border-transparent text-slate-600 hover:bg-slate-100 hover:text-slate-900'"
                        class="flex items-center gap-3 px-4 py-2.5 rounded-xl border text-sm font-bold transition-all text-left">
                    <i data-lucide="user" class="w-4 h-4"></i> Profile
                </button>
                
                <button @click="activeTab = 'security'" 
                        :class="activeTab === 'security' ? 'bg-white shadow-sm border-slate-200 text-primary-600' : 'border-transparent text-slate-600 hover:bg-slate-100 hover:text-slate-900'"
                        class="flex items-center gap-3 px-4 py-2.5 rounded-xl border text-sm font-bold transition-all text-left">
                    <i data-lucide="shield" class="w-4 h-4"></i> Security
                </button>

                <button @click="activeTab = 'eligibility'"
                        :class="activeTab === 'eligibility' ? 'bg-white shadow-sm border-slate-200 text-primary-600' : 'border-transparent text-slate-600 hover:bg-slate-100 hover:text-slate-900'"
                        class="flex items-center gap-3 px-4 py-2.5 rounded-xl border text-sm font-bold transition-all text-left">
                    <i data-lucide="scale" class="w-4 h-4"></i> Trip Eligibility
                </button>

                <button @click="activeTab = 'claims'"
                        :class="activeTab === 'claims' ? 'bg-white shadow-sm border-slate-200 text-primary-600' : 'border-transparent text-slate-600 hover:bg-slate-100 hover:text-slate-900'"
                        class="flex items-center gap-3 px-4 py-2.5 rounded-xl border text-sm font-bold transition-all text-left">
                    <i data-lucide="hand-coins" class="w-4 h-4"></i> Flight Claims
                </button>

                <button @click="activeTab = 'website'"
                        :class="activeTab === 'website' ? 'bg-white shadow-sm border-slate-200 text-primary-600' : 'border-transparent text-slate-600 hover:bg-slate-100 hover:text-slate-900'"
                        class="flex items-center gap-3 px-4 py-2.5 rounded-xl border text-sm font-bold transition-all text-left">
                    <i data-lucide="globe" class="w-4 h-4"></i> Website
                </button>

                {{-- <button @click="activeTab = 'system'"
                        :class="activeTab === 'system' ? 'bg-white shadow-sm border-slate-200 text-primary-600' : 'border-transparent text-slate-600 hover:bg-slate-100 hover:text-slate-900'"
                        class="flex items-center gap-3 px-4 py-2.5 rounded-xl border text-sm font-bold transition-all text-left mt-4">
                    <i data-lucide="sliders" class="w-4 h-4"></i> System Preferences
                </button> --}}
            </nav>
        </div>

        {{-- Settings Content Area --}}
        <div class="flex-1">
            
            {{-- TAB: PROFILE --}}
            <div x-show="activeTab === 'profile'" 
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-8"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="px-6 py-5 border-b border-slate-100">
                    <h2 class="text-lg font-bold text-slate-800">Profile Information</h2>
                    <p class="text-xs text-slate-500 mt-1">Update your admin account's profile information and email address.</p>
                </div>
                <div class="p-6 space-y-6">
                    <div class="max-w-md">
                        <label class="block text-[11px] font-bold text-slate-400 uppercase mb-2">Full Name</label>
                        <input type="text" wire:model="name" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:border-primary-500 outline-none">
                        @error('name') <span class="text-rose-500 text-[10px] font-bold mt-1 block">{{ $message }}</span> @enderror
                    </div>
                    <div class="max-w-md">
                        <label class="block text-[11px] font-bold text-slate-400 uppercase mb-2">Admin Email Address</label>
                        <input type="email" wire:model="email" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:border-primary-500 outline-none">
                        @error('email') <span class="text-rose-500 text-[10px] font-bold mt-1 block">{{ $message }}</span> @enderror
                    </div>
                </div>
                <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex justify-end">
                    <button wire:click="updateProfile" wire:loading.attr="disabled" class="min-w-[140px] px-6 py-2 bg-slate-900 text-white rounded-xl text-sm font-bold shadow-lg shadow-slate-900/20 hover:bg-slate-800 transition-all disabled:opacity-70 flex justify-center items-center gap-2">
                        <span wire:loading.remove wire:target="updateProfile">Save Changes</span>
                        <span wire:loading.flex wire:target="updateProfile" class="items-center gap-2">
                            <i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> Saving...
                        </span>
                    </button>
                </div>
            </div>

            {{-- TAB: SECURITY --}}
            <div x-show="activeTab === 'security'" x-cloak 
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-8"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="px-6 py-5 border-b border-slate-100">
                    <h2 class="text-lg font-bold text-slate-800">Update Password</h2>
                    <p class="text-xs text-slate-500 mt-1">Ensure your admin account is using a long, random password to stay secure.</p>
                </div>
                <div class="p-6 space-y-6">
                    <div class="max-w-md">
                        <label class="block text-[11px] font-bold text-slate-400 uppercase mb-2">Current Password</label>
                        <input type="password" wire:model="current_password" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:border-primary-500 outline-none">
                        @error('current_password') <span class="text-rose-500 text-[10px] font-bold mt-1 block">{{ $message }}</span> @enderror
                    </div>
                    <div class="max-w-md">
                        <label class="block text-[11px] font-bold text-slate-400 uppercase mb-2">New Password</label>
                        <input type="password" wire:model="new_password" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:border-primary-500 outline-none">
                        @error('new_password') <span class="text-rose-500 text-[10px] font-bold mt-1 block">{{ $message }}</span> @enderror
                    </div>
                    <div class="max-w-md">
                        <label class="block text-[11px] font-bold text-slate-400 uppercase mb-2">Confirm New Password</label>
                        <input type="password" wire:model="new_password_confirmation" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:border-primary-500 outline-none">
                    </div>
                </div>
                <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex justify-end">
                    <button wire:click="updatePassword" wire:loading.attr="disabled" class="min-w-[160px] px-6 py-2 bg-slate-900 text-white rounded-xl text-sm font-bold shadow-lg shadow-slate-900/20 hover:bg-slate-800 transition-all disabled:opacity-70 flex justify-center items-center gap-2">
                        <span wire:loading.remove wire:target="updatePassword">Update Password</span>
                        <span wire:loading.flex wire:target="updatePassword" class="items-center gap-2">
                            <i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> Updating...
                        </span>
                    </button>
                </div>
            </div>

            {{-- TAB: TRIP ELIGIBILITY --}}
            <div x-show="activeTab === 'eligibility'" x-cloak
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-8"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="px-6 py-5 border-b border-slate-100">
                    <h2 class="text-lg font-bold text-slate-800">Trip Eligibility Engine</h2>
                    <p class="text-xs text-slate-500 mt-1">Controls the automatic eligibility evaluation of disrupted, monitored trips (APPR, EU261, UK261, US DOT).</p>
                </div>
                <div class="p-6 space-y-6">
                    <div class="max-w-md">
                        <label class="block text-[11px] font-bold text-slate-400 uppercase mb-2">Minimum Confidence Threshold (%)</label>
                        <input type="number" min="0" max="100" step="1" wire:model="eligibility_confidence_threshold"
                               class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:border-primary-500 outline-none">
                        @error('eligibility_confidence_threshold') <span class="text-rose-500 text-[10px] font-bold mt-1 block">{{ $message }}</span> @enderror
                        <p class="text-xs text-slate-500 mt-2">
                            Eligible verdicts at or above this confidence are <strong>auto-approved</strong>;
                            below it they are <strong>sent for manual review</strong> instead.
                            Lower it to auto-approve more borderline claims; raise it to keep humans in the loop more often.
                        </p>
                    </div>
                </div>
                <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex justify-end">
                    <button wire:click="updateEligibility" wire:loading.attr="disabled" class="min-w-[140px] px-6 py-2 bg-slate-900 text-white rounded-xl text-sm font-bold shadow-lg shadow-slate-900/20 hover:bg-slate-800 transition-all disabled:opacity-70 flex justify-center items-center gap-2">
                        <span wire:loading.remove wire:target="updateEligibility">Save Settings</span>
                        <span wire:loading.flex wire:target="updateEligibility" class="items-center gap-2">
                            <i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> Saving...
                        </span>
                    </button>
                </div>
            </div>

            {{-- TAB: FLIGHT CLAIMS --}}
            <div x-show="activeTab === 'claims'" x-cloak
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-8"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="px-6 py-5 border-b border-slate-100">
                    <h2 class="text-lg font-bold text-slate-800">Flight Claims</h2>
                    <p class="text-xs text-slate-500 mt-1">Success fee and the trust indicators shown on the claim confirmation screen.</p>
                </div>
                <div class="p-6 space-y-6">
                    <div class="max-w-md">
                        <label class="block text-[11px] font-bold text-slate-400 uppercase mb-2">Success Fee (%)</label>
                        <input type="number" min="0" max="50" step="0.5" wire:model="claims_success_fee"
                               class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:border-primary-500 outline-none">
                        @error('claims_success_fee') <span class="text-rose-500 text-[10px] font-bold mt-1 block">{{ $message }}</span> @enderror
                        <p class="text-xs text-slate-500 mt-2">Deducted from the recovered compensation only when a claim succeeds - shown to the customer before they consent.</p>
                    </div>
                    <div class="grid sm:grid-cols-2 gap-4 max-w-2xl">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-400 uppercase mb-2">Successful claims (display)</label>
                            <input type="text" wire:model="claims_social_won" placeholder="e.g. 12,000+"
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:border-primary-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-400 uppercase mb-2">Total recovered (display)</label>
                            <input type="text" wire:model="claims_social_recovered" placeholder="e.g. EUR 6.4M"
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:border-primary-500 outline-none">
                        </div>
                    </div>
                    <p class="text-xs text-slate-500">Customer testimonials on the confirmation screen come from published <strong>Success Stories</strong>.</p>

                    <div class="pt-5 border-t border-slate-100">
                        <div class="flex items-center justify-between gap-3 mb-1">
                            <label class="block text-[11px] font-bold text-slate-400 uppercase">Alert recipients</label>
                            <span class="text-[11px] font-bold text-slate-400">{{ count(array_filter($alert_recipients, fn ($r) => trim($r['email'] ?? '') !== '')) }} configured</span>
                        </div>
                        <p class="text-xs text-slate-500 mb-3">
                            Every operational alert - airline replies, escalation decisions, payments and payouts - is emailed
                            to the mailboxes below; add as many as you need and tick what each one should receive.
                            Admin accounts always see the same alerts in the app's notification bell.
                            @if (!array_filter($alert_recipients, fn ($r) => trim($r['email'] ?? '') !== ''))
                                <span class="text-amber-600 font-bold">None set - alert emails currently go to every admin account.</span>
                            @endif
                        </p>

                        <div class="space-y-2.5 max-w-3xl">
                            @foreach ($alert_recipients as $i => $recipient)
                                <div class="rounded-xl border border-slate-200 p-3 bg-slate-50/50" wire:key="alert-recipient-{{ $i }}">
                                    <div class="flex flex-col sm:flex-row gap-2">
                                        <input type="text" wire:model="alert_recipients.{{ $i }}.name" placeholder="Name or team (optional)"
                                               class="sm:w-52 px-3 py-2 rounded-lg border border-slate-200 text-sm focus:border-primary-500 outline-none">
                                        <input type="email" wire:model="alert_recipients.{{ $i }}.email" placeholder="alerts@unjamm.com"
                                               class="flex-1 px-3 py-2 rounded-lg border border-slate-200 text-sm focus:border-primary-500 outline-none">
                                        <button type="button" wire:click="removeAlertRecipient({{ $i }})"
                                                class="shrink-0 px-2.5 py-2 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-colors" title="Remove">
                                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                                        </button>
                                    </div>
                                    @error("alert_recipients.{$i}.email") <span class="text-rose-500 text-[10px] font-bold mt-1 block">{{ $message }}</span> @enderror
                                    <div class="flex flex-wrap gap-x-4 gap-y-1.5 mt-2.5">
                                        @foreach ($alertTypes as $type => $label)
                                            <label class="inline-flex items-center gap-1.5 text-xs font-medium text-slate-600 cursor-pointer">
                                                <input type="checkbox" value="{{ $type }}" wire:model="alert_recipients.{{ $i }}.alerts"
                                                       class="rounded border-slate-300 text-slate-900 focus:ring-slate-900">
                                                {{ $label }}
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <button type="button" wire:click="addAlertRecipient"
                                class="mt-2.5 inline-flex items-center gap-1.5 text-xs font-bold text-primary-600 hover:text-primary-700 transition-colors">
                            <i data-lucide="plus" class="w-3.5 h-3.5"></i> Add another recipient
                        </button>
                    </div>
                </div>
                <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex justify-end">
                    <button wire:click="updateClaims" wire:loading.attr="disabled" class="min-w-[140px] px-6 py-2 bg-slate-900 text-white rounded-xl text-sm font-bold shadow-lg shadow-slate-900/20 hover:bg-slate-800 transition-all disabled:opacity-70 flex justify-center items-center gap-2">
                        <span wire:loading.remove wire:target="updateClaims">Save Settings</span>
                        <span wire:loading.flex wire:target="updateClaims" class="items-center gap-2">
                            <i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> Saving...
                        </span>
                    </button>
                </div>
            </div>

            {{-- TAB: WEBSITE CONFIGURATION --}}
            <div x-show="activeTab === 'website'" x-cloak
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-8"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="px-6 py-5 border-b border-slate-100">
                    <h2 class="text-lg font-bold text-slate-800">Website Configuration</h2>
                    <p class="text-xs text-slate-500 mt-1">Feature toggles for customer-facing sections of the app.</p>
                </div>
                <div class="p-6 space-y-6">
                    <label class="flex items-start gap-4 max-w-xl cursor-pointer select-none">
                        <input type="checkbox" wire:model="site_plus_promo"
                               class="mt-1 w-5 h-5 rounded border-slate-300 text-slate-900 focus:ring-slate-900">
                        <span>
                            <span class="block text-sm font-bold text-slate-800">Show the Unjamm Plus upgrade on claim confirmation</span>
                            <span class="block text-xs text-slate-500 mt-1">
                                The "Choose how we process it" section (Free vs Plus - priority queue, next-business-day payout,
                                family support). When off, customers go straight from payout to consent without any upsell.
                            </span>
                        </span>
                    </label>
                </div>
                <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex justify-end">
                    <button wire:click="updateWebsite" wire:loading.attr="disabled" class="min-w-[140px] px-6 py-2 bg-slate-900 text-white rounded-xl text-sm font-bold shadow-lg shadow-slate-900/20 hover:bg-slate-800 transition-all disabled:opacity-70 flex justify-center items-center gap-2">
                        <span wire:loading.remove wire:target="updateWebsite">Save Settings</span>
                        <span wire:loading.flex wire:target="updateWebsite" class="items-center gap-2">
                            <i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> Saving...
                        </span>
                    </button>
                </div>
            </div>

            {{-- TAB: SYSTEM CONFIG --}}
            <div x-show="activeTab === 'system'" x-cloak 
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-8"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="px-6 py-5 border-b border-slate-100">
                    <h2 class="text-lg font-bold text-slate-800">System Preferences</h2>
                    <p class="text-xs text-slate-500 mt-1">Manage global configurations for the application.</p>
                </div>
                <div class="p-6 space-y-6">
                    <div class="max-w-md">
                        <label class="block text-[11px] font-bold text-slate-400 uppercase mb-2">Application Name</label>
                        <input type="text" wire:model="app_name" placeholder="e.g. ApplicantBill" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:border-primary-500 outline-none">
                    </div>
                    <div class="max-w-md">
                        <label class="block text-[11px] font-bold text-slate-400 uppercase mb-2">Global Support Email</label>
                        <input type="email" wire:model="support_email" placeholder="support@yourdomain.com" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:border-primary-500 outline-none">
                    </div>
                </div>
                <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex justify-end">
                    <button wire:click="updateSystem" wire:loading.attr="disabled" class="min-w-[140px] px-6 py-2 bg-slate-900 text-white rounded-xl text-sm font-bold shadow-lg shadow-slate-900/20 hover:bg-slate-800 transition-all disabled:opacity-70 flex justify-center items-center gap-2">
                        <span wire:loading.remove wire:target="updateSystem">Save Settings</span>
                        <span wire:loading.flex wire:target="updateSystem" class="items-center gap-2">
                            <i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> Saving...
                        </span>
                    </button>
                </div>
            </div>

        </div>
    </div>
</div>