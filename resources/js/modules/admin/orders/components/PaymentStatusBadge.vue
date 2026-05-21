<script setup>
import { useAdminLocale } from '../../composables/useAdminLocale';

const props = defineProps({
    status: {
        type: String,
        required: true,
    },
});

const { t } = useAdminLocale();

const paletteByStatus = {
    unpaid: 'bg-amber-100 text-amber-800',
    paid: 'bg-emerald-100 text-emerald-800',
    partially_refunded: 'bg-orange-100 text-orange-800',
    refunded: 'bg-violet-100 text-violet-800',
};

const labelByStatus = {
    unpaid: 'Unpaid',
    paid: 'Paid',
    partially_refunded: 'Partially Refunded',
    refunded: 'Refunded',
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