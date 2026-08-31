<script setup>
import AdminLayout from '../../layouts/AdminLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useAdminLocale } from '../../composables/useAdminLocale';
import { usePlatformCurrency } from '../../composables/usePlatformCurrency';

const props = defineProps({
    supplier: { type: Object, required: true },
    can_manage: { type: Boolean, default: false },
    can_view_wallets: { type: Boolean, default: false },
    can_view_settlements: { type: Boolean, default: false },
});

const { locale, t, backArrow, settlementCycleLabel, providerStatusLabel } = useAdminLocale();
const { defaultCurrency } = usePlatformCurrency();
const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success ?? null);
const flashError = computed(() => page.props.flash?.error ?? null);
const flashInfo = computed(() => page.props.flash?.info ?? null);

const settlementsUrl = computed(() => route('admin.settlements.index', { provider_id: props.supplier.id }));

const formatMoney = (amount, currency) => {
    if (amount === null || amount === undefined || amount === '') {
        return '—';
    }

    return new Intl.NumberFormat(locale.value, {
        style: 'currency',
        currency: currency || props.supplier.default_currency || defaultCurrency.value,
    }).format(Number(amount));
};
</script>

<template>
    <AdminLayout>
        <Head :title="supplier.name" />

        <section class="space-y-6">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <Link :href="route('admin.suppliers.index')" class="text-sm font-medium text-cyan-700">{{ backArrow }} {{ t('Back to providers') }}</Link>
                <div class="mt-4 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <h2 class="text-2xl font-semibold text-slate-950">{{ supplier.name }}</h2>
                        <p class="mt-1 text-sm text-slate-500">{{ providerStatusLabel(supplier.status) }}</p>
                    </div>
                    <Link
                        v-if="can_manage"
                        :href="route('admin.suppliers.edit', supplier.id)"
                        class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700"
                    >
                        {{ t('Edit profile') }}
                    </Link>
                    <a
                        v-if="supplier.print_url"
                        :href="supplier.print_url"
                        target="_blank"
                        rel="noopener"
                        class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700"
                    >
                        {{ t('Print profile') }}
                    </a>
                </div>
                <p v-if="flashSuccess" class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">{{ flashSuccess }}</p>
                <p v-if="flashError" class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800">{{ flashError }}</p>
                <p v-if="flashInfo" class="mt-4 rounded-xl border border-cyan-200 bg-cyan-50 px-3 py-2 text-sm text-cyan-800">{{ flashInfo }}</p>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <Link
                    v-if="can_view_wallets"
                    :href="supplier.wallets?.length === 1 ? route('admin.provider-wallets.show', supplier.wallets[0].id) : route('admin.provider-wallets.create', { provider_id: supplier.id })"
                    class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm transition hover:border-cyan-300 hover:bg-cyan-50/30"
                >
                    <p class="text-xs font-semibold uppercase tracking-wide text-cyan-700">{{ t('Step 2') }}</p>
                    <h3 class="mt-2 text-lg font-semibold text-slate-950">{{ t('Provider ledger') }}</h3>
                    <p class="mt-2 text-sm text-slate-600">{{ t('Prepaid balance in Booke: deposits and booking debits.') }}</p>
                    <p v-if="supplier.wallets?.length" class="mt-3 text-sm font-medium text-slate-950">
                        {{ supplier.wallets.length }} {{ supplier.wallets.length === 1 ? t('wallet') : t('wallets') }}
                    </p>
                    <p v-else class="mt-3 text-sm font-medium text-cyan-800">{{ t('Create ledger wallet') }} →</p>
                </Link>

                <Link
                    v-if="can_view_settlements"
                    :href="settlementsUrl"
                    class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm transition hover:border-cyan-300 hover:bg-cyan-50/30"
                >
                    <p class="text-xs font-semibold uppercase tracking-wide text-cyan-700">{{ t('Step 3') }}</p>
                    <h3 class="mt-2 text-lg font-semibold text-slate-950">{{ t('Settlements') }}</h3>
                    <p class="mt-2 text-sm text-slate-600">{{ t('Match supplier invoices with Booke costs for each period.') }}</p>
                    <p class="mt-3 text-sm font-medium text-cyan-800">{{ t('Open settlements') }} →</p>
                </Link>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs text-slate-500">{{ t('Commission') }}</p>
                    <p class="mt-1 text-lg font-semibold">{{ supplier.commission_rate != null ? `${supplier.commission_rate}%` : '—' }}</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs text-slate-500">{{ t('Settlement cycle') }}</p>
                    <p class="mt-1 text-lg font-semibold">{{ settlementCycleLabel(supplier.settlement_cycle) }}</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs text-slate-500">{{ t('Credit limit') }}</p>
                    <p class="mt-1 text-lg font-semibold">{{ formatMoney(supplier.credit_limit, supplier.default_currency) }}</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs text-slate-500">{{ t('Currency') }}</p>
                    <p class="mt-1 text-lg font-semibold">{{ supplier.default_currency }}</p>
                </div>
            </div>

            <div v-if="supplier.wallets?.length" class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-base font-semibold">{{ t('Ledger wallets') }}</h3>
                <div class="mt-4 divide-y divide-slate-100">
                    <div v-for="wallet in supplier.wallets" :key="wallet.id" class="flex items-center justify-between py-3 text-sm">
                        <div>
                            <p class="font-medium">{{ wallet.currency }}</p>
                            <p class="text-xs" :class="wallet.is_negative ? 'text-rose-600' : 'text-slate-500'">
                                {{ formatMoney(wallet.balance, wallet.currency) }}
                            </p>
                        </div>
                        <Link
                            v-if="can_view_wallets"
                            :href="route('admin.provider-wallets.show', wallet.id)"
                            class="font-medium text-cyan-700"
                        >
                            {{ t('Open') }}
                        </Link>
                    </div>
                </div>
            </div>

            <details class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <summary class="cursor-pointer text-base font-semibold text-slate-950">{{ t('Contact & contract details') }}</summary>
                <dl class="mt-4 grid gap-4 sm:grid-cols-2 text-sm">
                    <div><dt class="text-slate-500">{{ t('Legal name') }}</dt><dd class="font-medium">{{ supplier.legal_name || '—' }}</dd></div>
                    <div><dt class="text-slate-500">{{ t('Contact') }}</dt><dd class="font-medium">{{ supplier.contact_name || '—' }}</dd></div>
                    <div><dt class="text-slate-500">{{ t('Email') }}</dt><dd class="font-medium">{{ supplier.contact_email || '—' }}</dd></div>
                    <div><dt class="text-slate-500">{{ t('Phone') }}</dt><dd class="font-medium">{{ supplier.contact_phone || '—' }}</dd></div>
                    <div><dt class="text-slate-500">{{ t('Website') }}</dt><dd class="font-medium">{{ supplier.website || '—' }}</dd></div>
                    <div><dt class="text-slate-500">{{ t('Contract period') }}</dt><dd class="font-medium">{{ supplier.contract_starts_at || '—' }} – {{ supplier.contract_ends_at || '—' }}</dd></div>
                </dl>
                <p v-if="supplier.contract_notes || supplier.notes" class="mt-4 whitespace-pre-wrap text-sm text-slate-700">
                    {{ supplier.contract_notes || supplier.notes }}
                </p>
            </details>
        </section>
    </AdminLayout>
</template>
