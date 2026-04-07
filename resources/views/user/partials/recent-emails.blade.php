{{-- Section header — outside the card, matching active-cases style --}}
<div class="flex items-center justify-between mb-3">
    <h2 class="text-sm font-black text-slate-900 flex items-center gap-2">
        <span class="w-5 h-5 bg-slate-100 rounded-lg flex items-center justify-center">
            <i data-lucide="history" class="w-3 h-3 text-slate-500"></i>
        </span>
        Recent Emails
        @if(count($recentEmails) > 0)
            <span class="text-[10px] font-bold text-slate-400 bg-slate-100 px-2 py-0.5 rounded-full">{{ count($recentEmails) }}</span>
        @endif
    </h2>
</div>

{{-- Card — h-full so it stretches to match the left column --}}
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden flex flex-col flex-1">

    @if(count($recentEmails) > 0)
        <div class="overflow-y-auto divide-y divide-slate-50 flex-1">
            @foreach($recentEmails as $email)
            <div class="flex items-start gap-3 px-4 py-3 hover:bg-slate-50/80 transition-colors">

                <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 mt-0.5
                    {{ $email->direction === 'inbound' ? 'bg-blue-50 text-blue-500' : 'bg-emerald-50 text-emerald-600' }}">
                    @if($email->direction === 'inbound')
                        <i data-lucide="arrow-down-left" class="w-3.5 h-3.5"></i>
                    @else
                        <i data-lucide="arrow-up-right" class="w-3.5 h-3.5"></i>
                    @endif
                </div>

                <div class="flex-1 min-w-0">
                    <div class="flex items-start justify-between gap-2">
                        <p class="text-xs font-bold text-slate-800 truncate leading-tight">{{ $email->subject ?? 'No Subject' }}</p>
                        <span class="text-[10px] text-slate-400 font-medium shrink-0">{{ $email->created_at->format('M d') }}</span>
                    </div>
                    <div class="flex items-center justify-between mt-1 gap-2">
                        <p class="text-[11px] text-slate-400 truncate">
                            {{ $email->direction === 'inbound' ? $email->sender_email : $email->recipient_email }}
                        </p>
                        <span class="text-[9px] font-mono font-bold text-slate-400 bg-slate-100 px-1.5 py-0.5 rounded shrink-0">
                            #{{ $email->case->case_reference_id }}
                        </span>
                    </div>
                </div>
            </div>
            @endforeach
        </div>

    @else
        <div class="flex-1 flex flex-col items-center justify-center text-center px-6 py-10">
            <div class="w-12 h-12 bg-slate-50 rounded-2xl flex items-center justify-center mb-4 border border-slate-100">
                <i data-lucide="mail-open" class="w-6 h-6 text-slate-300"></i>
            </div>
            <p class="text-sm font-bold text-slate-700">No email activity yet</p>
            <p class="text-xs text-slate-400 mt-1.5 leading-relaxed max-w-[180px]">
                Emails for your cases will appear here automatically.
            </p>
        </div>
    @endif

</div>
