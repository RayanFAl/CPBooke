<script setup>
defineProps({
    alt: {
        type: String,
        default: 'Booke',
    },
});
</script>

<template>
    <img
        src="/images/app_logo.png"
        :alt="alt"
        class="object-contain"
    >
</template>
