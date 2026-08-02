<script setup>
import { useAdminLocale } from '../../composables/useAdminLocale';

const props = defineProps({
    status: { type: String, required: true },
});

const { t } = useAdminLocale();

const palette = {
    completed: 'bg-emerald-100 text-emerald-800',
    pending: 'bg-amber-100 text-amber-800',
    failed: 'bg-rose-100 text-rose-800',
    reversed: 'bg-violet-100 text-violet-800',
};

const label = {
    completed: 'Completed',
    pending: 'Pending',
    failed: 'Failed',
    reversed: 'Reversed',
};
</script>

<template>
    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em]" :class="palette[status] ?? 'bg-slate-100 text-slate-700'">
        {{ t(label[status] ?? status) }}
    </span>
</template>
