<script setup>
import { computed } from 'vue';
import { useAdminLocale } from '../composables/useAdminLocale';
import { statusVariants } from '../config/statusPalette';

const props = defineProps({
    variant: {
        type: String,
        default: 'neutral',
    },
    label: {
        type: String,
        required: true,
    },
});

const { t } = useAdminLocale();

const classes = computed(() => statusVariants[props.variant] ?? statusVariants.neutral);
</script>

<template>
    <span
        class="inline-flex shrink-0 rounded-full px-2.5 py-0.5 text-xs font-semibold uppercase tracking-[0.12em]"
        :class="classes"
    >
        {{ t(label) }}
    </span>
</template>
