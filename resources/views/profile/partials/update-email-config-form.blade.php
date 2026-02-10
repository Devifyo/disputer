<div class="bg-white shadow-sm ring-1 ring-slate-900/5 sm:rounded-xl overflow-hidden">
    <div class="p-6 border-b border-slate-100 bg-slate-50/50">
        <h3 class="text-lg font-bold text-slate-900">Email Configuration</h3>
        <p class="text-sm text-slate-500 mt-1">Configure SMTP to send emails and IMAP to receive replies directly in your dashboard.</p>
    </div>

    <form method="post" id="email-config-form" action="{{ route('profile.email.update') }}" class="p-6 md:p-8">
        @csrf
        @method('patch')

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-10">
            
            <div class="space-y-5">
                <div class="flex items-center gap-2 pb-2 border-b border-slate-100">
                    <div class="p-1.5 bg-blue-50 text-blue-600 rounded-md">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" x2="11" y1="2" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                    </div>
                    <h4 class="font-bold text-slate-800 text-sm uppercase tracking-wide">Sending (SMTP)</h4>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Sender Name</label>
                        <input type="text" name="from_name" value="{{ old('from_name', $emailConfig->from_name) }}" placeholder="e.g. Support Team"
                            class="w-full rounded-lg border-slate-200 bg-slate-50/30 px-3 py-2.5 text-sm font-medium text-slate-800 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20 transition-all outline-none placeholder:text-slate-400">
                        @error('from_name') <span class="text-xs text-red-500 font-bold">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Sender Email</label>
                        <input type="email" name="from_email" value="{{ old('from_email', $emailConfig->from_email) }}" required
                            class="w-full rounded-lg border-slate-200 bg-slate-50/30 px-3 py-2.5 text-sm font-medium text-slate-800 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20 transition-all outline-none">
                        @error('from_email') <span class="text-xs text-red-500 font-bold">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <div class="col-span-2">
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">SMTP Host</label>
                            <input type="text" name="smtp_host" value="{{ old('smtp_host', $emailConfig->smtp_host) }}" placeholder="smtp.gmail.com" required
                                class="w-full rounded-lg border-slate-200 bg-slate-50/30 px-3 py-2.5 text-sm font-mono text-slate-800 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20 transition-all outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Port</label>
                            <input type="number" name="smtp_port" value="{{ old('smtp_port', $emailConfig->smtp_port) }}" placeholder="587" required
                                class="w-full rounded-lg border-slate-200 bg-slate-50/30 px-3 py-2.5 text-sm font-mono text-slate-800 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20 transition-all outline-none">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">SMTP Username</label>
                        <input type="text" name="smtp_username" value="{{ old('smtp_username', $emailConfig->smtp_username) }}" required
                            class="w-full rounded-lg border-slate-200 bg-slate-50/30 px-3 py-2.5 text-sm font-medium text-slate-800 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20 transition-all outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">SMTP Password</label>
                        <input type="password" name="smtp_password" value="{{ old('smtp_password', $emailConfig->smtp_password) }}" required
                            class="w-full rounded-lg border-slate-200 bg-slate-50/30 px-3 py-2.5 text-sm font-medium text-slate-800 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20 transition-all outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Encryption</label>
                        <div class="relative">
                            <select name="smtp_encryption" class="w-full rounded-lg border-slate-200 bg-slate-50/30 px-3 py-2.5 text-sm font-medium text-slate-800 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20 transition-all outline-none appearance-none">
                                <option value="tls" {{ old('smtp_encryption', $emailConfig->smtp_encryption) === 'tls' ? 'selected' : '' }}>TLS (Recommended)</option>
                                <option value="ssl" {{ old('smtp_encryption', $emailConfig->smtp_encryption) === 'ssl' ? 'selected' : '' }}>SSL</option>
                                <option value="none" {{ old('smtp_encryption', $emailConfig->smtp_encryption) === 'none' ? 'selected' : '' }}>None</option>
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-slate-500">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="space-y-5">
                <div class="flex items-center gap-2 pb-2 border-b border-slate-100">
                    <div class="p-1.5 bg-emerald-50 text-emerald-600 rounded-md">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg>
                    </div>
                    <h4 class="font-bold text-slate-800 text-sm uppercase tracking-wide">Receiving (IMAP)</h4>
                </div>

                <div class="space-y-4">
                    <div class="grid grid-cols-3 gap-3">
                        <div class="col-span-2">
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">IMAP Host</label>
                            <input type="text" name="imap_host" value="{{ old('imap_host', $emailConfig->imap_host) }}" placeholder="imap.gmail.com" required
                                class="w-full rounded-lg border-slate-200 bg-slate-50/30 px-3 py-2.5 text-sm font-mono text-slate-800 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20 transition-all outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Port</label>
                            <input type="number" name="imap_port" value="{{ old('imap_port', $emailConfig->imap_port) }}" placeholder="993" required
                                class="w-full rounded-lg border-slate-200 bg-slate-50/30 px-3 py-2.5 text-sm font-mono text-slate-800 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20 transition-all outline-none">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">IMAP Username</label>
                        <input type="text" name="imap_username" value="{{ old('imap_username', $emailConfig->imap_username) }}" required
                            class="w-full rounded-lg border-slate-200 bg-slate-50/30 px-3 py-2.5 text-sm font-medium text-slate-800 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20 transition-all outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">IMAP Password</label>
                        <input type="password" name="imap_password" value="{{ old('imap_password', $emailConfig->imap_password) }}" required
                            class="w-full rounded-lg border-slate-200 bg-slate-50/30 px-3 py-2.5 text-sm font-medium text-slate-800 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20 transition-all outline-none">
                        <p class="mt-1.5 text-[11px] font-medium text-slate-500">
                            <span class="text-blue-600">Tip:</span> If using Gmail, you must use an <a href="https://myaccount.google.com/apppasswords" target="_blank" class="underline hover:text-blue-700">App Password</a>.
                        </p>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Encryption</label>
                        <div class="relative">
                            <select name="imap_encryption" class="w-full rounded-lg border-slate-200 bg-slate-50/30 px-3 py-2.5 text-sm font-medium text-slate-800 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20 transition-all outline-none appearance-none">
                                <option value="ssl" {{ old('imap_encryption', $emailConfig->imap_encryption) === 'ssl' ? 'selected' : '' }}>SSL (Recommended)</option>
                                <option value="tls" {{ old('imap_encryption', $emailConfig->imap_encryption) === 'tls' ? 'selected' : '' }}>TLS</option>
                                <option value="none" {{ old('imap_encryption', $emailConfig->imap_encryption) === 'none' ? 'selected' : '' }}>None</option>
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-slate-500">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-8 flex items-center gap-4 pt-6 border-t border-slate-100">
            <button type="submit" class="inline-flex items-center justify-center px-5 py-2.5 bg-slate-900 border border-transparent rounded-lg font-bold text-xs text-white uppercase tracking-widest hover:bg-slate-800 active:bg-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2 transition ease-in-out duration-150 shadow-lg shadow-slate-900/10">
                Save Configuration
            </button>

            @if (session('success'))
                <div x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 3000)" class="flex items-center gap-2 text-sm text-emerald-600 font-bold bg-emerald-50 px-3 py-1.5 rounded-full">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    {{ __('Saved Successfully.') }}
                </div>
            @endif
        </div>
    </form>
</div>