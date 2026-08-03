<script setup>
defineProps({
    title: { type: String, default: '' },
});
</script>

<template>
    <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
            <div class="flex-1">
                <slot name="filters" />
            </div>
            <div class="mt-2 md:mt-0">
                <slot name="actions" />
            </div>
        </div>
    </div>
</template>
