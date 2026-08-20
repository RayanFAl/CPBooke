<script setup>
import AdminLayout from '../../layouts/AdminLayout.vue';
import SystemTimeline from '../../components/SystemTimeline.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import { useAdminLocale } from '../../composables/useAdminLocale';

const props = defineProps({
    settlement: { type: Object, required: true },
    items: { type: Object, required: true },
    attachments: { type: Array, default: () => [] },
    filters: { type: Object, required: true },
    can_manage: { type: Boolean, default: false },
    can_reopen: { type: Boolean, default: false },
    item_statuses: { type: Array, default: () => [] },
    resolution_reasons: { type: Object, default: () => ({}) },
    system_timeline: { type: Array, default: () => [] },
});

const { t } = useAdminLocale();
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

const formatStatus = (status) => status.replaceAll('_', ' ');
</script>

<template>
    <AdminLayout>
        <Head :title="`${t('Settlement')} #${settlement.id}`" />

        <section class="space-y-6">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <Link :href="route('admin.settlements.index')" class="text-sm font-medium text-cyan-700 hover:text-cyan-800">
                    ← {{ t('Settlements') }}
                </Link>
                <div class="mt-4 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.25em] text-cyan-700">{{ settlement.provider_name }}</p>
                        <h2 class="mt-2 text-2xl font-semibold text-slate-950">
                            {{ settlement.period_start }} → {{ settlement.period_end }}
                        </h2>
                        <p class="mt-2 text-sm text-slate-600">
                            {{ settlement.currency }} · {{ settlement.status }} · {{ settlement.orders_count }} {{ t('orders') }}
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-if="canMutate"
                            type="button"
                            class="rounded-xl border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50"
                            @click="compare"
                        >
                            {{ t('Re-compare') }}
                        </button>
                        <button
                            v-if="canApprove"
                            type="button"
                            class="rounded-xl border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50"
                            @click="approvePeriod"
                        >
                            {{ t('Approve period') }}
                        </button>
                        <button
                            v-if="canClose"
                            type="button"
                            class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800"
                            @click="closePeriod"
                        >
                            {{ t('Close period') }}
                        </button>
                    </div>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs uppercase tracking-wide text-slate-500">{{ t('Expected') }}</p>
                    <p class="mt-2 text-xl font-semibold text-slate-950">{{ settlement.expected_cost }}</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs uppercase tracking-wide text-slate-500">{{ t('Wallet') }}</p>
                    <p class="mt-2 text-xl font-semibold text-slate-950">{{ settlement.wallet_debit_total }}</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs uppercase tracking-wide text-slate-500">{{ t('Invoice') }}</p>
                    <p class="mt-2 text-xl font-semibold text-slate-950">{{ settlement.supplier_invoice_total }}</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs uppercase tracking-wide text-slate-500">{{ t('Difference') }}</p>
                    <p class="mt-2 text-xl font-semibold" :class="Number(settlement.difference) !== 0 ? 'text-amber-700' : 'text-slate-950'">
                        {{ settlement.difference }}
                    </p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs uppercase tracking-wide text-slate-500">{{ t('Need review') }}</p>
                    <p class="mt-2 text-xl font-semibold text-slate-950">
                        {{ settlement.review_count }}
                        <span class="text-sm font-normal text-slate-500">/ {{ settlement.matched_count }} {{ t('matched') }}</span>
                    </p>
                    <p class="mt-1 text-xs text-slate-500">{{ settlement.pending_approvals }} {{ t('pending approvals') }}</p>
                </div>
            </div>

            <div v-if="canMutate" class="grid gap-6 lg:grid-cols-2">
                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-sm font-semibold text-slate-950">{{ t('Import supplier invoice') }}</h3>
                    <p class="mt-1 text-sm text-slate-600">
                        {{ t('Paste CSV or upload CSV/XLSX: booking_reference,amount') }}
                    </p>
                    <form class="mt-4 space-y-3" @submit.prevent="importInvoice">
                        <textarea
                            v-model="invoiceForm.csv_text"
                            rows="5"
                            class="w-full rounded-xl border border-slate-300 px-3 py-2.5 font-mono text-sm"
                            placeholder="BK-1001,531.00&#10;BK-1002,420.50"
                        />
                        <input
                            type="file"
                            accept=".csv,.txt,.xlsx,.xlsm"
                            class="block w-full text-sm"
                            @change="invoiceForm.invoice_file = $event.target.files[0]"
                        >
                        <p v-if="invoiceForm.errors.lines" class="text-xs text-rose-600">{{ invoiceForm.errors.lines }}</p>
                        <button type="submit" class="rounded-xl bg-cyan-700 px-4 py-2 text-sm font-medium text-white hover:bg-cyan-800" :disabled="invoiceForm.processing">
                            {{ t('Import & compare') }}
                        </button>
                    </form>
                </div>
                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-sm font-semibold text-slate-950">{{ t('Attachments') }}</h3>
                    <p class="mt-1 text-sm text-slate-600">{{ t('Store the provider invoice PDF, CSV, or Excel on this period.') }}</p>
                    <form class="mt-4 space-y-3" @submit.prevent="uploadAttachment">
                        <input
                            type="file"
                            accept=".csv,.txt,.xlsx,.xlsm,.pdf"
                            class="block w-full text-sm"
                            @change="attachmentForm.file = $event.target.files[0]"
                        >
                        <button type="submit" class="rounded-xl border border-slate-300 px-4 py-2 text-sm" :disabled="attachmentForm.processing">
                            {{ t('Upload attachment') }}
                        </button>
                    </form>
                    <ul class="mt-4 space-y-2 text-sm">
                        <li v-for="file in attachments" :key="file.id">
                            <a
                                :href="route('admin.settlements.attachments.download', [settlement.id, file.id])"
                                class="text-cyan-700 hover:text-cyan-800"
                            >
                                {{ file.original_name }}
                            </a>
                            <span class="text-xs text-slate-500"> · {{ file.kind }}</span>
                        </li>
                        <li v-if="attachments.length === 0" class="text-slate-500">{{ t('No attachments yet.') }}</li>
                    </ul>
                </div>
            </div>

            <div v-else class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-sm font-semibold text-slate-950">{{ t('Attachments') }}</h3>
                <ul class="mt-4 space-y-2 text-sm">
                    <li v-for="file in attachments" :key="file.id">
                        <a
                            :href="route('admin.settlements.attachments.download', [settlement.id, file.id])"
                            class="text-cyan-700 hover:text-cyan-800"
                        >
                            {{ file.original_name }}
                        </a>
                    </li>
                </ul>
            </div>

            <form
                v-if="can_reopen && settlement.status === 'closed'"
                class="rounded-3xl border border-amber-200 bg-amber-50 p-5 shadow-sm space-y-3"
                @submit.prevent="reopenPeriod"
            >
                <h3 class="text-sm font-semibold text-amber-950">{{ t('Reopen period') }}</h3>
                <textarea v-model="reopenForm.reason" rows="2" class="w-full rounded-xl border border-amber-200 bg-white px-3 py-2 text-sm" />
                <p v-if="reopenForm.errors.reason" class="text-xs text-rose-700">{{ reopenForm.errors.reason }}</p>
                <button type="submit" class="rounded-xl bg-amber-700 px-4 py-2 text-sm font-medium text-white">{{ t('Reopen') }}</button>
            </form>

            <form class="flex gap-3" @submit.prevent="applyItemFilter">
                <select v-model="filterForm.item_status" class="rounded-xl border border-slate-300 px-3 py-2 text-sm">
                    <option value="">{{ t('All item statuses') }}</option>
                    <option v-for="status in item_statuses" :key="status" :value="status">{{ formatStatus(status) }}</option>
                </select>
                <button type="submit" class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-medium text-white">{{ t('Filter') }}</button>
            </form>

            <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3">{{ t('Booking') }}</th>
                            <th class="px-4 py-3">{{ t('Cost') }}</th>
                            <th class="px-4 py-3">{{ t('Wallet') }}</th>
                            <th class="px-4 py-3">{{ t('Invoice') }}</th>
                            <th class="px-4 py-3">{{ t('Difference') }}</th>
                            <th class="px-4 py-3">{{ t('Status') }}</th>
                            <th class="px-4 py-3">{{ t('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="item in items.data" :key="item.id" class="hover:bg-slate-50/80">
                            <td class="px-4 py-3">
                                <p class="font-medium text-slate-950">{{ item.booking_reference || '—' }}</p>
                                <p class="text-xs text-slate-500">
                                    <span v-if="item.order_id">Order #{{ item.order_id }} · {{ item.expected_cost_source }}</span>
                                    <span v-else>{{ t('Invoice only') }}</span>
                                </p>
                            </td>
                            <td class="px-4 py-3">{{ item.supplier_cost ?? '—' }}</td>
                            <td class="px-4 py-3">{{ item.wallet_debit ?? '—' }}</td>
                            <td class="px-4 py-3">{{ item.supplier_invoice_cost ?? '—' }}</td>
                            <td class="px-4 py-3">{{ item.difference }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium uppercase tracking-wide text-slate-700">
                                    {{ formatStatus(item.status) }}
                                </span>
                                <p v-if="item.pending_approval_id" class="mt-1 text-xs text-amber-700">{{ t('Pending approval') }}</p>
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
                            <td colspan="7" class="px-4 py-10 text-center text-slate-500">{{ t('No settlement items.') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="resolvingId" class="rounded-3xl border border-cyan-200 bg-cyan-50 p-5 shadow-sm">
                <h3 class="text-sm font-semibold text-cyan-950">{{ t('Resolve item') }} #{{ resolvingId }}</h3>
                <form class="mt-3 space-y-3" @submit.prevent="submitResolve">
                    <select v-model="resolveForm.resolution" class="w-full rounded-xl border border-cyan-200 bg-white px-3 py-2 text-sm">
                        <option value="accept_variance">{{ t('Accept variance') }}</option>
                        <option value="correct_data">{{ t('Correct data') }}</option>
                    </select>
                    <select v-model="resolveForm.reason" class="w-full rounded-xl border border-cyan-200 bg-white px-3 py-2 text-sm">
                        <option v-for="reason in reasonOptions" :key="reason.value" :value="reason.value">{{ formatStatus(reason.value) }}</option>
                    </select>
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
                        <label class="flex items-center gap-2 text-sm text-cyan-950">
                            <input v-model="resolveForm.drop_invoice_line" type="checkbox">
                            {{ t('Drop unmatched invoice line') }}
                        </label>
                    </template>
                    <textarea
                        v-model="resolveForm.resolution_note"
                        rows="3"
                        class="w-full rounded-xl border border-cyan-200 bg-white px-3 py-2 text-sm"
                        :placeholder="t('Explain how this variance was handled')"
                    />
                    <p v-if="resolveForm.errors.resolution" class="text-xs text-rose-700">{{ resolveForm.errors.resolution }}</p>
                    <p v-if="resolveForm.errors.reason" class="text-xs text-rose-700">{{ resolveForm.errors.reason }}</p>
                    <p v-if="resolveForm.errors.amount" class="text-xs text-rose-700">{{ resolveForm.errors.amount }}</p>
                    <p v-if="resolveForm.errors.resolution_note" class="text-xs text-rose-700">{{ resolveForm.errors.resolution_note }}</p>
                    <div class="flex gap-2">
                        <button type="submit" class="rounded-xl bg-cyan-700 px-4 py-2 text-sm font-medium text-white">{{ t('Confirm resolve') }}</button>
                        <button type="button" class="rounded-xl border border-cyan-200 px-4 py-2 text-sm" @click="resolvingId = null">{{ t('Cancel') }}</button>
                    </div>
                </form>
            </div>

            <SystemTimeline :events="system_timeline" />
        </section>
    </AdminLayout>
</template>
