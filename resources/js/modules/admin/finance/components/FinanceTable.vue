<script setup>
defineProps({
    title: { type: String, default: '' },
});
</script>

<template>
    <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div v-if="title" class="border-b border-slate-200 px-5 py-4">
            <h3 class="text-sm font-semibold text-slate-950">{{ title }}</h3>
        </div>

        <div class="overflow-x-auto p-3">
            <slot />
        </div>
    </div>
</template>
