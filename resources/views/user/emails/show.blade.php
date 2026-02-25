@extends('layouts.app')

@section('title', 'Read Email')

@section('content')
    <div class="flex flex-col h-[calc(100vh-4rem)] bg-slate-50">

        <header class="shrink-0 h-16 flex items-center justify-between px-4 sm:px-6 bg-white border-b border-slate-200 shadow-sm z-20">
            <div class="flex items-center gap-4 overflow-hidden">
                <a href="{{ route('user.emails.index') }}" class="p-2 -ml-2 rounded-full hover:bg-slate-100 text-slate-500 hover:text-slate-800 transition-colors shrink-0">
                    <i data-lucide="arrow-left" class="w-5 h-5"></i>
                </a>
                <div class="min-w-0">
                    <div class="flex items-center gap-2 mb-0.5">
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-500 border border-slate-200 uppercase tracking-wide">
                            Case #{{ $case->id }}
                        </span>
                        <span class="text-xs font-medium text-slate-400 truncate">{{ $case->institution }}</span>
                    </div>
                    <h1 class="font-bold text-slate-900 text-base sm:text-lg truncate">Formal Billing Dispute - Transaction #9988</h1>
                </div>
            </div>
            
            <div class="flex items-center gap-2 shrink-0">
                <button class="text-slate-400 hover:text-slate-600 p-2 rounded-lg hover:bg-slate-100 transition-colors hidden sm:block" title="Print Thread">
                    <i data-lucide="printer" class="w-5 h-5"></i>
                </button>
                <button class="bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 p-2 sm:px-3 sm:py-1.5 rounded-lg text-xs font-bold transition-all shadow-sm flex items-center gap-2">
                    <i data-lucide="check-circle" class="w-4 h-4 text-emerald-500"></i>
                    <span class="hidden sm:inline">Mark Resolved</span>
                </button>
            </div>
        </header>

        <div class="flex-1 overflow-y-auto p-4 sm:p-6 space-y-6" id="email-scroll-container">
            <div class="max-w-4xl mx-auto space-y-6">

                @foreach($messages as $msg)
                @php
                    $isMe = $msg->type == 'outgoing';
                    $avatarColor = $isMe ? 'bg-slate-900 text-white' : 'bg-white text-blue-600 border border-slate-200';
                    $senderName = $isMe ? 'You' : $msg->sender;
                    $email = $isMe ? 'me@example.com' : 'support@bank.com';
                @endphp

                <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden group">
                    
                    <div class="p-5 border-b border-slate-100 flex items-start gap-4 bg-white/50">
                        <div class="w-10 h-10 sm:w-11 sm:h-11 rounded-full {{ $avatarColor }} flex items-center justify-center shrink-0 text-xs font-bold shadow-sm">
                            @if($isMe)
                                ME
                            @else
                                <img src="https://ui-avatars.com/api/?name={{ urlencode($msg->sender) }}&background=random&color=fff" class="w-full h-full rounded-full object-cover opacity-90" alt="Bank">
                            @endif
                        </div>

                        <div class="flex-1 min-w-0">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1">
                                <div>
                                    <h3 class="text-sm font-bold text-slate-900">
                                        {{ $senderName }}
                                        @if(!$isMe) <span class="text-xs font-normal text-slate-500 ml-1">&lt;{{ $email }}&gt;</span> @endif
                                    </h3>
                                    <div class="text-xs text-slate-500 mt-0.5">
                                        to <span class="text-slate-700 font-medium">{{ $isMe ? 'Bank Support' : 'me' }}</span>
                                    </div>
                                </div>
                                <span class="text-xs text-slate-400 font-medium whitespace-nowrap">{{ $msg->date }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="p-5 sm:p-6 text-slate-800 text-sm leading-relaxed whitespace-pre-wrap font-sans">
{{ $msg->body }}
                    </div>

                    @if(!empty($msg->attachments))
                    <div class="px-5 pb-5 sm:px-6 sm:pb-6">
                        <div class="border-t border-slate-100 pt-4">
                            <p class="text-xs font-bold text-slate-500 mb-3 uppercase tracking-wider flex items-center gap-2">
                                <i data-lucide="paperclip" class="w-3 h-3"></i> {{ count($msg->attachments) }} Attachments
                            </p>
                            <div class="flex flex-wrap gap-3">
                                @foreach($msg->attachments as $file)
                                <div class="flex items-center gap-3 p-2.5 rounded-lg border border-slate-200 bg-slate-50 hover:bg-white hover:border-blue-300 transition-all cursor-pointer group/file w-full sm:w-auto min-w-[200px]">
                                    <div class="w-9 h-9 rounded bg-white border border-slate-200 flex items-center justify-center text-red-500 shrink-0">
                                        <i data-lucide="file-text" class="w-5 h-5"></i>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-xs font-bold text-slate-700 truncate group-hover/file:text-blue-600 transition-colors">{{ $file }}</p>
                                        <p class="text-[10px] text-slate-400">1.4 MB</p>
                                    </div>
                                    <i data-lucide="download" class="w-4 h-4 text-slate-300 group-hover/file:text-blue-500 transition-colors"></i>
                                </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    @endif
                </div>
                @endforeach
                
                <div class="h-4"></div>
            </div>
        </div>

        <div class="shrink-0 bg-white border-t border-slate-200 z-20">
            <form action="#" class="max-w-4xl mx-auto p-4 sm:p-6">
                
                <div class="relative bg-white border border-slate-300 rounded-xl shadow-sm focus-within:ring-2 focus-within:ring-blue-500/20 focus-within:border-blue-500 transition-all">
                    
                    <textarea 
                        rows="2" 
                        placeholder="Write your reply..." 
                        class="w-full bg-transparent border-0 focus:ring-0 text-slate-900 placeholder:text-slate-400 text-sm py-3 px-4 resize-none min-h-[60px] max-h-60"
                        oninput="this.style.height = ''; this.style.height = this.scrollHeight + 'px'"
                    ></textarea>

                    <div class="flex items-center justify-between px-2 pb-2 mt-1">
                        
                        <div class="flex items-center gap-1">
                            <label for="file-upload" class="p-2 text-slate-400 hover:text-slate-700 hover:bg-slate-100 rounded-lg cursor-pointer transition-colors" title="Attach Files">
                                <i data-lucide="paperclip" class="w-5 h-5"></i>
                                <input id="file-upload" type="file" multiple class="hidden" onchange="alert(this.files.length + ' files selected')">
                            </label>
                            
                            <div class="h-4 w-px bg-slate-200 mx-1 hidden sm:block"></div>
                            <button type="button" class="hidden sm:block p-2 text-slate-400 hover:text-slate-700 hover:bg-slate-100 rounded-lg transition-colors"><i data-lucide="bold" class="w-4 h-4"></i></button>
                            <button type="button" class="hidden sm:block p-2 text-slate-400 hover:text-slate-700 hover:bg-slate-100 rounded-lg transition-colors"><i data-lucide="italic" class="w-4 h-4"></i></button>
                        </div>

                        <button class="bg-slate-900 hover:bg-slate-800 text-white px-6 py-2 rounded-lg font-bold text-sm shadow-md transition-all hover:scale-105 active:scale-95 flex items-center gap-2">
                            <span>Send Reply</span>
                            <i data-lucide="send" class="w-3.5 h-3.5"></i>
                        </button>
                    </div>
                </div>

                <p class="text-center text-[10px] text-slate-400 mt-2">
                    Your reply will be sent securely to <strong>{{ $case->institution }}</strong>.
                </p>

            </form>
        </div>

    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            var container = document.getElementById("email-scroll-container");
            container.scrollTop = container.scrollHeight;
        });
    </script>
@endsection