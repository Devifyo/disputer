<div class="h-full overflow-y-auto p-6 pb-24 relative bg-slate-50/50">
    <x-flash />

    {{-- SweetAlert2 Asset --}}
    @assets
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    @endassets

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Dispute Templates</h1>
            <p class="text-sm text-slate-500">Manage the pre-written letter templates provided to users.</p>
        </div>
        
        <button wire:click="create" 
                wire:loading.attr="disabled"
                class="bg-slate-900 hover:bg-slate-800 text-white px-3 sm:px-4 py-2 rounded-lg text-xs font-bold shadow-lg shadow-slate-900/20 transition-all flex items-center gap-2 hover:scale-105 active:scale-95">
            <span wire:loading.remove wire:target="create" class="inline-flex items-center gap-2">
                <i data-lucide="plus" class="w-4 h-4"></i> Add Template
            </span>
            <span wire:loading.flex wire:target="create" class="inline-flex items-center gap-2">
                <i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> Opening...
            </span>
        </button>
    </div>

    {{-- Filters & Search --}}
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm flex flex-col overflow-hidden mb-6">
        <div class="p-4 bg-slate-50/50 border-b border-slate-100 flex flex-col md:flex-row gap-4">
            <div class="relative flex-1 max-w-md">
                <div class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">
                    <i data-lucide="search" class="w-4 h-4" wire:loading.remove wire:target="search"></i>
                    <i data-lucide="loader-2" class="w-4 h-4 animate-spin text-primary-600" wire:loading wire:target="search"></i>
                </div>
                <input type="text" wire:model.live.debounce.250ms="search" 
                       placeholder="Search templates..." 
                       class="w-full pl-10 pr-4 py-2 bg-white border border-slate-200 rounded-lg text-sm focus:border-primary-500 outline-none transition-all">
            </div>
            <div class="w-full md:w-64">
                <select wire:model.live="category_filter" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-sm focus:border-primary-500 outline-none transition-all">
                    <option value="">All Categories</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    
        {{-- Table --}}
        <div class="overflow-x-auto transition-opacity duration-200" wire:loading.class="opacity-50" wire:target="search, category_filter, gotoPage">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase text-[10px] tracking-widest whitespace-nowrap">
                    <tr>
                        <th class="px-6 py-4">Template Info</th>
                        <th class="px-6 py-4">Category</th>
                        <th class="px-6 py-4">Status</th>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($templates as $template)
                        <tr class="hover:bg-slate-50/50 transition-colors" wire:key="row-{{ $template->id }}">
                            <td class="px-6 py-4">
                                <div class="flex items-start gap-3">
                                    <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0 bg-{{ $template->color }}-100 text-{{ $template->color }}-600 mt-0.5">
                                        <i data-lucide="{{ $template->icon ?: 'file-text' }}" class="w-4 h-4"></i>
                                    </div>
                                    <div>
                                        <div class="font-bold text-slate-900">{{ $template->title }}</div>
                                        <div class="text-[11px] text-slate-500 mt-0.5 max-w-md truncate">{{ $template->description }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-2.5 py-1 rounded-md bg-slate-100 text-slate-600 text-[10px] font-bold uppercase tracking-wider border border-slate-200">
                                    {{ $template->category->name ?? 'None' }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="flex items-center gap-1.5 text-[10px] font-bold uppercase {{ $template->is_active ? 'text-emerald-600' : 'text-slate-400' }}">
                                    <span class="w-2 h-2 rounded-full {{ $template->is_active ? 'bg-emerald-500' : 'bg-slate-300' }}"></span>
                                    {{ $template->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex justify-end gap-1">
                                    <button wire:click="edit({{ $template->id }})" 
                                            wire:loading.attr="disabled"
                                            class="p-2 text-slate-400 hover:text-primary-600 rounded-lg transition-all">
                                        <i data-lucide="edit-3" class="w-4 h-4" wire:loading.remove wire:target="edit({{ $template->id }})"></i>
                                        <i data-lucide="loader-2" class="w-4 h-4 animate-spin text-primary-600" wire:loading wire:target="edit({{ $template->id }})"></i>
                                    </button>
                                    <button onclick="confirmDelete({{ $template->id }})" class="p-2 text-slate-400 hover:text-rose-600 rounded-lg transition-all">
                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-6 py-12 text-center text-slate-500">No templates found matching your criteria.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($templates->hasPages())
            <div class="p-4 border-t border-slate-50 bg-slate-50">{{ $templates->links() }}</div>
        @endif
    </div>

    {{-- MODAL (Alpine Powered) - REDESIGNED TO SINGLE COLUMN --}}
    <div x-data="{ modalOpen: @entangle('showModal') }" 
         x-show="modalOpen" 
         x-cloak
         class="fixed inset-0 z-[100] flex items-center justify-center p-4 sm:p-6">
        
        <div x-on:click="modalOpen = false" x-transition.opacity class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm"></div>
        
        {{-- Narrower Modal (max-w-3xl) for better reading flow --}}
        <div x-show="modalOpen"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95 translate-y-4"
             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 scale-100 translate-y-0"
             x-transition:leave-end="opacity-0 scale-95 translate-y-4"
             class="relative bg-white rounded-[2rem] shadow-2xl w-full max-w-3xl flex flex-col overflow-hidden max-h-full">
            
            {{-- Header --}}
            <div class="px-8 py-6 border-b flex items-center justify-between bg-white shrink-0">
                <div>
                    <h2 class="text-xl font-bold text-slate-900">
                        {{ $isEditMode ? 'Edit Template' : 'Create Template' }}
                    </h2>
                    <p class="text-[11px] text-slate-500 mt-0.5">Use brackets like <span class="font-mono text-primary-600 font-bold">[MERCHANT_NAME]</span> for dynamic fields.</p>
                </div>
                <button x-on:click="modalOpen = false" class="text-slate-400 hover:text-slate-600 transition-colors p-1 bg-slate-50 hover:bg-slate-100 rounded-lg">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            {{-- Body: Single Column, Top to Bottom Flow --}}
            <div class="p-8 overflow-y-auto custom-scrollbar flex flex-col gap-6">
                
                {{-- Row 1: Category & Title --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-400 uppercase mb-2">Category <span class="text-rose-500">*</span></label>
                        <select wire:model="institution_category_id" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:border-primary-500 outline-none bg-white">
                            <option value="">Select a Category</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                            @endforeach
                        </select>
                        @error('institution_category_id') <span class="text-rose-500 text-[10px] font-bold block mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-400 uppercase mb-2">Template Title <span class="text-rose-500">*</span></label>
                        <input type="text" wire:model.blur="title" placeholder="e.g. Unauthorized Transaction" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:border-primary-500 outline-none">
                        @error('title') <span class="text-rose-500 text-[10px] font-bold block mt-1">{{ $message }}</span> @enderror
                    </div>
                </div>

                {{-- Row 2: Slug & Active Toggle --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 items-start">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-400 uppercase mb-2">URL Slug <span class="text-rose-500">*</span></label>
                        <input type="text" wire:model.blur="slug" placeholder="e.g. unauthorized-transaction" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm font-mono text-slate-500 focus:border-primary-500 outline-none">
                        @error('slug') <span class="text-rose-500 text-[10px] font-bold block mt-1">{{ $message }}</span> @enderror
                    </div>
                    
                    <div class="sm:mt-8">
                        <label class="flex items-center gap-2 cursor-pointer group w-fit">
                            <div class="relative flex items-center">
                                <input type="checkbox" wire:model="is_active" class="peer appearance-none w-5 h-5 border-2 border-slate-300 rounded checked:bg-emerald-500 checked:border-emerald-500 transition-all">
                                <i data-lucide="check" class="absolute w-3.5 h-3.5 text-white opacity-0 peer-checked:opacity-100 left-1 transition-all pointer-events-none"></i>
                            </div>
                            <span class="text-sm font-bold text-slate-600 group-hover:text-slate-800 transition-colors">Template is Active (Visible to users)</span>
                        </label>
                    </div>
                </div>

                {{-- Row 3: Icon & Color --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 p-4 bg-slate-50 rounded-xl border border-slate-100">
                    <div>
                        <div class="flex justify-between items-center mb-2">
                            <label class="block text-[11px] font-bold text-slate-400 uppercase">Icon</label>
                            <a href="https://lucide.dev/icons" target="_blank" class="text-[9px] text-primary-500 hover:text-primary-700 font-bold">View Icons &rarr;</a>
                        </div>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                                <i data-lucide="{{ $icon ?: 'file-text' }}" class="w-4 h-4"></i>
                            </div>
                            <input type="text" wire:model.live.debounce.300ms="icon" placeholder="file-text" class="w-full pl-10 pr-3 py-2.5 rounded-lg border border-slate-200 text-sm focus:border-primary-500 outline-none bg-white">
                        </div>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-400 uppercase mb-2">Color Theme</label>
                        <select wire:model="color" class="w-full px-3 py-2.5 rounded-lg border border-slate-200 text-sm focus:border-primary-500 outline-none bg-white font-medium text-{{ $color }}-600">
                            <option value="slate">Slate</option>
                            <option value="blue">Blue</option>
                            <option value="emerald">Emerald</option>
                            <option value="sky">Sky</option>
                            <option value="rose">Rose</option>
                            <option value="amber">Amber</option>
                            <option value="purple">Purple</option>
                        </select>
                    </div>
                </div>

                <hr class="border-slate-100">

                {{-- Row 4: Description --}}
                <div>
                    <label class="block text-[11px] font-bold text-slate-400 uppercase mb-2">Short Description <span class="text-rose-500">*</span></label>
                    <textarea wire:model="description" rows="2" placeholder="Briefly explain what this template is used for..." class="w-full px-4 py-3 rounded-xl border border-slate-200 text-sm focus:border-primary-500 outline-none resize-none"></textarea>
                    @error('description') <span class="text-rose-500 text-[10px] font-bold block mt-1">{{ $message }}</span> @enderror
                </div>

                {{-- Row 5: Letter Content --}}
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="block text-[11px] font-bold text-slate-400 uppercase">Letter Content <span class="text-rose-500">*</span></label>
                        <span class="text-[10px] text-slate-400 font-medium">Supports line breaks</span>
                    </div>
                    <textarea wire:model="content" rows="12" placeholder="Date: [CURRENT_DATE]&#10;&#10;To the Fraud Department,&#10;&#10;I am writing to formally dispute..." class="w-full px-4 py-3 rounded-xl border border-slate-200 text-sm font-mono leading-relaxed focus:border-primary-500 outline-none resize-y min-h-[250px]"></textarea>
                    @error('content') <span class="text-rose-500 text-[10px] font-bold block mt-1">{{ $message }}</span> @enderror
                </div>

            </div>

            {{-- Footer --}}
            <div class="px-8 py-5 bg-slate-50 border-t flex justify-end gap-3 shrink-0">
                <button x-on:click="modalOpen = false" class="px-6 py-2.5 text-sm font-bold text-slate-500 hover:text-slate-800 transition-colors">
                    Cancel
                </button>
                <button wire:click="{{ $isEditMode ? 'update' : 'store' }}" 
                        wire:loading.attr="disabled"
                        class="min-w-[150px] px-8 py-2.5 bg-primary-600 text-white rounded-xl text-sm font-bold shadow-xl shadow-primary-600/20 active:scale-95 transition-all flex items-center justify-center gap-2 disabled:opacity-70 disabled:cursor-not-allowed hover:bg-primary-700">
                    <span wire:loading.remove wire:target="store, update">
                        {{ $isEditMode ? 'Save Template' : 'Create Template' }}
                    </span>
                    <span wire:loading.flex wire:target="store, update" class="items-center gap-2">
                        <i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> Saving...
                    </span>
                </button>
            </div>
        </div>
    </div>

    {{-- SCRIPTS --}}
    <script>
        function confirmDelete(id) {
            Swal.fire({
                title: 'Delete Template?',
                text: "This action cannot be undone.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444', 
                cancelButtonColor: '#94a3b8',
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel',
                heightAuto: false, 
                scrollbarPadding: false,
                customClass: {
                    popup: 'rounded-2xl',
                    confirmButton: 'px-6 py-2.5 rounded-xl font-bold text-sm',
                    cancelButton: 'px-6 py-2.5 rounded-xl font-bold text-sm'
                }
            }).then((result) => {
                if (result.isConfirmed) @this.call('deleteConfirmed', id);
            })
        }
    </script>
</div>