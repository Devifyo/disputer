<div class="h-full overflow-y-auto p-6 pb-24 relative">
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
                class="bg-slate-900 hover:bg-slate-800 text-white px-3 sm:px-4 py-2 rounded-lg text-xs font-bold shadow-lg shadow-slate-900/20 transition-all flex items-center gap-2 hover:scale-105 active:scale-95">
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
        @include('livewire.admin.institutions.partials.filters')
        @include('livewire.admin.institutions.partials.table')
    </div>

    {{-- Modal Component --}}
    @include('livewire.admin.institutions.partials.modal')

    {{-- SCRIPTS --}}
    <script>
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