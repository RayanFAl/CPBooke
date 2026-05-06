<script setup>
const props = defineProps({
    status: {
        type: String,
        required: true,
    },
});

const paletteByStatus = {
    pending: 'bg-amber-100 text-amber-800',
    confirmed: 'bg-emerald-100 text-emerald-800',
    failed: 'bg-rose-100 text-rose-800',
    cancelled: 'bg-slate-200 text-slate-700',
};

const labelByStatus = {
    pending: 'Pending',
    confirmed: 'Confirmed',
    failed: 'Failed',
    cancelled: 'Cancelled',
};
</script>

<template>
    <span
        class="inline-flex rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em]"
        :class="paletteByStatus[status] ?? 'bg-slate-100 text-slate-700'"
    >
        {{ labelByStatus[status] ?? status }}
    </span>
</template>