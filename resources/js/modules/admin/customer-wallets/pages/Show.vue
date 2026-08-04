<script setup>
import AdminLayout from '../../layouts/AdminLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useAdminLocale } from '../../composables/useAdminLocale';

const props = defineProps({
    wallet: {
        type: Object,
        required: true,
    },
    transactions: {
        type: Object,
        required: true,
    },
    can_manage: {
        type: Boolean,
        default: false,
    },
});

const { locale, t } = useAdminLocale();
const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success ?? null);

const creditForm = useForm({
    amount: '',
    note: '',
});

const debitForm = useForm({
    amount: '',
    note: '',
});

const formatMoney = (amount, currency) => new Intl.NumberFormat(locale.value, {
    style: 'currency',
    currency: currency || props.wallet.currency || 'LYD',
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
    const labels = {
        admin_credit: t('Admin credit'),
        admin_debit: t('Admin debit'),
        booking: t('Booking'),
        refund: t('Refund'),
        credit: t('Credit'),
        debit: t('Debit'),
        bonus: t('Bonus'),
        adjustment: t('Adjustment'),
    };

    return labels[type] ?? type;
};

const submitCredit = () => {
    creditForm.post(route('admin.customer-wallets.credit', props.wallet.id), {
        preserveScroll: true,
        onSuccess: () => creditForm.reset(),
    });
};

const submitDebit = () => {
    debitForm.post(route('admin.customer-wallets.debit', props.wallet.id), {
        preserveScroll: true,
        onSuccess: () => debitForm.reset(),
    });
};

const freezeWallet = () => {
    useForm({}).post(route('admin.customer-wallets.freeze', props.wallet.id), {
        preserveScroll: true,
    });
};

const unfreezeWallet = () => {
    useForm({}).post(route('admin.customer-wallets.unfreeze', props.wallet.id), {
        preserveScroll: true,
    });
};
</script>

<template>
    <AdminLayout>
        <Head :title="wallet.user_name" />

        <section class="space-y-6">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <Link
                            :href="route('admin.customer-wallets.index')"
                            class="text-sm font-medium text-cyan-700 hover:text-cyan-800"
                        >
                            ← {{ t('Back to customer wallets') }}
                        </Link>
                        <h2 class="mt-3 text-2xl font-semibold text-slate-950">{{ wallet.user_name }}</h2>
                        <p class="mt-1 text-sm text-slate-500">
                            {{ wallet.wallet_number }} · {{ wallet.currency }}
                        </p>
                        <p class="mt-1 text-sm text-slate-500">{{ wallet.user_email }}</p>
                        <p class="mt-2 text-xs text-slate-500">
                            <span
                                class="inline-flex rounded-full px-2.5 py-1 font-medium"
                                :class="wallet.is_frozen ? 'bg-rose-100 text-rose-800' : 'bg-emerald-100 text-emerald-800'"
                            >
                                {{ wallet.is_frozen ? t('Frozen') : t('Active') }}
                            </span>
                        </p>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-5 py-4 text-right">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ t('Current balance') }}</p>
                        <p class="mt-1 text-2xl font-semibold text-slate-950">
                            {{ formatMoney(wallet.balance, wallet.currency) }}
                        </p>
                    </div>
                </div>

                <p
                    v-if="flashSuccess"
                    class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800"
                >
                    {{ flashSuccess }}
                </p>

                <div v-if="can_manage" class="mt-4 flex flex-wrap gap-2">
                    <button
                        v-if="!wallet.is_frozen"
                        type="button"
                        class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-2 text-sm font-medium text-rose-800 hover:bg-rose-100"
                        @click="freezeWallet"
                    >
                        {{ t('Freeze wallet') }}
                    </button>
                    <button
                        v-else
                        type="button"
                        class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-medium text-emerald-800 hover:bg-emerald-100"
                        @click="unfreezeWallet"
                    >
                        {{ t('Unfreeze wallet') }}
                    </button>
                </div>
            </div>

            <div v-if="can_manage" class="grid gap-4 lg:grid-cols-2">
                <form class="space-y-3 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm" @submit.prevent="submitCredit">
                    <h3 class="text-base font-semibold text-slate-950">{{ t('Add credit') }}</h3>
                    <p class="text-sm text-slate-600">{{ t('Manual admin credit for testing or compensation.') }}</p>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-800">{{ t('Amount') }}</label>
                        <input
                            v-model="creditForm.amount"
                            type="number"
                            min="0.01"
                            step="0.01"
                            class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"
                            required
                        >
                        <p v-if="creditForm.errors.amount" class="mt-1 text-sm text-rose-600">{{ creditForm.errors.amount }}</p>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-800">{{ t('Note') }}</label>
                        <input
                            v-model="creditForm.note"
                            type="text"
                            class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"
                        >
                    </div>

                    <button
                        type="submit"
                        class="rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-medium text-white hover:bg-emerald-800 disabled:opacity-60"
                        :disabled="creditForm.processing"
                    >
                        {{ t('Add credit') }}
                    </button>
                </form>

                <form class="space-y-3 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm" @submit.prevent="submitDebit">
                    <h3 class="text-base font-semibold text-slate-950">{{ t('Deduct credit') }}</h3>
                    <p class="text-sm text-slate-600">{{ t('Manual debit from the customer wallet.') }}</p>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-800">{{ t('Amount') }}</label>
                        <input
                            v-model="debitForm.amount"
                            type="number"
                            min="0.01"
                            step="0.01"
                            class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"
                            required
                        >
                        <p v-if="debitForm.errors.amount" class="mt-1 text-sm text-rose-600">{{ debitForm.errors.amount }}</p>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-800">{{ t('Note') }}</label>
                        <input
                            v-model="debitForm.note"
                            type="text"
                            class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"
                        >
                    </div>

                    <button
                        type="submit"
                        class="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-medium text-white hover:bg-slate-800 disabled:opacity-60"
                        :disabled="debitForm.processing"
                    >
                        {{ t('Deduct credit') }}
                    </button>
                </form>
            </div>

            <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h3 class="text-base font-semibold text-slate-950">{{ t('Transaction history') }}</h3>
                    <p class="mt-1 text-sm text-slate-600">{{ t('Credits, bookings, refunds, and adjustments.') }}</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-3">{{ t('When') }}</th>
                                <th class="px-4 py-3">{{ t('Type') }}</th>
                                <th class="px-4 py-3">{{ t('Amount') }}</th>
                                <th class="px-4 py-3">{{ t('Before') }}</th>
                                <th class="px-4 py-3">{{ t('After') }}</th>
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
                                <td class="px-4 py-3 text-slate-800">{{ formatMoney(tx.balance_before, tx.currency) }}</td>
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
                                <td colspan="6" class="px-4 py-10 text-center text-slate-500">
                                    {{ t('No transactions recorded yet.') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </AdminLayout>
</template>
