<script setup>
import AdminLayout from '../../layouts/AdminLayout.vue';
import { useAdminLocale } from '../../composables/useAdminLocale';
import { useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    rates: { type: Array, default: () => [] },
    form: { type: Object, required: true },
    base_currency: { type: String, default: 'LYD' },
    update_url: { type: String, required: true },
});

const { t } = useAdminLocale();
const page = usePage();
const activeTab = ref('buy');

const tabs = [
    { id: 'buy', label: 'Buy' },
    { id: 'sell', label: 'Sell' },
];

const flashSuccess = computed(() => page.props.flash?.success ?? '');
const canManage = computed(() => {
    const permissions = page.props.auth?.user?.permissions ?? [];

    return permissions.includes('exchange-rates.manage');
});

const rateForm = useForm({
    usd_buy_rate_to_lyd: props.form.usd_buy_rate_to_lyd ?? '',
    usd_sell_rate_to_lyd: props.form.usd_sell_rate_to_lyd ?? '',
    eur_buy_rate_to_lyd: props.form.eur_buy_rate_to_lyd ?? '',
    eur_sell_rate_to_lyd: props.form.eur_sell_rate_to_lyd ?? '',
});

const parseRate = (value) => {
    const number = Number(value);

    return Number.isFinite(number) && number > 0 ? number : null;
};

const usdBuy = computed(() => parseRate(rateForm.usd_buy_rate_to_lyd));
const usdSell = computed(() => parseRate(rateForm.usd_sell_rate_to_lyd));
const eurBuy = computed(() => parseRate(rateForm.eur_buy_rate_to_lyd));
const eurSell = computed(() => parseRate(rateForm.eur_sell_rate_to_lyd));

const usdMid = computed(() => (usdBuy.value && usdSell.value ? (usdBuy.value + usdSell.value) / 2 : null));
const eurMid = computed(() => (eurBuy.value && eurSell.value ? (eurBuy.value + eurSell.value) / 2 : null));

const formatAmount = (value) => {
    if (value === null || !Number.isFinite(value)) {
        return '—';
    }

    return value.toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 4,
    });
};

const exampleUsdToLyd = computed(() => {
    const rate = activeTab.value === 'buy' ? usdBuy.value : usdSell.value;

    return rate === null ? null : 100 * rate;
});

const exampleEurToLyd = computed(() => {
    const rate = activeTab.value === 'buy' ? eurBuy.value : eurSell.value;

    return rate === null ? null : 100 * rate;
});

const tabHint = computed(() => (
    activeTab.value === 'buy'
        ? t('The rate when buying the foreign currency from the customer.')
        : t('The rate when selling the foreign currency to the customer.')
));

const submit = () => {
    if (!canManage.value) {
        return;
    }

    rateForm.transform((data) => ({
        usd_buy_rate_to_lyd: data.usd_buy_rate_to_lyd === '' ? null : Number(data.usd_buy_rate_to_lyd),
        usd_sell_rate_to_lyd: data.usd_sell_rate_to_lyd === '' ? null : Number(data.usd_sell_rate_to_lyd),
        eur_buy_rate_to_lyd: data.eur_buy_rate_to_lyd === '' ? null : Number(data.eur_buy_rate_to_lyd),
        eur_sell_rate_to_lyd: data.eur_sell_rate_to_lyd === '' ? null : Number(data.eur_sell_rate_to_lyd),
    })).put(props.update_url, {
        preserveScroll: true,
    });
};
</script>

<template>
    <AdminLayout
        :title="t('Exchange Rates')"
        :description="t('Set buy and sell rates for USD and EUR against LYD.')"
    >
        <section class="space-y-6">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.25em] text-cyan-700">{{ t('Settings') }}</p>
                <h2 class="mt-3 text-2xl font-semibold text-slate-950">{{ t('Exchange Rates') }}</h2>
                <p class="mt-3 max-w-3xl text-base leading-7 text-slate-700">
                    {{ t('Enter buy and sell prices in Libyan Dinars for 1 USD and 1 EUR. LYD stays fixed at 1.') }}
                </p>
                <p v-if="flashSuccess" class="mt-4 rounded-2xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {{ flashSuccess }}
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <button
                    v-for="tab in tabs"
                    :key="tab.id"
                    type="button"
                    class="rounded-2xl px-4 py-2 text-sm font-medium transition"
                    :class="activeTab === tab.id ? 'bg-slate-950 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                    @click="activeTab = tab.id"
                >
                    {{ t(tab.label) }}
                </button>
            </div>

            <form
                v-if="canManage"
                class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm"
                @submit.prevent="submit"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-950">
                            {{ activeTab === 'buy' ? t('Buy rates') : t('Sell rates') }}
                        </h3>
                        <p class="mt-1 text-sm text-slate-600">{{ tabHint }}</p>
                    </div>
                    <p class="rounded-2xl bg-slate-50 px-3 py-2 text-xs text-slate-600">
                        {{ t('Sell must be greater than or equal to buy.') }}
                    </p>
                </div>

                <!-- BUY TAB -->
                <div v-show="activeTab === 'buy'" class="mt-6 grid gap-5 lg:grid-cols-2">
                    <div class="rounded-3xl border border-emerald-100 bg-emerald-50/50 p-5">
                        <p class="text-sm font-semibold text-emerald-900">{{ t('US Dollar (USD)') }}</p>
                        <label class="mt-4 block text-sm">
                            <span class="mb-2 flex flex-wrap items-center gap-2 font-medium text-slate-800">
                                <span class="rounded-full bg-white px-3 py-1 text-slate-900 shadow-sm">1 USD</span>
                                <span>=</span>
                                <span class="text-slate-600">{{ t('Buy') }} LYD</span>
                            </span>
                            <div class="flex items-center gap-2">
                                <input
                                    v-model="rateForm.usd_buy_rate_to_lyd"
                                    type="number"
                                    min="0"
                                    step="0.00000001"
                                    class="w-full rounded-2xl border-slate-200 text-lg"
                                    :disabled="rateForm.processing"
                                    placeholder="9.350"
                                />
                                <span class="shrink-0 text-sm font-semibold text-slate-700">LYD</span>
                            </div>
                            <p v-if="rateForm.errors.usd_buy_rate_to_lyd" class="mt-2 text-xs text-rose-600">
                                {{ rateForm.errors.usd_buy_rate_to_lyd }}
                            </p>
                        </label>
                        <p class="mt-3 text-xs text-slate-500">
                            {{ t('Current sell') }}: {{ formatAmount(usdSell) }} LYD · {{ t('Mid') }}: {{ formatAmount(usdMid) }} LYD
                        </p>
                    </div>

                    <div class="rounded-3xl border border-emerald-100 bg-emerald-50/50 p-5">
                        <p class="text-sm font-semibold text-emerald-900">{{ t('Euro (EUR)') }}</p>
                        <label class="mt-4 block text-sm">
                            <span class="mb-2 flex flex-wrap items-center gap-2 font-medium text-slate-800">
                                <span class="rounded-full bg-white px-3 py-1 text-slate-900 shadow-sm">1 EUR</span>
                                <span>=</span>
                                <span class="text-slate-600">{{ t('Buy') }} LYD</span>
                            </span>
                            <div class="flex items-center gap-2">
                                <input
                                    v-model="rateForm.eur_buy_rate_to_lyd"
                                    type="number"
                                    min="0"
                                    step="0.00000001"
                                    class="w-full rounded-2xl border-slate-200 text-lg"
                                    :disabled="rateForm.processing"
                                    placeholder="10.850"
                                />
                                <span class="shrink-0 text-sm font-semibold text-slate-700">LYD</span>
                            </div>
                            <p v-if="rateForm.errors.eur_buy_rate_to_lyd" class="mt-2 text-xs text-rose-600">
                                {{ rateForm.errors.eur_buy_rate_to_lyd }}
                            </p>
                        </label>
                        <p class="mt-3 text-xs text-slate-500">
                            {{ t('Current sell') }}: {{ formatAmount(eurSell) }} LYD · {{ t('Mid') }}: {{ formatAmount(eurMid) }} LYD
                        </p>
                    </div>
                </div>

                <!-- SELL TAB -->
                <div v-show="activeTab === 'sell'" class="mt-6 grid gap-5 lg:grid-cols-2">
                    <div class="rounded-3xl border border-sky-100 bg-sky-50/50 p-5">
                        <p class="text-sm font-semibold text-sky-900">{{ t('US Dollar (USD)') }}</p>
                        <label class="mt-4 block text-sm">
                            <span class="mb-2 flex flex-wrap items-center gap-2 font-medium text-slate-800">
                                <span class="rounded-full bg-white px-3 py-1 text-slate-900 shadow-sm">1 USD</span>
                                <span>=</span>
                                <span class="text-slate-600">{{ t('Sell') }} LYD</span>
                            </span>
                            <div class="flex items-center gap-2">
                                <input
                                    v-model="rateForm.usd_sell_rate_to_lyd"
                                    type="number"
                                    min="0"
                                    step="0.00000001"
                                    class="w-full rounded-2xl border-slate-200 text-lg"
                                    :disabled="rateForm.processing"
                                    placeholder="9.420"
                                />
                                <span class="shrink-0 text-sm font-semibold text-slate-700">LYD</span>
                            </div>
                            <p v-if="rateForm.errors.usd_sell_rate_to_lyd" class="mt-2 text-xs text-rose-600">
                                {{ rateForm.errors.usd_sell_rate_to_lyd }}
                            </p>
                        </label>
                        <p class="mt-3 text-xs text-slate-500">
                            {{ t('Current buy') }}: {{ formatAmount(usdBuy) }} LYD · {{ t('Mid') }}: {{ formatAmount(usdMid) }} LYD
                        </p>
                    </div>

                    <div class="rounded-3xl border border-sky-100 bg-sky-50/50 p-5">
                        <p class="text-sm font-semibold text-sky-900">{{ t('Euro (EUR)') }}</p>
                        <label class="mt-4 block text-sm">
                            <span class="mb-2 flex flex-wrap items-center gap-2 font-medium text-slate-800">
                                <span class="rounded-full bg-white px-3 py-1 text-slate-900 shadow-sm">1 EUR</span>
                                <span>=</span>
                                <span class="text-slate-600">{{ t('Sell') }} LYD</span>
                            </span>
                            <div class="flex items-center gap-2">
                                <input
                                    v-model="rateForm.eur_sell_rate_to_lyd"
                                    type="number"
                                    min="0"
                                    step="0.00000001"
                                    class="w-full rounded-2xl border-slate-200 text-lg"
                                    :disabled="rateForm.processing"
                                    placeholder="10.930"
                                />
                                <span class="shrink-0 text-sm font-semibold text-slate-700">LYD</span>
                            </div>
                            <p v-if="rateForm.errors.eur_sell_rate_to_lyd" class="mt-2 text-xs text-rose-600">
                                {{ rateForm.errors.eur_sell_rate_to_lyd }}
                            </p>
                        </label>
                        <p class="mt-3 text-xs text-slate-500">
                            {{ t('Current buy') }}: {{ formatAmount(eurBuy) }} LYD · {{ t('Mid') }}: {{ formatAmount(eurMid) }} LYD
                        </p>
                    </div>
                </div>

                <div class="mt-5 rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                    <span class="font-medium text-slate-800">{{ t('Libyan Dinar (LYD)') }}:</span>
                    {{ t('Always fixed at 1 LYD = 1 LYD. Not editable.') }}
                </div>

                <div class="mt-6 rounded-3xl border border-slate-200 bg-slate-50 p-5">
                    <h4 class="text-sm font-semibold text-slate-950">
                        {{ t('Live preview (before save)') }} — {{ activeTab === 'buy' ? t('Buy') : t('Sell') }}
                    </h4>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        <div class="rounded-2xl bg-white px-4 py-3 text-sm text-slate-700">
                            <p class="font-medium text-slate-900">100 USD → LYD</p>
                            <p class="mt-1 text-lg font-semibold text-slate-950">{{ formatAmount(exampleUsdToLyd) }} LYD</p>
                        </div>
                        <div class="rounded-2xl bg-white px-4 py-3 text-sm text-slate-700">
                            <p class="font-medium text-slate-900">100 EUR → LYD</p>
                            <p class="mt-1 text-lg font-semibold text-slate-950">{{ formatAmount(exampleEurToLyd) }} LYD</p>
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

            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-slate-950">{{ t('Currently saved rates') }}</h3>
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
                        <template v-if="rate.currency_code === 'LYD'">
                            <p class="mt-3 text-2xl font-semibold text-slate-900">
                                1 <span class="text-sm font-medium text-slate-500">LYD</span>
                            </p>
                            <p class="mt-2 text-sm text-slate-600">{{ t('Fixed. Always equals itself.') }}</p>
                        </template>
                        <template v-else>
                            <p class="mt-3 text-sm text-slate-700">
                                <span class="font-medium">{{ t('Buy') }}:</span> {{ rate.buy_rate_to_lyd }} LYD
                            </p>
                            <p class="mt-1 text-sm text-slate-700">
                                <span class="font-medium">{{ t('Sell') }}:</span> {{ rate.sell_rate_to_lyd }} LYD
                            </p>
                            <p class="mt-1 text-sm text-slate-700">
                                <span class="font-medium">{{ t('Mid') }}:</span> {{ rate.mid_rate_to_lyd }} LYD
                            </p>
                        </template>
                        <p v-if="rate.updated_at" class="mt-3 text-xs text-slate-400">
                            {{ t('Last updated') }}: {{ rate.updated_at }}
                        </p>
                    </div>
                </div>
            </div>
        </section>
    </AdminLayout>
</template>
