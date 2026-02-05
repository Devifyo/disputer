@extends('layouts.app')

@section('title', 'Case #' . $case->case_reference_id)

@section('content')
    <header class="bg-white border-b border-slate-200 h-16 sticky top-0 z-30 flex items-center justify-between px-4 sm:px-6 lg:px-8 bg-opacity-90 backdrop-blur-md">
        <div class="flex items-center gap-4">
            <a href="{{ route('user.cases.index') }}" class="group p-2 -ml-2 rounded-lg hover:bg-slate-100 transition-colors text-slate-400 hover:text-slate-700">
                <i data-lucide="arrow-left" class="w-5 h-5 transition-transform group-hover:-translate-x-0.5"></i>
            </a>
            
            <div class="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-3">
                <h1 class="font-bold text-slate-900 text-lg tracking-tight flex items-center gap-2">
                    <span class="font-mono text-slate-500">#</span>{{ $case->case_reference_id }}
                </h1>
                
                @php
                    $statusColors = match(strtolower($case->status?->value ?? $case->status)) {
                        'sent', 'pending' => 'bg-amber-50 text-amber-700 border-amber-200',
                        'resolved', 'closed' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                        'escalated' => 'bg-purple-50 text-purple-700 border-purple-200',
                        default => 'bg-slate-100 text-slate-600 border-slate-200'
                    };
                @endphp
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border {{ $statusColors }}">
                    {{ ucfirst($case->status?->value ?? $case->status) }}
                </span>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <button class="hidden sm:flex items-center gap-2 px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs font-semibold text-slate-700 hover:bg-slate-50 hover:text-slate-900 shadow-sm transition-all">
                <i data-lucide="download" class="w-4 h-4"></i>
                <span>Export PDF</span>
            </button>
        </div>
    </header>

    <div class="flex-1 overflow-y-auto bg-slate-50/50 p-4 sm:p-6 lg:p-8">
        <div class="max-w-7xl mx-auto space-y-6">

            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 relative overflow-hidden">
                <div class="flex flex-col sm:flex-row sm:justify-between sm:items-end gap-4 relative z-10">
                    <div>
                        <div class="flex items-center gap-2 mb-1">
                            <span class="text-xs font-bold text-blue-600 uppercase tracking-wider">Current Stage</span>
                            @if($case->status?->value !== 'Closed')
                                <span class="relative flex h-2 w-2">
                                  <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-blue-400 opacity-75"></span>
                                  <span class="relative inline-flex rounded-full h-2 w-2 bg-blue-500"></span>
                                </span>
                            @endif
                        </div>
                        <h2 class="text-2xl font-bold text-slate-900">{{ $workflow['step_name'] ?? 'Processing' }}</h2>
                        <p class="text-sm text-slate-500 mt-1">Step {{ $workflow['current_step'] }} of {{ $workflow['total_steps'] }} in the dispute resolution process.</p>
                    </div>

                    @if($case->next_action_at)
                        <div class="bg-slate-50 border border-slate-200 rounded-lg px-4 py-3 flex items-start gap-3">
                            <div class="bg-white p-1.5 rounded-md shadow-sm border border-slate-100 text-blue-600">
                                <i data-lucide="calendar-clock" class="w-5 h-5"></i>
                            </div>
                            <div>
                                <p class="text-xs font-medium text-slate-500 uppercase">Estimated Completion</p>
                                <p class="text-sm font-bold text-slate-900 font-mono">{{ $case->next_action_at->format('M d, Y') }}</p>
                            </div>
                        </div>
                    @endif
                </div>

                <div class="mt-8 relative h-2 w-full bg-slate-100 rounded-full overflow-hidden">
                    <div class="absolute top-0 left-0 h-full bg-gradient-to-r from-blue-500 to-indigo-600 transition-all duration-1000 ease-out rounded-full" style="width: {{ $workflow['progress_percent'] }}%"></div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-8">
                
                <div class="lg:col-span-7 space-y-6">
                    
                    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                        <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-2 bg-slate-50/30">
                            <i data-lucide="file-text" class="w-4 h-4 text-slate-400"></i>
                            <h3 class="font-bold text-slate-800 text-sm">Dispute Information</h3>
                        </div>
                        
                        <div class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-y-6 gap-x-8">
                            <div class="group">
                                <label class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1 block group-hover:text-blue-600 transition-colors">Institution</label>
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-lg bg-slate-100 border border-slate-200 flex items-center justify-center text-xs font-bold text-slate-600">
                                        {{ substr($case->institution_name, 0, 1) }}
                                    </div>
                                    <div>
                                        <p class="font-bold text-slate-900 text-sm">{{ $case->institution_name }}</p>
                                        <p class="text-xs text-slate-500">{{ $case->institution->category->name ?? 'Financial' }}</p>
                                    </div>
                                </div>
                            </div>

                            <div class="group">
                                <label class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1 block group-hover:text-blue-600 transition-colors">Disputed Amount</label>
                                <p class="text-2xl font-bold text-slate-900 tracking-tight">
                                    ${{ number_format((float)($metadata['amount'] ?? 0), 2) }}
                                </p>
                            </div>

                            <div>
                                <label class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1 block">Transaction Date</label>
                                <p class="text-sm font-medium text-slate-700 flex items-center gap-2">
                                    <i data-lucide="calendar" class="w-3.5 h-3.5 text-slate-400"></i>
                                    {{ $metadata['txn_date'] ?? 'N/A' }}
                                </p>
                            </div>

                            <div>
                                <label class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1 block">Reference No.</label>
                                <p class="text-sm font-mono font-medium text-slate-600 bg-slate-50 inline-block px-2 py-0.5 rounded border border-slate-100">
                                    {{ $metadata['ref_num'] ?? 'N/A' }}
                                </p>
                            </div>
                        </div>
                    </div>

                    @if($case->attachments->count() > 0)
                    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                        <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-2 bg-slate-50/30">
                            <i data-lucide="paperclip" class="w-4 h-4 text-slate-400"></i>
                            <h3 class="font-bold text-slate-800 text-sm">Attachments</h3>
                        </div>
                        <ul class="divide-y divide-slate-50">
                            @foreach($case->attachments as $file)
                            <li class="flex items-center justify-between p-4 hover:bg-slate-50 transition-colors group">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center border border-blue-100 group-hover:scale-105 transition-transform">
                                        @if(Str::contains($file->mime_type, 'image'))
                                            <i data-lucide="image" class="w-5 h-5"></i>
                                        @else
                                            <i data-lucide="file-text" class="w-5 h-5"></i>
                                        @endif
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-slate-900">{{ $file->file_name }}</p>
                                        <p class="text-[10px] text-slate-500 uppercase font-semibold">{{ Str::afterLast($file->file_name, '.') }} • {{ $file->created_at->format('M d') }}</p>
                                    </div>
                                </div>
                                <a href="{{$file->public_link}}" 
                                    target="_blank" 
                                    class="inline-flex items-center justify-center text-blue-600 hover:text-blue-800 hover:bg-blue-50 p-1.5 rounded-md transition-all"
                                    title="View File">
                                        <i data-lucide="eye" class="w-4 h-4"></i>
                                </a>
                            </li>
                            @endforeach
                        </ul>
                    </div>
                    @endif
                </div>
                {{-- timeline --}}
               <div class="lg:col-span-5" 
                    x-data="{ 
                        modalOpen: false, 
                        modalSubject: '', 
                        modalBody: '', 
                        openModal(subject, body) { 
                            this.modalSubject = subject; 
                            this.modalBody = body.replace(/\\n/g, '\n').replace(/\\r/g, ''); 
                            this.modalOpen = true; 
                        } 
                    }"
                >
    
                    <div class="bg-white rounded-xl border border-slate-200 shadow-sm h-full flex flex-col">
                        <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-2 bg-slate-50/30">
                            <i data-lucide="activity" class="w-4 h-4 text-slate-400"></i>
                            <h3 class="font-bold text-slate-800 text-sm">Activity Log</h3>
                        </div>
                        
                        <div class="p-6 flex-1">
                            <div class="relative space-y-8">
                                
                                <div class="absolute top-2 bottom-2 left-4 w-0.5 bg-slate-100"></div>

                                @foreach($case->timeline as $index => $log)
                                <div class="relative pl-12">
                                    <div class="absolute left-0 top-0 bg-white border-2 border-slate-100 rounded-full w-8 h-8 flex items-center justify-center z-10 shadow-sm
                                        {{ $log->type == 'email_sent' ? 'text-blue-500 border-blue-100' : 'text-slate-400' }}">
                                        @if($log->type == 'email_sent')
                                            <i data-lucide="mail" class="w-3.5 h-3.5"></i>
                                        @elseif($log->type == 'case_created')
                                            <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                                        @else
                                            <i data-lucide="circle" class="w-3 h-3"></i>
                                        @endif
                                    </div>

                                    <div class="flex flex-col gap-1.5">
                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                            <span class="text-sm font-bold text-slate-900 leading-none">
                                                {{ $log->readable_type ?? ucfirst(str_replace('_', ' ', $log->type)) }}
                                            </span>
                                            <span class="text-[10px] font-semibold text-slate-400 uppercase bg-slate-50 px-1.5 py-0.5 rounded border border-slate-100 whitespace-nowrap">
                                                {{ $log->occurred_at ? $log->occurred_at->diffForHumans(null, true) : 'N/A' }}
                                            </span>
                                        </div>
                                        
                                        <p class="text-xs text-slate-600 leading-relaxed">
                                            {{ $log->description }}
                                        </p>

                                        @if(isset($log->metadata['full_body']))
                                            <button 
                                                @click="openModal(
                                                    {{ json_encode($log->metadata['subject'] ?? 'System Communication') }}, 
                                                    {{ json_encode($log->metadata['full_body']) }}
                                                )"
                                                class="mt-2 flex items-center gap-3 w-full p-2.5 rounded-lg border border-slate-200 bg-slate-50/50 hover:bg-blue-50 hover:border-blue-200 hover:shadow-sm transition-all group/btn text-left">
                                                
                                                <div class="bg-white p-1.5 rounded border border-slate-200 group-hover/btn:border-blue-200 shrink-0">
                                                    <i data-lucide="eye" class="w-3.5 h-3.5 text-slate-400 group-hover/btn:text-blue-500"></i>
                                                </div>
                                                
                                                <div class="min-w-0 flex-1">
                                                    <span class="block text-xs font-bold text-slate-700 group-hover/btn:text-blue-700 truncate">
                                                        View Email Content
                                                    </span>
                                                    <span class="block text-[10px] text-slate-400 group-hover/btn:text-blue-400 truncate w-full">
                                                        {{ $log->metadata['subject'] ?? 'No Subject' }}
                                                    </span>
                                                </div>
                                            </button>
                                        @endif
                                    </div>
                                </div>
                                @endforeach

                                @if($case->timeline->isEmpty())
                                    <div class="text-center py-8 relative z-10 bg-white">
                                        <p class="text-xs text-slate-400 italic">No activity recorded yet.</p>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div x-show="modalOpen" style="display: none;" 
                        class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6"
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0"
                        x-transition:enter-end="opacity-100"
                        x-transition:leave="transition ease-in duration-150"
                        x-transition:leave-start="opacity-100"
                        x-transition:leave-end="opacity-0">
                        
                        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="modalOpen = false"></div>
                        
                        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[85vh] flex flex-col overflow-hidden ring-1 ring-slate-900/5"
                            x-transition:enter="transition ease-out duration-300"
                            x-transition:enter-start="opacity-0 translate-y-8 scale-95"
                            x-transition:enter-end="opacity-100 translate-y-0 scale-100">
                            
                            <div class="px-6 py-5 border-b border-slate-100 flex items-start justify-between bg-slate-50/80">
                                <div class="min-w-0 flex-1 mr-4">
                                    <h3 class="text-lg font-bold text-slate-900 leading-tight truncate" x-text="modalSubject"></h3>
                                    <p class="text-xs text-slate-500 mt-1 flex items-center gap-1.5">
                                        <i data-lucide="shield-check" class="w-3 h-3 text-green-500"></i>
                                        Sent securely via System
                                    </p>
                                </div>
                                <button @click="modalOpen = false" class="p-2 rounded-lg hover:bg-slate-200 text-slate-400 hover:text-slate-600 transition-colors shrink-0">
                                    <i data-lucide="x" class="w-5 h-5"></i>
                                </button>
                            </div>

                            <div class="p-6 overflow-y-auto bg-white flex-1">
                                <div class="p-4 rounded-xl bg-slate-50 border border-slate-100">
                                    <div class="text-sm text-slate-700 font-mono whitespace-pre-wrap leading-relaxed select-text break-words" x-text="modalBody"></div>
                                </div>
                            </div>

                            <div class="px-6 py-4 border-t border-slate-100 bg-slate-50 flex justify-end gap-3">
                                <button @click="modalOpen = false" class="px-4 py-2 bg-white border border-slate-300 rounded-lg text-sm font-semibold text-slate-700 hover:bg-slate-50 hover:text-slate-900 shadow-sm transition-all">
                                    Close Viewer
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                {{-- end of timeline --}}
            </div>
        </div>
    </div>
@endsection