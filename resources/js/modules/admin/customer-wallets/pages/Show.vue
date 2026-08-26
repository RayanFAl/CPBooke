<script setup>
import AdminButton from '../../components/AdminButton.vue';
import AdminInput from '../../components/AdminInput.vue';
import AdminLayout from '../../layouts/AdminLayout.vue';
import AdminModal from '../../components/AdminModal.vue';
import AdminSelect from '../../components/AdminSelect.vue';
import AdminTextarea from '../../components/AdminTextarea.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';
import { useAdminConfirm } from '../../composables/useAdminConfirm';
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
    credit_reasons: {
        type: Array,
        default: () => [],
    },
    open_add_money: {
        type: Boolean,
        default: false,
    },
    can_manage: {
        type: Boolean,
        default: false,
    },
    can_view_user: {
        type: Boolean,
        default: false,
    },
    receipt_id: {
        type: Number,
        default: null,
    },
});

const { locale, t, backArrow } = useAdminLocale();
const { confirm } = useAdminConfirm();
const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success ?? null);
const receiptTransaction = computed(() => {
    if (!props.receipt_id) {
        return null;
    }

    return props.transactions.data.find((tx) => Number(tx.id) === Number(props.receipt_id)) ?? null;
});
const translatedFlashSuccess = computed(() => {
    const tx = receiptTransaction.value;

    if (tx) {
        const added = Math.abs(Number(tx.signed_amount ?? 0));

        return t(':admin added :amount to Customer Wallet. Before: :before. Added: +:added. After: :after.', {
            admin: tx.created_by || t('Admin'),
            amount: formatMoney(added, tx.currency),
            before: formatMoney(tx.balance_before, tx.currency),
            added: formatMoney(added, tx.currency),
            after: formatMoney(tx.balance_after, tx.currency),
        });
    }

    return flashSuccess.value ? t(flashSuccess.value) : null;
});

const creditForm = useForm({
    amount: '',
    reason: '',
    note: '',
});

const debitForm = useForm({
    amount: '',
    note: '',
});

const showAddMoney = ref(false);

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
        admin_credit: t('Admin top-up'),
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

const reasonLabel = (reason) => {
    const labels = {
        cash_received: t('Cash received'),
        bank_transfer: t('Bank transfer'),
        compensation: t('Compensation'),
        promotional: t('Promotional credit'),
        correction: t('Balance correction'),
        other: t('Other'),
    };

    return labels[reason] ?? reason;
};

const creditAmount = computed(() => {
    const amount = Number(creditForm.amount);

    return Number.isFinite(amount) && amount > 0 ? amount : 0;
});

const creditPreview = computed(() => {
    const before = Number(props.wallet.balance ?? 0);
    const added = creditAmount.value;

    return {
        before,
        added,
        after: before + added,
    };
});

const canAddMoney = computed(() => props.can_manage && !props.wallet.is_frozen);

const openAddMoney = () => {
    if (!canAddMoney.value) {
        return;
    }

    showAddMoney.value = true;
};

const closeAddMoney = () => {
    showAddMoney.value = false;
};

const submitCredit = async () => {
    if (!creditAmount.value || !creditForm.reason) {
        creditForm.post(route('admin.customer-wallets.credit', props.wallet.id), {
            preserveScroll: true,
            onSuccess: () => {
                creditForm.reset();
                showAddMoney.value = false;
            },
        });

        return;
    }

    const accepted = await confirm({
        title: 'Confirm wallet top-up',
        message: [
            t(':admin will add :amount to this customer wallet as a recorded transaction.', {
                admin: page.props.auth?.user?.full_name || page.props.auth?.user?.name || t('Admin'),
                amount: formatMoney(creditPreview.value.added, props.wallet.currency),
            }),
            `${t('Before')}: ${formatMoney(creditPreview.value.before, props.wallet.currency)}`,
            `${t('Added')}: +${formatMoney(creditPreview.value.added, props.wallet.currency)}`,
            `${t('After')}: ${formatMoney(creditPreview.value.after, props.wallet.currency)}`,
            `${t('Reason')}: ${reasonLabel(creditForm.reason)}`,
        ].join('\n'),
        confirmLabel: 'Record top-up',
        cancelLabel: 'Cancel',
        variant: 'primary',
    });

    if (!accepted) {
        return;
    }

    creditForm.post(route('admin.customer-wallets.credit', props.wallet.id), {
        preserveScroll: true,
        onSuccess: () => {
            creditForm.reset();
            showAddMoney.value = false;
        },
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

onMounted(() => {
    const hasCreditErrors = Object.keys(creditForm.errors).length > 0;

    if ((props.open_add_money && canAddMoney.value) || hasCreditErrors) {
        showAddMoney.value = true;
    }
});

watch(
    () => creditForm.errors,
    (errors) => {
        if (errors && Object.keys(errors).length > 0) {
            showAddMoney.value = true;
        }
    },
    { deep: true },
);
</script>

<template>
    <AdminLayout>
        <Head :title="`${wallet.user_name} · ${t('Customer Wallets')}`" />

        <section class="space-y-6">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <Link
                            :href="route('admin.customer-wallets.index')"
                            class="text-sm font-medium text-cyan-700 hover:text-cyan-800"
                        >
                            {{ backArrow }} {{ t('Back to customer wallets') }}
                        </Link>
                        <h2 class="mt-3 text-2xl font-semibold text-slate-950">
                            <Link
                                v-if="can_view_user"
                                :href="route('admin.users.show', wallet.user_id)"
                                class="hover:text-cyan-800"
                            >
                                {{ wallet.user_name }}
                            </Link>
                            <span v-else>{{ wallet.user_name }}</span>
                        </h2>
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

                    <div class="flex flex-col items-stretch gap-3 sm:items-end">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-5 py-4 text-right">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ t('Current balance') }}</p>
                            <p class="mt-1 text-2xl font-semibold text-slate-950">
                                {{ formatMoney(wallet.balance, wallet.currency) }}
                            </p>
                        </div>
                        <AdminButton
                            v-if="canAddMoney"
                            variant="success"
                            @click="openAddMoney"
                        >
                            {{ t('Add money') }}
                        </AdminButton>
                    </div>
                </div>

                <div
                    v-if="translatedFlashSuccess"
                    class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800"
                >
                    <p class="whitespace-pre-line">{{ translatedFlashSuccess }}</p>
                    <a
                        v-if="receiptTransaction?.print_url"
                        :href="receiptTransaction.print_url"
                        target="_blank"
                        rel="noopener"
                        class="mt-2 inline-flex font-semibold text-emerald-900 underline"
                    >
                        {{ t('Print receipt') }}
                    </a>
                </div>
                <p
                    v-else-if="wallet.is_frozen && can_manage"
                    class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800"
                >
                    {{ t('This wallet is frozen. Unfreeze it before recording an admin top-up.') }}
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

            <div v-if="can_manage && !wallet.is_frozen" class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <form class="space-y-3" @submit.prevent="submitDebit">
                    <h3 class="text-base font-semibold text-slate-950">{{ t('Deduct credit') }}</h3>
                    <p class="text-sm text-slate-600">{{ t('Manual debit from the customer wallet.') }}</p>

                    <div class="grid gap-3 lg:grid-cols-[1fr_1fr_auto] lg:items-end">
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
                    </div>
                </form>
            </div>

            <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 px-5 py-4">
                    <div>
                        <h3 class="text-base font-semibold text-slate-950">{{ t('Wallet transactions') }}</h3>
                        <p class="mt-1 text-sm text-slate-600">{{ t('Every balance change is a ledger row with amount, admin, and before/after balances.') }}</p>
                    </div>
                    <a
                        v-if="wallet.statement_print_url"
                        :href="wallet.statement_print_url"
                        target="_blank"
                        rel="noopener"
                        class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-800 hover:bg-slate-50"
                    >
                        {{ t('Print statement') }}
                    </a>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-3">{{ t('Transaction ID') }}</th>
                                <th class="px-4 py-3">{{ t('Date/Time') }}</th>
                                <th class="px-4 py-3">{{ t('Type') }}</th>
                                <th class="px-4 py-3">{{ t('Amount') }}</th>
                                <th class="px-4 py-3">{{ t('Before') }}</th>
                                <th class="px-4 py-3">{{ t('After') }}</th>
                                <th class="px-4 py-3">{{ t('Admin') }}</th>
                                <th class="px-4 py-3">{{ t('Details') }}</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="tx in transactions.data" :key="tx.id">
                                <td class="px-4 py-3 font-mono text-xs text-slate-700">
                                    <p>#{{ tx.transaction_id }}</p>
                                    <p class="mt-1 break-all text-[11px] text-slate-400">{{ tx.reference_id }}</p>
                                </td>
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
                                <td class="px-4 py-3 text-slate-700">{{ tx.created_by || '—' }}</td>
                                <td class="px-4 py-3 text-slate-600">
                                    <p v-if="tx.summary" class="font-medium text-slate-900">{{ tx.summary }}</p>
                                    <p v-if="tx.reason_label" class="mt-1">{{ t('Reason') }}: {{ t(tx.reason_label) }}</p>
                                    <p v-if="tx.note">{{ t('Note') }}: {{ tx.note }}</p>
                                    <p v-if="tx.order">
                                        {{ t('Order') }}:
                                        <Link
                                            :href="route('admin.orders.show', tx.order.id)"
                                            class="font-medium text-cyan-700 hover:text-cyan-800"
                                        >
                                            {{ tx.order.booking_reference || tx.order.external_booking_id }}
                                        </Link>
                                    </p>
                                    <p v-if="!tx.summary && tx.description">{{ tx.description }}</p>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a
                                        v-if="tx.print_url"
                                        :href="tx.print_url"
                                        target="_blank"
                                        rel="noopener"
                                        class="text-sm font-medium text-cyan-700 hover:text-cyan-800"
                                    >
                                        {{ t('Print') }}
                                    </a>
                                </td>
                            </tr>
                            <tr v-if="transactions.data.length === 0">
                                <td colspan="9" class="px-4 py-10 text-center text-slate-500">
                                    {{ t('No transactions recorded yet.') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <AdminModal
            :show="showAddMoney"
            title="Add money"
            description="Record an admin top-up as a wallet transaction. The balance is never edited directly."
            max-width="lg"
            @close="closeAddMoney"
        >
            <form class="space-y-4" @submit.prevent="submitCredit">
                <div class="grid gap-4 sm:grid-cols-2">
                    <AdminInput
                        v-model="creditForm.amount"
                        :label="t('Amount')"
                        type="number"
                        min="0.01"
                        step="0.01"
                        required
                        :error="creditForm.errors.amount"
                    />
                    <AdminInput
                        :model-value="wallet.currency"
                        :label="t('Currency')"
                        disabled
                    />
                </div>

                <AdminSelect
                    v-model="creditForm.reason"
                    :label="t('Reason')"
                    required
                    :error="creditForm.errors.reason"
                >
                    <option value="" disabled>{{ t('Select a reason') }}</option>
                    <option
                        v-for="reason in credit_reasons"
                        :key="reason.value"
                        :value="reason.value"
                    >
                        {{ t(reason.label) }}
                    </option>
                </AdminSelect>

                <AdminTextarea
                    v-model="creditForm.note"
                    :label="t('Note')"
                    :rows="3"
                    :placeholder="t('Optional context for this top-up')"
                    :error="creditForm.errors.note"
                />

                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm">
                    <p class="font-medium text-slate-900">{{ t('Preview') }}</p>
                    <dl class="mt-3 grid gap-2 sm:grid-cols-3">
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-slate-500">{{ t('Before') }}</dt>
                            <dd class="mt-1 text-slate-800">{{ formatMoney(creditPreview.before, wallet.currency) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-slate-500">{{ t('Added') }}</dt>
                            <dd class="mt-1 font-medium text-emerald-700">+{{ formatMoney(creditPreview.added, wallet.currency) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-slate-500">{{ t('After') }}</dt>
                            <dd class="mt-1 font-semibold text-slate-950">{{ formatMoney(creditPreview.after, wallet.currency) }}</dd>
                        </div>
                    </dl>
                </div>
            </form>

            <template #footer>
                <AdminButton variant="secondary" @click="closeAddMoney">
                    {{ t('Cancel') }}
                </AdminButton>
                <AdminButton
                    variant="success"
                    :processing="creditForm.processing"
                    @click="submitCredit"
                >
                    {{ t('Record top-up') }}
                </AdminButton>
            </template>
        </AdminModal>
    </AdminLayout>
</template>
