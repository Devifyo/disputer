<template>
    <span class="relative inline-flex group align-middle">
        <button
            type="button"
            class="text-slate-300 hover:text-slate-500 focus:text-slate-500 focus:outline-none transition-colors"
            :aria-label="`What does this mean? ${text}`"
        >
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="9"/>
                <path stroke-linecap="round" d="M12 11v5m0-8h.01"/>
            </svg>
        </button>
        <span
            role="tooltip"
            class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-2 w-52 rounded-xl bg-slate-900 text-white text-xs font-medium normal-case tracking-normal text-left px-3 py-2 opacity-0 group-hover:opacity-100 group-focus-within:opacity-100 transition-opacity z-20 shadow-lg"
        >
            {{ text }}
            <span class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-slate-900"></span>
        </span>
    </span>
</template>

<script setup>
// Small ⓘ icon that reveals an explanation on hover (desktop) or tap/focus
// (mobile). Purely presentational — pass the explanation as `text`.
defineProps({ text: { type: String, required: true } });
</script>
