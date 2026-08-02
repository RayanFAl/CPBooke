<script setup>
defineProps({
    eyebrow: { type: String, default: '' },
    title: { type: String, default: '' },
});
</script>

<template>
    <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
        <div v-if="eyebrow || title" class="mb-3">
            <p v-if="eyebrow" class="text-xs font-semibold uppercase tracking-[0.22em] text-cyan-700">{{ eyebrow }}</p>
            <h3 v-if="title" class="mt-2 text-lg font-semibold text-slate-950">{{ title }}</h3>
        </div>

        <div>
            <slot />
        </div>
    </div>
</template>
