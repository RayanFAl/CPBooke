<script setup>
import { useAdminLocale } from '../../composables/useAdminLocale';

const props = defineProps({
    accountType: {
        type: String,
        required: true,
    },
});

const { t } = useAdminLocale();

const paletteByType = {
    admin: 'bg-[#2f6db8]/10 text-[#255991]',
    customer: 'bg-emerald-100 text-emerald-800',
};

const labelByType = {
    admin: 'Admin',
    customer: 'Customer',
};
</script>

<template>
    <span
        class="inline-flex rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em]"
        :class="paletteByType[accountType] ?? 'bg-slate-100 text-slate-700'"
    >
        {{ t(labelByType[accountType] ?? accountType) }}
    </span>
</template>