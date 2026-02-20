@extends('layouts.auth')

@section('title', 'Sign In')

@section('content')
    <div class="text-center mb-8">
        <h2 class="text-2xl font-bold text-slate-900 tracking-tight">Welcome back</h2>
        <p class="mt-2 text-sm text-slate-500">Enter your credentials to access your dashboard.</p>
    </div>
    {{-- x alert --}}
    @if (session('status'))
        <div class="mb-6 p-4 bg-emerald-50 text-emerald-700 text-sm font-medium rounded-xl border border-emerald-100 flex items-start gap-3">
            <i data-lucide="check-circle-2" class="w-5 h-5 shrink-0 text-emerald-500"></i>
            <p>{{ session('status') }}</p>
        </div>
    @endif
    {{-- end x-alert --}}
    <form x-data="{ loading: false }" @submit="loading = true" method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf

        <div>
            <label for="email" class="block text-sm font-medium text-slate-700 mb-1">Email address</label>
            <div class="relative group">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <i data-lucide="mail" class="h-4 w-4 text-slate-400 group-focus-within:text-blue-500 transition-colors"></i>
                </div>
                <input id="email" name="email" type="email" autocomplete="email" required autofocus
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
            <div class="flex items-center justify-between mb-1">
                <label for="password" class="block text-sm font-medium text-slate-700">Password</label>
            </div>
            <div class="relative group">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <i data-lucide="lock" class="h-4 w-4 text-slate-400 group-focus-within:text-blue-500 transition-colors"></i>
                </div>
                <input id="password" name="password" type="password" required autocomplete="current-password"
                    class="block w-full pl-10 pr-3 py-2.5 bg-white border border-slate-200 rounded-lg text-sm placeholder-slate-400 focus:outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 transition-all shadow-sm"
                    placeholder="••••••••">
            </div>
            @error('password')
                <p class="text-red-600 text-xs mt-1 font-medium flex items-center gap-1">
                    <i data-lucide="alert-circle" class="w-3 h-3"></i> {{ $message }}
                </p>
            @enderror
        </div>

        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <input id="remember_me" name="remember" type="checkbox" 
                    class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-slate-300 rounded cursor-pointer">
                <label for="remember_me" class="ml-2 block text-sm text-slate-600 cursor-pointer select-none">Remember me</label>
            </div>
            <a href="{{ route('password.request') }}" class="text-sm font-medium text-blue-600 hover:text-blue-700 hover:underline transition-all">
                Forgot password?
            </a>
        </div>

        <button type="submit" 
                class="w-full flex justify-center items-center py-2.5 px-4 border border-transparent rounded-lg shadow-md shadow-blue-600/10 text-sm font-bold text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all disabled:opacity-70 disabled:cursor-not-allowed"
                :disabled="loading">
            
            <span x-show="!loading" class="flex items-center gap-2">
                Sign in
                <i data-lucide="arrow-right" class="w-4 h-4 opacity-80"></i>
            </span>

            <div x-show="loading" style="display: none;" class="flex items-center gap-2">
                <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span>Signing in...</span>
            </div>
        </button>

        <p class="mt-4 text-center text-sm text-slate-500">
            Don't have an account?
            <a href="{{ route('register') }}" class="font-bold text-slate-900 hover:text-blue-600 transition-colors">Register for free</a>
        </p>
    </form>
@endsection