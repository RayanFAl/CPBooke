<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useAdminLocale } from '../../composables/useAdminLocale';

const props = defineProps({
    initialFeatured: {
        type: Array,
        default: () => [],
    },
    maxFeatured: {
        type: Number,
        default: 10,
    },
    enabled: {
        type: Boolean,
        default: false,
    },
    searchUrl: {
        type: String,
        required: true,
    },
    updateUrl: {
        type: String,
        required: true,
    },
});

const { t } = useAdminLocale();

const isOpen = ref(false);
const featuredItems = ref(props.initialFeatured.map((airport) => ({ ...airport })));
const searchQuery = ref('');
const searchResults = ref([]);
const isSearching = ref(false);
const searchError = ref('');

const form = useForm({
    airports: featuredItems.value.map((airport) => airport.airport_key),
});

const canAddMore = computed(() => featuredItems.value.length < props.maxFeatured);
const featuredCountLabel = computed(() => `${featuredItems.value.length}/${props.maxFeatured}`);
const hasUnsavedChanges = computed(() => {
    const savedKeys = props.initialFeatured.map((airport) => airport.airport_key);
    const currentKeys = featuredItems.value.map((airport) => airport.airport_key);

    return JSON.stringify(savedKeys) !== JSON.stringify(currentKeys);
});

const syncForm = () => {
    form.airports = featuredItems.value.map((airport) => airport.airport_key);
};

const addAirport = (airport) => {
    if (!canAddMore.value || featuredItems.value.some((item) => item.airport_key === airport.airport_key)) {
        return;
    }

    featuredItems.value.push({ ...airport, sort_order: featuredItems.value.length + 1 });
    syncForm();
    searchQuery.value = '';
    searchResults.value = [];
};

const removeAirport = (index) => {
    featuredItems.value.splice(index, 1);
    featuredItems.value = featuredItems.value.map((airport, itemIndex) => ({
        ...airport,
        sort_order: itemIndex + 1,
    }));
    syncForm();
};

const moveAirport = (index, direction) => {
    const targetIndex = index + direction;

    if (targetIndex < 0 || targetIndex >= featuredItems.value.length) {
        return;
    }

    const items = [...featuredItems.value];
    [items[index], items[targetIndex]] = [items[targetIndex], items[index]];
    featuredItems.value = items.map((airport, itemIndex) => ({
        ...airport,
        sort_order: itemIndex + 1,
    }));
    syncForm();
};

const submitFeatured = () => {
    form.put(props.updateUrl, { preserveScroll: true });
};

let searchTimer = null;

const runSearch = async () => {
    const query = searchQuery.value.trim();

    if (query.length < 2) {
        searchResults.value = [];
        searchError.value = '';
        return;
    }

    isSearching.value = true;
    searchError.value = '';

    try {
        const response = await fetch(`${props.searchUrl}?q=${encodeURIComponent(query)}&limit=5`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            throw new Error('Search failed');
        }

        const payload = await response.json();
        const selectedKeys = new Set(featuredItems.value.map((airport) => airport.airport_key));

        searchResults.value = (payload.results ?? []).filter(
            (airport) => !selectedKeys.has(airport.airport_key),
        );
    } catch {
        searchError.value = t('Unable to search airports right now.');
        searchResults.value = [];
    } finally {
        isSearching.value = false;
    }
};

watch(searchQuery, () => {
    if (searchTimer) {
        window.clearTimeout(searchTimer);
    }

    searchTimer = window.setTimeout(runSearch, 300);
});

watch(
    () => props.initialFeatured,
    (featured) => {
        featuredItems.value = featured.map((airport) => ({ ...airport }));
        syncForm();
    },
    { deep: true },
);

const displayCode = (airport) => airport.iata_code || airport.icao_code || '-';
const displaySubtitle = (airport) => `${airport.city_en || '-'} · ${airport.country_name_en || '-'}`;

const togglePanel = () => {
    isOpen.value = !isOpen.value;
};
</script>

<template>
    <div
        v-if="enabled"
        class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm"
    >
        <button
            type="button"
            class="flex w-full items-center justify-between gap-4 px-6 py-4 text-start transition hover:bg-slate-50/80"
            :aria-expanded="isOpen"
            @click="togglePanel"
        >
            <div class="flex min-w-0 items-center gap-3">
                <span
                    class="inline-flex size-8 shrink-0 items-center justify-center rounded-xl border border-slate-200 text-slate-600 transition"
                    :class="isOpen ? 'rotate-180' : ''"
                >
                    <svg viewBox="0 0 20 20" fill="currentColor" class="size-4" aria-hidden="true">
                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.168l3.71-3.94a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" />
                    </svg>
                </span>
                <div class="min-w-0">
                    <h3 class="text-base font-semibold text-slate-950">{{ t('Best locations') }}</h3>
                    <p class="truncate text-sm text-slate-500">{{ t('Top airports shown first in the mobile app') }}</p>
                </div>
            </div>

            <div class="flex shrink-0 items-center gap-2">
                <span
                    v-if="hasUnsavedChanges"
                    class="hidden rounded-full bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-700 sm:inline"
                >
                    {{ t('Unsaved') }}
                </span>
                <span class="rounded-xl bg-slate-950 px-3 py-1.5 text-xs font-semibold text-white">
                    {{ featuredCountLabel }}
                </span>
            </div>
        </button>

        <form v-show="isOpen" class="border-t border-slate-200 px-6 py-5" @submit.prevent="submitFeatured">
            <div class="relative">
                <input
                    v-model="searchQuery"
                    type="text"
                    class="w-full rounded-2xl border border-slate-200 px-4 py-3 pe-10 text-sm outline-none transition focus:border-cyan-600"
                    :placeholder="t('Search airport to add...')"
                    :disabled="!canAddMore || form.processing"
                >
                <span class="pointer-events-none absolute inset-y-0 end-3 flex items-center text-slate-400">
                    <svg viewBox="0 0 20 20" fill="currentColor" class="size-4" aria-hidden="true">
                        <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11ZM2 9a7 7 0 1 1 12.452 4.391l3.328 3.329a.75.75 0 1 1-1.06 1.06l-3.329-3.328A7 7 0 0 1 2 9Z" clip-rule="evenodd" />
                    </svg>
                </span>
            </div>

            <p v-if="!canAddMore" class="mt-2 text-xs text-slate-500">{{ t('Maximum featured airports reached.') }}</p>
            <p v-else-if="isSearching" class="mt-2 text-xs text-slate-500">{{ t('Searching...') }}</p>
            <p v-else-if="searchError" class="mt-2 text-sm text-rose-600">{{ searchError }}</p>

            <ul v-if="searchResults.length > 0" class="mt-3 space-y-2">
                <li v-for="airport in searchResults" :key="airport.airport_key">
                    <button
                        type="button"
                        class="flex w-full items-center gap-3 rounded-2xl border border-slate-200 px-3 py-2.5 text-start transition hover:border-cyan-200 hover:bg-cyan-50/40"
                        @click="addAirport(airport)"
                    >
                        <span class="rounded-lg bg-slate-950 px-2 py-1 text-xs font-semibold text-white">{{ displayCode(airport) }}</span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-medium text-slate-900">{{ airport.name_en }}</span>
                            <span class="block truncate text-xs text-slate-500">{{ displaySubtitle(airport) }}</span>
                        </span>
                        <span class="text-xs font-medium text-cyan-700">{{ t('Add') }}</span>
                    </button>
                </li>
            </ul>

            <ul v-if="featuredItems.length > 0" class="mt-4 space-y-2">
                <li
                    v-for="(airport, index) in featuredItems"
                    :key="airport.airport_key"
                    class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50/50 px-3 py-2.5"
                >
                    <span class="inline-flex size-7 shrink-0 items-center justify-center rounded-lg bg-cyan-700 text-xs font-semibold text-white">
                        {{ index + 1 }}
                    </span>
                    <span class="rounded-lg bg-white px-2 py-1 text-xs font-semibold text-slate-900 ring-1 ring-slate-200">
                        {{ displayCode(airport) }}
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-medium text-slate-900">{{ airport.name_en }}</span>
                        <span class="block truncate text-xs text-slate-500">{{ displaySubtitle(airport) }}</span>
                    </span>
                    <div class="flex shrink-0 items-center gap-1">
                        <button
                            type="button"
                            class="inline-flex size-7 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 disabled:opacity-30"
                            :disabled="index === 0 || form.processing"
                            :aria-label="t('Move up')"
                            @click="moveAirport(index, -1)"
                        >
                            ↑
                        </button>
                        <button
                            type="button"
                            class="inline-flex size-7 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 disabled:opacity-30"
                            :disabled="index === featuredItems.length - 1 || form.processing"
                            :aria-label="t('Move down')"
                            @click="moveAirport(index, 1)"
                        >
                            ↓
                        </button>
                        <button
                            type="button"
                            class="inline-flex size-7 items-center justify-center rounded-lg border border-rose-200 bg-white text-rose-600"
                            :disabled="form.processing"
                            :aria-label="t('Remove')"
                            @click="removeAirport(index)"
                        >
                            ×
                        </button>
                    </div>
                </li>
            </ul>

            <p v-else class="mt-4 rounded-2xl border border-dashed border-slate-200 px-4 py-8 text-center text-sm text-slate-500">
                {{ t('No best locations yet. Search above to add one.') }}
            </p>

            <div class="mt-4 flex justify-end">
                <button
                    type="submit"
                    class="rounded-2xl bg-cyan-700 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-cyan-600 disabled:opacity-50"
                    :disabled="form.processing || !hasUnsavedChanges"
                >
                    {{ form.processing ? t('Saving...') : t('Save') }}
                </button>
            </div>
        </form>
    </div>
</template>
