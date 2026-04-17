@extends('layouts.auth')

@section('title', 'Create Account')

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
        <h2 class="text-2xl font-bold text-slate-900 tracking-tight">Create an account</h2>
        <p class="mt-2 text-sm text-slate-500">Join thousands of users resolving disputes today.</p>
    </div>

    <form
        id="registerForm"
        method="POST"
        action="{{ route('register') }}"
        class="space-y-5"
        x-data="{
            name: '{{ old('name') }}',
            email: '{{ old('email') }}',
            password: '',
            password_confirmation: '',
            loading: false,
            touched: {
                name: {{ old('name') || $errors->has('name') ? 'true' : 'false' }},
                email: {{ old('email') || $errors->has('email') ? 'true' : 'false' }},
                password: {{ $errors->has('password') ? 'true' : 'false' }},
                password_confirmation: false
            },
            errors: {
                name: '{{ addslashes($errors->first('name')) }}',
                email: '{{ addslashes($errors->first('email')) }}',
                password: '{{ addslashes($errors->first('password')) }}',
                password_confirmation: ''
            },
            get nameState() {
                if (!this.touched.name) return 'idle';
                return this.errors.name ? 'error' : (this.name.trim() ? 'success' : 'idle');
            },
            get emailState() {
                if (!this.touched.email) return 'idle';
                return this.errors.email ? 'error' : (this.email ? 'success' : 'idle');
            },
            get passwordState() {
                if (!this.touched.password) return 'idle';
                return this.errors.password ? 'error' : (this.password ? 'success' : 'idle');
            },
            get confirmState() {
                if (!this.touched.password_confirmation) return 'idle';
                return this.errors.password_confirmation ? 'error' : (this.password_confirmation ? 'success' : 'idle');
            },
            validateName() {
                this.touched.name = true;
                const v = this.name.trim();
                if (!v) { this.errors.name = 'Full name is required.'; return; }
                if (v.length < 2) { this.errors.name = 'Name must be at least 2 characters.'; return; }
                this.errors.name = '';
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
                if (this.password.length < 8) { this.errors.password = 'Password must be at least 8 characters.'; return; }
                this.errors.password = '';
                if (this.touched.password_confirmation) this.validateConfirm();
            },
            validateConfirm() {
                this.touched.password_confirmation = true;
                if (!this.password_confirmation) { this.errors.password_confirmation = 'Please confirm your password.'; return; }
                if (this.password_confirmation !== this.password) { this.errors.password_confirmation = 'Passwords do not match.'; return; }
                this.errors.password_confirmation = '';
            },
            handleSubmit() {
                this.validateName();
                this.validateEmail();
                this.validatePassword();
                this.validateConfirm();
                if (this.errors.name || this.errors.email || this.errors.password || this.errors.password_confirmation) {
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

        {{-- Full Name --}}
        <div>
            <label for="name" class="block text-sm font-medium text-slate-700 mb-1">Full Name</label>
            <div class="relative group">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 transition-colors"
                        :class="nameState === 'error' ? 'text-red-400' : nameState === 'success' ? 'text-emerald-500' : 'text-slate-400 group-focus-within:text-blue-500'"
                        fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                </div>
                <input id="name" name="name" type="text" autocomplete="name" autofocus
                    x-model="name"
                    @blur="validateName()"
                    @input="if(touched.name) { errors.name = ''; }"
                    :class="{
                        'border-red-400 bg-red-50/30 focus:border-red-400 focus:ring-red-400/10': nameState === 'error',
                        'border-emerald-400 bg-emerald-50/20 focus:border-emerald-400 focus:ring-emerald-400/10': nameState === 'success',
                        'border-slate-200 focus:border-blue-500 focus:ring-blue-500/10': nameState === 'idle'
                    }"
                    class="block w-full pl-10 pr-10 py-2.5 bg-white rounded-lg text-sm placeholder-slate-400 focus:outline-none focus:ring-4 border transition-all shadow-sm"
                    placeholder="John Doe">

                <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                    <svg x-show="nameState === 'success'" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                    </svg>
                    <svg x-show="nameState === 'error'" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </div>
            </div>
            <p x-show="nameState === 'error'" x-text="errors.name"
               class="slide-down text-red-500 text-xs mt-1.5 font-medium"></p>
        </div>

        {{-- Email --}}
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
                <input id="email" name="email" type="email" autocomplete="email"
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

        {{-- Password --}}
        <div>
            <label for="password" class="block text-sm font-medium text-slate-700 mb-1">Password</label>
            <div class="relative group">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 transition-colors"
                        :class="passwordState === 'error' ? 'text-red-400' : passwordState === 'success' ? 'text-emerald-500' : 'text-slate-400 group-focus-within:text-blue-500'"
                        fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                </div>
                <input id="password" name="password" type="password" autocomplete="new-password"
                    x-model="password"
                    @blur="validatePassword()"
                    @input="if(touched.password) { errors.password = ''; if(touched.password_confirmation) validateConfirm(); }"
                    :class="{
                        'border-red-400 bg-red-50/30 focus:border-red-400 focus:ring-red-400/10': passwordState === 'error',
                        'border-emerald-400 bg-emerald-50/20 focus:border-emerald-400 focus:ring-emerald-400/10': passwordState === 'success',
                        'border-slate-200 focus:border-blue-500 focus:ring-blue-500/10': passwordState === 'idle'
                    }"
                    class="block w-full pl-10 pr-10 py-2.5 bg-white rounded-lg text-sm placeholder-slate-400 focus:outline-none focus:ring-4 border transition-all shadow-sm"
                    placeholder="Min 8 characters">
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

        {{-- Confirm Password --}}
        <div>
            <label for="password_confirmation" class="block text-sm font-medium text-slate-700 mb-1">Confirm Password</label>
            <div class="relative group">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 transition-colors"
                        :class="confirmState === 'error' ? 'text-red-400' : confirmState === 'success' ? 'text-emerald-500' : 'text-slate-400 group-focus-within:text-blue-500'"
                        fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                </div>
                <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password"
                    x-model="password_confirmation"
                    @blur="validateConfirm()"
                    @input="if(touched.password_confirmation) { errors.password_confirmation = ''; }"
                    :class="{
                        'border-red-400 bg-red-50/30 focus:border-red-400 focus:ring-red-400/10': confirmState === 'error',
                        'border-emerald-400 bg-emerald-50/20 focus:border-emerald-400 focus:ring-emerald-400/10': confirmState === 'success',
                        'border-slate-200 focus:border-blue-500 focus:ring-blue-500/10': confirmState === 'idle'
                    }"
                    class="block w-full pl-10 pr-10 py-2.5 bg-white rounded-lg text-sm placeholder-slate-400 focus:outline-none focus:ring-4 border transition-all shadow-sm"
                    placeholder="Repeat password">
                <button type="button" onclick="togglePw('password_confirmation', this)"
                    class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                </button>
            </div>
            <p x-show="confirmState === 'error'" x-text="errors.password_confirmation"
               class="slide-down text-red-500 text-xs mt-1.5 font-medium"></p>
        </div>

        {{-- Submit --}}
        <div class="pt-2">
            <button type="submit"
                    class="w-full flex justify-center items-center py-2.5 px-4 border border-transparent rounded-lg shadow-md shadow-blue-600/10 text-sm font-bold text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all disabled:opacity-70 disabled:cursor-not-allowed"
                    :disabled="loading">
                <span x-show="!loading" class="flex items-center gap-2">
                    Create Account
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 opacity-80" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                    </svg>
                </span>
                <span x-show="loading" style="display:none" class="flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Creating account...
                </span>
            </button>
        </div>

        <p class="mt-4 text-center text-sm text-slate-500">
            Already have an account?
            <a href="{{ route('login') }}" class="font-bold text-slate-900 hover:text-blue-600 transition-colors">Sign in</a>
        </p>
    </form>
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
