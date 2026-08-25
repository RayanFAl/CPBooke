<script setup>
import { computed } from 'vue';
import { useAdminLocale } from '../composables/useAdminLocale';

const props = defineProps({
    title: {
        type: String,
        required: true,
    },
    description: {
        type: String,
        default: '',
    },
    icon: {
        type: String,
        default: 'default',
    },
});

const { t } = useAdminLocale();

const iconPath = computed(() => {
    if (props.icon === 'search') {
        return 'M8.5 3a5.5 5.5 0 104.383 8.823l3.09 3.09a.75.75 0 101.06-1.06l-3.09-3.09A5.5 5.5 0 008.5 3zm-4 5.5a4 4 0 118 0 4 4 0 01-8 0z';
    }

    return 'M4 4h12v10H4V8zm3 2v2h6v-2H7z';
});
</script>

<template>
    <div class="rounded-3xl border border-dashed border-slate-200 bg-slate-50 px-6 py-12 text-center">
        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-white text-slate-400 ring-1 ring-slate-200">
            <svg class="h-6 w-6" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path :d="iconPath" />
            </svg>
        </div>

        <h3 class="mt-5 text-lg font-semibold text-slate-950">
            {{ t(title) }}
        </h3>

        <p v-if="description" class="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-600">
            {{ t(description) }}
        </p>

        <div v-if="$slots.action" class="mt-6 flex justify-center">
            <slot name="action" />
        </div>
    </div>
</template>
