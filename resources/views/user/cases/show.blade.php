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
            @include('user.cases.partials.alerts')

            @livewire('user.cases.case-workflow', ['case' => $case])


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

                <div class="lg:col-span-5">
                    @include('user.cases.partials.timeline', ['case' => $case])
                </div>
                
            </div>
        </div>
    </div>
@endsection