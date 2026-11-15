<script setup>
import { useAdminLocale } from '../composables/useAdminLocale';

defineProps({
    eyebrow: {
        type: String,
        default: 'Module',
    },
    title: {
        type: String,
        required: true,
    },
    description: {
        type: String,
        default: '',
    },
});

const { t } = useAdminLocale();
</script>

<template>
    <section class="space-y-5">
        <div class="admin-panel p-5 sm:p-6">
            <p class="admin-eyebrow">
                {{ t(eyebrow) }}
            </p>
            <h2 class="mt-2 text-xl font-semibold tracking-tight text-slate-950">{{ t(title) }}</h2>
            <p v-if="description" class="mt-1.5 max-w-2xl text-sm leading-6 text-slate-500">
                {{ t(description) }}
            </p>
        </div>

        <div class="admin-panel p-5 sm:p-6">
            <slot />
        </div>
    </section>
</template>