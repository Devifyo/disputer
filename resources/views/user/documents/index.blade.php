@extends('layouts.app')

@section('title', 'Document Library')

@section('content')
    <header class="h-16 sticky top-0 z-30 flex items-center justify-between px-4 sm:px-6 lg:px-8 bg-white/80 backdrop-blur-md border-b border-slate-200 transition-all">
        <div class="flex items-center gap-4">
            <button id="open-sidebar" class="lg:hidden p-2 -ml-2 text-slate-400 hover:text-slate-700 hover:bg-slate-100 rounded-lg transition-colors">
                <i data-lucide="menu" class="w-5 h-5"></i>
            </button>
            <h1 class="font-bold text-slate-900 text-lg tracking-tight flex items-center gap-2">
                Library
                <span class="hidden sm:flex h-5 px-2 items-center justify-center rounded-full bg-slate-100 text-[10px] font-bold text-slate-600 border border-slate-200">
                    {{ $documents->total() }} Files
                </span>
            </h1>
        </div>
        
        <div>
            <a href="{{ route('user.cases.create') }}" class="group">
                <button class="bg-slate-900 hover:bg-slate-800 text-white px-4 py-2 rounded-lg text-xs font-semibold shadow-lg shadow-slate-900/10 transition-all flex items-center gap-2 transform active:scale-95">
                    <i data-lucide="upload-cloud" class="w-4 h-4 text-slate-300 group-hover:text-white transition-colors"></i>
                    <span>Upload New</span>
                </button>
            </a>
        </div>
    </header>

    <div class="flex-1 overflow-y-auto bg-slate-50 p-4 sm:p-6 lg:p-8">
        <div class="max-w-7xl mx-auto space-y-8">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm flex items-center gap-4 group hover:border-blue-300 transition-colors">
                    <div class="w-12 h-12 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center group-hover:scale-110 transition-transform">
                        <i data-lucide="files" class="w-6 h-6"></i>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total Files</p>
                        <p class="text-2xl font-bold text-slate-900">{{ $documents->total() }}</p>
                    </div>
                </div>

                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm flex items-center gap-4 group hover:border-purple-300 transition-colors">
                    <div class="w-12 h-12 rounded-lg bg-purple-50 text-purple-600 flex items-center justify-center group-hover:scale-110 transition-transform">
                        <i data-lucide="clock" class="w-6 h-6"></i>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Latest Upload</p>
                        <p class="text-sm font-bold text-slate-900">
                            {{ $documents->first() ? $documents->first()->created_at->diffForHumans() : 'N/A' }}
                        </p>
                    </div>
                </div>
            </div>

            <form id="docFilterForm" method="GET" action="{{ route('user.documents.index') }}" class="bg-white p-1.5 rounded-xl border border-slate-200 shadow-sm flex flex-col sm:flex-row gap-1 focus-within:ring-2 focus-within:ring-blue-500/20 transition-all">
                <div class="relative flex-1 group">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i data-lucide="search" class="w-4 h-4 text-slate-400 group-focus-within:text-blue-500 transition-colors"></i>
                    </div>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by filename or Case ID..." 
                           class="block w-full pl-10 pr-3 py-2.5 bg-transparent border-0 text-sm text-slate-900 placeholder:text-slate-400 focus:ring-0 focus:outline-none rounded-lg hover:bg-slate-50 transition-colors">
                </div>
                
                <div class="hidden sm:block w-px bg-slate-100 my-1"></div>

                <div class="relative min-w-[180px]">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i data-lucide="filter" class="w-3.5 h-3.5 text-slate-400"></i>
                    </div>
                    <select name="type" onchange="document.getElementById('docFilterForm').submit()" 
                            class="block w-full pl-9 pr-8 py-2.5 bg-transparent hover:bg-slate-50 border-0 rounded-lg text-xs font-semibold text-slate-600 focus:ring-0 cursor-pointer transition-colors appearance-none">
                        <option value="">All File Types</option>
                        <option value="pdf" {{ request('type') == 'pdf' ? 'selected' : '' }}>PDF Documents</option>
                        <option value="image" {{ request('type') == 'image' ? 'selected' : '' }}>Images (JPG/PNG)</option>
                        <option value="doc" {{ request('type') == 'doc' ? 'selected' : '' }}>Word / Text</option>
                    </select>
                    <div class="absolute inset-y-0 right-0 pr-2 flex items-center pointer-events-none">
                        <i data-lucide="chevron-down" class="w-3 h-3 text-slate-400"></i>
                    </div>
                </div>
            </form>

            @if($documents->count() > 0)
                
                <div class="hidden sm:block bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50/50 border-b border-slate-100 text-[10px] uppercase tracking-wider text-slate-500 font-bold">
                                <th class="px-6 py-4 pl-8 w-[40%]">Document Name</th>
                                <th class="px-6 py-4 w-[25%]">Associated Case</th>
                                <th class="px-6 py-4 w-[15%]">Uploaded</th>
                                <th class="px-6 py-4 w-[10%]">Share</th>
                                <th class="px-6 py-4 text-right pr-8 w-[10%]"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            @foreach($documents as $doc)
                            <tr class="group hover:bg-slate-50/60 transition-colors">
                                
                                <td class="px-6 py-4 pl-8">
                                    <div class="flex items-center gap-4">
                                        @php
                                            $isImage = Str::contains($doc->mime_type, 'image');
                                            $isPdf = Str::contains($doc->mime_type, 'pdf');
                                            $iconStyle = $isImage ? 'bg-purple-100 text-purple-600' : ($isPdf ? 'bg-red-100 text-red-600' : 'bg-blue-100 text-blue-600');
                                        @endphp
                                        <div class="w-10 h-10 rounded-lg {{ $iconStyle }} flex items-center justify-center shrink-0 shadow-sm group-hover:scale-105 transition-transform">
                                            @if($isImage) <i data-lucide="image" class="w-5 h-5"></i>
                                            @elseif($isPdf) <i data-lucide="file-text" class="w-5 h-5"></i>
                                            @else <i data-lucide="file" class="w-5 h-5"></i>
                                            @endif
                                        </div>
                                        <div class="min-w-0">
                                            <p class="text-sm font-bold text-slate-900 truncate max-w-[220px]" title="{{ $doc->file_name }}">
                                                {{ $doc->file_name }}
                                            </p>
                                            <p class="text-[10px] text-slate-500 font-medium uppercase tracking-wide">{{ Str::afterLast($doc->file_name, '.') }}</p>
                                        </div>
                                    </div>
                                </td>
                                
                                <td class="px-6 py-4">
                                    @if($doc->case)
                                    <a href="{{ route('user.cases.show', $doc->case->case_reference_id) }}" class="group/link flex items-center gap-2">
                                        <div class="w-1.5 h-1.5 rounded-full bg-slate-300 group-hover/link:bg-blue-500 transition-colors"></div>
                                        <div>
                                            <p class="text-xs font-bold text-slate-700 group-hover/link:text-blue-600 transition-colors truncate max-w-[150px]">
                                                {{ $doc->case->institution_name }}
                                            </p>
                                            <p class="text-[10px] font-mono text-slate-400 group-hover/link:text-blue-400">
                                                #{{ $doc->case->case_reference_id }}
                                            </p>
                                        </div>
                                    </a>
                                    @else
                                        <span class="text-xs text-slate-400 italic">Unattached</span>
                                    @endif
                                </td>

                                <td class="px-6 py-4">
                                    <span class="text-xs font-medium text-slate-600">
                                        {{ $doc->created_at->format('M d, Y') }}
                                    </span>
                                </td>

                                <td class="px-6 py-4">
                                    <div x-data="{ copied: false }">
                                        <button 
                                            @click="navigator.clipboard.writeText('{{ $doc->public_link }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                            class="flex items-center gap-2 px-2.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-md transition-all text-xs font-bold group/btn focus:ring-2 focus:ring-slate-300 focus:outline-none"
                                            title="Copy Direct Link">
                                            
                                            <template x-if="!copied">
                                                <i data-lucide="link" class="w-3.5 h-3.5 group-hover/btn:text-blue-600"></i>
                                            </template>
                                            <template x-if="copied">
                                                <i data-lucide="check" class="w-3.5 h-3.5 text-green-600"></i>
                                            </template>
                                            
                                            <span x-text="copied ? 'Copied' : 'Link'"></span>
                                        </button>
                                    </div>
                                </td>

                                <td class="px-6 py-4 text-right pr-8">
                                    <a href="{{ $doc->public_link }}" target="_blank" 
                                       class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-slate-400 hover:text-blue-600 hover:bg-blue-50 transition-all opacity-0 group-hover:opacity-100 transform translate-x-2 group-hover:translate-x-0"
                                       title="Download File">
                                        <i data-lucide="download" class="w-4 h-4"></i>
                                    </a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="grid grid-cols-1 sm:hidden gap-4">
                    @foreach($documents as $doc)
                    <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex items-start gap-4 active:scale-[0.98] transition-transform">
                        @php
                            $isImage = Str::contains($doc->mime_type, 'image');
                            $isPdf = Str::contains($doc->mime_type, 'pdf');
                            $iconBg = $isImage ? 'bg-purple-100 text-purple-600' : ($isPdf ? 'bg-red-100 text-red-600' : 'bg-blue-100 text-blue-600');
                        @endphp
                        <div class="w-12 h-12 rounded-xl {{ $iconBg }} flex items-center justify-center shrink-0 shadow-inner">
                            @if($isImage) <i data-lucide="image" class="w-6 h-6"></i>
                            @elseif($isPdf) <i data-lucide="file-text" class="w-6 h-6"></i>
                            @else <i data-lucide="file" class="w-6 h-6"></i>
                            @endif
                        </div>
                        
                        <div class="flex-1 min-w-0">
                            <div class="flex justify-between items-start mb-1">
                                <h3 class="text-sm font-bold text-slate-900 truncate pr-2">{{ $doc->file_name }}</h3>
                                <a href="{{ $doc->public_link }}" target="_blank" class="p-1 -mr-2 text-slate-400 hover:text-blue-600">
                                    <i data-lucide="download" class="w-4 h-4"></i>
                                </a>
                            </div>
                            
                            <div class="flex items-center gap-3 text-xs text-slate-500 mb-2">
                                <span>{{ $doc->created_at->format('M d') }}</span>
                                <span class="text-slate-300">|</span>
                                
                                <div x-data="{ copied: false }">
                                    <button 
                                        @click="navigator.clipboard.writeText('{{ $doc->public_link }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                        class="flex items-center gap-1 hover:text-blue-600 transition-colors">
                                        <template x-if="!copied">
                                            <i data-lucide="link" class="w-3 h-3"></i>
                                        </template>
                                        <template x-if="copied">
                                            <i data-lucide="check" class="w-3 h-3 text-green-600"></i>
                                        </template>
                                        <span x-text="copied ? 'Copied' : 'Copy Link'"></span>
                                    </button>
                                </div>
                            </div>
                            
                            @if($doc->case)
                            <div class="inline-flex items-center gap-1.5 px-2 py-1 rounded-md bg-slate-50 border border-slate-100">
                                <i data-lucide="folder" class="w-3 h-3 text-slate-400"></i>
                                <span class="text-xs font-semibold text-slate-700 max-w-[150px] truncate">{{ $doc->case->institution_name }}</span>
                            </div>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>

                <div class="mt-8">
                    {{ $documents->withQueryString()->links() }}
                </div>

            @else
                <div class="flex flex-col items-center justify-center py-20 bg-white rounded-xl border border-slate-200 border-dashed text-center">
                    <div class="w-20 h-20 bg-slate-50 rounded-full flex items-center justify-center mb-6 shadow-sm">
                        <i data-lucide="folder-search" class="w-10 h-10 text-slate-300"></i>
                    </div>
                    <h3 class="text-slate-900 font-bold text-xl tracking-tight">No documents found</h3>
                    @if(request('search') || request('type'))
                        <p class="text-slate-500 text-sm mt-2 max-w-xs mx-auto">
                            We couldn't find any files matching "<strong>{{ request('search') }}</strong>". <br>Try adjusting your filters.
                        </p>
                        <a href="{{ route('user.documents.index') }}" class="mt-6 text-sm font-bold text-blue-600 hover:text-blue-800 hover:underline">
                            Clear all filters
                        </a>
                    @else
                        <p class="text-slate-500 text-sm mt-2 max-w-xs mx-auto">
                            Your library is empty. Documents uploaded during your dispute process will appear here.
                        </p>
                        <a href="{{ route('user.cases.create') }}" class="mt-6 inline-flex items-center gap-2 text-sm font-bold text-white bg-slate-900 hover:bg-slate-800 px-6 py-3 rounded-xl shadow-lg hover:shadow-xl transition-all">
                            Start a New Dispute
                        </a>
                    @endif
                </div>
            @endif

        </div>
    </div>
@endsection