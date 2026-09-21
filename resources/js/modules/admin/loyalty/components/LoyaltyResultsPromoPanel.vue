<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { useAdminLocale } from '../../composables/useAdminLocale';

const props = defineProps({
    initialSettings: {
        type: Object,
        default: null,
    },
    canManage: {
        type: Boolean,
        default: false,
    },
    updateUrl: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['saved']);

const { t } = useAdminLocale();

const defaultPromo = () => ({
    enabled: true,
    require_active_level: true,
    title_ar: 'استمتع برحلتك أكثر',
    title_en: 'Enjoy your trip more',
    body_ar: 'يمكنك توفير {percent}% لأنك في {level}',
    body_en: 'You can save {percent}% because you are {level}',
    cta_ar: 'اعرض المزايا',
    cta_en: 'View benefits',
    action_type: 'route',
    action_value: '/loyalty',
    flights: {
        body_ar: 'يمكنك توفير {percent}% على الرحلات لأنك في {level}',
        body_en: 'You can save {percent}% on flights because you are {level}',
    },
    hotels: {
        body_ar: 'يمكنك توفير {percent}% على الفنادق لأنك في {level}',
        body_en: 'You can save {percent}% on hotels because you are {level}',
    },
});

const normalizePromo = (promo) => {
    const base = defaultPromo();
    const source = promo && typeof promo === 'object' ? promo : {};

    return {
        enabled: Boolean(source.enabled ?? base.enabled),
        require_active_level: Boolean(source.require_active_level ?? base.require_active_level),
        title_ar: String(source.title_ar ?? base.title_ar),
        title_en: String(source.title_en ?? base.title_en),
        body_ar: String(source.body_ar ?? base.body_ar),
        body_en: String(source.body_en ?? base.body_en),
        cta_ar: String(source.cta_ar ?? base.cta_ar),
        cta_en: String(source.cta_en ?? base.cta_en),
        action_type: String(source.action_type ?? base.action_type),
        action_value: String(source.action_value ?? base.action_value),
        flights: {
            body_ar: String(source.flights?.body_ar ?? base.flights.body_ar),
            body_en: String(source.flights?.body_en ?? base.flights.body_en),
        },
        hotels: {
            body_ar: String(source.hotels?.body_ar ?? base.hotels.body_ar),
            body_en: String(source.hotels?.body_en ?? base.hotels.body_en),
        },
    };
};

const form = reactive(normalizePromo(props.initialSettings?.results_promo));
const isSubmitting = ref(false);
const errorMessage = ref('');
const successMessage = ref('');
const showMore = ref(false);

watch(
    () => props.initialSettings?.results_promo,
    (promo) => {
        Object.assign(form, normalizePromo(promo));
    },
    { deep: true },
);

const needsActionValue = computed(() => ['route', 'url'].includes(form.action_type));

const actionTypes = computed(() => ([
    { value: 'route', label: t('App route') },
    { value: 'url', label: t('External URL') },
    { value: 'search_flights', label: t('Search flights') },
    { value: 'search_hotels', label: t('Search hotels') },
    { value: 'search_insurance', label: t('Search insurance') },
    { value: 'search_esim', label: t('Search eSIM') },
]));

const inputClass = 'w-full rounded-md border border-slate-200 bg-white px-2.5 py-1.5 text-sm text-slate-900 outline-none focus:border-slate-400 disabled:opacity-60';

const csrfToken = () => {
    if (typeof document === 'undefined') {
        return '';
    }

    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
};

const resetForm = () => {
    Object.assign(form, normalizePromo(props.initialSettings?.results_promo));
    errorMessage.value = '';
    successMessage.value = '';
};

const submit = async () => {
    if (!props.canManage || !props.updateUrl || isSubmitting.value) {
        return;
    }

    isSubmitting.value = true;
    errorMessage.value = '';
    successMessage.value = '';
    const current = props.initialSettings ?? {};

    try {
        const response = await fetch(props.updateUrl, {
            method: 'PUT',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                loyalty_enabled: Boolean(current.loyalty_enabled ?? true),
                auto_upgrade_enabled: Boolean(current.auto_upgrade_enabled ?? true),
                auto_downgrade_enabled: Boolean(current.auto_downgrade_enabled ?? false),
                visible_in_mobile_app: Boolean(current.visible_in_mobile_app ?? true),
                allow_discount_stacking: Boolean(current.allow_discount_stacking ?? false),
                default_currency: String(current.default_currency ?? 'LYD'),
                max_global_discount_amount: current.max_global_discount_amount ?? null,
                minimum_discountable_order_amount: current.minimum_discountable_order_amount ?? null,
                results_promo: {
                    enabled: Boolean(form.enabled),
                    require_active_level: Boolean(form.require_active_level),
                    title_ar: form.title_ar,
                    title_en: form.title_en,
                    body_ar: form.body_ar,
                    body_en: form.body_en,
                    cta_ar: form.cta_ar,
                    cta_en: form.cta_en,
                    action_type: form.action_type,
                    action_value: form.action_value,
                    flights: {
                        body_ar: form.flights.body_ar,
                        body_en: form.flights.body_en,
                    },
                    hotels: {
                        body_ar: form.hotels.body_ar,
                        body_en: form.hotels.body_en,
                    },
                },
            }),
        });

        const payload = await response.json();

        if (!response.ok || payload.success === false) {
            errorMessage.value = t('Unable to save loyalty settings right now.');

            return;
        }

        emit('saved', payload.data);
        successMessage.value = t('Promo card saved.');
    } catch {
        errorMessage.value = t('Unable to save loyalty settings right now.');
    } finally {
        isSubmitting.value = false;
    }
};
</script>

<template>
    <div class="max-w-xl overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-3">
            <div>
                <h3 class="text-sm font-semibold text-slate-950">{{ t('Promo card') }}</h3>
                <p class="mt-0.5 text-xs text-slate-500">
                    {{ t('Use {percent} and {level} in the text.') }}
                </p>
            </div>
            <label class="inline-flex items-center gap-2 text-xs text-slate-600">
                <span>{{ form.enabled ? t('On') : t('Off') }}</span>
                <input v-model="form.enabled" type="checkbox" class="h-4 w-4" :disabled="!canManage">
            </label>
        </div>

        <div class="space-y-3 px-4 py-4">
            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                <label class="block text-xs">
                    <span class="mb-0.5 block text-slate-500">{{ t('Title') }} AR</span>
                    <input v-model="form.title_ar" type="text" :class="inputClass" :disabled="!canManage">
                </label>
                <label class="block text-xs">
                    <span class="mb-0.5 block text-slate-500">{{ t('Title') }} EN</span>
                    <input v-model="form.title_en" type="text" :class="inputClass" :disabled="!canManage">
                </label>
            </div>

            <label class="block text-xs">
                <span class="mb-0.5 block text-slate-500">{{ t('Body') }} AR</span>
                <input v-model="form.body_ar" type="text" :class="inputClass" :disabled="!canManage">
            </label>
            <label class="block text-xs">
                <span class="mb-0.5 block text-slate-500">{{ t('Body') }} EN</span>
                <input v-model="form.body_en" type="text" :class="inputClass" :disabled="!canManage">
            </label>

            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                <label class="block text-xs">
                    <span class="mb-0.5 block text-slate-500">{{ t('CTA') }} AR</span>
                    <input v-model="form.cta_ar" type="text" :class="inputClass" :disabled="!canManage">
                </label>
                <label class="block text-xs">
                    <span class="mb-0.5 block text-slate-500">{{ t('CTA') }} EN</span>
                    <input v-model="form.cta_en" type="text" :class="inputClass" :disabled="!canManage">
                </label>
            </div>

            <label class="block text-xs">
                <span class="mb-0.5 block text-slate-500">{{ t('Button opens') }}</span>
                <input
                    v-model="form.action_value"
                    type="text"
                    :class="inputClass"
                    :disabled="!canManage"
                    placeholder="/loyalty"
                >
            </label>

            <button
                type="button"
                class="text-xs font-medium text-slate-500 transition hover:text-slate-800"
                @click="showMore = !showMore"
            >
                {{ showMore ? t('Hide more') : t('More options') }}
            </button>

            <div v-if="showMore" class="space-y-2 rounded-lg border border-slate-100 bg-slate-50 p-3">
                <label class="flex items-center justify-between gap-2 text-xs text-slate-600">
                    <span>{{ t('Require active level') }}</span>
                    <input v-model="form.require_active_level" type="checkbox" class="h-3.5 w-3.5" :disabled="!canManage">
                </label>
                <label class="block text-xs">
                    <span class="mb-0.5 block text-slate-500">{{ t('Action type') }}</span>
                    <select v-model="form.action_type" :class="inputClass" :disabled="!canManage">
                        <option v-for="option in actionTypes" :key="option.value" :value="option.value">
                            {{ option.label }}
                        </option>
                    </select>
                </label>
                <label v-if="needsActionValue" class="block text-xs">
                    <span class="mb-0.5 block text-slate-500">{{ t('Action value') }}</span>
                    <input v-model="form.action_value" type="text" :class="inputClass" :disabled="!canManage">
                </label>
                <label class="block text-xs">
                    <span class="mb-0.5 block text-slate-500">{{ t('Flights body') }} AR</span>
                    <input v-model="form.flights.body_ar" type="text" :class="inputClass" :disabled="!canManage">
                </label>
                <label class="block text-xs">
                    <span class="mb-0.5 block text-slate-500">{{ t('Flights body') }} EN</span>
                    <input v-model="form.flights.body_en" type="text" :class="inputClass" :disabled="!canManage">
                </label>
                <label class="block text-xs">
                    <span class="mb-0.5 block text-slate-500">{{ t('Hotels body') }} AR</span>
                    <input v-model="form.hotels.body_ar" type="text" :class="inputClass" :disabled="!canManage">
                </label>
                <label class="block text-xs">
                    <span class="mb-0.5 block text-slate-500">{{ t('Hotels body') }} EN</span>
                    <input v-model="form.hotels.body_en" type="text" :class="inputClass" :disabled="!canManage">
                </label>
            </div>

            <p v-if="errorMessage" class="text-xs text-rose-600">{{ errorMessage }}</p>
            <p v-else-if="successMessage" class="text-xs text-emerald-600">{{ successMessage }}</p>

            <div v-if="canManage" class="flex items-center justify-end gap-2 pt-1">
                <button
                    type="button"
                    class="rounded-md border border-slate-200 px-3 py-1.5 text-sm text-slate-700 transition hover:bg-slate-50"
                    @click="resetForm"
                >
                    {{ t('Cancel') }}
                </button>
                <button
                    type="button"
                    class="rounded-md bg-slate-950 px-3 py-1.5 text-sm font-medium text-white transition hover:bg-slate-800 disabled:opacity-60"
                    :disabled="isSubmitting"
                    @click="submit"
                >
                    {{ isSubmitting ? t('Saving...') : t('Save') }}
                </button>
            </div>
        </div>
    </div>
</template>
