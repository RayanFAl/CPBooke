<script setup>
import { useAdminLocale } from '../../composables/useAdminLocale';

const props = defineProps({
    amount: { type: [Number, String], required: true },
    currency: { type: String, default: 'LYD' },
});

const { locale } = useAdminLocale();

const format = (value) => {
    try {
        return new Intl.NumberFormat(locale.value, { style: 'currency', currency: props.currency }).format(Number(value ?? 0));
    } catch {
        return `${value} ${props.currency}`;
    }
};
</script>

<template>
    <span class="font-semibold text-slate-950">{{ format(amount) }}</span>
</template>
