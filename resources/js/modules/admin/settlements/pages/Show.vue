<script setup>
import AdminLayout from '../../layouts/AdminLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import { useAdminLocale } from '../../composables/useAdminLocale';

const props = defineProps({
    settlement: { type: Object, required: true },
    items: { type: Object, required: true },
    attachments: { type: Array, default: () => [] },
    invoice_imports: { type: Array, default: () => [] },
    filters: { type: Object, required: true },
    can_manage: { type: Boolean, default: false },
    can_reopen: { type: Boolean, default: false },
    item_statuses: { type: Array, default: () => [] },
    resolution_reasons: { type: Object, default: () => ({}) },
    provider_api_wallets: {
        type: Object,
        default: () => ({
            available: false,
            error: null,
            wallet_count: 0,
            wallets: [],
            fetched_at: null,
        }),
    },
});

const { locale, t, backArrow, forwardArrow, settlementStatusLabel, settlementItemStatusLabel } = useAdminLocale();
const resolvingId = ref(null);

const filterForm = reactive({
    item_status: props.filters.item_status ?? '',
});

const invoiceForm = useForm({
    csv_text: '',
    invoice_file: null,
    attachment: null,
});

const attachmentForm = useForm({
    file: null,
});

const resolveForm = useForm({
    resolution: 'accept_variance',
    reason: 'extra_supplier_fee',
    resolution_note: '',
    amount: '',
    booking_reference: '',
    order_id: '',
    supplier_invoice_cost: '',
    drop_invoice_line: false,
});

const reopenForm = useForm({
    reason: '',
});

const reasonOptions = computed(() => Object.entries(props.resolution_reasons)
    .filter(([, config]) => config.resolution === resolveForm.resolution)
    .map(([value, config]) => ({ value, posts: config.posts_ledger })));

const canMutate = computed(() => props.can_manage && props.settlement.can_mutate);
const canClose = computed(() => props.can_manage
    && ['open', 'approved', 'reopened'].includes(props.settlement.status)
    && Number(props.settlement.review_count) === 0
    && Number(props.settlement.pending_approvals) === 0);
const canApprove = computed(() => props.can_manage
    && props.settlement.status === 'open'
    && Number(props.settlement.review_count) === 0
    && Number(props.settlement.pending_approvals) === 0);

const workflowStep = computed(() => {
    if (props.settlement.status === 'closed') {
        return 4;
    }

    if (canClose.value || props.settlement.status === 'approved') {
        return 4;
    }

    if (Number(props.settlement.review_count) > 0) {
        return 3;
    }

    if (Number(props.settlement.supplier_invoice_total) > 0 || props.invoice_imports.length > 0) {
        return 3;
    }

    return 2;
});

const applyItemFilter = () => {
    router.get(route('admin.settlements.show', props.settlement.id), {
        ...(filterForm.item_status ? { item_status: filterForm.item_status } : {}),
    }, { preserveState: true, preserveScroll: true, replace: true });
};

const importInvoice = () => {
    invoiceForm.transform((data) => {
        const payload = { csv_text: data.csv_text };
        if (data.invoice_file) {
            payload.invoice_file = data.invoice_file;
        }
        if (data.attachment) {
            payload.attachment = data.attachment;
        }
        return payload;
    }).post(route('admin.settlements.import-invoice', props.settlement.id), {
        preserveScroll: true,
        forceFormData: true,
    });
};

const uploadAttachment = () => {
    attachmentForm.post(route('admin.settlements.attachments.store', props.settlement.id), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => attachmentForm.reset(),
    });
};

const compare = () => {
    router.post(route('admin.settlements.compare', props.settlement.id), {}, { preserveScroll: true });
};

const approvePeriod = () => {
    router.post(route('admin.settlements.approve', props.settlement.id), {}, { preserveScroll: true });
};

const closePeriod = () => {
    router.post(route('admin.settlements.close', props.settlement.id), {}, { preserveScroll: true });
};

const reopenPeriod = () => {
    reopenForm.post(route('admin.settlements.reopen', props.settlement.id), { preserveScroll: true });
};

const openResolve = (item) => {
    resolvingId.value = item.id;
    resolveForm.reset();
    resolveForm.clearErrors();
    resolveForm.resolution = 'accept_variance';
    resolveForm.reason = 'extra_supplier_fee';
    resolveForm.amount = item.difference ?? '';
    resolveForm.booking_reference = item.booking_reference ?? '';
    resolveForm.order_id = item.order_id ?? '';
};

const submitResolve = () => {
    if (!resolvingId.value) {
        return;
    }

    resolveForm.post(route('admin.settlements.items.resolve', [props.settlement.id, resolvingId.value]), {
        preserveScroll: true,
        onSuccess: () => {
            resolvingId.value = null;
            resolveForm.reset();
        },
    });
};

const formatMoney = (amount, currency) => new Intl.NumberFormat(locale.value, {
    style: 'currency',
    currency: currency || props.settlement.currency || 'LYD',
}).format(Number(amount ?? 0));

const formatFetchedAt = (value) => {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat(locale.value, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
};

const workflowSteps = [
    { n: 1, label: 'Create period' },
    { n: 2, label: 'Import invoice' },
    { n: 3, label: 'Fix differences' },
    { n: 4, label: 'Approve & close' },
];
</script>

<template>
    <AdminLayout>
        <Head :title="`${t('Settlement')} #${settlement.id}`" />

        <section class="space-y-6">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <Link :href="route('admin.settlements.index')" class="text-sm font-medium text-cyan-700 hover:text-cyan-800">
                    {{ backArrow }} {{ t('Settlements') }}
                </Link>
                <div class="mt-4 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.25em] text-cyan-700">{{ settlement.provider_name }}</p>
                        <h2 class="mt-2 text-2xl font-semibold text-slate-950">
                            {{ settlement.period_start }} {{ forwardArrow }} {{ settlement.period_end }}
                        </h2>
                        <p class="mt-2 text-sm text-slate-600">
                            {{ settlementStatusLabel(settlement.status) }} · {{ settlement.orders_count }} {{ t('orders') }}
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a
                            v-if="settlement.print_url"
                            :href="settlement.print_url"
                            target="_blank"
                            rel="noopener"
                            class="rounded-xl border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50"
                        >
                            {{ t('Print report') }}
                        </a>
                        <a
                            v-if="settlement.export_csv_url"
                            :href="settlement.export_csv_url"
                            class="rounded-xl border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50"
                        >
                            {{ t('Export CSV') }}
                        </a>
                        <button v-if="canApprove" type="button" class="rounded-xl border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50" @click="approvePeriod">
                            {{ t('Approve period') }}
                        </button>
                        <button v-if="canClose" type="button" class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800" @click="closePeriod">
                            {{ t('Close period') }}
                        </button>
                    </div>
                </div>

                <div class="mt-6 grid gap-2 sm:grid-cols-4">
                    <div
                        v-for="step in workflowSteps"
                        :key="step.n"
                        class="rounded-xl border px-3 py-2 text-center text-xs"
                        :class="workflowStep >= step.n ? 'border-cyan-300 bg-cyan-50 text-cyan-950' : 'border-slate-200 bg-slate-50 text-slate-500'"
                    >
                        <span class="font-semibold">{{ step.n }}.</span> {{ t(step.label) }}
                    </div>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs text-slate-500">{{ t('Booke cost') }}</p>
                    <p class="mt-2 text-xl font-semibold">{{ settlement.expected_cost }}</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs text-slate-500">{{ t('Invoice amount') }}</p>
                    <p class="mt-2 text-xl font-semibold">{{ settlement.supplier_invoice_total || '—' }}</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs text-slate-500">{{ t('Difference') }}</p>
                    <p class="mt-2 text-xl font-semibold" :class="Number(settlement.difference) !== 0 ? 'text-amber-700' : 'text-slate-950'">
                        {{ settlement.difference }}
                    </p>
                    <p class="mt-1 text-xs text-slate-500">{{ settlement.review_count }} {{ t('Need review') }}</p>
                </div>
            </div>

            <div class="rounded-3xl border border-cyan-200 bg-cyan-50 p-5 shadow-sm">
                <div class="flex flex-col gap-2 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <h3 class="text-sm font-semibold text-cyan-950">{{ t('Supplier portal balance (reference)') }}</h3>
                        <p class="mt-1 text-xs text-cyan-900">{{ t('For settlement comparison only — not stored in Booke.') }}</p>
                    </div>
                    <p v-if="provider_api_wallets.fetched_at" class="text-xs text-cyan-800">
                        {{ t('Fetched at') }}: {{ formatFetchedAt(provider_api_wallets.fetched_at) }}
                    </p>
                </div>
                <p v-if="provider_api_wallets.error" class="mt-3 text-sm text-rose-800">{{ provider_api_wallets.error }}</p>
                <p
                    v-else-if="provider_api_wallets.available && provider_api_wallets.wallet_count === 0"
                    class="mt-3 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-900"
                >
                    {{ t('Connected to supplier portal. Atom returned 0 wallets for this tenant.') }}
                </p>
                <p v-else-if="provider_api_wallets.wallet_count === 0" class="mt-3 text-sm text-cyan-900">
                    {{ t('No provider wallets returned by the API.') }}
                </p>
                <div v-else class="mt-3 flex flex-wrap gap-3">
                    <div v-for="wallet in provider_api_wallets.wallets" :key="wallet.currency" class="rounded-xl border border-cyan-200 bg-white px-4 py-3">
                        <p class="text-xs text-slate-500">{{ wallet.currency }}</p>
                        <p class="text-lg font-semibold">{{ formatMoney(wallet.balance, wallet.currency) }}</p>
                    </div>
                    <p class="self-center text-xs text-cyan-800">
                        {{ provider_api_wallets.wallet_count }} {{ provider_api_wallets.wallet_count === 1 ? t('wallet') : t('wallets') }}
                    </p>
                </div>
            </div>

            <div v-if="canMutate" class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-base font-semibold text-slate-950">{{ t('Import invoice') }}</h3>
                <p class="mt-1 text-sm text-slate-600">{{ t('Paste CSV or upload CSV/XLSX: booking_reference,amount') }}</p>
                <form class="mt-4 space-y-3" @submit.prevent="importInvoice">
                    <textarea
                        v-model="invoiceForm.csv_text"
                        rows="4"
                        class="w-full rounded-xl border border-slate-300 px-3 py-2.5 font-mono text-sm"
                        placeholder="BK-1001,531.00&#10;BK-1002,420.50"
                    />
                    <input type="file" accept=".csv,.txt,.xlsx,.xlsm" class="block w-full text-sm" @change="invoiceForm.invoice_file = $event.target.files[0]">
                    <div class="flex gap-2">
                        <button type="submit" class="rounded-xl bg-cyan-700 px-4 py-2 text-sm font-medium text-white hover:bg-cyan-800" :disabled="invoiceForm.processing">
                            {{ t('Import & compare') }}
                        </button>
                        <button type="button" class="rounded-xl border border-slate-300 px-4 py-2 text-sm" @click="compare">
                            {{ t('Re-compare') }}
                        </button>
                    </div>
                </form>
            </div>

            <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <h3 class="text-base font-semibold text-slate-950">{{ t('Line items') }}</h3>
                    <form class="flex gap-2" @submit.prevent="applyItemFilter">
                        <select v-model="filterForm.item_status" class="rounded-xl border border-slate-300 px-3 py-2 text-sm">
                            <option value="">{{ t('All item statuses') }}</option>
                            <option v-for="status in item_statuses" :key="status" :value="status">
                                {{ settlementItemStatusLabel(status) }}
                            </option>
                        </select>
                        <button type="submit" class="rounded-xl bg-slate-950 px-3 py-2 text-sm font-medium text-white">{{ t('Filter') }}</button>
                    </form>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-3">{{ t('Booking') }}</th>
                                <th class="px-4 py-3">{{ t('Booke cost') }}</th>
                                <th class="px-4 py-3">{{ t('Invoice amount') }}</th>
                                <th class="px-4 py-3">{{ t('Difference') }}</th>
                                <th class="px-4 py-3">{{ t('Status') }}</th>
                                <th class="px-4 py-3">{{ t('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="item in items.data" :key="item.id" class="hover:bg-slate-50/80">
                                <td class="px-4 py-3 font-medium">{{ item.booking_reference || '—' }}</td>
                                <td class="px-4 py-3">{{ item.supplier_cost ?? item.wallet_debit ?? '—' }}</td>
                                <td class="px-4 py-3">{{ item.supplier_invoice_cost ?? '—' }}</td>
                                <td class="px-4 py-3">{{ item.difference }}</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">
                                        {{ settlementItemStatusLabel(item.status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <button
                                        v-if="canMutate && item.needs_review && !item.pending_approval_id"
                                        type="button"
                                        class="text-xs font-medium text-cyan-700 hover:text-cyan-800"
                                        @click="openResolve(item)"
                                    >
                                        {{ t('Resolve') }}
                                    </button>
                                    <span v-else class="text-xs text-slate-400">—</span>
                                </td>
                            </tr>
                            <tr v-if="items.data.length === 0">
                                <td colspan="6" class="px-4 py-10 text-center text-slate-500">{{ t('No settlement items.') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-if="resolvingId" class="rounded-3xl border border-cyan-200 bg-cyan-50 p-5 shadow-sm">
                <h3 class="text-sm font-semibold text-cyan-950">{{ t('Resolve item') }}</h3>
                <form class="mt-3 space-y-3" @submit.prevent="submitResolve">
                    <div class="flex flex-wrap gap-2">
                        <button
                            type="button"
                            class="rounded-xl px-4 py-2 text-sm"
                            :class="resolveForm.resolution === 'accept_variance' ? 'bg-cyan-700 text-white' : 'border border-cyan-200 bg-white text-cyan-950'"
                            @click="resolveForm.resolution = 'accept_variance'"
                        >
                            {{ t('Accept difference') }}
                        </button>
                        <button
                            type="button"
                            class="rounded-xl px-4 py-2 text-sm"
                            :class="resolveForm.resolution === 'correct_data' ? 'bg-cyan-700 text-white' : 'border border-cyan-200 bg-white text-cyan-950'"
                            @click="resolveForm.resolution = 'correct_data'"
                        >
                            {{ t('Fix invoice line') }}
                        </button>
                    </div>
                    <input
                        v-if="resolveForm.resolution === 'accept_variance'"
                        v-model="resolveForm.amount"
                        type="number"
                        step="0.01"
                        class="w-full rounded-xl border border-cyan-200 bg-white px-3 py-2 text-sm"
                        :placeholder="t('Adjustment amount')"
                    >
                    <template v-if="resolveForm.resolution === 'correct_data'">
                        <input v-model="resolveForm.booking_reference" class="w-full rounded-xl border border-cyan-200 bg-white px-3 py-2 text-sm" :placeholder="t('Booking reference')">
                        <input v-model="resolveForm.supplier_invoice_cost" type="number" step="0.01" class="w-full rounded-xl border border-cyan-200 bg-white px-3 py-2 text-sm" :placeholder="t('Invoice amount')">
                    </template>
                    <textarea v-model="resolveForm.resolution_note" rows="2" class="w-full rounded-xl border border-cyan-200 bg-white px-3 py-2 text-sm" :placeholder="t('Explain how this variance was handled')" />
                    <details>
                        <summary class="cursor-pointer text-xs font-medium text-cyan-900">{{ t('More resolution options') }}</summary>
                        <select v-model="resolveForm.reason" class="mt-2 w-full rounded-xl border border-cyan-200 bg-white px-3 py-2 text-sm">
                            <option v-for="reason in reasonOptions" :key="reason.value" :value="reason.value">
                                {{ settlementItemStatusLabel(reason.value) }}
                            </option>
                        </select>
                    </details>
                    <div class="flex gap-2">
                        <button type="submit" class="rounded-xl bg-cyan-700 px-4 py-2 text-sm font-medium text-white">{{ t('Confirm resolve') }}</button>
                        <button type="button" class="rounded-xl border border-cyan-200 px-4 py-2 text-sm" @click="resolvingId = null">{{ t('Cancel') }}</button>
                    </div>
                </form>
            </div>

            <details class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <summary class="cursor-pointer text-base font-semibold text-slate-950">{{ t('More details') }}</summary>
                <div class="mt-4 space-y-6">
                    <div v-if="settlement.close_snapshot" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm">
                        <h4 class="font-semibold text-emerald-950">{{ t('Close snapshot') }}</h4>
                        <p class="mt-2 text-emerald-900">{{ settlement.close_snapshot.expected_total }} · {{ settlement.close_snapshot.invoice_total }} · {{ settlement.close_snapshot.variance_total }}</p>
                    </div>

                    <div>
                        <h4 class="text-sm font-semibold">{{ t('Invoice history') }}</h4>
                        <ul class="mt-2 space-y-1 text-sm text-slate-600">
                            <li v-for="item in invoice_imports" :key="item.id">
                                #{{ item.sequence }} · {{ item.row_count }} {{ t('rows') }}
                                <span v-if="item.is_active" class="text-cyan-700">({{ t('Active') }})</span>
                            </li>
                            <li v-if="invoice_imports.length === 0">{{ t('No invoice imports yet.') }}</li>
                        </ul>
                    </div>

                    <div>
                        <h4 class="text-sm font-semibold">{{ t('Attachments') }}</h4>
                        <ul class="mt-2 space-y-1 text-sm">
                            <li v-for="file in attachments" :key="file.id">
                                <a :href="route('admin.settlements.attachments.download', [settlement.id, file.id])" class="text-cyan-700 hover:text-cyan-800">
                                    {{ file.original_name }}
                                </a>
                            </li>
                            <li v-if="attachments.length === 0" class="text-slate-500">{{ t('No attachments yet.') }}</li>
                        </ul>
                        <form v-if="canMutate" class="mt-3 flex gap-2" @submit.prevent="uploadAttachment">
                            <input type="file" accept=".csv,.txt,.xlsx,.xlsm,.pdf" class="text-sm" @change="attachmentForm.file = $event.target.files[0]">
                            <button type="submit" class="rounded-xl border border-slate-300 px-3 py-1.5 text-sm" :disabled="attachmentForm.processing">
                                {{ t('Upload attachment') }}
                            </button>
                        </form>
                    </div>

                    <form
                        v-if="can_reopen && settlement.status === 'closed'"
                        class="space-y-2"
                        @submit.prevent="reopenPeriod"
                    >
                        <h4 class="text-sm font-semibold text-amber-950">{{ t('Reopen period') }}</h4>
                        <textarea v-model="reopenForm.reason" rows="2" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                        <button type="submit" class="rounded-xl bg-amber-700 px-4 py-2 text-sm font-medium text-white">{{ t('Reopen') }}</button>
                    </form>
                </div>
            </details>
        </section>
    </AdminLayout>
</template>
