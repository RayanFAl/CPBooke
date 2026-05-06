<script setup>
defineProps({
    active: {
        type: Boolean,
        required: true,
    },
});
</script>

<template>
    <span
        class="inline-flex rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em]"
        :class="active ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-700'"
    >
        {{ active ? 'Active' : 'Inactive' }}
    </span>
</template>