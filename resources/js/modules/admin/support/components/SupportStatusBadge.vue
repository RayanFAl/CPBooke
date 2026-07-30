<script setup>
import { useAdminLocale } from '../../composables/useAdminLocale';

defineProps({
    status: {
        type: String,
        required: true,
    },
});

const { t } = useAdminLocale();

const paletteByStatus = {
    open: 'bg-sky-100 text-sky-800',
    in_progress: 'bg-amber-100 text-amber-800',
    waiting_customer: 'bg-violet-100 text-violet-800',
    resolved: 'bg-emerald-100 text-emerald-800',
    closed: 'bg-slate-200 text-slate-700',
};

const labelByStatus = {
    open: 'Open',
    in_progress: 'In Progress',
    waiting_customer: 'Waiting Customer',
    resolved: 'Resolved',
    closed: 'Closed',
};
</script>

<template>
    <span
        class="inline-flex rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em]"
        :class="paletteByStatus[status] ?? 'bg-slate-100 text-slate-700'"
    >
        {{ t(labelByStatus[status] ?? status) }}
    </span>
</template>
