<script setup>
import AdminSkeleton from './AdminSkeleton.vue';

defineProps({
    columns: {
        type: Number,
        default: 6,
    },
    rows: {
        type: Number,
        default: 8,
    },
});
</script>

<template>
    <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm" aria-hidden="true">
        <div class="border-b border-slate-200 bg-slate-50 px-6 py-4">
            <div class="flex gap-4">
                <AdminSkeleton
                    v-for="column in columns"
                    :key="`head-${column}`"
                    height="0.875rem"
                    width="5rem"
                    rounded="rounded-lg"
                />
            </div>
        </div>

        <div class="divide-y divide-slate-100">
            <div
                v-for="row in rows"
                :key="`row-${row}`"
                class="grid gap-4 px-6 py-5"
                :style="{ gridTemplateColumns: `repeat(${columns}, minmax(0, 1fr))` }"
            >
                <AdminSkeleton
                    v-for="column in columns"
                    :key="`cell-${row}-${column}`"
                    height="1rem"
                    rounded="rounded-lg"
                />
            </div>
        </div>
    </div>
</template>
