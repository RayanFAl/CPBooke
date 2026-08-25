<script setup>
import AdminLayout from '../../layouts/AdminLayout.vue';
import FeaturedAirportsPanel from '../components/FeaturedAirportsPanel.vue';
import AirportFeaturedStar from '../components/AirportFeaturedStar.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import { useAdminLocale } from '../../composables/useAdminLocale';

const props = defineProps({
    airports: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        required: true,
    },
    usesFullSchema: {
        type: Boolean,
        default: false,
    },
    importStatus: {
        type: Object,
        default: null,
    },
    featuredAirports: {
        type: Array,
        default: () => [],
    },
    maxFeaturedAirports: {
        type: Number,
        default: 10,
    },
    featuredAirportKeys: {
        type: Array,
        default: () => [],
    },
    country_options: {
        type: Array,
        default: () => [],
    },
    type_options: {
        type: Array,
        default: () => [],
    },
    per_page_options: {
        type: Array,
        default: () => [20, 50, 100],
    },
});

const { t, paginationLabel } = useAdminLocale();

const filterForm = reactive({
    search: props.filters.search ?? '',
    country: props.filters.country ?? '',
    type: props.filters.type ?? '',
    per_page: props.filters.per_page ?? 20,
});

const filtersReady = ref(false);
let searchDebounceTimer = null;

const fileInput = ref(null);
const selectedFileName = ref('');

const importForm = useForm({
    file: null,
    fresh: false,
});

const airportsCountLabel = computed(() => `${props.airports.total} ${t(props.airports.total === 1 ? 'total airport' : 'total airports')}`);

const importIsRunning = computed(() => ['queued', 'processing'].includes(props.importStatus?.status));

const importStatusLabel = computed(() => {
    const status = props.importStatus?.status;

    if (status === 'queued') {
        return t('Import queued');
    }

    if (status === 'processing') {
        return t('Import in progress');
    }

    if (status === 'completed') {
        return t('Import completed');
    }

    if (status === 'failed') {
        return t('Import failed');
    }

    return t('No import running');
});

let pollTimer = null;

const displayValue = (value) => {
    if (value === null || value === undefined || value === '') {
        return '-';
    }

    return value;
};

const featuredKeySet = computed(() => new Set(props.featuredAirportKeys));

const isAirportFeatured = (airport) => featuredKeySet.value.has(airport.airport_key ?? airport.id);

const featuredCount = computed(() => props.featuredAirportKeys.length);

const openAirportPage = (airport) => {
    const key = airport.airport_key ?? airport.id;

    if (!key) {
        return;
    }

    router.visit(route('admin.airports.edit', key));
};

const filterPayload = () => ({
    ...(filterForm.search.trim() ? { search: filterForm.search.trim() } : {}),
    ...(filterForm.country ? { country: filterForm.country } : {}),
    ...(filterForm.type ? { type: filterForm.type } : {}),
    ...(Number(filterForm.per_page) !== 20 ? { per_page: filterForm.per_page } : {}),
});

const applyFilters = () => {
    router.get(route('admin.airports.index'), filterPayload(), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const resetFilters = () => {
    filtersReady.value = false;
    filterForm.search = '';
    filterForm.country = '';
    filterForm.type = '';
    filterForm.per_page = 20;
    applyFilters();
    nextTick(() => {
        filtersReady.value = true;
    });
};

const formatType = (value) => {
    if (!value) {
        return '-';
    }

    return String(value).replaceAll('_', ' ');
};

const onFileChange = (event) => {
    const file = event.target.files?.[0] ?? null;
    importForm.file = file;
    selectedFileName.value = file?.name ?? '';
    importForm.clearErrors('file');
};

const submitImport = () => {
    importForm.post(route('admin.airports.import'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            importForm.reset();
            selectedFileName.value = '';

            if (fileInput.value) {
                fileInput.value.value = '';
            }
        },
    });
};

const pollImportStatus = () => {
    router.reload({
        only: ['importStatus', 'airports'],
        preserveScroll: true,
        preserveState: true,
    });
};

const startPolling = () => {
    if (pollTimer) {
        return;
    }

    pollTimer = window.setInterval(pollImportStatus, 4000);
};

const stopPolling = () => {
    if (!pollTimer) {
        return;
    }

    window.clearInterval(pollTimer);
    pollTimer = null;
};

watch(
    () => props.importStatus?.status,
    (status) => {
        if (['queued', 'processing'].includes(status)) {
            startPolling();
            return;
        }

        stopPolling();
    },
    { immediate: true },
);

watch(
    () => filterForm.search,
    () => {
        if (!filtersReady.value) {
            return;
        }

        clearTimeout(searchDebounceTimer);
        searchDebounceTimer = setTimeout(() => applyFilters(), 400);
    },
);

watch(
    () => [
        filterForm.country,
        filterForm.type,
        filterForm.per_page,
    ],
    () => {
        if (!filtersReady.value) {
            return;
        }

        applyFilters();
    },
);

onMounted(() => {
    filtersReady.value = true;

    if (importIsRunning.value) {
        startPolling();
    }
});

onUnmounted(() => {
    clearTimeout(searchDebounceTimer);
    stopPolling();
});
</script>

<template>
    <Head :title="t('Airports')" />

    <AdminLayout
        title="Airports"
        description="Manage airport directory records with the same admin workflow used across modules."
    >
        <section class="space-y-6">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.25em] text-cyan-700">
                            {{ t('Operations Data') }}
                        </p>
                        <h2 class="mt-2 text-2xl font-semibold text-slate-950">{{ t('Airports directory') }}</h2>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">
                            {{ t('Click any row to view and edit the full airport record.') }}
                        </p>
                    </div>

                    <div class="flex items-center gap-3">
                        <div class="rounded-2xl bg-slate-950 px-4 py-3 text-sm text-white">
                            {{ airportsCountLabel }}
                        </div>
                        <Link
                            :href="route('admin.airports.create')"
                            class="inline-flex items-center justify-center rounded-2xl bg-cyan-700 px-4 py-3 text-sm font-medium text-white transition hover:bg-cyan-600"
                        >
                            {{ t('Create airport') }}
                        </Link>
                    </div>
                </div>

                <form class="mt-6 space-y-4" @submit.prevent="applyFilters">
                    <div class="grid gap-4 md:grid-cols-[minmax(0,1fr)_minmax(12rem,16rem)_minmax(12rem,16rem)] md:items-end">
                        <label class="space-y-2 text-sm font-medium text-slate-700">
                            <span>{{ t('Search') }}</span>
                            <input
                                v-model="filterForm.search"
                                type="search"
                                class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-cyan-600"
                                :placeholder="t('Name, code, city, or country')"
                            >
                        </label>

                        <label v-if="country_options.length" class="space-y-2 text-sm font-medium text-slate-700">
                            <span>{{ t('Country') }}</span>
                            <select
                                v-model="filterForm.country"
                                class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-cyan-600"
                            >
                                <option value="">{{ t('All countries') }}</option>
                                <option v-for="country in country_options" :key="country.value" :value="country.value">
                                    {{ country.label }}
                                </option>
                            </select>
                        </label>

                        <label v-if="usesFullSchema && type_options.length" class="space-y-2 text-sm font-medium text-slate-700">
                            <span>{{ t('Type') }}</span>
                            <select
                                v-model="filterForm.type"
                                class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-cyan-600"
                            >
                                <option value="">{{ t('All types') }}</option>
                                <option v-for="type in type_options" :key="type" :value="type">
                                    {{ formatType(type) }}
                                </option>
                            </select>
                        </label>
                    </div>

                    <div class="flex flex-wrap items-end gap-3">
                        <label class="min-w-[7rem] space-y-2 text-sm font-medium text-slate-700">
                            <span>{{ t('Per page') }}</span>
                            <select
                                v-model.number="filterForm.per_page"
                                class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-cyan-600"
                            >
                                <option v-for="size in per_page_options" :key="size" :value="size">
                                    {{ size }}
                                </option>
                            </select>
                        </label>

                        <button
                            type="submit"
                            class="inline-flex items-center justify-center rounded-2xl bg-slate-950 px-4 py-3 text-sm font-medium text-white transition hover:bg-slate-800"
                        >
                            {{ t('Apply') }}
                        </button>

                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-2xl border border-slate-200 px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                            @click="resetFilters"
                        >
                            {{ t('Reset') }}
                        </button>
                    </div>
                </form>
            </div>

            <FeaturedAirportsPanel
                :initial-featured="featuredAirports"
                :max-featured="maxFeaturedAirports"
                :enabled="usesFullSchema"
                :search-url="route('admin.airports.featured.search')"
                :update-url="route('admin.airports.featured.update')"
            />

            <div
                v-if="usesFullSchema"
                class="rounded-3xl border border-cyan-200 bg-cyan-50/40 p-6 shadow-sm"
            >
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.25em] text-cyan-700">
                            {{ t('Bulk import') }}
                        </p>
                        <h3 class="mt-2 text-xl font-semibold text-slate-950">{{ t('Import airports from Excel') }}</h3>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">
                            {{ t('Upload an .xlsx or .csv file. Arabic and French fields are imported with UTF-8 support.') }}
                        </p>
                    </div>

                    <div
                        class="rounded-2xl border px-4 py-3 text-sm"
                        :class="importIsRunning ? 'border-amber-200 bg-amber-50 text-amber-800' : 'border-slate-200 bg-white text-slate-700'"
                    >
                        <p class="font-semibold">{{ importStatusLabel }}</p>
                        <p v-if="importStatus?.message" class="mt-1">{{ importStatus.message }}</p>
                        <p v-if="importStatus?.stats" class="mt-2 text-xs">
                            {{ t('Inserted') }}: {{ importStatus.stats.imported ?? 0 }} ·
                            {{ t('Updated') }}: {{ importStatus.stats.updated ?? 0 }} ·
                            {{ t('Skipped') }}: {{ importStatus.stats.skipped ?? 0 }}
                        </p>
                    </div>
                </div>

                <form class="mt-6 grid gap-4 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-end" @submit.prevent="submitImport">
                    <div class="space-y-4">
                        <label class="block space-y-2 text-sm font-medium text-slate-700">
                            <span>{{ t('Import file') }}</span>
                            <input
                                ref="fileInput"
                                type="file"
                                accept=".xlsx,.xls,.csv"
                                class="block w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 file:me-4 file:rounded-xl file:border-0 file:bg-slate-950 file:px-4 file:py-2 file:text-sm file:font-medium file:text-white"
                                :disabled="importIsRunning || importForm.processing"
                                @change="onFileChange"
                            >
                            <span v-if="selectedFileName" class="text-xs text-slate-500">{{ selectedFileName }}</span>
                            <p v-if="importForm.errors.file" class="text-sm text-rose-600">{{ importForm.errors.file }}</p>
                        </label>

                        <label class="flex items-center gap-3 text-sm text-slate-700">
                            <input
                                v-model="importForm.fresh"
                                type="checkbox"
                                class="rounded border-slate-300 text-cyan-700 focus:ring-cyan-600"
                                :disabled="importIsRunning || importForm.processing"
                            >
                            <span>{{ t('Replace existing airports before import') }}</span>
                        </label>
                    </div>

                    <button
                        type="submit"
                        class="inline-flex items-center justify-center rounded-2xl bg-cyan-700 px-5 py-3 text-sm font-medium text-white transition hover:bg-cyan-600 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="importIsRunning || importForm.processing || !importForm.file"
                    >
                        {{ importForm.processing ? t('Uploading...') : t('Start import') }}
                    </button>
                </form>
            </div>

            <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr class="text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">
                                <template v-if="usesFullSchema">
                                    <th class="w-14 px-4 py-4"></th>
                                    <th class="px-6 py-4">{{ t('IATA code') }}</th>
                                    <th class="px-6 py-4">{{ t('ICAO code') }}</th>
                                    <th class="px-6 py-4">{{ t('Name (English)') }}</th>
                                    <th class="px-6 py-4">{{ t('City (English)') }}</th>
                                    <th class="px-6 py-4">{{ t('Country') }}</th>
                                    <th class="px-6 py-4">{{ t('Type') }}</th>
                                </template>
                                <template v-else>
                                    <th class="px-6 py-4">{{ t('Name') }}</th>
                                    <th class="px-6 py-4">{{ t('Code') }}</th>
                                    <th class="px-6 py-4">{{ t('City') }}</th>
                                    <th class="px-6 py-4">{{ t('Country') }}</th>
                                </template>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm text-slate-700">
                            <tr
                                v-for="airport in airports.data"
                                :key="airport.id"
                                class="group cursor-pointer bg-white align-top transition duration-200 hover:bg-cyan-50/50"
                                role="link"
                                :aria-label="`${t('Open airport record')} ${usesFullSchema ? airport.name_en : airport.name}`"
                                tabindex="0"
                                @click="openAirportPage(airport)"
                                @keydown.enter.prevent="openAirportPage(airport)"
                                @keydown.space.prevent="openAirportPage(airport)"
                            >
                                <template v-if="usesFullSchema">
                                    <td class="px-4 py-5" @click.stop>
                                        <AirportFeaturedStar
                                            :airport-key="airport.airport_key ?? airport.id"
                                            :is-featured="isAirportFeatured(airport)"
                                            :featured-count="featuredCount"
                                            :max-featured="maxFeaturedAirports"
                                            size="sm"
                                        />
                                    </td>
                                    <td class="px-6 py-5 font-medium text-slate-900">{{ displayValue(airport.iata_code) }}</td>
                                    <td class="px-6 py-5">{{ displayValue(airport.icao_code) }}</td>
                                    <td class="px-6 py-5 font-medium text-slate-900">{{ displayValue(airport.name_en) }}</td>
                                    <td class="px-6 py-5">{{ displayValue(airport.city_en) }}</td>
                                    <td class="px-6 py-5">
                                        <div>{{ displayValue(airport.country_name_en) }}</div>
                                        <div v-if="airport.country_iso2" class="mt-1 text-xs uppercase tracking-[0.18em] text-slate-400">
                                            {{ airport.country_iso2 }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-5">{{ formatType(airport.type) }}</td>
                                </template>
                                <template v-else>
                                    <td class="px-6 py-5 font-medium text-slate-900">{{ airport.name }}</td>
                                    <td class="px-6 py-5">
                                        <span v-if="airport.code">{{ airport.code }}</span>
                                        <span v-else class="text-slate-400">-</span>
                                    </td>
                                    <td class="px-6 py-5">{{ airport.city || '-' }}</td>
                                    <td class="px-6 py-5">{{ airport.country || '-' }}</td>
                                </template>
                            </tr>

                            <tr v-if="airports.data.length === 0">
                                <td :colspan="usesFullSchema ? 7 : 4" class="px-6 py-12 text-center text-sm text-slate-500">
                                    {{ t('No airports matched the selected filters.') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex flex-col gap-4 border-t border-slate-200 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm text-slate-500">
                        {{ t('Showing') }} {{ airports.from ?? 0 }} {{ t('to') }} {{ airports.to ?? 0 }} {{ t('of') }} {{ airports.total }} {{ t('airports.') }}
                    </p>

                    <nav class="flex flex-wrap gap-2">
                        <component
                            :is="link.url ? Link : 'span'"
                            v-for="link in airports.links"
                            :key="`${link.label}-${link.url}`"
                            :href="link.url"
                            class="rounded-xl px-3 py-2 text-sm font-medium transition"
                            :class="link.active ? 'bg-slate-950 text-white' : 'border border-slate-200 text-slate-600 hover:bg-slate-50'"
                            v-html="paginationLabel(link.label)"
                        />
                    </nav>
                </div>
            </div>
        </section>
    </AdminLayout>
</template>
