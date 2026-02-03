@extends('layouts.auth')

@section('title', 'Sign In')

@section('content')
    <div class="text-center">
        <h2 class="text-2xl font-semibold text-gray-900 tracking-tight">Access your Dashboard</h2>
        <p class="mt-2 text-sm text-gray-500">Enter your credentials to continue.</p>
    </div>

    <form id="loginForm" class="mt-8 space-y-6" method="POST" action="{{ route('login') }}">
        @csrf

        <div class="space-y-4">
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700">Email address</label>
                <div class="mt-1">
                    <input id="email" name="email" type="email" autocomplete="email" required autofocus
                        class="appearance-none block w-full px-3 py-2.5 border border-gray-300 rounded-lg shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent sm:text-sm transition-all"
                        placeholder="you@example.com" value="{{ old('email') }}">
                    @error('email')
                        <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                <div class="mt-1">
                    <input id="password" name="password" type="password" required autocomplete="current-password"
                        class="appearance-none block w-full px-3 py-2.5 border border-gray-300 rounded-lg shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent sm:text-sm transition-all"
                        placeholder="••••••••">
                    @error('password')
                        <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <input id="remember_me" name="remember" type="checkbox" class="h-4 w-4 text-primary focus:ring-primary border-gray-300 rounded">
                <label for="remember_me" class="ml-2 block text-sm text-gray-600">Remember me</label>
            </div>
            <div class="text-sm">
                <a href="{{ route('password.request') }}" class="font-medium text-primary hover:text-primary-hover">Forgot password?</a>
            </div>
        </div>

        <div>
            <button type="submit" id="submitBtn" class="w-full flex justify-center py-2.5 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-primary hover:bg-primary-hover focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary transition-colors">
                <span class="btn-text">Sign in</span>
                <svg class="spinner hidden animate-spin ml-2 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
            </button>
        </div>

        <p class="mt-2 text-center text-sm text-gray-600">
            Don't have an account?
            <a href="{{ route('register') }}" class="font-medium text-primary hover:text-primary-hover">Register now</a>
        </p>
    </form>
@endsection

@push('scripts')
<script>
    $(document).ready(function() {
        // Auto focus email
        $('#email').focus();

        // Button Loading State on Submit
        $('#loginForm').on('submit', function() {
            var btn = $('#submitBtn');
            btn.attr('disabled', true).addClass('opacity-80 cursor-wait');
            btn.find('.btn-text').text('Signing in...');
            btn.find('.spinner').removeClass('hidden');
        });
    });
</script>
@endpush
