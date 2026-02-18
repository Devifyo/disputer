<div class="p-6 pb-24 min-h-full relative">
    <x-flash />

    {{-- SweetAlert2 Asset --}}
    @assets
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    @endassets

    {{-- GLOBAL LOADER --}}
    <div wire:loading.delay.longest wire:target="store, update, deleteConfirmed" 
         class="fixed inset-0 z-[200] flex items-center justify-center bg-slate-900/20 backdrop-blur-[2px] transition-all duration-300">
        <div class="bg-white p-4 rounded-2xl shadow-2xl flex flex-col items-center gap-3 animate-in zoom-in-95 duration-200">
            <div class="relative flex h-10 w-10">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-violet-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-10 w-10 bg-violet-600 flex items-center justify-center">
                    <i data-lucide="loader-2" class="w-6 h-6 text-white animate-spin"></i>
                </span>
            </div>
            <span class="text-xs font-bold text-slate-600 uppercase tracking-wider">Processing...</span>
        </div>
    </div>

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">All Institutes</h1>
            <p class="text-sm text-slate-500">Manage banks, universities, and corporate entities.</p>
        </div>
        
        <button wire:click="create" 
                wire:loading.attr="disabled"
                class="min-w-[140px] flex items-center justify-center gap-2 px-5 py-2.5 bg-violet-600 text-white rounded-xl text-sm font-bold shadow-lg shadow-violet-200 hover:bg-violet-700 transition-all active:scale-95 disabled:opacity-70 disabled:cursor-not-allowed">
            <span wire:loading.remove wire:target="create" class="inline-flex items-center gap-2">
                <i data-lucide="plus" class="w-4 h-4"></i> Add Institute
            </span>
            <span wire:loading.flex wire:target="create" class="inline-flex items-center gap-2">
                <i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> Opening...
            </span>
        </button>
    </div>

    {{-- Filters & Table Container --}}
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm flex flex-col overflow-hidden mb-6">
        
        {{-- Filter Bar --}}
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
    
        {{-- Table Content (with smooth loading opacity) --}}
        <div class="overflow-x-auto transition-opacity duration-200" 
             wire:loading.class="opacity-50" 
             wire:target="search, filterCategory, filterStatus, gotoPage, nextPage, previousPage">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase text-[10px] tracking-widest whitespace-nowrap">
                    <tr>
                        <th class="px-6 py-4">Name</th>
                        <th class="px-6 py-4">Category</th>
                        <th class="px-6 py-4">Verification</th>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($institutions as $inst)
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            <td class="px-6 py-4">
                                <div class="font-bold text-slate-900">{{ $inst->name }}</div>
                                <div class="text-xs text-slate-400">{{ $inst->contact_email }}</div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-2.5 py-1 rounded-md text-[10px] font-bold bg-slate-100 text-slate-600 border border-slate-200">
                                    {{ $inst->category->name ?? 'N/A' }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <button wire:click="toggleVerified({{ $inst->id }})" 
                                        wire:loading.attr="disabled"
                                        class="flex items-center gap-1.5 px-2 py-1 rounded-full text-[10px] font-bold border transition-all disabled:opacity-70 disabled:cursor-not-allowed {{ $inst->is_verified ? 'bg-emerald-50 text-emerald-700 border-emerald-100' : 'bg-slate-50 text-slate-500 border-slate-200' }}">
                                    <span wire:loading.remove wire:target="toggleVerified({{ $inst->id }})" class="inline-flex items-center gap-1.5">
                                        <i data-lucide="{{ $inst->is_verified ? 'check-circle-2' : 'circle-dashed' }}" class="w-3 h-3"></i>
                                        {{ $inst->is_verified ? 'Verified' : 'Unverified' }}
                                    </span>
                                    <span wire:loading.flex wire:target="toggleVerified({{ $inst->id }})" class="inline-flex items-center gap-1.5">
                                        <i data-lucide="loader-2" class="w-3 h-3 animate-spin"></i> Updating...
                                    </span>
                                </button>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex justify-end gap-1">
                                    <button wire:click="edit({{ $inst->id }})" 
                                            class="p-2 text-slate-400 hover:text-blue-600 rounded-lg transition-all disabled:opacity-50">
                                        <div wire:loading.remove wire:target="edit({{ $inst->id }})"><i data-lucide="edit-3" class="w-4 h-4"></i></div>
                                        <div wire:loading.flex wire:target="edit({{ $inst->id }})"><i data-lucide="loader-2" class="w-4 h-4 animate-spin text-blue-600"></i></div>
                                    </button>
                                    <button onclick="confirmDelete({{ $inst->id }})" class="p-2 text-slate-400 hover:text-rose-600 rounded-lg transition-all">
                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-6 py-12 text-center text-slate-500">No institutes found matching your filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($institutions->hasPages())
            <div class="p-4 border-t border-slate-50">{{ $institutions->links() }}</div>
        @endif
    </div>

    {{-- MODAL --}}
    @if($showModal)
        <div id="modal-overlay" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
            <div onclick="closeModalFast()" class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm animate-in fade-in"></div>
            
            <div class="relative bg-white rounded-[2rem] shadow-2xl w-full max-w-2xl flex flex-col overflow-hidden max-h-[90vh] animate-in zoom-in-95 duration-200">
                
                {{-- Header --}}
                <div class="px-8 py-6 border-b flex items-center justify-between bg-white shrink-0">
                    <h2 class="text-xl font-bold text-slate-900">
                        {{ $isEditMode ? 'Edit Institute' : 'Register Institute' }}
                    </h2>
                    <button wire:click="closeModal" onclick="closeModalFast()" class="text-slate-400 hover:text-slate-600 transition-colors p-1">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>

                {{-- Body --}}
                <div class="p-8 overflow-y-auto custom-scrollbar">
                    <div class="space-y-6">
                        <div class="grid grid-cols-2 gap-6">
                            <div class="col-span-2">
                                <input type="text" wire:model="name" placeholder="Institute Name" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:border-violet-500 outline-none">
                                @error('name') <span class="text-rose-500 text-[10px] font-bold">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <select wire:model="institution_category_id" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:border-violet-500 outline-none">
                                    <option value="">Select Category</option>
                                    @foreach($this->categories as $cat)
                                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                    @endforeach
                                </select>
                                @error('institution_category_id') <span class="text-rose-500 text-[10px] font-bold">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <input type="email" wire:model="contact_email" placeholder="Contact Email" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:border-violet-500 outline-none">
                                @error('contact_email') <span class="text-rose-500 text-[10px] font-bold">{{ $message }}</span> @enderror
                            </div>
                            
                            {{-- Verification Checkbox --}}
                            <div class="flex items-center">
                                <label class="flex items-center gap-2 cursor-pointer group">
                                    <div class="relative flex items-center">
                                        <input type="checkbox" wire:model="is_verified" class="peer appearance-none w-5 h-5 border-2 border-slate-300 rounded checked:bg-violet-600 checked:border-violet-600 transition-all">
                                        <i data-lucide="check" class="absolute w-3.5 h-3.5 text-white opacity-0 peer-checked:opacity-100 left-1 transition-all pointer-events-none"></i>
                                    </div>
                                    <span class="text-sm font-bold text-slate-600 group-hover:text-slate-800 transition-colors">Verified Institute</span>
                                </label>
                            </div>
                        </div>

                        {{-- Escalation --}}
                        <div class="bg-slate-50 p-5 rounded-xl border border-slate-100 grid grid-cols-2 gap-4">
                            <div class="col-span-2 flex items-center gap-2 mb-1">
                                <i data-lucide="shield-alert" class="w-4 h-4 text-violet-500"></i>
                                <span class="text-[10px] font-black uppercase tracking-widest text-slate-400">Escalation Details</span>
                            </div>
                            <div>
                                <input type="text" wire:model="escalation_contact_name" placeholder="Contact Name (e.g. Director)" class="w-full px-3 py-2 rounded-lg border border-slate-200 text-xs focus:border-violet-500 outline-none bg-white">
                            </div>
                            <div>
                                <input type="email" wire:model="escalation_email" placeholder="Email (e.g. director@bank.com)" class="w-full px-3 py-2 rounded-lg border border-slate-200 text-xs focus:border-violet-500 outline-none bg-white">
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Footer --}}
                <div class="px-8 py-6 bg-slate-50 border-t flex justify-end gap-3 shrink-0">
                    <button wire:click="closeModal" onclick="closeModalFast()" class="text-sm font-bold text-slate-400 hover:text-slate-600 transition-colors">
                        Cancel
                    </button>

                    <button wire:click="{{ $isEditMode ? 'update' : 'store' }}" 
                            wire:loading.attr="disabled"
                            class="min-w-[120px] px-8 py-2.5 bg-slate-900 text-white rounded-xl text-sm font-bold shadow-xl active:scale-95 transition-all flex items-center justify-center gap-2 disabled:opacity-70 disabled:cursor-not-allowed">
                        <span wire:loading.remove wire:target="{{ $isEditMode ? 'update' : 'store' }}">
                            {{ $isEditMode ? 'Save Changes' : 'Register' }}
                        </span>
                        <span wire:loading.flex wire:target="{{ $isEditMode ? 'update' : 'store' }}" class="inline-flex items-center gap-2">
                            <i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> Saving...
                        </span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- SCRIPTS --}}
    <script>
        document.addEventListener('livewire:init', () => {
           Livewire.hook('morph.updated', ({ el, component }) => {
                if (typeof lucide !== 'undefined') lucide.createIcons();
           });
        });

        function closeModalFast() {
            const modal = document.getElementById('modal-overlay');
            if (modal) modal.style.display = 'none';
        }

        function confirmDelete(id) {
            Swal.fire({
                title: 'Are you sure?',
                text: "This action cannot be undone!",
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
                if (result.isConfirmed) {
                    @this.call('deleteConfirmed', id);
                }
            })
        }
    </script>
</div>