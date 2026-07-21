{{--
    Styled confirmation modal replacing native wire:confirm dialogs.
    Open from any button inside the same Livewire component:
    $dispatch('admin-confirm', { title, message, confirmLabel, danger, method, params })
    On confirm it calls $wire[method](...params) - existing wire:loading
    spinners keyed to the method still apply.
--}}
<div x-data="{ open: false, title: '', message: '', confirmLabel: 'Confirm', danger: false, method: null, params: [] }"
     @admin-confirm.window="
        title = $event.detail.title || 'Are you sure?';
        message = $event.detail.message || '';
        confirmLabel = $event.detail.confirmLabel || 'Confirm';
        danger = !!$event.detail.danger;
        method = $event.detail.method;
        params = $event.detail.params || [];
        open = true"
     @keydown.escape.window="open = false"
     x-show="open" x-cloak
     class="fixed inset-0 z-[70] flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" @click="open = false"></div>
    <div x-show="open" x-transition.scale.origin.center class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md p-6">
        <div class="flex items-start gap-4">
            <span class="shrink-0 w-10 h-10 rounded-full flex items-center justify-center"
                  :class="danger ? 'bg-rose-50 text-rose-600' : 'bg-slate-100 text-slate-700'">
                <i data-lucide="triangle-alert" class="w-5 h-5" x-show="danger"></i>
                <i data-lucide="circle-help" class="w-5 h-5" x-show="!danger"></i>
            </span>
            <div class="min-w-0">
                <h3 class="font-bold text-slate-900" x-text="title"></h3>
                <p class="text-sm text-slate-500 mt-1 leading-relaxed" x-text="message"></p>
            </div>
        </div>
        <div class="flex justify-end gap-2 mt-6">
            <button @click="open = false"
                    class="px-4 py-2.5 rounded-xl border border-slate-200 bg-white text-slate-700 text-sm font-bold hover:border-slate-300 transition-colors">
                Cancel
            </button>
            <button @click="open = false; $wire[method](...params)"
                    class="px-5 py-2.5 rounded-xl text-white text-sm font-bold transition-colors"
                    :class="danger ? 'bg-rose-600 hover:bg-rose-700' : 'bg-slate-900 hover:bg-slate-800'"
                    x-text="confirmLabel"></button>
        </div>
    </div>
</div>
