<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useAdminLocale } from '../../composables/useAdminLocale';

const props = defineProps({
    initialMatrix: {
        type: Object,
        default: () => ({
            tiers: [],
            companies: [],
            rates: [],
            airlines: { count: 0, source: null, error: null },
        }),
    },
    syncUrl: {
        type: String,
        default: '',
    },
    showUrl: {
        type: String,
        default: '',
    },
    canManage: {
        type: Boolean,
        default: false,
    },
    /** When set, edit rates for this level only. */
    tierId: {
        type: [Number, String],
        default: null,
    },
    compact: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['saved']);

const { t } = useAdminLocale();

const safeClone = (value) => {
    if (value == null) {
        return {
            tiers: [],
            companies: [],
            rates: [],
            airlines: { count: 0, source: null, error: null },
        };
    }

    try {
        return JSON.parse(JSON.stringify(value));
    } catch {
        return {
            tiers: Array.isArray(value.tiers) ? [...value.tiers] : [],
            companies: Array.isArray(value.companies) ? [...value.companies] : [],
            rates: Array.isArray(value.rates) ? [...value.rates] : [],
            airlines: value.airlines ?? { count: 0, source: null, error: null },
        };
    }
};

const matrix = ref(safeClone(props.initialMatrix));
const draft = reactive({});
const isSaving = ref(false);
const isRefreshing = ref(false);
const statusMessage = ref('');
const errorMessage = ref('');
const activeService = ref('flight');

const serviceTabs = computed(() => [
    { id: 'flight', label: t('Flights') },
    { id: 'hotel', label: t('Hotels') },
    { id: 'esim', label: t('eSIM') },
    { id: 'insurance', label: t('Insurance') },
]);

const csrfToken = () => {
    if (typeof document === 'undefined') {
        return '';
    }

    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
};

const rateKey = (serviceType, companyKey, tierId) => `${serviceType}::${companyKey}::${tierId}`;

const rebuildDraft = (nextMatrix) => {
    Object.keys(draft).forEach((key) => {
        delete draft[key];
    });

    for (const rate of nextMatrix.rates ?? []) {
        const key = rateKey(rate.service_type, rate.company_key, rate.tier_id);
        draft[key] = {
            discount_percentage: rate.discount_percentage ?? '',
            is_active: Boolean(rate.is_active),
            company_name: rate.company_name ?? '',
        };
    }

    for (const company of nextMatrix.companies ?? []) {
        for (const tier of nextMatrix.tiers ?? []) {
            const key = rateKey(company.service_type, company.company_key, tier.id);
            if (!draft[key]) {
                draft[key] = {
                    discount_percentage: tier.default_discount_percentage ?? '',
                    is_active: true,
                    company_name: company.company_name,
                };
            }
        }
    }
};

watch(
    () => props.initialMatrix,
    (value) => {
        matrix.value = safeClone(value);
        rebuildDraft(matrix.value);
    },
    { immediate: true, deep: true },
);

const focusedTierId = computed(() => (props.tierId == null || props.tierId === '' ? null : Number(props.tierId)));

const tiers = computed(() => {
    const all = matrix.value.tiers ?? [];

    if (focusedTierId.value == null) {
        return all;
    }

    return all.filter((tier) => Number(tier.id) === focusedTierId.value);
});

const activeTier = computed(() => tiers.value[0] ?? null);

const airlinesMeta = computed(() => matrix.value.airlines ?? { count: 0, source: null, error: null });

const companies = computed(() => (matrix.value.companies ?? [])
    .filter((company) => company.service_type === activeService.value));

const companyCountByService = computed(() => {
    const counts = { flight: 0, hotel: 0, esim: 0, insurance: 0 };

    for (const company of matrix.value.companies ?? []) {
        if (Object.prototype.hasOwnProperty.call(counts, company.service_type)) {
            counts[company.service_type] += 1;
        }
    }

    return counts;
});

const serviceTabClass = (tabId) => (activeService.value === tabId
    ? 'bg-slate-950 text-white'
    : 'bg-slate-100 text-slate-600 hover:bg-slate-200');

const cell = (company, tierId) => {
    const key = rateKey(company.service_type, company.company_key, tierId);
    if (!draft[key]) {
        draft[key] = {
            discount_percentage: '',
            is_active: true,
            company_name: company.company_name,
        };
    }

    return draft[key];
};

const applyMatrix = (payload) => {
    matrix.value = safeClone(payload);
    rebuildDraft(matrix.value);
};

const refreshAirlines = async () => {
    if (isRefreshing.value || !props.showUrl) {
        return;
    }

    isRefreshing.value = true;
    errorMessage.value = '';
    statusMessage.value = '';

    try {
        const url = new URL(props.showUrl, window.location.origin);
        url.searchParams.set('refresh_airlines', '1');

        const response = await fetch(url.toString(), {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        const payload = await response.json();

        if (!response.ok || payload.success === false) {
            errorMessage.value = t('Unable to refresh airlines right now.');
            return;
        }

        applyMatrix(payload.data);
        statusMessage.value = t('Airlines list refreshed.');
        activeService.value = 'flight';
        emit('saved', payload.data);
    } catch {
        errorMessage.value = t('Unable to refresh airlines right now.');
    } finally {
        isRefreshing.value = false;
    }
};

const saveRates = async () => {
    if (!props.canManage || isSaving.value || !props.syncUrl) {
        return;
    }

    isSaving.value = true;
    errorMessage.value = '';
    statusMessage.value = '';

    const rates = [];
    const tiersToSave = tiers.value;

    for (const company of matrix.value.companies ?? []) {
        for (const tier of tiersToSave) {
            const key = rateKey(company.service_type, company.company_key, tier.id);
            const value = draft[key] ?? {
                discount_percentage: '',
                is_active: true,
                company_name: company.company_name,
            };

            rates.push({
                tier_id: tier.id,
                service_type: company.service_type,
                company_key: company.company_key,
                company_name: value.company_name || company.company_name,
                discount_percentage: value.discount_percentage === '' ? null : Number(value.discount_percentage),
                is_active: Boolean(value.is_active),
            });
        }
    }

    try {
        const response = await fetch(props.syncUrl, {
            method: 'PUT',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ rates }),
        });

        const payload = await response.json();

        if (!response.ok || payload.success === false) {
            errorMessage.value = t('Unable to save discount rates right now.');
            return;
        }

        applyMatrix(payload.data);
        statusMessage.value = t('Discount rates saved.');
        emit('saved', payload.data);
    } catch {
        errorMessage.value = t('Unable to save discount rates right now.');
    } finally {
        isSaving.value = false;
    }
};

onMounted(async () => {
    const hasCompanies = (matrix.value.companies ?? []).length > 0;
    const hasTiers = (matrix.value.tiers ?? []).length > 0;

    if ((!hasCompanies || !hasTiers) && props.showUrl) {
        await refreshAirlines();
    }
});

defineExpose({ saveRates, isSaving });
</script>

<template>
    <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-5 py-4">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h3 class="text-sm font-semibold text-slate-950">
                        {{ focusedTierId ? t('Rates for this level') : t('Discount rates') }}
                    </h3>
                    <p class="mt-1 text-sm text-slate-500">
                        <template v-if="focusedTierId && activeTier">
                            {{ activeTier.name }} — {{ t('Pick a service and set the %.') }}
                        </template>
                        <template v-else>
                            {{ t('Pick a service, then set the % for each level.') }}
                        </template>
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <button
                        v-if="activeService === 'flight'"
                        type="button"
                        class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:opacity-60"
                        :disabled="isRefreshing"
                        @click="refreshAirlines"
                    >
                        {{ isRefreshing ? t('Refreshing...') : t('Refresh airlines') }}
                    </button>

                    <button
                        v-if="canManage"
                        type="button"
                        class="rounded-lg bg-slate-950 px-3 py-2 text-sm font-medium text-white transition hover:bg-slate-800 disabled:opacity-60"
                        :disabled="isSaving"
                        @click="saveRates"
                    >
                        {{ isSaving ? t('Saving...') : t('Save rates') }}
                    </button>
                </div>
            </div>

            <nav class="mt-4 flex flex-wrap gap-2">
                <button
                    v-for="tab in serviceTabs"
                    :key="tab.id"
                    type="button"
                    class="inline-flex items-center gap-2 rounded-full px-3.5 py-2 text-sm font-medium transition"
                    :class="serviceTabClass(tab.id)"
                    @click="activeService = tab.id"
                >
                    <span>{{ tab.label }}</span>
                    <span
                        class="rounded-full px-1.5 py-0.5 text-[11px] font-semibold"
                        :class="activeService === tab.id ? 'bg-white/15 text-white' : 'bg-slate-100 text-slate-500'"
                    >
                        {{ companyCountByService[tab.id] || 0 }}
                    </span>
                </button>
            </nav>
        </div>

        <div class="space-y-4 p-5">
            <p v-if="airlinesMeta.error && activeService === 'flight'" class="text-sm text-amber-700">{{ airlinesMeta.error }}</p>
            <p v-if="statusMessage" class="text-sm text-emerald-700">{{ statusMessage }}</p>
            <p v-if="errorMessage" class="text-sm text-rose-600">{{ errorMessage }}</p>

            <div v-if="tiers.length === 0" class="rounded-lg bg-slate-50 px-4 py-4 text-sm text-slate-600">
                {{ t('No loyalty tiers have been configured yet.') }}
            </div>

            <div v-else-if="companies.length === 0" class="rounded-lg bg-slate-50 px-4 py-4 text-sm text-slate-600">
                <template v-if="activeService === 'flight'">
                    {{ t('No airlines found yet. Click Refresh airlines.') }}
                </template>
                <template v-else>
                    {{ t('No company configured for this service yet.') }}
                </template>
            </div>

            <!-- Single-company service for one level -->
            <div
                v-else-if="activeService !== 'flight' && focusedTierId && activeTier"
                class="max-w-sm"
            >
                <div
                    v-for="company in companies"
                    :key="`${company.service_type}-${company.company_key}`"
                    class="rounded-xl border border-slate-200 bg-slate-50/60 p-4"
                >
                    <p class="text-sm font-medium text-slate-950">{{ company.company_name }}</p>
                    <div class="mt-3 flex items-center gap-2">
                        <input
                            v-model="cell(company, activeTier.id).discount_percentage"
                            type="number"
                            min="0"
                            max="100"
                            step="0.01"
                            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none focus:border-slate-400"
                            :disabled="!canManage"
                        >
                        <span class="text-sm text-slate-500">%</span>
                    </div>
                </div>
            </div>

            <!-- Single-company services across levels (fallback) -->
            <div
                v-else-if="activeService !== 'flight'"
                class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4"
            >
                <template v-for="company in companies" :key="`${company.service_type}-${company.company_key}`">
                    <label
                        v-for="tier in tiers"
                        :key="`${company.company_key}-${tier.id}`"
                        class="rounded-xl border border-slate-200 bg-slate-50/50 p-4"
                    >
                        <span class="block text-xs font-medium text-slate-500">{{ tier.name }}</span>
                        <div class="mt-2 flex items-center gap-2">
                            <input
                                v-model="cell(company, tier.id).discount_percentage"
                                type="number"
                                min="0"
                                max="100"
                                step="0.01"
                                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none focus:border-slate-400"
                                :disabled="!canManage"
                            >
                            <span class="text-sm text-slate-500">%</span>
                        </div>
                    </label>
                </template>
            </div>

            <!-- Flights for one level -->
            <div v-else-if="focusedTierId && activeTier" class="overflow-x-auto rounded-xl border border-slate-200">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3 font-medium">{{ t('Airline') }}</th>
                            <th class="px-4 py-3 font-medium">{{ t('Discount') }} %</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="company in companies"
                            :key="company.company_key"
                            class="border-t border-slate-100"
                        >
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <img
                                        v-if="company.logo_url"
                                        :src="company.logo_url"
                                        :alt="company.company_name"
                                        class="h-8 w-8 rounded-md border border-slate-200 bg-white object-contain p-0.5"
                                        loading="lazy"
                                        @error="($event) => { $event.target.style.display = 'none' }"
                                    >
                                    <div>
                                        <div class="font-medium text-slate-950">{{ company.company_name }}</div>
                                        <div class="text-xs text-slate-400">{{ company.company_key }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex max-w-36 items-center gap-2">
                                    <input
                                        v-model="cell(company, activeTier.id).discount_percentage"
                                        type="number"
                                        min="0"
                                        max="100"
                                        step="0.01"
                                        class="w-24 rounded-lg border border-slate-200 px-2 py-1.5 text-sm outline-none focus:border-slate-400"
                                        :disabled="!canManage"
                                    >
                                    <span class="text-xs text-slate-400">%</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Flights across levels (fallback) -->
            <div v-else class="overflow-x-auto rounded-xl border border-slate-200">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3 font-medium">{{ t('Airline') }}</th>
                            <th
                                v-for="tier in tiers"
                                :key="tier.id"
                                class="px-4 py-3 font-medium"
                            >
                                {{ tier.name }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="company in companies"
                            :key="company.company_key"
                            class="border-t border-slate-100"
                        >
                            <td class="px-4 py-3 font-medium text-slate-950">{{ company.company_name }}</td>
                            <td
                                v-for="tier in tiers"
                                :key="`${company.company_key}-${tier.id}`"
                                class="px-4 py-3"
                            >
                                <input
                                    v-model="cell(company, tier.id).discount_percentage"
                                    type="number"
                                    min="0"
                                    max="100"
                                    step="0.01"
                                    class="w-20 rounded-lg border border-slate-200 px-2 py-1.5 text-sm outline-none focus:border-slate-400"
                                    :disabled="!canManage"
                                >
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </article>
</template>
