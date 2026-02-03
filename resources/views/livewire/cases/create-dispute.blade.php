<div class="w-full min-h-screen h-auto bg-gray-50 pb-32 overflow-y-auto">

    <div class="max-w-4xl mx-auto px-4 sm:px-6 pt-6">

        <div class="mb-8">
            <div class="flex items-center justify-center space-x-4">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold transition-all duration-300 {{ $step >= 1 ? 'bg-slate-900 text-white shadow-lg' : 'bg-slate-200 text-slate-400' }}">
                        @if($step > 1) <i data-lucide="check" class="w-4 h-4"></i> @else 1 @endif
                    </div>
                    <span class="text-sm font-medium {{ $step >= 1 ? 'text-slate-900' : 'text-slate-400' }}">Institution</span>
                </div>
                <div class="w-10 h-px bg-slate-200"></div>
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold transition-all duration-300 {{ $step >= 2 ? 'bg-slate-900 text-white shadow-lg' : 'bg-slate-200 text-slate-400' }}">
                         @if($step > 2) <i data-lucide="check" class="w-4 h-4"></i> @else 2 @endif
                    </div>
                    <span class="text-sm font-medium {{ $step >= 2 ? 'text-slate-900' : 'text-slate-400' }}">Details</span>
                </div>
                <div class="w-10 h-px bg-slate-200"></div>
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold transition-all duration-300 {{ $step >= 3 ? 'bg-slate-900 text-white shadow-lg' : 'bg-slate-200 text-slate-400' }}">
                        3
                    </div>
                    <span class="text-sm font-medium {{ $step >= 3 ? 'text-slate-900' : 'text-slate-400' }}">Review</span>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-xl shadow-slate-200/50 border border-slate-100 relative h-auto overflow-visible block">

            @if($step === 1)
                <div class="p-6 md:p-10 animate-fade-in block">
                    @if($mode === 'search')
                        <div class="text-center max-w-xl mx-auto mb-8">
                            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Who is this dispute with?</h1>
                            <p class="text-slate-500 mt-2 text-base">Search for a bank, airline, or service provider.</p>
                        </div>
                        <div class="relative max-w-lg mx-auto mb-10">
                            <div class="relative group">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <i data-lucide="search" class="w-5 h-5 text-slate-400 group-focus-within:text-blue-600 transition-colors"></i>
                                </div>
                                <input wire:model.live.debounce.250ms="query" type="text" class="block w-full pl-12 pr-6 py-4 bg-slate-50 border-2 border-slate-100 rounded-xl text-slate-900 placeholder-slate-400 text-lg shadow-sm focus:outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 transition-all" placeholder="Start typing name..." autocomplete="off">
                            </div>
                            @if(strlen($query) >= 1)
                            <div class="absolute top-full left-0 right-0 mt-2 bg-white rounded-xl border border-slate-100 shadow-2xl z-50 overflow-hidden divide-y divide-slate-50">
                                @forelse($results as $inst)
                                    <button wire:click="selectExisting({{ $inst->id }}, '{{ $inst->name }}')" class="w-full text-left px-5 py-3.5 hover:bg-slate-50 flex items-center justify-between group transition-colors">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center font-bold text-xs border border-blue-200">{{ substr($inst->name, 0, 2) }}</div>
                                            <div><p class="font-semibold text-slate-900 text-sm">{{ $inst->name }}</p><p class="text-[10px] text-slate-500 font-medium uppercase tracking-wide">{{ $inst->category->name ?? 'General' }}</p></div>
                                        </div>
                                        <i data-lucide="arrow-right" class="w-4 h-4 text-slate-300 group-hover:text-blue-600 transition-colors"></i>
                                    </button>
                                @empty
                                @endforelse
                                @php $exactMatch = $results->first(function($inst) use ($query) { return strcasecmp($inst->name, trim($query)) === 0; }); @endphp
                                @if(!$exactMatch)
                                <button wire:click="enableCreateMode" class="w-full text-left px-5 py-4 bg-slate-50 hover:bg-blue-50/50 flex items-center gap-3 group transition-colors">
                                    <div class="w-8 h-8 rounded-full bg-white border-2 border-dashed border-slate-300 flex items-center justify-center text-slate-400 group-hover:border-blue-500 group-hover:text-blue-500 transition-colors"><i data-lucide="plus" class="w-4 h-4"></i></div>
                                    <div><p class="font-semibold text-slate-900 text-sm">Create "{{ $query ?: 'New' }}"</p><p class="text-xs text-slate-500">Institution not listed? Add it manually.</p></div>
                                </button>
                                @endif
                            </div>
                            @endif
                        </div>
                        @if(count($popular) > 0 && strlen($query) == 0)
                        <div class="max-w-2xl mx-auto">
                            <div class="flex items-center gap-4 mb-4"><div class="h-px bg-slate-200 flex-1"></div><span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Or choose popular</span><div class="h-px bg-slate-200 flex-1"></div></div>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                                @foreach($popular as $inst)
                                    <button wire:click="selectExisting({{ $inst->id }}, '{{ $inst->name }}')" class="flex flex-col items-center justify-center gap-2 p-4 rounded-xl border border-slate-200 hover:border-blue-500 hover:shadow-md hover:bg-blue-50/10 transition-all group bg-white">
                                        <div class="w-10 h-10 rounded-full bg-slate-50 text-slate-600 flex items-center justify-center font-bold text-base border border-slate-100 group-hover:bg-blue-600 group-hover:text-white transition-colors">{{ substr($inst->name, 0, 1) }}</div>
                                        <span class="text-xs font-medium text-slate-700 group-hover:text-slate-900 text-center leading-tight">{{ $inst->name }}</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                        @endif
                    @endif

                    @if($mode === 'create_custom')
                         <div class="max-w-md mx-auto animate-fade-in-up">
                            <div class="flex items-center justify-between mb-6">
                                <h2 class="text-lg font-bold text-slate-900">Add New Details</h2>
                                <button wire:click="$set('mode', 'search')" class="p-1.5 hover:bg-slate-100 rounded-full text-slate-400 transition-colors"><i data-lucide="x" class="w-5 h-5"></i></button>
                            </div>
                            <div class="space-y-4">
                                <input wire:model="customName" type="text" class="w-full px-4 py-3 bg-white border border-slate-300 rounded-xl outline-none focus:border-blue-500" placeholder="Name">
                                <select wire:model.live="categoryId" class="w-full px-4 py-3 bg-white border border-slate-300 rounded-xl outline-none focus:border-blue-500">
                                    <option value="" disabled selected>Select Category...</option>
                                    @foreach($categories as $cat) <option value="{{ $cat->id }}">{{ $cat->name }}</option> @endforeach
                                    <option value="other">Other</option>
                                </select>
                                @if($categoryId === 'other') <input wire:model="customCategoryName" type="text" class="w-full px-4 py-3 bg-blue-50 border border-blue-200 text-blue-800 rounded-xl outline-none focus:border-blue-500" placeholder="Custom Category"> @endif
                                <button wire:click="submitCustom" class="w-full py-3.5 bg-slate-900 text-white font-bold rounded-xl mt-2 hover:bg-slate-800 transition-colors">Continue</button>
                            </div>
                         </div>
                    @endif
                </div>
            @endif

            @if($step === 2)
                <div class="animate-fade-in block h-auto">

                    <div class="px-8 pt-6 pb-4 border-b border-slate-100 flex items-center justify-between">
                        <div>
                            <h1 class="text-xl font-bold text-slate-900">Case Details</h1>
                            <span class="text-slate-500 text-xs">Disputing with <span class="bg-blue-50 text-blue-700 px-1.5 py-0.5 rounded text-xs font-bold">{{ $selectedInstitutionName }}</span></span>
                        </div>
                        <button wire:click="goToStep(1)" class="text-xs text-slate-400 hover:text-slate-600 font-medium underline">Change</button>
                    </div>

                    <div class="p-8 block">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-5">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1.5">Transaction Date</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"><i data-lucide="calendar" class="w-4 h-4 text-slate-400"></i></div>
                                    <input wire:model="transactionDate" type="date" class="w-full pl-9 pr-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:border-blue-500 transition-all text-sm">
                                </div>
                                @error('transactionDate') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1.5">Total Amount</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"><span class="text-slate-500 font-bold text-sm">$</span></div>
                                    <input wire:model="transactionAmount" type="number" step="0.01" class="w-full pl-7 pr-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:border-blue-500 transition-all font-mono text-sm">
                                </div>
                                @error('transactionAmount') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="mb-5">
                            <label class="block text-xs font-bold text-slate-700 mb-1.5">Reference (Optional)</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"><i data-lucide="hash" class="w-4 h-4 text-slate-400"></i></div>
                                <input wire:model="referenceNumber" type="text" class="w-full pl-9 pr-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:border-blue-500 transition-all text-sm">
                            </div>
                        </div>

                        <div class="mb-6">
                            <label class="block text-xs font-bold text-slate-700 mb-1.5">What is the issue?</label>
                            <textarea wire:model="issueDescription" rows="4" class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:border-blue-500 transition-all resize-none text-sm" placeholder="Please describe exactly what happened..."></textarea>
                            @error('issueDescription') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div class="flex items-center justify-between pt-5 border-t border-slate-100">
                            <button wire:click="goToStep(1)" class="px-5 py-2.5 text-slate-500 font-medium hover:bg-slate-50 rounded-lg transition-colors text-sm">Back</button>

                            {{-- FIXED BUTTON: Uses Class Toggling to ensure flex alignment --}}
                            <button wire:click="generateReview"
                                    wire:loading.attr="disabled"
                                    class="px-6 py-2.5 bg-slate-900 hover:bg-slate-800 text-white font-bold rounded-xl shadow-lg flex items-center justify-center transition-all min-w-[160px] text-sm">

                                <div wire:loading.class="hidden" wire:target="generateReview" class="flex items-center gap-2">
                                    <span>Generate Draft</span>
                                    <i data-lucide="sparkles" class="w-4 h-4 text-yellow-400"></i>
                                </div>

                                <div wire:loading.class.remove="hidden" wire:target="generateReview" class="hidden flex items-center gap-2">
                                    <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <span>Thinking...</span>
                                </div>
                            </button>
                        </div>
                    </div>
                </div>
            @endif

            @if($step === 3)
                <div class="p-8 animate-fade-in block">
                    <div class="mb-6">
                        <h1 class="text-xl font-bold text-slate-900">Review & Send</h1>
                        <p class="text-slate-500 text-sm mt-0.5">Review the generated letter.</p>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                        <div class="lg:col-span-2 space-y-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1.5">Subject Line</label>
                                <input wire:model="generatedSubject" type="text"
                                    class="w-full px-4 py-2.5 font-bold text-slate-800 bg-white border border-slate-300 rounded-xl focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 outline-none transition-all shadow-sm text-sm">
                                @error('generatedSubject') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>

                            <div class="relative">
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1.5">Email Body</label>
                                <textarea wire:model="generatedLetter" rows="12"
                                    class="w-full p-4 bg-white border border-slate-300 rounded-xl focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 outline-none transition-all font-mono text-sm leading-relaxed shadow-sm resize-none"></textarea>
                                <div class="absolute top-8 right-3"><span class="text-[10px] bg-slate-100 text-slate-500 px-1.5 py-0.5 rounded border border-slate-200">AI Generated</span></div>
                            </div>
                            @error('generatedLetter') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div class="lg:col-span-1 space-y-4">
                            <div class="bg-slate-50 p-5 rounded-xl border border-slate-200">
                                <h3 class="font-bold text-slate-900 mb-3 text-sm">Recipient</h3>
                                <div class="mb-2">
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wide mb-1.5">Institution Email</label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"><i data-lucide="mail" class="w-4 h-4 text-slate-400"></i></div>
                                        <input wire:model="institutionEmail" type="email" placeholder="support@bank.com" class="w-full pl-9 pr-3 py-2 bg-white border border-slate-300 rounded-lg focus:border-blue-500 outline-none text-sm">
                                    </div>
                                    @error('institutionEmail') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            <button wire:click="finalizeDispute" wire:loading.attr="disabled" class="w-full py-3 bg-green-600 hover:bg-green-700 text-white font-bold rounded-xl shadow-lg hover:shadow-xl transition-all flex items-center justify-center gap-2 text-sm">
                                <div wire:loading.class="hidden" wire:target="finalizeDispute" class="flex items-center gap-2"><span>Send Dispute</span><i data-lucide="send" class="w-4 h-4"></i></div>
                                <div wire:loading.class.remove="hidden" wire:target="finalizeDispute" class="hidden flex items-center gap-2"><svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Sending...</div>
                            </button>

                            <button wire:click="goToStep(2)" class="w-full mt-2 py-2.5 text-slate-500 font-medium hover:text-slate-800 transition-colors text-sm">Go Back & Edit</button>
                        </div>
                    </div>
                </div>
            @endif

        </div>
    </div>

    <style>
        @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
        .animate-fade-in { animation: fadeIn 0.3s ease-out forwards; }
        .animate-fade-in-up { animation: fadeIn 0.4s ease-out forwards; }
    </style>
</div>
