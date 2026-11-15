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
    draft: 'bg-slate-200 text-slate-700',
    pending_payment: 'bg-amber-100 text-amber-800',
    paid: 'bg-emerald-100 text-emerald-800',
    processing: 'bg-[#2f6db8]/10 text-[#255991]',
    confirmed: 'bg-emerald-100 text-emerald-800',
    completed: 'bg-[#2f6db8]/10 text-[#255991]',
    failed: 'bg-rose-100 text-rose-800',
    cancelled: 'bg-slate-200 text-slate-700',
    refunded: 'bg-[#2f6db8]/10 text-[#255991]',
};

const labelByStatus = {
    draft: 'Draft',
    pending_payment: 'Pending Payment',
    paid: 'Paid',
    processing: 'Processing',
    confirmed: 'Confirmed',
    completed: 'Completed',
    failed: 'Failed',
    cancelled: 'Cancelled',
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