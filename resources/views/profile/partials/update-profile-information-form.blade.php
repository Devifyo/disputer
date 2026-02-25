<section>
    <header>
        <h2 class="text-lg font-bold text-slate-900">
            {{ __('Profile Information') }}
        </h2>
        <p class="mt-1 text-sm text-slate-600">
            {{ __("Update your account's profile information and email address.") }}
        </p>
    </header>

    <form id="send-verification" method="post" action="#">
        @csrf
    </form>

    <form method="post" id="profile-update-form" action="{{ route('profile.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('patch')

        <div>
            <label for="name" class="block text-sm font-bold text-slate-700 mb-1">
                {{ __('Name') }}
            </label>
            <div class="relative">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                </div>
                <input id="name" name="name" type="text" 
                    class="block w-full rounded-lg border-slate-300 bg-white py-2.5 pl-10 pr-3 text-sm placeholder-slate-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 transition-all" 
                    value="{{ old('name', $user->name) }}" 
                    required autofocus autocomplete="name" 
                    placeholder="Your Full Name" />
            </div>
            @error('name') <p class="text-xs font-bold text-red-500 mt-2">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="email" class="block text-sm font-bold text-slate-700 mb-1">
                {{ __('Email') }}
            </label>
            <div class="relative">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                </div>
                <input id="email" name="email" type="email" 
                    class="block w-full rounded-lg border-slate-300 bg-white py-2.5 pl-10 pr-3 text-sm placeholder-slate-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 transition-all" 
                    value="{{ old('email', $user->email) }}" 
                    required autocomplete="username" 
                    placeholder="name@example.com" />
            </div>
            @error('email') <p class="text-xs font-bold text-red-500 mt-2">{{ $message }}</p> @enderror

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div class="mt-3 bg-amber-50 border border-amber-100 rounded-lg p-3">
                    <p class="text-sm text-amber-800">
                        {{ __('Your email address is unverified.') }}

                        <button form="send-verification" class="underline text-sm font-bold text-amber-900 hover:text-amber-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500 rounded">
                            {{ __('Click here to re-send the verification email.') }}
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 font-bold text-sm text-green-600 flex items-center gap-1">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" /></svg>
                            {{ __('A new verification link has been sent to your email address.') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div class="flex items-center gap-4 pt-2">
            <button type="submit" class="inline-flex items-center justify-center px-5 py-2.5 bg-slate-900 border border-transparent rounded-lg font-bold text-xs text-white uppercase tracking-widest hover:bg-slate-800 active:bg-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2 transition ease-in-out duration-150 shadow-lg shadow-slate-900/10">
                {{ __('Save Profile') }}
            </button>

            @if (session('status') === 'profile-updated')
                <div x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 2000)" class="flex items-center gap-2 text-sm text-emerald-600 font-bold bg-emerald-50 px-3 py-1.5 rounded-full">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    {{ __('Saved.') }}
                </div>
            @endif
        </div>
    </form>
</section>