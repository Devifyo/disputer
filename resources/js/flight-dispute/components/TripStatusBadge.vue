<template>
    <span :class="['inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold ring-1', theme.chip]">
        <span :class="['w-1.5 h-1.5 rounded-full shrink-0', theme.dot]"></span>
        {{ label || theme.label }}
    </span>
</template>

<script setup>
import { computed } from 'vue';

// Dashboard statuses for a monitored trip (kept in sync with
// Trip::displayStatus() on the backend).
const THEMES = {
    scheduled:                  { label: 'Scheduled',                  chip: 'bg-slate-50 text-slate-600 ring-slate-200',      dot: 'bg-slate-400' },
    monitoring:                 { label: 'Monitoring',                 chip: 'bg-sky-50 text-sky-700 ring-sky-200',            dot: 'bg-sky-500 animate-pulse' },
    on_time:                    { label: 'On Time',                    chip: 'bg-emerald-50 text-emerald-700 ring-emerald-200', dot: 'bg-emerald-500' },
    delayed:                    { label: 'Delayed',                    chip: 'bg-amber-50 text-amber-700 ring-amber-200',      dot: 'bg-amber-500' },
    cancelled:                  { label: 'Cancelled',                  chip: 'bg-rose-50 text-rose-700 ring-rose-200',         dot: 'bg-rose-500' },
    completed:                  { label: 'Completed',                  chip: 'bg-slate-100 text-slate-600 ring-slate-200',     dot: 'bg-slate-400' },
    potentially_eligible:       { label: 'Potentially Eligible',       chip: 'bg-violet-50 text-violet-700 ring-violet-200',   dot: 'bg-violet-500 animate-pulse' },
    eligibility_review_pending: { label: 'Eligibility Review Pending', chip: 'bg-violet-50 text-violet-700 ring-violet-200',   dot: 'bg-violet-500' },
};

const props = defineProps({
    status: { type: String, default: 'scheduled' },
    label:  { type: String, default: '' },
});

const theme = computed(() => THEMES[props.status] || THEMES.scheduled);
</script>
