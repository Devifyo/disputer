<template>
    <div class="flex items-center gap-2 flex-wrap">
        <button
            v-for="chip in chips"
            :key="chip.key"
            type="button"
            @click="$emit('update:modelValue', chip.key)"
            class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-full text-xs font-bold ring-1 transition-all active:scale-95"
            :class="modelValue === chip.key
                ? 'bg-slate-900 text-white ring-slate-900 shadow-sm'
                : 'bg-white text-slate-500 ring-slate-200 hover:text-slate-800 hover:ring-slate-300'"
        >
            {{ chip.label }}
            <span
                class="min-w-[20px] h-5 px-1.5 rounded-full text-[11px] font-black flex items-center justify-center"
                :class="modelValue === chip.key ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-500'"
            >{{ chip.count }}</span>
        </button>
    </div>
</template>

<script setup>
// Status filter chips with counts. `chips` is [{ key, label, count }];
// the active key is v-model bound.
defineProps({
    chips:      { type: Array, required: true },
    modelValue: { type: String, required: true },
});
defineEmits(['update:modelValue']);
</script>
