@if(!$isEmailConfigured)
{{-- Email not connected warning --}}
<div class="bg-white rounded-2xl border border-rose-200 shadow-sm overflow-hidden">
    <div class="p-4 flex items-start gap-3">
        <div class="w-9 h-9 bg-rose-100 rounded-xl flex items-center justify-center text-rose-600 shrink-0 mt-0.5">
            <i data-lucide="alert-triangle" class="w-4 h-4"></i>
        </div>
        <div class="flex-1 min-w-0">
            <h3 class="text-sm font-bold text-slate-900">Setup Required</h3>
            <p class="text-xs text-slate-500 mt-0.5 leading-relaxed">Connect your email to start creating and tracking dispute cases automatically.</p>
        </div>
    </div>
    <div class="px-4 pb-4">
        <a href="{{ route('profile.edit') }}#email-settings"
           class="w-full flex items-center justify-center gap-2 bg-rose-600 hover:bg-rose-700 text-white py-2.5 rounded-xl text-xs font-bold transition-all shadow-sm">
            <i data-lucide="mail" class="w-3.5 h-3.5"></i>
            Connect Email Now
        </a>
    </div>
</div>
@else
{{-- All systems operational --}}
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="p-4 flex items-start gap-3">
        <div class="w-9 h-9 bg-emerald-100 rounded-xl flex items-center justify-center text-emerald-600 shrink-0 mt-0.5">
            <i data-lucide="shield-check" class="w-4 h-4"></i>
        </div>
        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2">
                <h3 class="text-sm font-bold text-slate-900">System Operational</h3>
                <span class="flex h-1.5 w-1.5 relative">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-1.5 w-1.5 bg-emerald-500"></span>
                </span>
            </div>
            <p class="text-xs text-slate-500 mt-0.5 leading-relaxed">Email connected. Cases are being created and tracked automatically.</p>
        </div>
    </div>
    <div class="border-t border-slate-100 px-4 py-3 grid grid-cols-2 gap-3">
        <div class="text-center">
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-0.5">Email</p>
            <span class="inline-flex items-center gap-1 text-[10px] font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-full">
                <i data-lucide="check" class="w-2.5 h-2.5"></i> Connected
            </span>
        </div>
        <div class="text-center">
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-0.5">Auto-track</p>
            <span class="inline-flex items-center gap-1 text-[10px] font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-full">
                <i data-lucide="check" class="w-2.5 h-2.5"></i> Active
            </span>
        </div>
    </div>
</div>
@endif
