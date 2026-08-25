<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useAdminLocale } from '../composables/useAdminLocale';

const props = defineProps({
    items: {
        type: Array,
        required: true,
    },
});

const { t, isArabic } = useAdminLocale();

const separator = computed(() => (isArabic.value ? '‹' : '›'));

const labelFor = (item) => {
    if (item.translate === false) {
        return item.label;
    }

    return t(item.label);
};
</script>

<template>
    <nav aria-label="Breadcrumb" class="mb-6">
        <ol class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-slate-500">
            <li
                v-for="(item, index) in items"
                :key="`${item.label}-${index}`"
                class="inline-flex min-w-0 items-center gap-2"
            >
                <span
                    v-if="index > 0"
                    class="text-slate-300"
                    aria-hidden="true"
                >
                    {{ separator }}
                </span>

                <Link
                    v-if="item.href && !item.current"
                    :href="item.href"
                    class="truncate font-medium text-cyan-700 transition hover:text-cyan-800"
                >
                    {{ labelFor(item) }}
                </Link>

                <span
                    v-else
                    class="truncate font-medium text-slate-700"
                    :aria-current="item.current ? 'page' : undefined"
                >
                    {{ labelFor(item) }}
                </span>
            </li>
        </ol>
    </nav>
</template>
