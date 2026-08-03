<script setup>
import AdminLayout from '../../layouts/AdminLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useAdminLocale } from '../../composables/useAdminLocale';

const props = defineProps({
    supplier: { type: Object, required: true },
    can_manage: { type: Boolean, default: false },
    can_view_wallets: { type: Boolean, default: false },
});

const { locale, t } = useAdminLocale();
const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success ?? null);

const formatMoney = (amount, currency) => {
    if (amount === null || amount === undefined || amount === '') {
        return '—';
    }

    return new Intl.NumberFormat(locale.value, {
        style: 'currency',
        currency: currency || props.supplier.default_currency || 'LYD',
    }).format(Number(amount));
};
</script>

<template>
    <AdminLayout>
        <Head :title="supplier.name" />

        <section class="space-y-6">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <Link :href="route('admin.suppliers.index')" class="text-sm font-medium text-cyan-700">← {{ t('Back to providers') }}</Link>
                        <h2 class="mt-3 text-2xl font-semibold text-slate-950">{{ supplier.name }}</h2>
                        <p class="mt-1 text-sm text-slate-500">{{ supplier.key }} · {{ supplier.status }} · {{ supplier.integration_status }}</p>
                    </div>
                    <div class="flex gap-2">
                        <Link
                            v-if="can_manage"
                            :href="route('admin.suppliers.edit', supplier.id)"
                            class="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-medium text-white hover:bg-slate-800"
                        >
                            {{ t('Edit') }}
                        </Link>
                        <Link
                            v-if="can_view_wallets"
                            :href="route('admin.provider-wallets.create', { provider_id: supplier.id })"
                            class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700"
                        >
                            {{ t('Create wallet') }}
                        </Link>
                    </div>
                </div>
                <p v-if="flashSuccess" class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">{{ flashSuccess }}</p>
            </div>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ t('Commission') }}</p>
                    <p class="mt-2 text-xl font-semibold">{{ supplier.commission_rate != null ? `${supplier.commission_rate}%` : '—' }}</p>
                </div>
                <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ t('Settlement cycle') }}</p>
                    <p class="mt-2 text-xl font-semibold">{{ supplier.settlement_cycle }}</p>
                </div>
                <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ t('Credit limit') }}</p>
                    <p class="mt-2 text-xl font-semibold">{{ formatMoney(supplier.credit_limit, supplier.default_currency) }}</p>
                </div>
                <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ t('Currency') }}</p>
                    <p class="mt-2 text-xl font-semibold">{{ supplier.default_currency }}</p>
                </div>
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-base font-semibold">{{ t('Contacts & contract') }}</h3>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div><dt class="text-slate-500">{{ t('Legal name') }}</dt><dd class="font-medium">{{ supplier.legal_name || '—' }}</dd></div>
                        <div><dt class="text-slate-500">{{ t('Contact') }}</dt><dd class="font-medium">{{ supplier.contact_name || '—' }}</dd></div>
                        <div><dt class="text-slate-500">{{ t('Email') }}</dt><dd class="font-medium">{{ supplier.contact_email || '—' }}</dd></div>
                        <div><dt class="text-slate-500">{{ t('Phone') }}</dt><dd class="font-medium">{{ supplier.contact_phone || '—' }}</dd></div>
                        <div><dt class="text-slate-500">{{ t('Contract period') }}</dt><dd class="font-medium">{{ supplier.contract_starts_at || '—' }} → {{ supplier.contract_ends_at || '—' }}</dd></div>
                        <div><dt class="text-slate-500">{{ t('Website') }}</dt><dd class="font-medium">{{ supplier.website || '—' }}</dd></div>
                    </dl>
                </div>

                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-base font-semibold">{{ t('Notes') }}</h3>
                    <p class="mt-3 whitespace-pre-wrap text-sm text-slate-700">{{ supplier.contract_notes || t('No contract notes.') }}</p>
                    <p class="mt-4 whitespace-pre-wrap text-sm text-slate-700">{{ supplier.notes || t('No internal notes.') }}</p>
                </div>
            </div>

            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-base font-semibold">{{ t('Linked wallets') }}</h3>
                <div v-if="!supplier.wallets?.length" class="mt-4 text-sm text-slate-500">{{ t('No wallets linked yet.') }}</div>
                <div v-else class="mt-4 divide-y divide-slate-100">
                    <div v-for="wallet in supplier.wallets" :key="wallet.id" class="flex items-center justify-between py-3 text-sm">
                        <div>
                            <p class="font-medium">{{ wallet.currency }} · {{ wallet.environment }}</p>
                            <p class="text-xs text-slate-500" :class="wallet.is_negative ? 'text-rose-600' : ''">
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
        </section>
    </AdminLayout>
</template>
