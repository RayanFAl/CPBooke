<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useAdminLocale } from '../composables/useAdminLocale';

const props = defineProps({
    text: {
        type: String,
        required: true,
    },
});

const { t } = useAdminLocale();
const open = ref(false);
const rootRef = ref(null);
const openedByHover = ref(false);

const label = computed(() => t('Page guide'));

const canHover = () => (
    typeof window !== 'undefined'
    && window.matchMedia('(hover: hover) and (pointer: fine)').matches
);

const show = () => {
    if (!canHover()) {
        return;
    }

    openedByHover.value = true;
    open.value = true;
};

const hide = () => {
    if (!openedByHover.value) {
        return;
    }

    openedByHover.value = false;
    open.value = false;
};

const toggle = () => {
    openedByHover.value = false;
    open.value = !open.value;
};

const onDocumentPointer = (event) => {
    if (!open.value || !rootRef.value) {
        return;
    }

    if (!rootRef.value.contains(event.target)) {
        openedByHover.value = false;
        open.value = false;
    }
};

const onKeydown = (event) => {
    if (event.key === 'Escape') {
        openedByHover.value = false;
        open.value = false;
    }
};

onMounted(() => {
    document.addEventListener('pointerdown', onDocumentPointer);
    document.addEventListener('keydown', onKeydown);
});

onBeforeUnmount(() => {
    document.removeEventListener('pointerdown', onDocumentPointer);
    document.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <span
        ref="rootRef"
        class="relative inline-flex shrink-0"
        @mouseenter="show"
        @mouseleave="hide"
        @focusin="show"
        @focusout="hide"
    >
        <button
            type="button"
            class="inline-flex h-6 w-6 items-center justify-center rounded-full border border-cyan-200 bg-cyan-50 text-xs font-bold text-cyan-800 transition hover:bg-cyan-100 focus:outline-none focus:ring-2 focus:ring-cyan-500"
            :aria-label="label"
            :aria-expanded="open"
            :title="label"
            @click.stop="toggle"
        >
            !
        </button>

        <span
            v-if="open && text"
            role="tooltip"
            class="absolute left-1/2 top-full z-50 mt-2 w-80 max-w-[min(20rem,calc(100vw-2.5rem))] -translate-x-1/2 rounded-2xl border border-slate-200 bg-white p-3 text-start text-sm font-normal leading-6 text-slate-700 shadow-lg shadow-slate-950/10"
        >
            {{ text }}
        </span>
    </span>
</template>
