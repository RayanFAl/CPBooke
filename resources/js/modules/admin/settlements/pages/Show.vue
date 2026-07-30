<script setup>
import AdminLayout from '../../layouts/AdminLayout.vue';
import SystemTimeline from '../../components/SystemTimeline.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';
import { useAdminLocale } from '../../composables/useAdminLocale';

const props = defineProps({
    settlement: { type: Object, required: true },
    items: { type: Object, required: true },
    filters: { type: Object, required: true },
    can_manage: { type: Boolean, default: false },
    item_statuses: { type: Array, default: () => [] },
    system_timeline: { type: Array, default: () => [] },
});

const { t } = useAdminLocale();
const resolvingId = ref(null);

const filterForm = reactive({
    item_status: props.filters.item_status ?? '',
});

const invoiceForm = useForm({
    csv_text: '',
    lines: [],
});

const resolveForm = useForm({
    resolution_note: '',
});

const applyItemFilter = () => {
    router.get(route('admin.settlements.show', props.settlement.id), {
        ...(filterForm.item_status ? { item_status: filterForm.item_status } : {}),
    }, { preserveState: true, preserveScroll: true, replace: true });
};

const importInvoice = () => {
    invoiceForm.post(route('admin.settlements.import-invoice', props.settlement.id), {
        preserveScroll: true,
    });
};

const compare = () => {
    router.post(route('admin.settlements.compare', props.settlement.id), {}, { preserveScroll: true });
};

const closePeriod = () => {
    router.post(route('admin.settlements.close', props.settlement.id), {}, { preserveScroll: true });
};

const openResolve = (itemId) => {
    resolvingId.value = itemId;
    resolveForm.reset();
    resolveForm.clearErrors();
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
                    <div v-if="can_manage && settlement.status !== 'closed'" class="flex flex-wrap gap-2">
                        <button type="button" class="rounded-xl border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50" @click="compare">
                            {{ t('Re-compare') }}
                        </button>
                        <button
                            type="button"
                            class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:opacity-50"
                            :disabled="settlement.review_count > 0"
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
                </div>
            </div>

            <div v-if="can_manage && settlement.status !== 'closed'" class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-sm font-semibold text-slate-950">{{ t('Import supplier invoice') }}</h3>
                <p class="mt-1 text-sm text-slate-600">
                    {{ t('Paste CSV lines: booking_reference,amount') }}
                </p>
                <form class="mt-4 space-y-3" @submit.prevent="importInvoice">
                    <textarea
                        v-model="invoiceForm.csv_text"
                        rows="6"
                        class="w-full rounded-xl border border-slate-300 px-3 py-2.5 font-mono text-sm"
                        placeholder="BK-1001,531.00&#10;BK-1002,420.50"
                    />
                    <p v-if="invoiceForm.errors.lines" class="text-xs text-rose-600">{{ invoiceForm.errors.lines }}</p>
                    <button type="submit" class="rounded-xl bg-cyan-700 px-4 py-2 text-sm font-medium text-white hover:bg-cyan-800" :disabled="invoiceForm.processing">
                        {{ t('Import & compare') }}
                    </button>
                </form>
            </div>

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
                                    <span v-if="item.order_id">Order #{{ item.order_id }}</span>
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
                            </td>
                            <td class="px-4 py-3">
                                <button
                                    v-if="can_manage && item.needs_review && settlement.status !== 'closed'"
                                    type="button"
                                    class="text-xs font-medium text-cyan-700 hover:text-cyan-800"
                                    @click="openResolve(item.id)"
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
                    <textarea
                        v-model="resolveForm.resolution_note"
                        rows="3"
                        class="w-full rounded-xl border border-cyan-200 bg-white px-3 py-2 text-sm"
                        :placeholder="t('Explain how this variance was handled')"
                    />
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
