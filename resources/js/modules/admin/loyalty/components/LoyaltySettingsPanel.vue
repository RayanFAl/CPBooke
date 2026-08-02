<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { useAdminLocale } from '../../composables/useAdminLocale';
import { usePlatformCurrency } from '../../composables/usePlatformCurrency';

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
    fetchImpl: {
        type: Function,
        default: (...args) => fetch(...args),
    },
});

const { t } = useAdminLocale();
const { defaultCurrency } = usePlatformCurrency();

const defaults = () => ({
    loyalty_enabled: true,
    auto_upgrade_enabled: true,
    auto_downgrade_enabled: false,
    visible_in_mobile_app: true,
    allow_discount_stacking: false,
    default_currency: defaultCurrency.value,
    max_global_discount_amount: '',
    minimum_discountable_order_amount: '',
    settings_version: 1,
    updated_at: null,
});

const normalizeSettings = (settings) => ({
    loyalty_enabled: Boolean(settings?.loyalty_enabled ?? true),
    auto_upgrade_enabled: Boolean(settings?.auto_upgrade_enabled ?? true),
    auto_downgrade_enabled: Boolean(settings?.auto_downgrade_enabled ?? false),
    visible_in_mobile_app: Boolean(settings?.visible_in_mobile_app ?? true),
    allow_discount_stacking: Boolean(settings?.allow_discount_stacking ?? false),
    default_currency: String(settings?.default_currency ?? defaultCurrency.value),
    max_global_discount_amount: settings?.max_global_discount_amount ?? '',
    minimum_discountable_order_amount: settings?.minimum_discountable_order_amount ?? '',
    settings_version: Number(settings?.settings_version ?? 1),
    updated_at: settings?.updated_at ?? null,
});

const form = reactive(defaults());
const isSubmitting = ref(false);
const successMessage = ref('');
const errorMessage = ref('');

watch(
    () => props.initialSettings,
    (settings) => {
        Object.assign(form, normalizeSettings(settings));
    },
    { immediate: true },
);

const hasSettings = computed(() => props.initialSettings !== null);
const updatedAtLabel = computed(() => form.updated_at || t('Not available'));

const csrfToken = () => {
    if (typeof document === 'undefined') {
        return '';
    }

    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
};

const toNullableNumber = (value) => {
    if (value === '' || value === null || value === undefined) {
        return null;
    }

    return Number(value);
};

const payload = () => ({
    loyalty_enabled: form.loyalty_enabled,
    auto_upgrade_enabled: form.auto_upgrade_enabled,
    auto_downgrade_enabled: form.auto_downgrade_enabled,
    visible_in_mobile_app: form.visible_in_mobile_app,
    allow_discount_stacking: form.allow_discount_stacking,
    default_currency: form.default_currency.trim().toUpperCase(),
    max_global_discount_amount: toNullableNumber(form.max_global_discount_amount),
    minimum_discountable_order_amount: toNullableNumber(form.minimum_discountable_order_amount),
});

const submit = async () => {
    if (!props.canManage || !props.updateUrl || isSubmitting.value) {
        return;
    }

    isSubmitting.value = true;
    successMessage.value = '';
    errorMessage.value = '';

    try {
        const response = await props.fetchImpl(props.updateUrl, {
            method: 'PUT',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(payload()),
        });

        const responsePayload = await response.json();

        if (!response.ok || responsePayload.success === false) {
            errorMessage.value = t('Unable to save loyalty settings right now.');

            return;
        }

        Object.assign(form, normalizeSettings(responsePayload.data));
        successMessage.value = t('Loyalty settings saved successfully.');
    } catch {
        errorMessage.value = t('Unable to save loyalty settings right now.');
    } finally {
        isSubmitting.value = false;
    }
};
</script>

<template>
    <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-slate-950">{{ t('Loyalty program settings') }}</h2>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">
                    {{ t('Use these settings to enable or disable the loyalty program and control the main discount rules used by loyalty pricing.') }}
                </p>
            </div>

            <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                <p class="font-medium text-slate-900">{{ t('Version') }} {{ form.settings_version }}</p>
                <p class="mt-1 text-xs uppercase tracking-[0.16em] text-slate-500">{{ t('Updated at') }} · {{ updatedAtLabel }}</p>
            </div>
        </div>

        <div v-if="!hasSettings" class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            {{ t('Loyalty settings are not available yet.') }}
        </div>

        <div v-else>
            <div v-if="!canManage" class="mt-4 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                {{ t('You can review these settings, but only Super Admin can change them.') }}
            </div>

            <form class="mt-4 space-y-4" @submit.prevent="submit">
                <div class="grid gap-4 lg:grid-cols-2">
                    <label class="text-sm text-slate-600">
                        <span class="block font-medium text-slate-700">{{ t('Default currency') }}</span>
                        <input
                            v-model="form.default_currency"
                            data-testid="default-currency"
                            type="text"
                            maxlength="3"
                            :disabled="!canManage || isSubmitting"
                            class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 uppercase disabled:bg-slate-100"
                        />
                    </label>

                    <label class="text-sm text-slate-600">
                        <span class="block font-medium text-slate-700">{{ t('Maximum global discount amount') }}</span>
                        <span class="mt-1 block text-xs text-slate-500">{{ t('This is the highest loyalty discount amount allowed across the program.') }}</span>
                        <input
                            v-model="form.max_global_discount_amount"
                            data-testid="max-global-discount"
                            type="number"
                            min="0"
                            step="0.01"
                            :disabled="!canManage || isSubmitting"
                            class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 disabled:bg-slate-100"
                        />
                    </label>

                    <label class="text-sm text-slate-600 lg:col-span-2">
                        <span class="block font-medium text-slate-700">{{ t('Minimum discountable order amount') }}</span>
                        <span class="mt-1 block text-xs text-slate-500">{{ t('Orders below this amount will not receive loyalty discounts.') }}</span>
                        <input
                            v-model="form.minimum_discountable_order_amount"
                            data-testid="minimum-order-amount"
                            type="number"
                            min="0"
                            step="0.01"
                            :disabled="!canManage || isSubmitting"
                            class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 disabled:bg-slate-100"
                        />
                    </label>
                </div>

                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    <label class="flex items-center gap-3 rounded-lg border border-slate-200 px-4 py-3 text-sm text-slate-700">
                        <input v-model="form.loyalty_enabled" data-testid="loyalty-enabled" type="checkbox" :disabled="!canManage || isSubmitting" class="rounded border-slate-300 text-slate-950 focus:ring-slate-400" />
                        <span>{{ t('Loyalty enabled') }}</span>
                    </label>

                    <label class="flex items-center gap-3 rounded-lg border border-slate-200 px-4 py-3 text-sm text-slate-700">
                        <input v-model="form.auto_upgrade_enabled" data-testid="auto-upgrade-enabled" type="checkbox" :disabled="!canManage || isSubmitting" class="rounded border-slate-300 text-slate-950 focus:ring-slate-400" />
                        <span>{{ t('Auto upgrade enabled') }}</span>
                    </label>

                    <label class="flex items-center gap-3 rounded-lg border border-slate-200 px-4 py-3 text-sm text-slate-700">
                        <input v-model="form.auto_downgrade_enabled" data-testid="auto-downgrade-enabled" type="checkbox" :disabled="!canManage || isSubmitting" class="rounded border-slate-300 text-slate-950 focus:ring-slate-400" />
                        <span>{{ t('Auto downgrade enabled') }}</span>
                    </label>

                    <label class="flex items-center gap-3 rounded-lg border border-slate-200 px-4 py-3 text-sm text-slate-700">
                        <input v-model="form.visible_in_mobile_app" data-testid="visible-in-mobile-app" type="checkbox" :disabled="!canManage || isSubmitting" class="rounded border-slate-300 text-slate-950 focus:ring-slate-400" />
                        <span>{{ t('Visible in mobile app') }}</span>
                    </label>

                    <label class="flex items-center gap-3 rounded-lg border border-slate-200 px-4 py-3 text-sm text-slate-700 md:col-span-2 xl:col-span-1">
                        <input v-model="form.allow_discount_stacking" data-testid="allow-discount-stacking" type="checkbox" :disabled="!canManage || isSubmitting" class="rounded border-slate-300 text-slate-950 focus:ring-slate-400" />
                        <span>{{ t('Allow discount stacking') }}</span>
                    </label>
                </div>

                <div v-if="successMessage || errorMessage" class="space-y-3">
                    <div v-if="successMessage" data-testid="success-message" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                        {{ successMessage }}
                    </div>
                    <div v-if="errorMessage" data-testid="error-message" class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800">
                        {{ errorMessage }}
                    </div>
                </div>

                <div v-if="canManage" class="flex justify-end">
                    <button
                        data-testid="save-settings"
                        type="submit"
                        :disabled="isSubmitting"
                        class="inline-flex items-center justify-center rounded-lg bg-slate-950 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:bg-slate-400"
                    >
                        {{ isSubmitting ? t('Saving...') : t('Save settings') }}
                    </button>
                </div>
            </form>
        </div>
    </section>
</template>