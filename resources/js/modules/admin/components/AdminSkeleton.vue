<script setup>
defineProps({
    width: {
        type: String,
        default: '100%',
    },
    height: {
        type: String,
        default: '1rem',
    },
    rounded: {
        type: String,
        default: 'rounded-2xl',
    },
});
</script>

<template>
    <div
        class="admin-skeleton"
        :class="rounded"
        :style="{ width, height }"
        aria-hidden="true"
    />
</template>
