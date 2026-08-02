<script setup>
import AdminLayout from '../../layouts/AdminLayout.vue';
import SystemTimeline from '../../components/SystemTimeline.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useAdminLocale } from '../../composables/useAdminLocale';
import { usePlatformCurrency } from '../../composables/usePlatformCurrency';

const props = defineProps({
    wallet: {
        type: Object,
        required: true,
    },
    transactions: {
        type: Object,
        required: true,
    },
    system_timeline: {
        type: Array,
        default: () => [],
    },
    can_manage: {
        type: Boolean,
        default: false,
    },
});

const { locale, t } = useAdminLocale();
const { defaultCurrency } = usePlatformCurrency();
const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success ?? null);

const depositForm = useForm({
    amount: '',
    note: '',
});

const adjustForm = useForm({
    amount: '',
    note: '',
});

const formatMoney = (amount, currency) => new Intl.NumberFormat(locale.value, {
    style: 'currency',
    currency: currency || props.wallet.currency || defaultCurrency.value,
}).format(Number(amount ?? 0));

const formatDateTime = (value) => {
    if (!value) {
        return '-';
    }

    return new Intl.DateTimeFormat(locale.value, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
};

const typeLabel = (type) => {
    if (type === 'deposit') {
        return t('Deposit');
    }

    if (type === 'debit') {
        return t('Debit');
    }

    if (type === 'adjustment') {
        return t('Adjustment');
    }

    return type;
};

const submitDeposit = () => {
    depositForm.post(route('admin.provider-wallets.deposit', props.wallet.id), {
        preserveScroll: true,
        onSuccess: () => depositForm.reset(),
    });
};

const submitAdjust = () => {
    adjustForm.post(route('admin.provider-wallets.adjust', props.wallet.id), {
        preserveScroll: true,
        onSuccess: () => adjustForm.reset(),
    });
};
</script>

<template>
    <AdminLayout>
        <Head :title="wallet.provider_name" />

        <section class="space-y-6">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <Link
                            :href="route('admin.provider-wallets.index')"
                            class="text-sm font-medium text-cyan-700 hover:text-cyan-800"
                        >
                            ← {{ t('Back to wallets') }}
                        </Link>
                        <h2 class="mt-3 text-2xl font-semibold text-slate-950">{{ wallet.provider_name }}</h2>
                        <p class="mt-1 text-sm text-slate-500">
                            {{ wallet.provider_key }} · {{ wallet.currency }} · {{ wallet.environment }}
                        </p>
                        <p class="mt-2 text-xs text-slate-500">
                            {{ wallet.allow_negative ? t('Policy: allow negative balance') : t('Policy: reject when insufficient') }}
                        </p>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-5 py-4 text-right">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ t('Current balance') }}</p>
                        <p
                            class="mt-1 text-2xl font-semibold"
                            :class="wallet.is_negative ? 'text-rose-700' : 'text-slate-950'"
                        >
                            {{ formatMoney(wallet.balance, wallet.currency) }}
                        </p>
                        <p v-if="wallet.is_negative" class="mt-2 text-xs font-medium text-rose-700">
                            {{ t('Balance is negative. Top up soon.') }}
                        </p>
                        <p v-else-if="wallet.is_low_balance" class="mt-2 text-xs font-medium text-amber-700">
                            {{ t('Balance is at or below the low threshold.') }}
                        </p>
                    </div>
                </div>

                <p
                    v-if="flashSuccess"
                    class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800"
                >
                    {{ flashSuccess }}
                </p>
            </div>

            <div v-if="can_manage" class="grid gap-4 lg:grid-cols-2">
                <form class="space-y-3 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm" @submit.prevent="submitDeposit">
                    <h3 class="text-base font-semibold text-slate-950">{{ t('Record deposit') }}</h3>
                    <p class="text-sm text-slate-600">{{ t('Add prepaid credit after funding the supplier.') }}</p>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-800">{{ t('Amount') }}</label>
                        <input
                            v-model="depositForm.amount"
                            type="number"
                            min="0.01"
                            step="0.01"
                            class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"
                            required
                        >
                        <p v-if="depositForm.errors.amount" class="mt-1 text-sm text-rose-600">{{ depositForm.errors.amount }}</p>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-800">{{ t('Note') }}</label>
                        <input
                            v-model="depositForm.note"
                            type="text"
                            class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"
                            :placeholder="t('Optional bank reference')"
                        >
                    </div>

                    <button
                        type="submit"
                        class="rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-medium text-white hover:bg-emerald-800 disabled:opacity-60"
                        :disabled="depositForm.processing"
                    >
                        {{ t('Add deposit') }}
                    </button>
                </form>

                <form class="space-y-3 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm" @submit.prevent="submitAdjust">
                    <h3 class="text-base font-semibold text-slate-950">{{ t('Manual adjustment') }}</h3>
                    <p class="text-sm text-slate-600">{{ t('Use a positive or negative amount to correct the ledger.') }}</p>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-800">{{ t('Amount') }}</label>
                        <input
                            v-model="adjustForm.amount"
                            type="number"
                            step="0.01"
                            class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"
                            required
                        >
                        <p v-if="adjustForm.errors.amount" class="mt-1 text-sm text-rose-600">{{ adjustForm.errors.amount }}</p>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-800">{{ t('Note') }}</label>
                        <input
                            v-model="adjustForm.note"
                            type="text"
                            class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"
                        >
                    </div>

                    <button
                        type="submit"
                        class="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-medium text-white hover:bg-slate-800 disabled:opacity-60"
                        :disabled="adjustForm.processing"
                    >
                        {{ t('Apply adjustment') }}
                    </button>
                </form>
            </div>

            <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h3 class="text-base font-semibold text-slate-950">{{ t('Movement history') }}</h3>
                    <p class="mt-1 text-sm text-slate-600">{{ t('Deposits, booking debits, and adjustments.') }}</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-3">{{ t('When') }}</th>
                                <th class="px-4 py-3">{{ t('Type') }}</th>
                                <th class="px-4 py-3">{{ t('Amount') }}</th>
                                <th class="px-4 py-3">{{ t('Balance after') }}</th>
                                <th class="px-4 py-3">{{ t('Details') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="tx in transactions.data" :key="tx.id">
                                <td class="px-4 py-3 text-slate-700">{{ formatDateTime(tx.created_at) }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-800">
                                        {{ typeLabel(tx.type) }}
                                    </span>
                                </td>
                                <td
                                    class="px-4 py-3 font-medium"
                                    :class="Number(tx.signed_amount) < 0 ? 'text-rose-700' : 'text-emerald-700'"
                                >
                                    {{ formatMoney(tx.signed_amount, tx.currency) }}
                                </td>
                                <td class="px-4 py-3 text-slate-800">{{ formatMoney(tx.balance_after, tx.currency) }}</td>
                                <td class="px-4 py-3 text-slate-600">
                                    <p class="text-xs uppercase tracking-wide text-slate-400">
                                        {{ tx.reference_type }} #{{ tx.reference_id }}
                                    </p>
                                    <p v-if="tx.order">
                                        {{ t('Order') }}:
                                        <Link
                                            :href="route('admin.orders.show', tx.order.id)"
                                            class="font-medium text-cyan-700 hover:text-cyan-800"
                                        >
                                            {{ tx.order.booking_reference || tx.order.external_booking_id }}
                                        </Link>
                                    </p>
                                    <p v-if="tx.description">{{ tx.description }}</p>
                                    <p v-if="tx.created_by" class="text-xs text-slate-500">{{ tx.created_by }}</p>
                                </td>
                            </tr>
                            <tr v-if="transactions.data.length === 0">
                                <td colspan="5" class="px-4 py-10 text-center text-slate-500">
                                    {{ t('No movements recorded yet.') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <SystemTimeline :events="system_timeline" />
        </section>
    </AdminLayout>
</template>
