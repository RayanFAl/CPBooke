<script setup>
defineProps({
    title: { type: String, default: '' },
    subtitle: { type: String, default: '' },
});
</script>

<template>
    <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex items-start justify-between">
            <div>
                <p v-if="title" class="text-sm font-semibold text-slate-950">{{ title }}</p>
                <p v-if="subtitle" class="mt-1 text-xs text-slate-500">{{ subtitle }}</p>
            </div>
        </div>

        <div class="mt-4">
            <slot name="chart">
                <!-- Chart canvas placeholder -->
                <div class="h-48 rounded-xl bg-slate-50" />
            </slot>
        </div>
    </div>
</template>
