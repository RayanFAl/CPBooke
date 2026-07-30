<script setup>
import AdminLayout from '../../layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import { useAdminLocale } from '../../composables/useAdminLocale';

const props = defineProps({
    wallets: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        required: true,
    },
    can_manage: {
        type: Boolean,
        default: false,
    },
});

const { locale, t } = useAdminLocale();

const filterForm = reactive({
    search: props.filters.search ?? '',
});

const formatMoney = (amount, currency) => new Intl.NumberFormat(locale.value, {
    style: 'currency',
    currency: currency || 'LYD',
}).format(Number(amount ?? 0));

const applyFilters = () => {
    router.get(route('admin.provider-wallets.index'), {
        ...(filterForm.search.trim() ? { search: filterForm.search.trim() } : {}),
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const resetFilters = () => {
    filterForm.search = '';
    applyFilters();
};

const walletsCountLabel = computed(() => `${props.wallets.total} ${t(props.wallets.total === 1 ? 'provider wallet' : 'provider wallets')}`);

const openWallet = (wallet) => {
    router.visit(route('admin.provider-wallets.show', wallet.id));
};
</script>

<template>
    <AdminLayout>
        <Head :title="t('Provider Wallets')" />

        <section class="space-y-6">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.25em] text-cyan-700">
                            {{ t('Finance') }}
                        </p>
                        <h2 class="mt-3 text-2xl font-semibold text-slate-950">{{ t('Provider Wallets') }}</h2>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">
                            {{ t('Track prepaid balances deposited with flight suppliers and debits from synced bookings.') }}
                        </p>
                        <p class="mt-3 text-sm text-slate-500">{{ walletsCountLabel }}</p>
                    </div>

                    <Link
                        v-if="can_manage"
                        :href="route('admin.provider-wallets.create')"
                        class="inline-flex items-center justify-center rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-slate-800"
                    >
                        {{ t('Create wallet') }}
                    </Link>
                </div>
            </div>

            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <form class="flex flex-col gap-3 md:flex-row" @submit.prevent="applyFilters">
                    <input
                        v-model="filterForm.search"
                        type="search"
                        class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm text-slate-900"
                        :placeholder="t('Search provider key, name, or currency')"
                    >
                    <div class="flex gap-2">
                        <button
                            type="submit"
                            class="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-medium text-white hover:bg-slate-800"
                        >
                            {{ t('Search') }}
                        </button>
                        <button
                            type="button"
                            class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
                            @click="resetFilters"
                        >
                            {{ t('Reset') }}
                        </button>
                    </div>
                </form>
            </div>

            <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-3">{{ t('Provider') }}</th>
                                <th class="px-4 py-3">{{ t('Currency') }}</th>
                                <th class="px-4 py-3">{{ t('Balance') }}</th>
                                <th class="px-4 py-3">{{ t('Status') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr
                                v-for="wallet in wallets.data"
                                :key="wallet.id"
                                class="cursor-pointer hover:bg-cyan-50/50"
                                role="link"
                                tabindex="0"
                                :aria-label="`${t('Open')} ${wallet.provider_name}`"
                                @click="openWallet(wallet)"
                                @keydown.enter.prevent="openWallet(wallet)"
                                @keydown.space.prevent="openWallet(wallet)"
                            >
                                <td class="px-4 py-3">
                                    <p class="font-medium text-slate-950">{{ wallet.provider_name }}</p>
                                    <p class="text-xs text-slate-500">{{ wallet.provider_key }} · {{ wallet.environment }}</p>
                                </td>
                                <td class="px-4 py-3 text-slate-700">{{ wallet.currency }}</td>
                                <td class="px-4 py-3">
                                    <span
                                        class="font-semibold"
                                        :class="wallet.is_negative ? 'text-rose-700' : 'text-slate-950'"
                                    >
                                        {{ formatMoney(wallet.balance, wallet.currency) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        v-if="wallet.is_negative"
                                        class="inline-flex rounded-full bg-rose-100 px-2.5 py-1 text-xs font-medium text-rose-800"
                                    >
                                        {{ t('Negative balance') }}
                                    </span>
                                    <span
                                        v-else-if="wallet.is_low_balance"
                                        class="inline-flex rounded-full bg-amber-100 px-2.5 py-1 text-xs font-medium text-amber-800"
                                    >
                                        {{ t('Low balance') }}
                                    </span>
                                    <span
                                        v-else
                                        class="inline-flex rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-medium text-emerald-800"
                                    >
                                        {{ t('Healthy') }}
                                    </span>
                                </td>
                            </tr>
                            <tr v-if="wallets.data.length === 0">
                                <td colspan="4" class="px-4 py-10 text-center text-slate-500">
                                    {{ t('No provider wallets yet.') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </AdminLayout>
</template>
