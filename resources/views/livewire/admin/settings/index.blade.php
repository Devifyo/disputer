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