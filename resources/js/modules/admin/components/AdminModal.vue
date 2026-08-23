<script setup>
import { computed, ref, toRef } from 'vue';
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
    description: {
        type: String,
        default: '',
    },
    maxWidth: {
        type: String,
        default: 'lg',
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

const maxWidthClass = computed(() => ({
    sm: 'max-w-sm',
    md: 'max-w-md',
    lg: 'max-w-lg',
    xl: 'max-w-xl',
    '2xl': 'max-w-2xl',
}[props.maxWidth] ?? 'max-w-lg'));
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
                class="fixed inset-0 z-50 flex items-end justify-center bg-slate-950/40 p-4 sm:items-center"
                @click.self="close"
            >
                <Transition
                    enter-active-class="transition duration-200 ease-out"
                    enter-from-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    enter-to-class="opacity-100 translate-y-0 sm:scale-100"
                    leave-active-class="transition duration-150 ease-in"
                    leave-from-class="opacity-100 translate-y-0 sm:scale-100"
                    leave-to-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    appear
                >
                    <div
                        v-if="show"
                        ref="panelRef"
                        role="dialog"
                        aria-modal="true"
                        :aria-labelledby="title ? 'admin-modal-title' : undefined"
                        tabindex="-1"
                        class="w-full rounded-3xl border border-slate-200 bg-white shadow-xl outline-none"
                        :class="maxWidthClass"
                    >
                        <div class="border-b border-slate-100 px-6 py-5">
                            <div class="flex items-start justify-between gap-4">
                                <div class="min-w-0">
                                    <h2
                                        v-if="title"
                                        id="admin-modal-title"
                                        class="text-lg font-semibold text-slate-950"
                                    >
                                        {{ t(title) }}
                                    </h2>
                                    <p
                                        v-if="description"
                                        class="mt-1 text-sm text-slate-600"
                                    >
                                        {{ t(description) }}
                                    </p>
                                    <slot name="header" />
                                </div>
                                <button
                                    v-if="closeable"
                                    type="button"
                                    class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-slate-200 text-slate-500 transition hover:bg-slate-50 hover:text-slate-900"
                                    :aria-label="t('Close')"
                                    @click="close"
                                >
                                    <svg viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M4.22 4.22a.75.75 0 011.06 0L10 8.94l4.72-4.72a.75.75 0 111.06 1.06L11.06 10l4.72 4.72a.75.75 0 11-1.06 1.06L10 11.06l-4.72 4.72a.75.75 0 11-1.06-1.06L8.94 10 4.22 5.28a.75.75 0 010-1.06z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div class="px-6 py-5">
                            <slot />
                        </div>

                        <div
                            v-if="$slots.footer"
                            class="flex flex-wrap items-center justify-end gap-3 border-t border-slate-100 px-6 py-4"
                        >
                            <slot name="footer" />
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>
