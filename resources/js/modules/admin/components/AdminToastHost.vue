<script setup>
import { useAdminToast } from '../composables/useAdminToast';
import { useAdminLocale } from '../composables/useAdminLocale';

const { toasts, dismiss } = useAdminToast();
const { t } = useAdminLocale();

const variantClasses = {
    success: 'border-emerald-200 bg-emerald-50 text-emerald-900',
    error: 'border-rose-200 bg-rose-50 text-rose-900',
    info: 'border-sky-200 bg-sky-50 text-sky-900',
};
</script>

<template>
    <div
        class="pointer-events-none fixed inset-x-4 bottom-4 z-[100] flex flex-col items-end gap-3 sm:inset-x-auto sm:end-6 sm:bottom-6"
        aria-live="polite"
        aria-relevant="additions"
    >
        <TransitionGroup
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0 translate-y-2"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 translate-y-2"
        >
            <div
                v-for="toast in toasts"
                :key="toast.id"
                class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-2xl border px-4 py-3 shadow-lg shadow-slate-950/10"
                :class="variantClasses[toast.variant] ?? variantClasses.info"
                role="status"
            >
                <p class="min-w-0 flex-1 text-sm font-medium leading-6">
                    {{ toast.message }}
                </p>
                <button
                    type="button"
                    class="shrink-0 rounded-lg px-2 py-1 text-xs font-semibold uppercase tracking-[0.12em] opacity-70 transition hover:opacity-100"
                    :aria-label="t('Dismiss')"
                    @click="dismiss(toast.id)"
                >
                    {{ t('Dismiss') }}
                </button>
            </div>
        </TransitionGroup>
    </div>
</template>
