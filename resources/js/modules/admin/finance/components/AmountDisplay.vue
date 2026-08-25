<script setup>
import { computed } from 'vue';
import { useAdminLocale } from '../../composables/useAdminLocale';
import { usePlatformCurrency } from '../../composables/usePlatformCurrency';

const props = defineProps({
    amount: { type: [Number, String], required: true },
    currency: { type: String, default: null },
});

const { locale } = useAdminLocale();
const { defaultCurrency } = usePlatformCurrency();
const resolvedCurrency = computed(() => props.currency || defaultCurrency.value || 'LYD');

const format = (value) => {
    try {
        return new Intl.NumberFormat(locale.value, {
            style: 'currency',
            currency: resolvedCurrency.value,
        }).format(Number(value ?? 0));
    } catch {
        return `${value} ${resolvedCurrency.value}`;
    }
};
</script>

<template>
    <span class="font-semibold text-slate-950">{{ format(amount) }}</span>
</template>
