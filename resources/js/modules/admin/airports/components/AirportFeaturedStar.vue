<script setup>
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { useAdminLocale } from '../../composables/useAdminLocale';

const props = defineProps({
    airportKey: {
        type: String,
        required: true,
    },
    isFeatured: {
        type: Boolean,
        default: false,
    },
    featuredOrder: {
        type: Number,
        default: null,
    },
    featuredCount: {
        type: Number,
        default: null,
    },
    maxFeatured: {
        type: Number,
        default: 10,
    },
    size: {
        type: String,
        default: 'md',
    },
    showLabel: {
        type: Boolean,
        default: false,
    },
});

const { t } = useAdminLocale();
const featured = ref(props.isFeatured);
const order = ref(props.featuredOrder);
const isProcessing = ref(false);

watch(
    () => [props.isFeatured, props.featuredOrder],
    ([nextFeatured, nextOrder]) => {
        featured.value = nextFeatured;
        order.value = nextOrder;
    },
);

const canAdd = computed(() => {
    if (featured.value) {
        return true;
    }

    if (props.featuredCount === null) {
        return true;
    }

    return props.featuredCount < props.maxFeatured;
});

const buttonClass = computed(() => {
    if (props.size === 'sm') {
        return 'inline-flex size-8 items-center justify-center rounded-lg transition';
    }

    return 'inline-flex size-11 items-center justify-center rounded-2xl transition';
});

const iconClass = computed(() => (props.size === 'sm' ? 'size-4' : 'size-5'));

const toggleFeatured = () => {
    if (!canAdd.value || isProcessing.value) {
        return;
    }

    isProcessing.value = true;

    router.post(route('admin.airports.featured.toggle', props.airportKey), {}, {
        preserveScroll: true,
        onFinish: () => {
            isProcessing.value = false;
        },
    });
};
</script>

<template>
    <div class="flex items-center gap-2">
        <button
            type="button"
            :class="[
                buttonClass,
                featured
                    ? 'bg-amber-100 text-amber-600 hover:bg-amber-200'
                    : canAdd
                        ? 'border border-slate-200 bg-white text-slate-400 hover:border-amber-200 hover:text-amber-500'
                        : 'cursor-not-allowed border border-slate-200 bg-slate-50 text-slate-300',
            ]"
            :disabled="!canAdd || isProcessing"
            :title="featured ? t('Remove from best locations') : t('Add to best locations')"
            :aria-label="featured ? t('Remove from best locations') : t('Add to best locations')"
            @click.stop.prevent="toggleFeatured"
        >
            <svg viewBox="0 0 20 20" fill="currentColor" :class="iconClass" aria-hidden="true">
                <path
                    fill-rule="evenodd"
                    d="M10.868 2.884c-.321-.772-1.415-.772-1.736 0l-1.83 4.401-4.753.381c-.833.067-1.171 1.107-.536 1.651l3.62 3.102-1.106 4.637c-.194.813.691 1.456 1.405 1.02L10 15.591l4.069 2.485c.713.436 1.598-.207 1.404-1.02l-1.106-4.637 3.62-3.102c.635-.544.297-1.584-.536-1.65l-4.752-.382-1.831-4.401Z"
                    clip-rule="evenodd"
                />
            </svg>
        </button>

        <div v-if="showLabel" class="min-w-0">
            <p class="text-sm font-medium text-slate-900">
                {{ featured ? t('In best locations') : t('Not in best locations') }}
            </p>
            <p v-if="featured && order" class="text-xs text-slate-500">
                {{ t('Position') }} #{{ order }}
            </p>
            <p v-else-if="!canAdd" class="text-xs text-slate-500">
                {{ t('Best locations list is full') }}
            </p>
        </div>
    </div>
</template>
