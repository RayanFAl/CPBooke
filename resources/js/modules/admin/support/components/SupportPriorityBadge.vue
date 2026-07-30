<script setup>
import { useAdminLocale } from '../../composables/useAdminLocale';

defineProps({
    priority: {
        type: String,
        required: true,
    },
});

const { t } = useAdminLocale();

const paletteByPriority = {
    low: 'bg-slate-200 text-slate-700',
    medium: 'bg-cyan-100 text-cyan-800',
    high: 'bg-amber-100 text-amber-800',
    urgent: 'bg-rose-100 text-rose-800',
};

const labelByPriority = {
    low: 'Low',
    medium: 'Medium',
    high: 'High',
    urgent: 'Urgent',
};
</script>

<template>
    <span
        class="inline-flex rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em]"
        :class="paletteByPriority[priority] ?? 'bg-slate-100 text-slate-700'"
    >
        {{ t(labelByPriority[priority] ?? priority) }}
    </span>
</template>
