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
            <p class="text-sm text-slate-500">Manage the pre-written cases email templates provided to users.</p>
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
                                    {{-- VIEW BUTTON --}}
                                    <button wire:click="view({{ $template->id }})" 
                                            wire:loading.attr="disabled"
                                            title="View Template"
                                            class="p-2 text-slate-400 hover:text-emerald-600 rounded-lg transition-all">
                                        <i data-lucide="eye" class="w-4 h-4" wire:loading.remove wire:target="view({{ $template->id }})"></i>
                                        <i data-lucide="loader-2" class="w-4 h-4 animate-spin text-emerald-600" wire:loading wire:target="view({{ $template->id }})"></i>
                                    </button>

                                    {{-- EDIT BUTTON --}}
                                    <button wire:click="edit({{ $template->id }})" 
                                            wire:loading.attr="disabled"
                                            title="Edit Template"
                                            class="p-2 text-slate-400 hover:text-primary-600 rounded-lg transition-all">
                                        <i data-lucide="edit-3" class="w-4 h-4" wire:loading.remove wire:target="edit({{ $template->id }})"></i>
                                        <i data-lucide="loader-2" class="w-4 h-4 animate-spin text-primary-600" wire:loading wire:target="edit({{ $template->id }})"></i>
                                    </button>

                                    {{-- DELETE BUTTON --}}
                                    <button onclick="confirmDelete({{ $template->id }})" title="Delete Template" class="p-2 text-slate-400 hover:text-rose-600 rounded-lg transition-all">
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

    {{-- MODALS --}}
    @include('livewire.admin.templates.partials.modals.form-modal')
    @include('livewire.admin.templates.partials.modals.view-modal')

    {{-- SCRIPTS --}}
    <script>
        function confirmDelete(id) {
            Swal.fire({
                title: 'Delete Template?',
                text: "This action cannot be undone.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#0f172a', 
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