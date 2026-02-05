@extends('layouts.app')

@section('title', 'Email Thread')

@section('content')
    <header class="h-16 sticky top-0 z-30 flex items-center justify-between px-6 bg-white/80 backdrop-blur-md border-b border-slate-200">
        <div class="flex items-center gap-4">
            <a href="{{ route('user.emails.index') }}" class="p-2 -ml-2 rounded-full hover:bg-slate-100 text-slate-400 transition-colors">
                <i data-lucide="arrow-left" class="w-5 h-5"></i>
            </a>
            <div>
                <h1 class="font-bold text-slate-900 text-sm">Re: Formal Billing Dispute</h1>
                <div class="flex items-center gap-2 text-xs text-slate-500">
                    <span class="font-medium text-blue-600">Case #{{ $case->id }}</span>
                    <span>•</span>
                    <span>{{ $case->institution }}</span>
                </div>
            </div>
        </div>
        
        <div class="flex items-center gap-2">
            <button class="bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-3 py-1.5 rounded-lg text-xs font-bold transition-all shadow-sm">
                Mark Resolved
            </button>
            <button class="bg-slate-900 hover:bg-slate-800 text-white px-3 py-1.5 rounded-lg text-xs font-bold transition-all shadow-sm flex items-center gap-2">
                <i data-lucide="reply" class="w-3 h-3"></i> Reply
            </button>
        </div>
    </header>

    <div class="p-6 max-w-4xl mx-auto space-y-8 pb-32">
        
        @foreach($messages as $msg)
        <div class="flex gap-4 {{ $msg->type == 'outgoing' ? 'flex-row-reverse' : '' }}">
            
            <div class="shrink-0">
                @if($msg->type == 'outgoing')
                    <div class="w-10 h-10 rounded-full bg-slate-900 text-white flex items-center justify-center font-bold text-xs shadow-md">ME</div>
                @else
                    <div class="w-10 h-10 rounded-full bg-white border border-slate-200 flex items-center justify-center shadow-sm">
                        <img src="https://ui-avatars.com/api/?name=Chase+Bank&background=random" class="w-full h-full rounded-full opacity-80" alt="Bank">
                    </div>
                @endif
            </div>

            <div class="flex flex-col max-w-[85%] {{ $msg->type == 'outgoing' ? 'items-end' : 'items-start' }}">
                
                <div class="flex items-center gap-2 mb-1 px-1">
                    <span class="text-xs font-bold text-slate-700">{{ $msg->sender }}</span>
                    <span class="text-[10px] text-slate-400">{{ $msg->date }}</span>
                </div>

                <div class="rounded-2xl p-6 shadow-sm border {{ $msg->type == 'outgoing' ? 'bg-blue-50 border-blue-100 rounded-tr-none' : 'bg-white border-slate-200 rounded-tl-none' }}">
                    <p class="text-sm text-slate-700 whitespace-pre-wrap leading-relaxed">{{ $msg->body }}</p>
                    
                    @if(!empty($msg->attachments))
                    <div class="mt-4 pt-4 border-t border-slate-200/60 space-y-2">
                        @foreach($msg->attachments as $file)
                        <div class="flex items-center gap-3 p-2 rounded-lg bg-white/50 border border-slate-200 hover:bg-white transition-colors cursor-pointer w-full max-w-xs">
                            <div class="w-8 h-8 rounded bg-red-50 text-red-500 flex items-center justify-center">
                                <i data-lucide="file-text" class="w-4 h-4"></i>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-xs font-bold text-slate-700 truncate">{{ $file }}</p>
                                <p class="text-[10px] text-slate-400">PDF • 1.2 MB</p>
                            </div>
                            <i data-lucide="download" class="w-4 h-4 text-slate-400"></i>
                        </div>
                        @endforeach
                    </div>
                    @endif
                </div>

            </div>
        </div>
        @endforeach

    </div>

    <div class="fixed bottom-0 left-0 lg:left-64 right-0 p-4 bg-white border-t border-slate-200 shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.05)] z-20">
        <div class="max-w-4xl mx-auto flex gap-3">
            <button class="p-3 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-lg transition-colors">
                <i data-lucide="paperclip" class="w-5 h-5"></i>
            </button>
            <input type="text" placeholder="Type your reply here..." class="flex-1 bg-slate-100 border-transparent focus:bg-white focus:border-blue-500 focus:ring-0 rounded-lg text-sm px-4">
            <button class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-bold text-sm shadow-md shadow-blue-500/20 transition-all">
                Send
            </button>
        </div>
    </div>
@endsection