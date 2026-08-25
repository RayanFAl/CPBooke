<script setup>
import { ref, toRef } from 'vue';
import { useAdminOverlay } from '../composables/useAdminOverlay';
import { useAdminLocale } from '../composables/useAdminLocale';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    title: {
        type: String,
        default: '',
    },
    subtitle: {
        type: String,
        default: '',
    },
    size: {
        type: String,
        default: 'md',
    },
    closeable: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits(['close']);

const panelRef = ref(null);
const { t } = useAdminLocale();

const close = () => {
    if (props.closeable) {
        emit('close');
    }
};

useAdminOverlay(panelRef, toRef(props, 'show'), close);

const sizeClass = {
    sm: 'max-w-sm',
    md: 'max-w-md',
    lg: 'max-w-lg',
    xl: 'max-w-xl',
}[props.size] ?? 'max-w-md';
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="show"
                class="fixed inset-0 z-50 bg-slate-950/30 backdrop-blur-sm"
                @click="close"
            />
        </Transition>

        <Transition
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="translate-x-full rtl:-translate-x-full"
            enter-to-class="translate-x-0"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="translate-x-0"
            leave-to-class="translate-x-full rtl:-translate-x-full"
        >
            <aside
                v-if="show"
                ref="panelRef"
                role="dialog"
                aria-modal="true"
                :aria-labelledby="$slots.header ? 'admin-drawer-title' : (title ? 'admin-drawer-title' : undefined)"
                tabindex="-1"
                class="fixed inset-y-0 end-0 z-[60] flex w-full flex-col border-s border-slate-200 bg-white shadow-[0_20px_80px_-20px_rgba(15,23,42,0.45)] outline-none"
                :class="sizeClass"
            >
                <div class="border-b border-slate-200 bg-slate-50 px-6 py-5">
                    <div class="flex items-start justify-between gap-4">
                                <div class="min-w-0">
                                    <slot name="header">
                                        <p
                                            v-if="subtitle"
                                            class="text-xs font-semibold uppercase tracking-[0.22em] text-cyan-700"
                                        >
                                            {{ t(subtitle) }}
                                        </p>
                                        <h2
                                            v-if="title"
                                            id="admin-drawer-title"
                                            class="mt-2 text-2xl font-semibold text-slate-950"
                                        >
                                            {{ t(title) }}
                                        </h2>
                                    </slot>
                                </div>
                        <button
                            v-if="closeable"
                            type="button"
                            class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-slate-200 text-slate-500 transition hover:bg-white hover:text-slate-900"
                            :aria-label="t('Close drawer')"
                            @click="close"
                        >
                            <svg viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5" aria-hidden="true">
                                <path fill-rule="evenodd" d="M4.22 4.22a.75.75 0 011.06 0L10 8.94l4.72-4.72a.75.75 0 111.06 1.06L11.06 10l4.72 4.72a.75.75 0 11-1.06 1.06L10 11.06l-4.72 4.72a.75.75 0 11-1.06-1.06L8.94 10 4.22 5.28a.75.75 0 010-1.06z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto px-6 py-6">
                    <slot />
                </div>

                <div
                    v-if="$slots.footer"
                    class="border-t border-slate-200 px-6 py-4"
                >
                    <slot name="footer" />
                </div>
            </aside>
        </Transition>
    </Teleport>
</template>
