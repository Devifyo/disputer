@extends('layouts.auth')

@section('title', 'Sign In')

@push('styles')
<style>
    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        15%, 45%, 75% { transform: translateX(-6px); }
        30%, 60%, 90% { transform: translateX(6px); }
    }
    .shake { animation: shake 0.5s ease-in-out; }

    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-4px); }
        to   { opacity: 1; transform: translateY(0); }
    }
    .slide-down { animation: slideDown 0.2s ease-out forwards; }
</style>
@endpush

@section('content')
    <div class="text-center mb-8">
        <h2 class="text-2xl font-bold text-slate-900 tracking-tight">Welcome back</h2>
        <p class="mt-2 text-sm text-slate-500">Enter your credentials to access your dashboard.</p>
    </div>

    @if (session('status'))
        <div class="mb-6 p-4 bg-emerald-50 text-emerald-700 text-sm font-medium rounded-xl border border-emerald-100 flex items-start gap-3">
            <i data-lucide="check-circle-2" class="w-5 h-5 shrink-0 text-emerald-500"></i>
            <p>{{ session('status') }}</p>
        </div>
    @endif

    <form
        id="loginForm"
        method="POST"
        action="{{ route('login') }}"
        class="space-y-5"
        x-data="{
            email: '{{ old('email') }}',
            password: '',
            loading: false,
            touched: { email: {{ old('email') || $errors->has('email') ? 'true' : 'false' }}, password: {{ $errors->has('password') ? 'true' : 'false' }} },
            errors: {
                email: '{{ addslashes($errors->first('email')) }}',
                password: '{{ addslashes($errors->first('password')) }}'
            },
            get emailState() {
                if (!this.touched.email) return 'idle';
                return this.errors.email ? 'error' : (this.email ? 'success' : 'idle');
            },
            get passwordState() {
                if (!this.touched.password) return 'idle';
                return this.errors.password ? 'error' : (this.password ? 'success' : 'idle');
            },
            validateEmail() {
                this.touched.email = true;
                const v = this.email.trim();
                if (!v) { this.errors.email = 'Email address is required.'; return; }
                if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)) { this.errors.email = 'Please enter a valid email address.'; return; }
                this.errors.email = '';
            },
            validatePassword() {
                this.touched.password = true;
                if (!this.password) { this.errors.password = 'Password is required.'; return; }
                this.errors.password = '';
            },
            handleSubmit() {
                this.validateEmail();
                this.validatePassword();
                if (this.errors.email || this.errors.password) {
                    this.$el.classList.add('shake');
                    setTimeout(() => this.$el.classList.remove('shake'), 500);
                    return;
                }
                this.loading = true;
                this.$el.submit();
            }
        }"
        @submit.prevent="handleSubmit()"
    >
        @csrf

        {{-- Email --}}
        <div>
            <label for="email" class="block text-sm font-medium text-slate-700 mb-1">Email address</label>
            <div class="relative group">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 transition-colors"
                        :class="emailState === 'error' ? 'text-red-400' : emailState === 'success' ? 'text-emerald-500' : 'text-slate-400 group-focus-within:text-blue-500'"
                        fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                </div>
                <input id="email" name="email" type="email" autocomplete="email" autofocus
                    x-model="email"
                    @blur="validateEmail()"
                    @input="if(touched.email) { errors.email = ''; }"
                    :class="{
                        'border-red-400 bg-red-50/30 focus:border-red-400 focus:ring-red-400/10': emailState === 'error',
                        'border-emerald-400 bg-emerald-50/20 focus:border-emerald-400 focus:ring-emerald-400/10': emailState === 'success',
                        'border-slate-200 focus:border-blue-500 focus:ring-blue-500/10': emailState === 'idle'
                    }"
                    class="block w-full pl-10 pr-10 py-2.5 bg-white rounded-lg text-sm placeholder-slate-400 focus:outline-none focus:ring-4 border transition-all shadow-sm"
                    placeholder="name@company.com">

                {{-- Right status icon --}}
                <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                    <svg x-show="emailState === 'success'" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                    </svg>
                    <svg x-show="emailState === 'error'" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </div>
            </div>
            <p x-show="emailState === 'error'" x-text="errors.email"
               class="slide-down text-red-500 text-xs mt-1.5 font-medium flex items-center gap-1"></p>
        </div>

        {{-- Password --}}
        <div>
            <div class="flex items-center justify-between mb-1">
                <label for="password" class="block text-sm font-medium text-slate-700">Password</label>
                <a href="{{ route('password.request') }}" class="text-xs font-medium text-blue-600 hover:text-blue-700 hover:underline transition-all">
                    Forgot password?
                </a>
            </div>
            <div class="relative group">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 transition-colors"
                        :class="passwordState === 'error' ? 'text-red-400' : passwordState === 'success' ? 'text-emerald-500' : 'text-slate-400 group-focus-within:text-blue-500'"
                        fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                </div>
                <input id="password" name="password" type="password" autocomplete="current-password"
                    x-model="password"
                    @blur="validatePassword()"
                    @input="if(touched.password) { errors.password = ''; }"
                    :class="{
                        'border-red-400 bg-red-50/30 focus:border-red-400 focus:ring-red-400/10': passwordState === 'error',
                        'border-emerald-400 bg-emerald-50/20 focus:border-emerald-400 focus:ring-emerald-400/10': passwordState === 'success',
                        'border-slate-200 focus:border-blue-500 focus:ring-blue-500/10': passwordState === 'idle'
                    }"
                    class="block w-full pl-10 pr-10 py-2.5 bg-white rounded-lg text-sm placeholder-slate-400 focus:outline-none focus:ring-4 border transition-all shadow-sm"
                    placeholder="••••••••">
                <button type="button" onclick="togglePw('password', this)"
                    class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                </button>
            </div>
            <p x-show="passwordState === 'error'" x-text="errors.password"
               class="slide-down text-red-500 text-xs mt-1.5 font-medium"></p>
        </div>

        {{-- Remember me --}}
        <div class="flex items-center">
            <input id="remember_me" name="remember" type="checkbox"
                class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-slate-300 rounded cursor-pointer">
            <label for="remember_me" class="ml-2 block text-sm text-slate-600 cursor-pointer select-none">Remember me</label>
        </div>

        {{-- Submit --}}
        <button type="submit"
                class="w-full flex justify-center items-center py-2.5 px-4 border border-transparent rounded-lg shadow-md shadow-blue-600/10 text-sm font-bold text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all disabled:opacity-70 disabled:cursor-not-allowed"
                :disabled="loading">
            <span x-show="!loading" class="flex items-center gap-2">
                Sign in
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 opacity-80" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                </svg>
            </span>
            <span x-show="loading" style="display:none" class="flex items-center gap-2">
                <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Signing in...
            </span>
        </button>

        <p class="text-center text-sm text-slate-500">
            Don't have an account?
            <a href="{{ route('register') }}" class="font-bold text-slate-900 hover:text-blue-600 transition-colors">Register for free</a>
        </p>
    </form>

    <div class="mt-6">
        <div class="relative">
            <div class="absolute inset-0 flex items-center">
                <div class="w-full border-t border-slate-200"></div>
            </div>
            <div class="relative flex justify-center text-sm">
                <span class="px-3 bg-white text-slate-400">Or continue with</span>
            </div>
        </div>

        <div class="mt-4 flex flex-col gap-3">
            <a href="{{ route('social.redirect', 'google') }}"
               class="flex items-center justify-center gap-3 w-full py-2.5 px-4 border border-slate-300 rounded-lg text-sm font-medium text-slate-700 bg-white hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-slate-400 transition-all shadow">
                <svg class="w-5 h-5 shrink-0" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                    <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                    <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
                    <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                </svg>
                Continue with Google
            </a>

            <a href="{{ route('social.redirect', 'apple') }}"
               class="flex items-center justify-center gap-3 w-full py-2.5 px-4 rounded-lg text-sm font-medium text-white bg-black hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-slate-800 transition-all shadow-sm">
                <svg class="w-5 h-5 shrink-0" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                    <path d="M17.05 20.28c-.98.95-2.05.8-3.08.35-1.09-.46-2.09-.48-3.24 0-1.44.62-2.2.44-3.06-.35C2.79 15.25 3.51 7.7 9.05 7.4c1.33.07 2.25.73 3.03.76.93-.16 1.82-.85 2.8-.91 1.27-.1 2.3.4 3.01 1.36-2.73 1.64-2.28 5.36.2 6.43-.57 1.53-1.37 3.03-1.04 5.24zm-5.8-13.38c-.1-2.23 1.66-4.14 3.88-4.34.26 2.3-2.1 4.46-3.88 4.34z"/>
                </svg>
                Continue with Apple
            </a>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    const EYE = `<path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>`;
    const EYE_OFF = `<path stroke-linecap="round" stroke-linejoin="round" d="M9.88 9.88a3 3 0 104.24 4.24"/><path stroke-linecap="round" stroke-linejoin="round" d="M10.73 5.08A10.43 10.43 0 0112 5c7 0 10 7 10 7a13.16 13.16 0 01-1.67 2.68"/><path stroke-linecap="round" stroke-linejoin="round" d="M6.61 6.61A13.526 13.526 0 002 12s3 7 10 7a9.74 9.74 0 005.39-1.61"/><path stroke-linecap="round" stroke-linejoin="round" d="M2 2l20 20"/>`;

    function togglePw(id, btn) {
        const input = document.getElementById(id);
        const isHidden = input.type === 'password';
        input.type = isHidden ? 'text' : 'password';
        btn.querySelector('svg').innerHTML = isHidden ? EYE_OFF : EYE;
    }
</script>
@endpush
