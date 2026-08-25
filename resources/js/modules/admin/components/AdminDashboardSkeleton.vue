<script setup>
import AdminSkeleton from './AdminSkeleton.vue';
</script>

<template>
    <div class="space-y-6" aria-hidden="true">
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div
                v-for="index in 4"
                :key="index"
                class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm"
            >
                <AdminSkeleton height="0.75rem" width="6rem" rounded="rounded-lg" />
                <AdminSkeleton class="mt-4" height="2rem" width="40%" rounded="rounded-xl" />
                <AdminSkeleton class="mt-3" height="0.875rem" width="80%" rounded="rounded-lg" />
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <div
                v-for="index in 2"
                :key="`chart-${index}`"
                class="rounded-[1.8rem] border border-slate-200 bg-white p-5 shadow-sm"
            >
                <AdminSkeleton height="0.875rem" width="7rem" rounded="rounded-lg" />
                <AdminSkeleton class="mt-3" height="1.5rem" width="45%" rounded="rounded-xl" />
                <AdminSkeleton class="mt-6" height="10rem" width="100%" rounded="rounded-[1.5rem]" />
            </div>
        </div>
    </div>
</template>
