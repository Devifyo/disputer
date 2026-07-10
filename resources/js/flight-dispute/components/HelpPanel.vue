<template>
    <div class="bg-white rounded-2xl ring-1 ring-slate-900/5 p-6">
        <h2 class="text-lg font-black text-slate-900 mb-5">How can we help you?</h2>
        <a v-for="h in help" :key="h.label" :href="h.url" class="flex items-center gap-3 py-3 border-b last:border-0 border-slate-100 group">
            <span class="w-10 h-10 rounded-full bg-primary-50 text-primary-600 flex items-center justify-center shrink-0" v-html="h.icon"></span>
            <span class="flex-1 min-w-0">
                <span class="block font-bold text-slate-900">{{ h.label }}</span>
                <span class="block text-sm text-slate-400">{{ h.sub }}</span>
            </span>
            <svg class="w-4 h-4 text-slate-300 group-hover:text-primary-500 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
        </a>
    </div>
</template>

<script setup>
const help = [
    { label: 'Chat', sub: 'Send us a message', url: '/support', icon: '<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M8 12h8M8 8h8m-8 8h5m5-4a8 8 0 01-11.6 7.1L3 21l1.9-4.4A8 8 0 1121 12z"/></svg>' },
    { label: 'Customer Service', sub: 'Questions? Contact us!', url: '/support', icon: '<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M4 7l8 5 8-5"/></svg>' },
    { label: 'FAQs', sub: 'Your questions, answered', url: '/support', icon: '<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M9.5 9.5a2.5 2.5 0 013.9-2c1 .7 1.1 2 .1 2.8-.7.6-1.5.8-1.5 1.7M12 16h.01"/></svg>' },
];
</script>
