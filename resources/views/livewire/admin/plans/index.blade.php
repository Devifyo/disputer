<div class="h-full overflow-y-auto p-6 pb-24 relative bg-slate-50/50">
    <x-flash />

    {{-- SweetAlert2 Asset --}}
    @assets
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    @endassets

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Plans & Pricing</h1>
            <p class="text-sm text-slate-500">Manage subscriptions, one-time case bundles, and pricing.</p>
        </div>
        
        {{-- <button wire:click="create" 
                wire:loading.attr="disabled"
                class="bg-slate-900 hover:bg-slate-800 text-white px-3 sm:px-4 py-2 rounded-lg text-xs font-bold shadow-lg shadow-slate-900/20 transition-all flex items-center gap-2 hover:scale-105 active:scale-95">
            <span wire:loading.remove wire:target="create" class="inline-flex items-center gap-2">
                <i data-lucide="plus" class="w-4 h-4"></i> Add Plan
            </span>
            <span wire:loading.flex wire:target="create" class="inline-flex items-center gap-2">
                <i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> Opening...
            </span>
        </button> --}}
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
                       placeholder="Search plans..." 
                       class="w-full pl-10 pr-4 py-2 bg-white border border-slate-200 rounded-lg text-sm focus:border-primary-500 outline-none transition-all">
            </div>
        </div>
    
        {{-- Table --}}
        <div class="overflow-x-auto transition-opacity duration-200" wire:loading.class="opacity-50" wire:target="search, gotoPage">
            <table class="w-full text-left text-sm whitespace-nowrap">
                <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase text-[10px] tracking-widest">
                    <tr>
                        <th class="px-6 py-4">Plan Name</th>
                        <th class="px-6 py-4">Billing Type</th>
                        <th class="px-6 py-4">Price</th>
                        <th class="px-6 py-4">Status</th>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($plans as $plan)
                        <tr class="hover:bg-slate-50/50 transition-colors" wire:key="row-{{ $plan->id }}">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-xs {{ $plan->type === 'recurring_yearly' ? 'bg-primary-600/10 text-primary-600' : 'bg-emerald-600/10 text-emerald-600' }}">
                                        {{ substr($plan->name, 0, 1) }}
                                    </div>
                                    <div>
                                        <div class="font-bold text-slate-900 leading-none mb-1">{{ $plan->name }}</div>
                                        <div class="text-xs text-slate-400 font-mono">{{ $plan->slug }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex flex-col">
                                    @if($plan->type === 'recurring_yearly')
                                        <span class="text-xs font-bold text-slate-700">Yearly Subscription</span>
                                        <span class="text-[10px] text-slate-400">Unlimited Cases</span>
                                    @else
                                        <span class="text-xs font-bold text-slate-700">One-Time Payment</span>
                                        <span class="text-[10px] text-slate-400">{{ $plan->case_limit }} Case(s) Included</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="font-bold text-slate-900">${{ number_format($plan->price, 2) }} <span class="text-xs text-slate-400 font-normal uppercase">{{ $plan->currency }}</span></div>
                            </td>
                            <td class="px-6 py-4">
                                <button wire:click="toggleStatus({{ $plan->id }})" 
                                        class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors focus:outline-none {{ $plan->is_active ? 'bg-emerald-500' : 'bg-slate-300' }}"
                                        title="{{ $plan->is_active ? 'Deactivate Plan' : 'Activate Plan' }}">
                                    <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition-transform {{ $plan->is_active ? 'translate-x-4' : 'translate-x-1' }}"></span>
                                </button>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex justify-end gap-1">
                                    <button wire:click="edit({{ $plan->id }})" 
                                            wire:loading.attr="disabled"
                                            class="p-2 text-slate-400 hover:text-primary-600 rounded-lg transition-all">
                                        <i data-lucide="edit-3" class="w-4 h-4" wire:loading.remove wire:target="edit({{ $plan->id }})"></i>
                                        <i data-lucide="loader-2" class="w-4 h-4 animate-spin text-primary-600" wire:loading wire:target="edit({{ $plan->id }})"></i>
                                    </button>
                                    <button onclick="confirmDelete({{ $plan->id }})" class="p-2 text-slate-400 hover:text-rose-600 rounded-lg transition-all">
                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-6 py-12 text-center text-slate-500">No plans found matching your criteria.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($plans->hasPages())
            <div class="p-4 border-t border-slate-50 bg-slate-50">{{ $plans->links() }}</div>
        @endif
    </div>

    {{-- MODAL (Alpine Powered) --}}
    <div x-data="{ modalOpen: @entangle('showModal') }" 
         x-show="modalOpen" 
         x-cloak
         class="fixed inset-0 z-[100] flex items-center justify-center p-4 sm:p-6">
        
        <div x-on:click="modalOpen = false" x-transition.opacity class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm"></div>
        
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
                        {{ $isEditMode ? 'Edit Plan' : 'Create Plan' }}
                    </h2>
                    <p class="text-[11px] text-slate-500 mt-0.5">Configure billing terms, limits, and pricing.</p>
                </div>
                <button x-on:click="modalOpen = false" class="text-slate-400 hover:text-slate-600 transition-colors p-1 bg-slate-50 hover:bg-slate-100 rounded-lg">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            {{-- Body --}}
            <div class="p-8 overflow-y-auto custom-scrollbar flex flex-col gap-6">
                
                {{-- Row 1: Name & Slug --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-400 uppercase mb-2">Plan Name <span class="text-rose-500">*</span></label>
                        <input type="text" wire:model.live.debounce.500ms="name" placeholder="e.g. Yearly Pro" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:border-primary-500 outline-none">
                        @error('name') <span class="text-rose-500 text-[10px] font-bold block mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-400 uppercase mb-2">URL Slug <span class="text-rose-500">*</span></label>
                        <input type="text" wire:model.blur="slug" 
                               placeholder="e.g. yearly-pro" 
                               {{ $isEditMode ? 'disabled' : '' }}
                               class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm font-mono focus:border-primary-500 outline-none disabled:bg-slate-100 disabled:text-slate-400 disabled:cursor-not-allowed transition-colors">
                        
                        @if($isEditMode)
                            <p class="text-[9px] text-amber-500 font-bold mt-1.5"><i data-lucide="lock" class="w-3 h-3 inline pb-0.5"></i> Slug cannot be changed after creation.</p>
                        @endif
                        
                        @error('slug') <span class="text-rose-500 text-[10px] font-bold block mt-1">{{ $message }}</span> @enderror
                    </div>
                </div>

                {{-- Row 2: Type & Case Limit --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 p-4 bg-slate-50 rounded-xl border border-slate-100">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-400 uppercase mb-2">Billing Type <span class="text-rose-500">*</span></label>
                        <select wire:model.live="type" {{ $isEditMode ? 'disabled' : '' }} class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:border-primary-500 outline-none bg-white">
                            <option value="recurring_yearly">Recurring Yearly</option>
                            <option value="one_time">One-Time Payment</option>
                        </select>
                         @if($isEditMode)
                            <p class="text-[9px] text-amber-500 font-bold mt-1.5"><i data-lucide="lock" class="w-3 h-3 inline pb-0.5"></i> Billing Type cannot be changed after creation.</p>
                        @endif
                        @error('type') <span class="text-rose-500 text-[10px] font-bold block mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-400 uppercase mb-2">Total Cases Allowed</label>
                        <input type="number" wire:model.blur="case_limit" placeholder="Leave blank for unlimited" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:border-primary-500 outline-none bg-white" {{ $type === 'recurring_yearly' ? 'disabled' : '' }}>
                        <p class="text-[9px] text-slate-400 font-medium mt-1">
                            @if($type === 'recurring_yearly')
                                Yearly plans are automatically unlimited.
                            @else
                                Number of cases a user can submit before needing to buy again.
                            @endif
                        </p>
                        @error('case_limit') <span class="text-rose-500 text-[10px] font-bold block mt-1">{{ $message }}</span> @enderror
                    </div>
                </div>

                {{-- Row 3: Price & Currency --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-400 uppercase mb-2">Price <span class="text-rose-500">*</span></label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400 font-bold">$</div>
                            <input type="number" step="0.01" wire:model.blur="price" placeholder="199.00" class="w-full pl-8 pr-3 py-2.5 rounded-xl border border-slate-200 text-sm focus:border-primary-500 outline-none font-mono">
                        </div>
                        @error('price') <span class="text-rose-500 text-[10px] font-bold block mt-1">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-400 uppercase mb-2">Currency</label>
                        <select wire:model="currency" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:border-primary-500 outline-none bg-white font-bold">
                            <option value="USD" selected>USD ($)</option>
                            {{-- <option value="EUR">EUR (€)</option>
                            <option value="GBP">GBP (£)</option> --}}
                        </select>
                        @error('currency') <span class="text-rose-500 text-[10px] font-bold block mt-1">{{ $message }}</span> @enderror
                    </div>
                </div>

                <hr class="border-slate-100">

                {{-- Row 4: Features & Status --}}
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="md:col-span-2">
                        <div class="flex items-center justify-between mb-2">
                            <label class="block text-[11px] font-bold text-slate-400 uppercase">Features List</label>
                            <span class="text-[10px] text-slate-400 font-medium">One feature per line</span>
                        </div>
                        <textarea wire:model.blur="features" rows="5" placeholder="Unlimited Disputes&#10;Priority Support&#10;Access to all templates" class="w-full px-4 py-3 rounded-xl border border-slate-200 text-sm leading-relaxed focus:border-primary-500 outline-none resize-y"></textarea>
                    </div>
                    
                    <div class="md:col-span-1 pt-6 md:pt-0 border-t md:border-t-0 md:border-l border-slate-100 md:pl-6">
                        <label class="block text-[11px] font-bold text-slate-400 uppercase mb-4">Visibility</label>
                        <label class="flex items-center gap-2 cursor-pointer group w-fit">
                            <div class="relative flex items-center">
                                <input type="checkbox" wire:model="is_active" class="peer appearance-none w-5 h-5 border-2 border-slate-300 rounded checked:bg-emerald-500 checked:border-emerald-500 transition-all">
                                <i data-lucide="check" class="absolute w-3.5 h-3.5 text-white opacity-0 peer-checked:opacity-100 left-1 transition-all pointer-events-none"></i>
                            </div>
                            <span class="text-sm font-bold text-slate-600 group-hover:text-slate-800 transition-colors">Plan is Active</span>
                        </label>
                    </div>
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
                        {{ $isEditMode ? 'Save Plan' : 'Create Plan' }}
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
        document.addEventListener('livewire:init', () => {
           Livewire.hook('morph.updated', ({ el, component }) => {
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
           });
        });

        function confirmDelete(id) {
            Swal.fire({
                title: 'Delete Plan?',
                text: "This action cannot be undone. Users currently subscribed will not be affected, but no new users can purchase it.",
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
                if (result.isConfirmed) @this.call('delete', id);
            })
        }
    </script>
</div>