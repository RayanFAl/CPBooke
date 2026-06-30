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
    <section class="space-y-6">
        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-[0.25em] text-cyan-700">
                {{ t(eyebrow) }}
            </p>
            <h2 class="mt-3 text-2xl font-semibold text-slate-950">{{ t(title) }}</h2>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">
                {{ t(description) }}
            </p>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            <div class="rounded-3xl border border-dashed border-slate-300 bg-white p-5">
                <p class="text-sm font-medium text-slate-900">{{ t('Status') }}</p>
                <p class="mt-2 text-sm text-slate-600">{{ t('Foundation ready for feature implementation.') }}</p>
            </div>
            <div class="rounded-3xl border border-dashed border-slate-300 bg-white p-5">
                <p class="text-sm font-medium text-slate-900">{{ t('Backend') }}</p>
                <p class="mt-2 text-sm text-slate-600">{{ t('Module controller and route are in place.') }}</p>
            </div>
            <div class="rounded-3xl border border-dashed border-slate-300 bg-white p-5">
                <p class="text-sm font-medium text-slate-900">{{ t('Frontend') }}</p>
                <p class="mt-2 text-sm text-slate-600">{{ t('Inertia page is wired through the shared admin layout.') }}</p>
            </div>
        </div>

        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <slot />
        </div>
    </section>
</template>