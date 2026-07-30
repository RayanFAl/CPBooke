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
    overdue: 'bg-rose-100 text-rose-800',
    at_risk: 'bg-amber-100 text-amber-800',
    on_track: 'bg-emerald-100 text-emerald-800',
};

const labelByStatus = {
    overdue: 'Overdue',
    at_risk: 'At Risk',
    on_track: 'On Track',
};
</script>

<template>
    <span
        class="inline-flex rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em]"
        :class="paletteByStatus[status] ?? 'bg-emerald-100 text-emerald-800'"
    >
        {{ t(labelByStatus[status] ?? status.replaceAll('_', ' ')) }}
    </span>
</template>
