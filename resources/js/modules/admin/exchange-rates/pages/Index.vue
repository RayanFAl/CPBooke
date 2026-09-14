<script setup>
import AdminLayout from '../../layouts/AdminLayout.vue';
import { useAdminLocale } from '../../composables/useAdminLocale';
import { useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    rates: { type: Array, default: () => [] },
    form: { type: Object, required: true },
    base_currency: { type: String, default: 'LYD' },
    update_url: { type: String, required: true },
});

const { t } = useAdminLocale();
const page = usePage();

const flashSuccess = computed(() => page.props.flash?.success ?? '');
const canManage = computed(() => {
    const permissions = page.props.auth?.user?.permissions ?? [];

    return permissions.includes('exchange-rates.manage');
});

const rateForm = useForm({
    usd_rate_to_lyd: props.form.usd_rate_to_lyd ?? '',
    eur_rate_to_lyd: props.form.eur_rate_to_lyd ?? '',
});

const usdRate = computed(() => {
    const value = Number(rateForm.usd_rate_to_lyd);

    return Number.isFinite(value) && value > 0 ? value : null;
});

const eurRate = computed(() => {
    const value = Number(rateForm.eur_rate_to_lyd);

    return Number.isFinite(value) && value > 0 ? value : null;
});

const formatAmount = (value) => {
    if (value === null || !Number.isFinite(value)) {
        return '—';
    }

    return value.toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 4,
    });
};

const exampleUsdToLyd = computed(() => (usdRate.value === null ? null : 100 * usdRate.value));
const exampleEurToLyd = computed(() => (eurRate.value === null ? null : 100 * eurRate.value));
const exampleEurToUsd = computed(() => {
    if (usdRate.value === null || eurRate.value === null) {
        return null;
    }

    return (100 * eurRate.value) / usdRate.value;
});
const exampleUsdToEur = computed(() => {
    if (usdRate.value === null || eurRate.value === null) {
        return null;
    }

    return (100 * usdRate.value) / eurRate.value;
});

const submit = () => {
    if (!canManage.value) {
        return;
    }

    rateForm.transform((data) => ({
        usd_rate_to_lyd: data.usd_rate_to_lyd === '' ? null : Number(data.usd_rate_to_lyd),
        eur_rate_to_lyd: data.eur_rate_to_lyd === '' ? null : Number(data.eur_rate_to_lyd),
    })).put(props.update_url, {
        preserveScroll: true,
    });
};
</script>

<template>
    <AdminLayout
        :title="t('Exchange Rates')"
        :description="t('Enter how many Libyan Dinars equal 1 US Dollar and 1 Euro.')"
    >
        <section class="space-y-6">
            <!-- What is this page? -->
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.25em] text-cyan-700">{{ t('Settings') }}</p>
                <h2 class="mt-3 text-2xl font-semibold text-slate-950">{{ t('Exchange Rates') }}</h2>
                <p class="mt-3 max-w-3xl text-base leading-7 text-slate-700">
                    {{ t('This page has one job only: tell the system the price of USD and EUR in Libyan Dinars (LYD).') }}
                </p>

                <div class="mt-5 grid gap-3 md:grid-cols-3">
                    <div class="rounded-2xl bg-emerald-50 px-4 py-3 text-sm leading-6 text-emerald-900">
                        <p class="font-semibold">{{ t('1) Base currency') }}</p>
                        <p class="mt-1">{{ t('LYD (Libyan Dinar) is always 1. You never change it.') }}</p>
                    </div>
                    <div class="rounded-2xl bg-sky-50 px-4 py-3 text-sm leading-6 text-sky-900">
                        <p class="font-semibold">{{ t('2) What you edit') }}</p>
                        <p class="mt-1">{{ t('Only two numbers: how many LYD for 1 USD, and how many LYD for 1 EUR.') }}</p>
                    </div>
                    <div class="rounded-2xl bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-950">
                        <p class="font-semibold">{{ t('3) Auto conversion') }}</p>
                        <p class="mt-1">{{ t('EUR ↔ USD is calculated automatically through LYD. You do not enter a separate EUR/USD rate.') }}</p>
                    </div>
                </div>

                <p v-if="flashSuccess" class="mt-4 rounded-2xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {{ flashSuccess }}
                </p>
            </div>

            <!-- Edit form first (most important action) -->
            <form
                v-if="canManage"
                class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm"
                @submit.prevent="submit"
            >
                <h3 class="text-lg font-semibold text-slate-950">{{ t('Set the rates here') }}</h3>
                <p class="mt-1 text-sm text-slate-600">
                    {{ t('Type the LYD value for each currency, then press Save.') }}
                </p>

                <div class="mt-6 grid gap-5 lg:grid-cols-2">
                    <div class="rounded-3xl border border-sky-100 bg-sky-50/60 p-5">
                        <p class="text-sm font-semibold text-sky-900">{{ t('US Dollar (USD)') }}</p>
                        <p class="mt-1 text-sm text-sky-800">{{ t('How many Libyan Dinars is 1 US Dollar worth?') }}</p>

                        <label class="mt-4 block text-sm">
                            <span class="mb-2 flex flex-wrap items-center gap-2 font-medium text-slate-800">
                                <span class="rounded-full bg-white px-3 py-1 text-slate-900 shadow-sm">1 USD</span>
                                <span>=</span>
                                <span class="text-slate-600">{{ t('LYD amount') }}</span>
                            </span>
                            <div class="flex items-center gap-2">
                                <input
                                    v-model="rateForm.usd_rate_to_lyd"
                                    type="number"
                                    min="0"
                                    step="0.00000001"
                                    class="w-full rounded-2xl border-slate-200 text-lg"
                                    :disabled="rateForm.processing"
                                    placeholder="9.385"
                                />
                                <span class="shrink-0 text-sm font-semibold text-slate-700">LYD</span>
                            </div>
                            <p v-if="rateForm.errors.usd_rate_to_lyd" class="mt-2 text-xs text-rose-600">
                                {{ rateForm.errors.usd_rate_to_lyd }}
                            </p>
                            <p v-else class="mt-2 text-xs text-slate-500">
                                {{ t('Example: write 9.385 if one dollar equals about nine dinars and thirty-eight.') }}
                            </p>
                        </label>
                    </div>

                    <div class="rounded-3xl border border-violet-100 bg-violet-50/60 p-5">
                        <p class="text-sm font-semibold text-violet-900">{{ t('Euro (EUR)') }}</p>
                        <p class="mt-1 text-sm text-violet-800">{{ t('How many Libyan Dinars is 1 Euro worth?') }}</p>

                        <label class="mt-4 block text-sm">
                            <span class="mb-2 flex flex-wrap items-center gap-2 font-medium text-slate-800">
                                <span class="rounded-full bg-white px-3 py-1 text-slate-900 shadow-sm">1 EUR</span>
                                <span>=</span>
                                <span class="text-slate-600">{{ t('LYD amount') }}</span>
                            </span>
                            <div class="flex items-center gap-2">
                                <input
                                    v-model="rateForm.eur_rate_to_lyd"
                                    type="number"
                                    min="0"
                                    step="0.00000001"
                                    class="w-full rounded-2xl border-slate-200 text-lg"
                                    :disabled="rateForm.processing"
                                    placeholder="10.890"
                                />
                                <span class="shrink-0 text-sm font-semibold text-slate-700">LYD</span>
                            </div>
                            <p v-if="rateForm.errors.eur_rate_to_lyd" class="mt-2 text-xs text-rose-600">
                                {{ rateForm.errors.eur_rate_to_lyd }}
                            </p>
                            <p v-else class="mt-2 text-xs text-slate-500">
                                {{ t('Example: write 10.890 if one euro equals about ten dinars and eighty-nine.') }}
                            </p>
                        </label>
                    </div>
                </div>

                <div class="mt-5 rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                    <span class="font-medium text-slate-800">{{ t('Libyan Dinar (LYD)') }}:</span>
                    {{ t('Always fixed at 1 LYD = 1 LYD. Not editable.') }}
                </div>

                <!-- Live preview -->
                <div class="mt-6 rounded-3xl border border-slate-200 bg-white p-5">
                    <h4 class="text-sm font-semibold text-slate-950">{{ t('Live preview (before save)') }}</h4>
                    <p class="mt-1 text-xs text-slate-500">
                        {{ t('These numbers update as you type. They show what the system will calculate.') }}
                    </p>

                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        <div class="rounded-2xl bg-slate-50 px-4 py-3 text-sm text-slate-700">
                            <p class="font-medium text-slate-900">100 USD → LYD</p>
                            <p class="mt-1 text-lg font-semibold text-slate-950">{{ formatAmount(exampleUsdToLyd) }} LYD</p>
                        </div>
                        <div class="rounded-2xl bg-slate-50 px-4 py-3 text-sm text-slate-700">
                            <p class="font-medium text-slate-900">100 EUR → LYD</p>
                            <p class="mt-1 text-lg font-semibold text-slate-950">{{ formatAmount(exampleEurToLyd) }} LYD</p>
                        </div>
                        <div class="rounded-2xl bg-slate-50 px-4 py-3 text-sm text-slate-700">
                            <p class="font-medium text-slate-900">100 EUR → USD</p>
                            <p class="mt-1 text-lg font-semibold text-slate-950">{{ formatAmount(exampleEurToUsd) }} USD</p>
                            <p class="mt-1 text-xs text-slate-500">{{ t('Calculated through LYD automatically') }}</p>
                        </div>
                        <div class="rounded-2xl bg-slate-50 px-4 py-3 text-sm text-slate-700">
                            <p class="font-medium text-slate-900">100 USD → EUR</p>
                            <p class="mt-1 text-lg font-semibold text-slate-950">{{ formatAmount(exampleUsdToEur) }} EUR</p>
                            <p class="mt-1 text-xs text-slate-500">{{ t('Calculated through LYD automatically') }}</p>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex justify-end">
                    <button
                        type="submit"
                        class="rounded-2xl bg-slate-950 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-slate-800 disabled:opacity-60"
                        :disabled="rateForm.processing"
                    >
                        {{ rateForm.processing ? t('Saving…') : t('Save exchange rates') }}
                    </button>
                </div>
            </form>

            <div
                v-else
                class="rounded-3xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-950"
            >
                {{ t('You can view the current rates, but you do not have permission to change them.') }}
            </div>

            <!-- Current saved rates -->
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-slate-950">{{ t('Currently saved rates') }}</h3>
                <p class="mt-1 text-sm text-slate-600">{{ t('These are the rates stored in the database right now.') }}</p>

                <div class="mt-5 grid gap-4 md:grid-cols-3">
                    <div
                        v-for="rate in rates"
                        :key="rate.currency_code"
                        class="rounded-3xl border border-slate-200 bg-slate-50 p-5"
                    >
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">
                            {{ rate.currency_code }}
                        </p>
                        <p class="mt-2 text-base font-semibold text-slate-950">
                            <template v-if="rate.currency_code === 'LYD'">{{ t('Libyan Dinar') }}</template>
                            <template v-else-if="rate.currency_code === 'USD'">{{ t('US Dollar') }}</template>
                            <template v-else-if="rate.currency_code === 'EUR'">{{ t('Euro') }}</template>
                            <template v-else>{{ rate.currency_code }}</template>
                        </p>
                        <p class="mt-3 text-2xl font-semibold text-slate-900">
                            <template v-if="rate.currency_code === 'LYD'">1</template>
                            <template v-else>{{ rate.rate_to_lyd }}</template>
                            <span class="text-sm font-medium text-slate-500">LYD</span>
                        </p>
                        <p class="mt-2 text-sm text-slate-600">
                            <template v-if="rate.currency_code === 'LYD'">
                                {{ t('Fixed. Always equals itself.') }}
                            </template>
                            <template v-else>
                                1 {{ rate.currency_code }} = {{ rate.rate_to_lyd }} LYD
                            </template>
                        </p>
                        <p v-if="rate.updated_at" class="mt-3 text-xs text-slate-400">
                            {{ t('Last updated') }}: {{ rate.updated_at }}
                        </p>
                    </div>
                </div>
            </div>
        </section>
    </AdminLayout>
</template>
