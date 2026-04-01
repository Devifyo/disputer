@props(['unreadEmail', 'case'])

@php
    // 1. Map attachments for the Email Viewer modal
    $attachmentsData = \App\Models\Attachment::where('email_id', $unreadEmail->id)->get()->map(function($att) {
        return [
            'name' => $att->file_name,
            'url'  => $att->public_link,
            'type' => $att->mime_type,
            'path' => $att->file_path,
        ];
    })->toArray();

    // 2. Prepare the full payload for the @click dispatch
    $emailPayload = [
        'emailId'     => $unreadEmail->id,
        'caseId'      => $case->id,
        'subject'     => $unreadEmail->subject,
        'body'        => (string) ($unreadEmail->body_html ?? $unreadEmail->body_text),
        'direction'   => 'inbound',
        'attachments' => $attachmentsData,
        'recipient'   => $unreadEmail->sender_email
    ];
@endphp

<div @click="$dispatch('open-email', @js($emailPayload))"
     class="group relative bg-rose-50/50 border border-rose-200 rounded-2xl p-4 shadow-sm hover:shadow-md hover:bg-rose-100/60 transition-all duration-300 cursor-pointer overflow-hidden">
    
    {{-- High-Urgency Left Accent Bar --}}
    <div class="absolute left-0 top-0 bottom-0 w-1.5 bg-rose-600 shadow-[2px_0_10px_rgba(225,29,72,0.2)]"></div>

    <div class="relative flex flex-col sm:flex-row sm:items-center justify-between gap-4 pl-2">
        <div class="flex items-center gap-4">
            {{-- Double-Pulse Icon Container --}}
            <div class="relative flex items-center justify-center shrink-0">
                <div class="absolute inset-0 bg-rose-500 rounded-full animate-ping opacity-30"></div>
                <div class="absolute inset-0 bg-rose-400 rounded-full animate-pulse opacity-20 scale-150"></div>
                
                {{-- Inline SVG: Renders instantly, no JS lag --}}
                <div class="relative bg-rose-600 text-white p-2.5 rounded-xl shadow-lg shadow-rose-200">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 13V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v12c0 1.1.9 2 2 2h8"/>
                        <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>
                        <path d="M19 16v3"/><path d="M19 21h.01"/>
                    </svg>
                </div>
            </div>

            <div>
                <div class="flex items-center gap-2 mb-0.5">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-rose-600 text-[10px] font-black uppercase tracking-widest text-white shadow-sm">
                        Unread Email
                    </span>
                    <span class="text-[10px] font-bold text-rose-500 tabular-nums">
                        {{ $unreadEmail->created_at->diffForHumans() }}
                    </span>
                </div>
                <h3 class="text-sm font-bold text-slate-900 group-hover:text-rose-700 transition-colors">
                    {{ $unreadEmail->subject }}
                </h3>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <div class="px-5 py-2.5 bg-rose-600 text-white text-xs font-black rounded-xl group-hover:bg-rose-700 group-hover:scale-105 transition-all shadow-lg shadow-rose-200/50 flex items-center gap-2">
                <span>View Email</span>
                <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m9 18 6-6-6-6"/>
                </svg>
            </div>
        </div>
    </div>
</div>