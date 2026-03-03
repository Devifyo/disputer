<div class="p-4 bg-slate-50/50 border-b border-slate-100 flex flex-col md:flex-row gap-4">
    {{-- Search --}}
    <div class="relative flex-1 max-w-md">
        <div class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">
            <i data-lucide="search" class="w-4 h-4" wire:loading.remove wire:target="search"></i>
            <i data-lucide="loader-2" class="w-4 h-4 animate-spin text-violet-600" wire:loading wire:target="search"></i>
        </div>
        <input type="text" wire:model.live.debounce.250ms="search" 
               placeholder="Search institutes..." 
               class="w-full pl-10 pr-4 py-2 bg-white border border-slate-200 rounded-lg text-sm focus:border-violet-500 focus:ring-2 focus:ring-violet-500/10 outline-none transition-all">
    </div>

    {{-- Category Filter --}}
    <div class="w-full md:w-48">
        <select wire:model.live="filterCategory" 
                class="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-sm text-slate-600 focus:border-violet-500 focus:ring-2 focus:ring-violet-500/10 outline-none cursor-pointer hover:border-slate-300 transition-all">
            <option value="">All Categories</option>
            @foreach($this->categories as $cat)
                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
            @endforeach
        </select>
    </div>

    {{-- Status Filter --}}
    <div class="w-full md:w-40">
        <select wire:model.live="filterStatus" 
                class="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-sm text-slate-600 focus:border-violet-500 focus:ring-2 focus:ring-violet-500/10 outline-none cursor-pointer hover:border-slate-300 transition-all">
            <option value="">All Statuses</option>
            <option value="verified">Verified</option>
            <option value="unverified">Unverified</option>
        </select>
    </div>
</div>