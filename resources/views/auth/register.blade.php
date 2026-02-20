@extends('layouts.auth')

@section('title', 'Create Account')

@section('content')
    <div class="text-center mb-8">
        <h2 class="text-2xl font-bold text-slate-900 tracking-tight">Create an account</h2>
        <p class="mt-2 text-sm text-slate-500">Join thousands of users resolving disputes today.</p>
    </div>

    <form id="registerForm" novalidate x-data="{ loading: false }" @valid-submit="loading = true" method="POST" action="{{ route('register') }}" class="space-y-5">
        @csrf

        <div>
            <label for="name" class="block text-sm font-medium text-slate-700 mb-1">Full Name</label>
            <div class="relative group">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <i data-lucide="user" class="h-4 w-4 text-slate-400 group-focus-within:text-blue-500 transition-colors"></i>
                </div>
                <input id="name" name="name" type="text" autofocus
                    class="block w-full pl-10 pr-3 py-2.5 bg-white border border-slate-200 rounded-lg text-sm placeholder-slate-400 focus:outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 transition-all shadow-sm"
                    placeholder="John Doe" value="{{ old('name') }}">
            </div>
            @error('name')
                <p class="text-red-600 text-xs mt-1 font-medium flex items-center gap-1">
                    <i data-lucide="alert-circle" class="w-3 h-3"></i> {{ $message }}
                </p>
            @enderror
        </div>

        <div>
            <label for="email" class="block text-sm font-medium text-slate-700 mb-1">Email Address</label>
            <div class="relative group">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <i data-lucide="mail" class="h-4 w-4 text-slate-400 group-focus-within:text-blue-500 transition-colors"></i>
                </div>
                <input id="email" name="email" type="email"
                    class="block w-full pl-10 pr-3 py-2.5 bg-white border border-slate-200 rounded-lg text-sm placeholder-slate-400 focus:outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 transition-all shadow-sm"
                    placeholder="name@company.com" value="{{ old('email') }}">
            </div>
            @error('email')
                <p class="text-red-600 text-xs mt-1 font-medium flex items-center gap-1">
                    <i data-lucide="alert-circle" class="w-3 h-3"></i> {{ $message }}
                </p>
            @enderror
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-slate-700 mb-1">Password</label>
            <div class="relative group">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <i data-lucide="lock" class="h-4 w-4 text-slate-400 group-focus-within:text-blue-500 transition-colors"></i>
                </div>
                <input id="password" name="password" type="password"
                    class="block w-full pl-10 pr-3 py-2.5 bg-white border border-slate-200 rounded-lg text-sm placeholder-slate-400 focus:outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 transition-all shadow-sm"
                    placeholder="Min 8 characters">
            </div>
            @error('password')
                <p class="text-red-600 text-xs mt-1 font-medium flex items-center gap-1">
                    <i data-lucide="alert-circle" class="w-3 h-3"></i> {{ $message }}
                </p>
            @enderror
        </div>

        <div>
            <label for="password_confirmation" class="block text-sm font-medium text-slate-700 mb-1">Confirm Password</label>
            <div class="relative group">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <i data-lucide="check-circle-2" class="h-4 w-4 text-slate-400 group-focus-within:text-blue-500 transition-colors"></i>
                </div>
                <input id="password_confirmation" name="password_confirmation" type="password"
                    class="block w-full pl-10 pr-3 py-2.5 bg-white border border-slate-200 rounded-lg text-sm placeholder-slate-400 focus:outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 transition-all shadow-sm"
                    placeholder="Repeat password">
            </div>
        </div>

        <div class="pt-2">
            <button type="submit" 
                    class="w-full flex justify-center items-center py-2.5 px-4 border border-transparent rounded-lg shadow-md shadow-blue-600/10 text-sm font-bold text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all disabled:opacity-70 disabled:cursor-not-allowed"
                    :disabled="loading">
                
                <span x-show="!loading" class="flex items-center gap-2">
                    Create Account
                    <i data-lucide="arrow-right" class="w-4 h-4 opacity-80"></i>
                </span>

                <div x-show="loading" style="display: none;" class="flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>Creating account...</span>
                </div>
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
        $(document).ready(function () {
            $("#registerForm").validate({
                rules: {
                    name: { required: true, minlength: 2 },
                    email: { required: true, email: true },
                    password: { required: true, minlength: 8 },
                    password_confirmation: { required: true, equalTo: "#password" }
                },
                messages: {
                    name: {
                        required: "Please enter your full name.",
                        minlength: "Your name must be at least 2 characters long."
                    },
                    email: "Please enter a valid email address.",
                    password: {
                        required: "Please provide a password.",
                        minlength: "Your password must be at least 8 characters long."
                    },
                    password_confirmation: {
                        required: "Please confirm your password.",
                        equalTo: "Passwords do not match."
                    }
                }
            });
        });
    </script>
@endpush