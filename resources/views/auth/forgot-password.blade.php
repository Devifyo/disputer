@extends('layouts.auth')

@section('title', 'Reset Password')

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
        <h2 class="text-2xl font-bold text-slate-900 tracking-tight">Reset your password</h2>
        <p class="mt-2 text-sm text-slate-500">
            Enter your email and we'll send you instructions to reset your password.
        </p>
    </div>

    @if (session('status'))
        <div class="mb-6 p-4 bg-emerald-50 text-emerald-700 text-sm font-medium rounded-xl border border-emerald-100 flex items-start gap-3">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 shrink-0 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <p>{{ session('status') }}</p>
        </div>
    @endif

    <form
        method="POST"
        action="{{ route('password.email') }}"
        class="space-y-6"
        x-data="{
            email: '{{ old('email') }}',
            loading: false,
            touched: { email: {{ old('email') || $errors->has('email') ? 'true' : 'false' }} },
            errors: { email: '{{ addslashes($errors->first('email')) }}' },
            get emailState() {
                if (!this.touched.email) return 'idle';
                return this.errors.email ? 'error' : (this.email ? 'success' : 'idle');
            },
            validateEmail() {
                this.touched.email = true;
                const v = this.email.trim();
                if (!v) { this.errors.email = 'Email address is required.'; return; }
                if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)) { this.errors.email = 'Please enter a valid email address.'; return; }
                this.errors.email = '';
            },
            handleSubmit() {
                this.validateEmail();
                if (this.errors.email) {
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

        <div>
            <label for="email" class="block text-sm font-medium text-slate-700 mb-1">Email Address</label>
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
               class="slide-down text-red-500 text-xs mt-1.5 font-medium"></p>
        </div>

        <button type="submit"
                class="w-full flex justify-center items-center py-2.5 px-4 border border-transparent rounded-lg shadow-md shadow-blue-600/10 text-sm font-bold text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all disabled:opacity-70 disabled:cursor-not-allowed"
                :disabled="loading">
            <span x-show="!loading" class="flex items-center gap-2">
                Send Reset Link
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 opacity-80" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                </svg>
            </span>
            <span x-show="loading" style="display:none" class="flex items-center gap-2">
                <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Sending...
            </span>
        </button>

        <p class="mt-2 text-center text-sm text-slate-500">
            Remember your password?
            <a href="{{ route('login') }}" class="font-bold text-slate-900 hover:text-blue-600 transition-colors inline-flex items-center gap-1">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Back to Login
            </a>
        </p>
    </form>
@endsection
