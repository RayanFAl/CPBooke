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
    router.get(route('admin.customer-wallets.index'), {
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

const walletsCountLabel = computed(() => `${props.wallets.total} ${t(props.wallets.total === 1 ? 'customer wallet' : 'customer wallets')}`);

const openWallet = (wallet) => {
    router.visit(route('admin.customer-wallets.show', wallet.id));
};
</script>

<template>
    <AdminLayout>
        <Head :title="t('Customer Wallets')" />

        <section class="space-y-6">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <h2 class="text-2xl font-semibold text-slate-950">{{ t('Customer Wallets') }}</h2>
                        <p class="mt-2 text-sm text-slate-600">
                            {{ t('Search customer wallets, then open a ledger to record an admin top-up.') }}
                        </p>
                    </div>
                    <p class="text-sm font-medium text-slate-500">{{ walletsCountLabel }}</p>
                </div>

                <form class="mt-6 flex flex-col gap-3 sm:flex-row" @submit.prevent="applyFilters">
                    <input
                        v-model="filterForm.search"
                        type="search"
                        class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"
                        :placeholder="t('Search by customer, wallet number, or currency')"
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
                                <th class="px-4 py-3">{{ t('Customer') }}</th>
                                <th class="px-4 py-3">{{ t('Wallet') }}</th>
                                <th class="px-4 py-3">{{ t('Balance') }}</th>
                                <th class="px-4 py-3">{{ t('Status') }}</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr
                                v-for="wallet in wallets.data"
                                :key="wallet.id"
                                class="cursor-pointer hover:bg-slate-50"
                                @click="openWallet(wallet)"
                            >
                                <td class="px-4 py-3">
                                    <p class="font-medium text-slate-900">{{ wallet.user_name }}</p>
                                    <p class="text-xs text-slate-500">{{ wallet.user_email }}</p>
                                </td>
                                <td class="px-4 py-3 text-slate-700">
                                    <p>{{ wallet.wallet_number }}</p>
                                    <p class="text-xs text-slate-500">{{ wallet.currency }}</p>
                                </td>
                                <td class="px-4 py-3 font-semibold text-slate-900">
                                    {{ formatMoney(wallet.balance, wallet.currency) }}
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                        :class="wallet.is_frozen ? 'bg-rose-100 text-rose-800' : 'bg-emerald-100 text-emerald-800'"
                                    >
                                        {{ wallet.is_frozen ? t('Frozen') : t('Active') }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <Link
                                        :href="route('admin.customer-wallets.show', wallet.id)"
                                        class="text-sm font-medium text-cyan-700 hover:text-cyan-800"
                                    >
                                        {{ t('View') }}
                                    </Link>
                                </td>
                            </tr>
                            <tr v-if="wallets.data.length === 0">
                                <td colspan="5" class="px-4 py-10 text-center text-slate-500">
                                    {{ t('No customer wallets found.') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </AdminLayout>
</template>
