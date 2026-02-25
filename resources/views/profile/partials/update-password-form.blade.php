<section>
    <header>
        <h2 class="text-lg font-bold text-slate-900">
            {{ __('Update Password') }}
        </h2>
        <p class="mt-1 text-sm text-slate-600">
            {{ __('Ensure your account is using a long, random password to stay secure.') }}
        </p>
    </header>

    <form method="post" id="password-update-form" action="{{ route('password.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('put')

        <div>
            <label for="current_password" class="block text-sm font-bold text-slate-700 mb-1">
                {{ __('Current Password') }}
            </label>
            <div class="relative">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                </div>
                <input id="current_password" name="current_password" type="password" 
                    class="block w-full rounded-lg border-slate-300 bg-white py-2.5 pl-10 pr-3 text-sm placeholder-slate-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 transition-all" 
                    autocomplete="current-password" 
                    placeholder="Enter current password" />
            </div>
            @error('current_password') <p class="text-xs font-bold text-red-500 mt-2">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password" class="block text-sm font-bold text-slate-700 mb-1">
                {{ __('New Password') }}
            </label>
            <div class="relative">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                    </svg>
                </div>
                <input id="password" name="password" type="password" 
                    class="block w-full rounded-lg border-slate-300 bg-white py-2.5 pl-10 pr-3 text-sm placeholder-slate-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 transition-all" 
                    autocomplete="new-password" 
                    placeholder="Enter new password" />
            </div>
            @error('password') <p class="text-xs font-bold text-red-500 mt-2">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password_confirmation" class="block text-sm font-bold text-slate-700 mb-1">
                {{ __('Confirm Password') }}
            </label>
            <div class="relative">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <input id="password_confirmation" name="password_confirmation" type="password" 
                    class="block w-full rounded-lg border-slate-300 bg-white py-2.5 pl-10 pr-3 text-sm placeholder-slate-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 transition-all" 
                    autocomplete="new-password" 
                    placeholder="Confirm new password" />
            </div>
            @error('password_confirmation') <p class="text-xs font-bold text-red-500 mt-2">{{ $message }}</p> @enderror
        </div>

        <div class="flex items-center gap-4 pt-4">
            <button type="submit" class="inline-flex items-center justify-center px-5 py-2.5 bg-slate-900 border border-transparent rounded-lg font-bold text-xs text-white uppercase tracking-widest hover:bg-slate-800 active:bg-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2 transition ease-in-out duration-150 shadow-lg shadow-slate-900/10">
                {{ __('Update Password') }}
            </button>

            @if (session('status') === 'password-updated')
                <div x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 2000)" class="flex items-center gap-2 text-sm text-emerald-600 font-bold bg-emerald-50 px-3 py-1.5 rounded-full">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    {{ __('Saved.') }}
                </div>
            @endif
        </div>
    </form>
</section>