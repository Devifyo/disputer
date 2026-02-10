@extends('layouts.app')

@section('title', 'Account Settings')

@section('content')
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            
            <div class="mb-6">
                <h2 class="text-2xl font-bold text-slate-900 tracking-tight">
                    {{ __('Account Settings') }}
                </h2>
                <p class="text-slate-500 text-sm">Manage your profile, email connections, and security.</p>
            </div>

            <div class="flex flex-col lg:flex-row gap-8">
                
                <nav class="w-full lg:w-64 shrink-0 space-y-1">
                    
                    <button type="button" data-tab="profile" 
                            class="tab-button w-full flex items-center gap-3 px-4 py-3 text-sm font-bold rounded-lg transition-all group bg-white text-blue-600 shadow-sm ring-1 ring-slate-900/5">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="opacity-75 group-hover:opacity-100"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        Profile Information
                    </button>

                    <button type="button" data-tab="email" 
                            class="tab-button w-full flex items-center gap-3 px-4 py-3 text-sm font-bold rounded-lg transition-all group text-slate-600 hover:bg-slate-100 hover:text-slate-900">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="opacity-75 group-hover:opacity-100"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                        Email Configuration
                        <span class="ml-auto flex h-2.5 w-2.5 relative">
                            @if(isEmailConfigured())
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
                            @else
                                <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-amber-400"></span>
                            @endif
                        </span>
                    </button>

                    <button type="button" data-tab="password" 
                            class="tab-button w-full flex items-center gap-3 px-4 py-3 text-sm font-bold rounded-lg transition-all group text-slate-600 hover:bg-slate-100 hover:text-slate-900">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="opacity-75 group-hover:opacity-100"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        Security & Password
                    </button>
                </nav>

                <div class="flex-1 min-w-0">
                    
                    <div id="tab-profile" class="tab-pane">
                        <div class="p-4 sm:p-8 bg-white shadow-sm ring-1 ring-slate-900/5 sm:rounded-xl">
                            <div class="max-w-xl">
                                @include('profile.partials.update-profile-information-form')
                            </div>
                        </div>
                    </div>

                    <div id="tab-email" class="tab-pane hidden">
                        @include('profile.partials.update-email-config-form')
                    </div>

                    <div id="tab-password" class="tab-pane hidden space-y-6">
                        <div class="p-4 sm:p-8 bg-white shadow-sm ring-1 ring-slate-900/5 sm:rounded-xl">
                            <div class="max-w-xl">
                                @include('profile.partials.update-password-form')
                            </div>
                        </div>

                        <div class="p-4 sm:p-8 bg-white shadow-sm ring-1 ring-slate-900/5 sm:rounded-xl">
                            <div class="max-w-xl">
                                @include('profile.partials.delete-user-form')
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
       $(document).ready(function() {
            
            // ==========================================
            // 1. TAB SWITCHING LOGIC (jQuery)
            // ==========================================
            
            // Function to activate a specific tab
            function activateTab(tabName) {
                // 1. Reset all buttons to inactive state
                $('.tab-button').removeClass('bg-white text-blue-600 shadow-sm ring-1 ring-slate-900/5')
                                .addClass('text-slate-600 hover:bg-slate-100 hover:text-slate-900');
                
                // 2. Hide all tab panes
                $('.tab-pane').addClass('hidden');

                // 3. Activate the specific button
                $('.tab-button[data-tab="' + tabName + '"]')
                                .removeClass('text-slate-600 hover:bg-slate-100 hover:text-slate-900')
                                .addClass('bg-white text-blue-600 shadow-sm ring-1 ring-slate-900/5');

                // 4. Show the specific tab pane
                $('#tab-' + tabName).removeClass('hidden');

                // 5. Update URL Hash without jumping
                if(history.pushState) {
                    history.pushState(null, null, '#' + (tabName === 'email' ? 'email-settings' : tabName));
                } else {
                    location.hash = '#' + (tabName === 'email' ? 'email-settings' : tabName);
                }
            }

            // Click Handler
            $('.tab-button').on('click', function() {
                var tab = $(this).data('tab');
                activateTab(tab);
            });

            // Check URL Hash on Page Load
            var hash = window.location.hash;
            if (hash === '#email-settings' || hash === '#email') {
                activateTab('email');
            } else if (hash === '#password') {
                activateTab('password');
            } else {
                // Default is profile (HTML is already set to profile, but this enforces consistency)
                activateTab('profile'); 
            }

            // ==========================================
            // 2. VALIDATION LOGIC
            // ==========================================

            // Global Tailwind Styling for Errors
            $.validator.setDefaults({
                errorElement: 'p',
                errorClass: 'text-red-500 text-xs font-bold mt-1',
                highlight: function(element) {
                    $(element).addClass('border-red-500 ring-red-500 focus:border-red-500 focus:ring-red-500');
                    $(element).removeClass('border-slate-300 focus:border-blue-500 focus:ring-blue-500');
                },
                unhighlight: function(element) {
                    $(element).removeClass('border-red-500 ring-red-500 focus:border-red-500 focus:ring-red-500');
                    $(element).addClass('border-slate-300 focus:border-blue-500 focus:ring-blue-500');
                },
                errorPlacement: function(error, element) {
                    if (element.parent().hasClass('relative')) {
                        error.insertAfter(element.parent());
                    } else {
                        error.insertAfter(element);
                    }
                }
            });

            // Profile Information Form
            $("#profile-update-form").validate({
                rules: {
                    name: "required",
                    email: {
                        required: true,
                        email: true
                    }
                },
                messages: {
                    name: "Please enter your full name.",
                    email: {
                        required: "We need your email address to contact you.",
                        email: "Please enter a valid email format (e.g., user@example.com)."
                    }
                }
            });

            // Email Configuration Form
            $("#email-config-form").validate({
                rules: {
                    from_name: "required",
                    from_email: { required: true, email: true },
                    smtp_host: "required",
                    smtp_port: { required: true, digits: true },
                    smtp_username: "required",
                    smtp_password: "required",
                    imap_host: "required",
                    imap_port: { required: true, digits: true },
                    imap_username: "required",
                    imap_password: "required"
                },
                messages: {
                    from_name: "Please enter the sender name (e.g., Support Team).",
                    from_email: {
                        required: "Sender email is required.",
                        email: "Please enter a valid email address."
                    },
                    smtp_host: "SMTP Host is required.",
                    smtp_port: {
                        required: "SMTP Port is required.",
                        digits: "Port must be a number (e.g., 587)."
                    },
                    smtp_username: "SMTP Username is required.",
                    smtp_password: "SMTP Password is required.",
                    imap_host: "IMAP Host is required.",
                    imap_port: {
                        required: "IMAP Port is required.",
                        digits: "Port must be a number (e.g., 993)."
                    },
                    imap_username: "IMAP Username is required.",
                    imap_password: "IMAP Password is required."
                }
            });

            // Password Update Form
            $("#password-update-form").validate({
                ignore: [], // Important: Validates even if tab is hidden
                rules: {
                    current_password: "required",
                    password: {
                        required: true,
                        minlength: 8
                    },
                    password_confirmation: {
                        required: true,
                        equalTo: "#password"
                    }
                },
                messages: {
                    current_password: "Please enter your current password to authorize this change.",
                    password: {
                        required: "Please provide a new password.",
                        minlength: "Your password must be at least 8 characters long."
                    },
                    password_confirmation: {
                        required: "Please confirm your new password.",
                        equalTo: "The passwords do not match. Please try again."
                    }
                }
            });
            
            // Delete Account Form
            $("#delete-account-form").validate({
                ignore: [],
                rules: {
                    password: "required"
                },
                messages: {
                    password: "You must enter your password to confirm account deletion."
                }
            });
        });
    </script>
@endpush