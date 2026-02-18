<div
    x-data="{ 
        notifications: [],
        add(event) {
            const id = Date.now();
            const payload = event.detail[0] || event.detail;
            this.notifications.push({
                id: id,
                type: payload.type || 'info',
                title: payload.title || '',
                message: typeof payload === 'string' ? payload : payload.message
            });
            setTimeout(() => this.remove(id), 5000);
            // Re-initialize icons for the new element
            setTimeout(() => lucide.createIcons(), 50);
        },
        remove(id) {
            this.notifications = this.notifications.filter(n => n.id !== id);
        }
    }"
    @toast.window="add($event)"
    class="fixed top-6 right-6 z-[9999] flex flex-col gap-3 w-full max-w-sm pointer-events-none"
>
    <template x-for="n in notifications" :key="n.id">
        <div 
            x-transition:enter="transform ease-out duration-300 transition"
            x-transition:enter-start="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-4"
            x-transition:enter-end="translate-y-0 opacity-100 sm:translate-x-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="pointer-events-auto w-full bg-white rounded-2xl shadow-xl border-l-4 p-4 flex gap-3 items-start overflow-hidden"
            :class="{
                'border-emerald-500': n.type === 'success',
                'border-rose-500': n.type === 'error',
                'border-amber-500': n.type === 'warning',
                'border-blue-500': n.type === 'info'
            }"
        >
            <div class="shrink-0 mt-0.5">
                <template x-if="n.type === 'success'"><i data-lucide="check-circle" class="w-5 h-5 text-emerald-500"></i></template>
                <template x-if="n.type === 'error'"><i data-lucide="alert-circle" class="w-5 h-5 text-rose-500"></i></template>
                <template x-if="n.type === 'warning'"><i data-lucide="alert-triangle" class="w-5 h-5 text-amber-500"></i></template>
                <template x-if="n.type === 'info'"><i data-lucide="info" class="w-5 h-5 text-blue-500"></i></template>
            </div>

            <div class="flex-1 min-w-0">
                <template x-if="n.title">
                    <h3 class="text-sm font-bold text-slate-900 leading-none mb-1" x-text="n.title"></h3>
                </template>
                <p class="text-xs text-slate-600 leading-relaxed" x-text="n.message"></p>
            </div>

            <button @click="remove(n.id)" class="shrink-0 text-slate-300 hover:text-slate-500 transition-colors">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>
    </template>
</div>