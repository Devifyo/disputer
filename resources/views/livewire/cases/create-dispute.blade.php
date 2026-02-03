<div class="max-w-4xl mx-auto py-10 px-4 sm:px-6">

    <div class="mb-12">
        <div class="flex items-center justify-center space-x-4">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold transition-all duration-300
                    {{ $step >= 1 ? 'bg-slate-900 text-white shadow-lg' : 'bg-slate-100 text-slate-400' }}">
                    @if($step > 1) <i data-lucide="check" class="w-4 h-4"></i> @else 1 @endif
                </div>
                <span class="text-sm font-medium {{ $step >= 1 ? 'text-slate-900' : 'text-slate-400' }}">Institution</span>
            </div>

            <div class="w-12 h-px bg-slate-200"></div>

            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold transition-all duration-300
                    {{ $step >= 2 ? 'bg-slate-900 text-white shadow-lg' : 'bg-slate-100 text-slate-400' }}">
                    2
                </div>
                <span class="text-sm font-medium {{ $step >= 2 ? 'text-slate-900' : 'text-slate-400' }}">Details</span>
            </div>

            <div class="w-12 h-px bg-slate-200"></div>

            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold transition-all duration-300
                    {{ $step >= 3 ? 'bg-slate-900 text-white shadow-lg' : 'bg-slate-100 text-slate-400' }}">
                    3
                </div>
                <span class="text-sm font-medium {{ $step >= 3 ? 'text-slate-900' : 'text-slate-400' }}">Review</span>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-xl shadow-slate-200/50 border border-slate-100 overflow-hidden min-h-[500px] relative">

        @if($step === 1)
            <div class="p-8 md:p-12 animate-fade-in">

                @if($mode === 'search')
                    <div class="text-center max-w-2xl mx-auto mb-10">
                        <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Who is this dispute with?</h1>
                        <p class="text-slate-500 mt-3 text-lg">Search for a bank, airline, or service provider.</p>
                    </div>

                    <div class="relative max-w-xl mx-auto mb-12">
                        <div class="relative group">
                            <div class="absolute inset-y-0 left-0 pl-5 flex items-center pointer-events-none">
                                <i data-lucide="search" class="w-6 h-6 text-slate-400 group-focus-within:text-blue-600 transition-colors"></i>
                            </div>
                            <input wire:model.live.debounce.250ms="query" type="text"
                                class="block w-full pl-14 pr-6 py-5 bg-slate-50 border-2 border-slate-100 rounded-2xl text-slate-900 placeholder-slate-400 text-lg shadow-sm focus:outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 transition-all"
                                placeholder="Start typing name..." autocomplete="off">
                        </div>

                        @if(strlen($query) >= 1)
                        <div class="absolute top-full left-0 right-0 mt-3 bg-white rounded-xl border border-slate-100 shadow-2xl z-50 overflow-hidden divide-y divide-slate-50">

                            @forelse($results as $inst)
                                <button wire:click="selectExisting({{ $inst->id }}, '{{ $inst->name }}')" class="w-full text-left px-5 py-4 hover:bg-slate-50 flex items-center justify-between group transition-colors">
                                    <div class="flex items-center gap-4">
                                        <div class="w-10 h-10 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center font-bold text-sm border border-blue-200">
                                            {{ substr($inst->name, 0, 2) }}
                                        </div>
                                        <div>
                                            <p class="font-semibold text-slate-900 text-base">{{ $inst->name }}</p>
                                            <p class="text-xs text-slate-500 font-medium uppercase tracking-wide">{{ $inst->category->name ?? 'General' }}</p>
                                        </div>
                                    </div>
                                    <i data-lucide="arrow-right" class="w-5 h-5 text-slate-300 group-hover:text-blue-600 transition-colors"></i>
                                </button>
                            @empty
                            @endforelse

                            @php
                                $exactMatch = $results->first(function($inst) use ($query) {
                                    return strcasecmp($inst->name, trim($query)) === 0;
                                });
                            @endphp

                            @if(!$exactMatch)
                            <button wire:click="enableCreateMode" class="w-full text-left px-5 py-5 bg-slate-50 hover:bg-blue-50/50 flex items-center gap-4 group transition-colors">
                                <div class="w-10 h-10 rounded-full bg-white border-2 border-dashed border-slate-300 flex items-center justify-center text-slate-400 group-hover:border-blue-500 group-hover:text-blue-500 transition-colors">
                                    <i data-lucide="plus" class="w-5 h-5"></i>
                                </div>
                                <div>
                                    <p class="font-semibold text-slate-900">Create "{{ $query ?: 'New' }}"</p>
                                    <p class="text-xs text-slate-500">Institution not listed? Add it manually.</p>
                                </div>
                            </button>
                            @endif

                        </div>
                        @endif
                    </div>

                    @if(count($popular) > 0 && strlen($query) == 0)
                    <div class="max-w-2xl mx-auto">
                        <div class="flex items-center gap-4 mb-6">
                            <div class="h-px bg-slate-200 flex-1"></div>
                            <span class="text-xs font-bold text-slate-400 uppercase tracking-widest">Or choose popular</span>
                            <div class="h-px bg-slate-200 flex-1"></div>
                        </div>

                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            @foreach($popular as $inst)
                                <button wire:click="selectExisting({{ $inst->id }}, '{{ $inst->name }}')"
                                    class="flex flex-col items-center justify-center gap-3 p-6 rounded-xl border border-slate-200 hover:border-blue-500 hover:shadow-md hover:bg-blue-50/10 transition-all group bg-white">
                                    <div class="w-12 h-12 rounded-full bg-slate-50 text-slate-600 flex items-center justify-center font-bold text-lg border border-slate-100 group-hover:bg-blue-600 group-hover:text-white transition-colors">
                                        {{ substr($inst->name, 0, 1) }}
                                    </div>
                                    <span class="text-sm font-medium text-slate-700 group-hover:text-slate-900 text-center leading-tight">{{ $inst->name }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                    @endif

                @endif

                @if($mode === 'create_custom')
                    <div class="max-w-md mx-auto animate-fade-in-up">
                        <div class="flex items-center justify-between mb-6">
                            <h2 class="text-xl font-bold text-slate-900">Add New Details</h2>
                            <button wire:click="$set('mode', 'search')" class="p-2 hover:bg-slate-100 rounded-full text-slate-400 transition-colors">
                                <i data-lucide="x" class="w-5 h-5"></i>
                            </button>
                        </div>

                        <div class="space-y-5">
                            <div>
                                <label class="block text-sm font-semibold text-slate-700 mb-2">Institution Name</label>
                                <input wire:model="customName" type="text" class="w-full px-4 py-3 bg-white border border-slate-300 rounded-xl focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 outline-none text-slate-900 font-medium">
                                @error('customName') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-slate-700 mb-2">Industry / Category</label>
                                <div class="relative">
                                    <select wire:model.live="categoryId" class="w-full px-4 py-3 bg-white border border-slate-300 rounded-xl appearance-none focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 outline-none text-slate-700">
                                        <option value="" disabled selected>Select Category...</option>
                                        @foreach($categories as $cat)
                                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                        @endforeach
                                        <option value="other" class="font-bold text-blue-600">+ Add New Industry</option>
                                    </select>
                                    <i data-lucide="chevron-down" class="absolute right-4 top-3.5 w-5 h-5 text-slate-400 pointer-events-none"></i>
                                </div>
                                @error('categoryId') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>

                            @if($categoryId === 'other')
                            <div class="animate-fade-in">
                                <label class="block text-sm font-semibold text-slate-700 mb-2">Custom Industry Name</label>
                                <input wire:model="customCategoryName" type="text" class="w-full px-4 py-3 bg-blue-50 border border-blue-200 text-blue-800 rounded-xl focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 outline-none" placeholder="e.g. Crypto Exchange">
                                @error('customCategoryName') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>
                            @endif

                            <button wire:click="submitCustom" class="w-full py-4 bg-slate-900 hover:bg-slate-800 text-white font-bold rounded-xl shadow-lg hover:shadow-xl transition-all flex items-center justify-center gap-2 mt-4">
                                Continue <i data-lucide="arrow-right" class="w-5 h-5"></i>
                            </button>
                        </div>
                    </div>
                @endif
            </div>
        @endif

        @if($step === 2)
            <div class="p-8 md:p-12 animate-fade-in">

                <div class="flex items-center justify-between mb-8 border-b border-slate-100 pb-6">
                    <div>
                        <h1 class="text-2xl font-bold text-slate-900">Case Details</h1>
                        <div class="flex items-center gap-2 mt-1">
                            <span class="text-slate-500 text-sm">Disputing with</span>
                            <span class="bg-blue-50 text-blue-700 px-2 py-0.5 rounded text-sm font-bold border border-blue-100">{{ $selectedInstitutionName }}</span>
                        </div>
                    </div>
                    <button wire:click="goToStep(1)" class="text-sm text-slate-400 hover:text-slate-600 font-medium underline">Change</button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Transaction Date</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i data-lucide="calendar" class="w-5 h-5 text-slate-400"></i>
                            </div>
                            <input wire:model="transactionDate" type="date" class="w-full pl-10 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 outline-none transition-all">
                        </div>
                        @error('transactionDate') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Total Amount</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <span class="text-slate-500 font-bold text-lg">$</span>
                            </div>
                            <input wire:model="transactionAmount" type="number" step="0.01" placeholder="0.00" class="w-full pl-8 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 outline-none transition-all font-mono">
                        </div>
                        @error('transactionAmount') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="mb-8">
                    <label class="block text-sm font-semibold text-slate-700 mb-2">Transaction ID / Reference (Optional)</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i data-lucide="hash" class="w-5 h-5 text-slate-400"></i>
                        </div>
                        <input wire:model="referenceNumber" type="text" placeholder="e.g. TXN-8842-X" class="w-full pl-10 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 outline-none transition-all">
                    </div>
                </div>

                <div class="mb-8">
                    <label class="block text-sm font-semibold text-slate-700 mb-2">What is the issue?</label>
                    <div class="relative">
                        <textarea wire:model="issueDescription" rows="5" class="w-full p-4 bg-slate-50 border border-slate-200 rounded-xl focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 outline-none transition-all resize-none" placeholder="Please describe exactly what happened..."></textarea>
                        <div class="absolute bottom-3 right-3 text-xs text-slate-400 pointer-events-none">
                            Be specific
                        </div>
                    </div>
                    @error('issueDescription') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div class="flex items-center justify-between pt-6 border-t border-slate-100">
                    <button wire:click="goToStep(1)" class="px-6 py-3 text-slate-500 font-medium hover:bg-slate-50 rounded-lg transition-colors">
                        Back
                    </button>
                    <button wire:click="submitDetails" class="px-8 py-3 bg-slate-900 hover:bg-slate-800 text-white font-bold rounded-xl shadow-lg hover:shadow-xl transition-all flex items-center gap-2">
                        Generate Letter <i data-lucide="sparkles" class="w-5 h-5 text-yellow-400"></i>
                    </button>
                </div>

            </div>
        @endif

    </div>
    <style>
        /* Simple Fade Animation */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(5px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in {
            animation: fadeIn 0.3s ease-out forwards;
        }
        .animate-fade-in-up {
            animation: fadeIn 0.4s ease-out forwards;
        }
    </style>
</div>

