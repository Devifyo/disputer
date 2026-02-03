<div class="transition-all duration-300">

    @if($mode === 'search')
        <div class="text-center mb-8">
            <h1 class="text-2xl font-bold text-slate-900">Who is this dispute with?</h1>
            <p class="text-slate-500 mt-2 text-sm">Search for a registered institution or add a new one.</p>
        </div>

        <div class="relative max-w-lg mx-auto">
            <div class="relative group">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                    <i data-lucide="search" class="w-5 h-5 text-slate-400 group-focus-within:text-primary transition-colors"></i>
                </div>
                <input wire:model.live.debounce.300ms="query"
                       type="text"
                       class="block w-full pl-11 pr-4 py-4 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-lg shadow-sm"
                       placeholder="e.g. Chase Bank, United Airlines..."
                       autocomplete="off">
            </div>

            @if(strlen($query) >= 1)
            <div class="absolute top-full left-0 right-0 mt-2 bg-white rounded-xl border border-slate-100 shadow-xl z-20 overflow-hidden divide-y divide-slate-50 max-h-80 overflow-y-auto">

                @forelse($results as $inst)
                    <button wire:click="selectInstitution({{ $inst->id }})" class="w-full text-left px-4 py-3 hover:bg-slate-50 flex items-center justify-between group transition-colors border-b border-slate-50">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center font-bold text-xs border border-blue-100 shrink-0">
                                {{ substr($inst->name, 0, 2) }}
                            </div>
                            <div>
                                <p class="font-semibold text-slate-800">{{ $inst->name }}</p>
                                <p class="text-xs text-slate-500">{{ $inst->category->name ?? 'General' }} • Verified</p>
                            </div>
                        </div>
                        <i data-lucide="chevron-right" class="w-4 h-4 text-slate-300 group-hover:text-primary"></i>
                    </button>
                @empty
                    @endforelse

                <button wire:click="enableCreateMode" class="w-full text-left px-4 py-4 bg-slate-50 hover:bg-slate-100 flex items-center gap-3 group transition-colors sticky bottom-0 border-t border-slate-100">
                    <div class="w-10 h-10 rounded-full bg-white border border-dashed border-slate-300 flex items-center justify-center text-slate-400 group-hover:border-primary group-hover:text-primary transition-colors shrink-0">
                        <i data-lucide="plus" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <p class="font-medium text-slate-900">Not listed? Create "<span class="font-bold">{{ $query ?: 'Custom' }}</span>"</p>
                        <p class="text-xs text-slate-500">Add them manually to proceed.</p>
                    </div>
                </button>
            </div>
            @endif
        </div>

        @if(count($popular) > 0 && strlen($query) == 0)
        <div class="mt-10 pt-8 border-t border-slate-100 text-center">
            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-4">Popular Institutions</p>
            <div class="flex flex-wrap justify-center gap-3">
                @foreach($popular as $inst)
                    <button wire:click="selectInstitution({{ $inst->id }})" class="px-4 py-2 bg-white border border-slate-200 rounded-full text-sm font-medium text-slate-600 hover:border-primary hover:text-primary transition-colors shadow-sm">
                        {{ $inst->name }}
                    </button>
                @endforeach
            </div>
        </div>
        @endif
    @endif

    @if($mode === 'create')
        <div class="max-w-lg mx-auto bg-slate-50 rounded-xl p-6 border border-slate-200">
            <div class="flex justify-between items-start mb-4">
                <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wide">Add New Institution</h3>
                <button wire:click="cancelCreateMode" class="text-slate-400 hover:text-slate-600"><i data-lucide="x" class="w-4 h-4"></i></button>
            </div>

            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Institution Name</label>
                    <input wire:model="customName" type="text" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none font-medium text-slate-900">
                    @error('customName') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Category / Industry</label>
                    <div class="relative">
                        <select wire:model.live="categoryId" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg appearance-none focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none text-slate-700">
                            <option value="" disabled selected>Select an industry...</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                            @endforeach
                            <option value="other" class="font-bold text-blue-600">+ Other / Add New</option>
                        </select>
                        <i data-lucide="chevron-down" class="absolute right-3 top-2.5 w-4 h-4 text-slate-400 pointer-events-none"></i>
                    </div>
                    @error('categoryId') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>

                @if($categoryId === 'other')
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">New Industry Name</label>
                    <input wire:model="customCategoryName" type="text" class="w-full px-3 py-2 bg-blue-50 border border-blue-200 text-blue-800 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none" placeholder="e.g. Crypto Exchange">
                    @error('customCategoryName') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                @endif

                <button wire:click="submitCustom" class="w-full py-2.5 bg-primary hover:bg-slate-800 text-white font-medium rounded-lg shadow-md transition-all flex items-center justify-center gap-2">
                    Continue to Details <i data-lucide="arrow-right" class="w-4 h-4"></i>
                </button>
            </div>
        </div>
    @endif
</div>
