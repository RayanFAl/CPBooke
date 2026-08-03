<script setup>
import AdminLayout from '../../layouts/AdminLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';
import { useAdminLocale } from '../../composables/useAdminLocale';

const props = defineProps({
    approvals: { type: Object, required: true },
    filters: { type: Object, required: true },
    can_approve: { type: Boolean, default: false },
    types: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
});

const { t } = useAdminLocale();
const rejectingId = ref(null);

const filterForm = reactive({
    status: props.filters.status ?? 'pending',
    type: props.filters.type ?? '',
});

const rejectForm = useForm({
    rejection_reason: '',
});

const applyFilters = () => {
    router.get(route('admin.approvals.index'), {
        ...(filterForm.status ? { status: filterForm.status } : {}),
        ...(filterForm.type ? { type: filterForm.type } : {}),
    }, { preserveState: true, preserveScroll: true, replace: true });
};

const openShow = (approvalId) => {
    router.visit(route('admin.approvals.show', approvalId));
};

const approve = (approvalId) => {
    if (!props.can_approve) {
        return;
    }

    router.post(route('admin.approvals.approve', approvalId), {}, {
        preserveScroll: true,
    });
};

const retry = (approvalId) => {
    if (!props.can_approve) {
        return;
    }

    router.post(route('admin.approvals.retry', approvalId), {}, {
        preserveScroll: true,
    });
};

const openReject = (approvalId) => {
    rejectingId.value = approvalId;
    rejectForm.reset();
    rejectForm.clearErrors();
};

const submitReject = () => {
    if (!rejectingId.value) {
        return;
    }

    rejectForm.post(route('admin.approvals.reject', rejectingId.value), {
        preserveScroll: true,
        onSuccess: () => {
            rejectingId.value = null;
            rejectForm.reset();
        },
    });
};

const formatType = (type) => type.replaceAll('_', ' ');

const snapshotLine = (approval) => {
    const snapshot = approval.snapshot ?? {};

    if (!snapshot || Object.keys(snapshot).length === 0) {
        return '—';
    }

    const parts = [];

    if (snapshot.selling_price != null) {
        parts.push(`${t('Sell')}: ${snapshot.selling_price}`);
    }

    if (snapshot.supplier_cost != null) {
        parts.push(`${t('Cost')}: ${snapshot.supplier_cost}`);
    }

    if (snapshot.profit_amount != null) {
        parts.push(`${t('Profit')}: ${snapshot.profit_amount}`);
    }

    if (snapshot.balance_before != null) {
        parts.push(`${t('Wallet')}: ${snapshot.balance_before}`);
    }

    return parts.join(' · ') || '—';
};
</script>

<template>
    <AdminLayout>
        <Head :title="t('Approvals')" />

        <section class="space-y-6">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.25em] text-cyan-700">{{ t('Governance') }}</p>
                    <h2 class="mt-3 text-2xl font-semibold text-slate-950">{{ t('Approvals') }}</h2>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">
                        {{ t('Review pending refunds, cancellations, and wallet movements before they affect ledgers and provider balances.') }}
                    </p>
                </div>
            </div>

            <form class="flex flex-col gap-3 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm md:flex-row" @submit.prevent="applyFilters">
                <select v-model="filterForm.status" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                    <option value="all">{{ t('All statuses') }}</option>
                    <option v-for="status in statuses" :key="status" :value="status">{{ status }}</option>
                </select>
                <select v-model="filterForm.type" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                    <option value="">{{ t('All types') }}</option>
                    <option v-for="type in types" :key="type" :value="type">{{ formatType(type) }}</option>
                </select>
                <button type="submit" class="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-medium text-white hover:bg-slate-800">
                    {{ t('Filter') }}
                </button>
            </form>

            <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3">#</th>
                            <th class="px-4 py-3">{{ t('Type') }}</th>
                            <th class="px-4 py-3">{{ t('Entity') }}</th>
                            <th class="px-4 py-3">{{ t('Snapshot') }}</th>
                            <th class="px-4 py-3">{{ t('Requested by') }}</th>
                            <th class="px-4 py-3">{{ t('Status') }}</th>
                            <th class="px-4 py-3">{{ t('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr
                            v-for="approval in approvals.data"
                            :key="approval.id"
                            class="align-top hover:bg-slate-50/80 cursor-pointer"
                            @click="openShow(approval.id)"
                        >
                            <td class="px-4 py-3 font-medium text-slate-950">{{ approval.id }}</td>
                            <td class="px-4 py-3">
                                <p class="font-medium capitalize text-slate-950">{{ formatType(approval.type) }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ approval.reason }}</p>
                            </td>
                            <td class="px-4 py-3 text-slate-700">
                                {{ approval.entity_type }} #{{ approval.entity_id }}
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-600">{{ snapshotLine(approval) }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ approval.requested_by }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium uppercase tracking-wide text-slate-700">
                                    {{ approval.status }}
                                </span>
                                <p v-if="approval.execution_error" class="mt-1 text-xs text-rose-600">{{ approval.execution_error }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <div v-if="approval.status === 'pending' && can_approve" class="flex flex-col gap-2">
                                    <button
                                        type="button"
                                        class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-emerald-700"
                                        @click.stop="approve(approval.id)"
                                    >
                                        {{ t('Approve') }}
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-medium text-rose-700 hover:bg-rose-50"
                                        @click.stop="openReject(approval.id)"
                                    >
                                        {{ t('Reject') }}
                                    </button>
                                </div>
                                <div v-else-if="approval.status === 'failed' && can_approve" class="flex flex-col gap-2">
                                    <button
                                        type="button"
                                        class="rounded-lg bg-amber-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-amber-700"
                                        @click.stop="retry(approval.id)"
                                    >
                                        {{ t('Retry Execution') }}
                                    </button>
                                </div>
                                <span v-else class="text-xs text-slate-400">—</span>
                            </td>
                        </tr>
                        <tr v-if="approvals.data.length === 0">
                            <td colspan="7" class="px-4 py-10 text-center text-slate-500">{{ t('No approval requests yet.') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                v-if="rejectingId"
                class="rounded-3xl border border-rose-200 bg-rose-50 p-5 shadow-sm"
            >
                <h3 class="text-sm font-semibold text-rose-900">{{ t('Reject approval') }} #{{ rejectingId }}</h3>
                <form class="mt-4 space-y-3" @submit.prevent="submitReject">
                    <textarea
                        v-model="rejectForm.rejection_reason"
                        rows="3"
                        class="w-full rounded-xl border border-rose-200 bg-white px-3 py-2 text-sm"
                        :placeholder="t('Explain why this request is rejected')"
                    />
                    <p v-if="rejectForm.errors.rejection_reason" class="text-xs text-rose-700">{{ rejectForm.errors.rejection_reason }}</p>
                    <div class="flex gap-2">
                        <button type="submit" class="rounded-xl bg-rose-700 px-4 py-2 text-sm font-medium text-white hover:bg-rose-800">
                            {{ t('Confirm reject') }}
                        </button>
                        <button type="button" class="rounded-xl border border-rose-200 px-4 py-2 text-sm text-rose-800" @click="rejectingId = null">
                            {{ t('Cancel') }}
                        </button>
                    </div>
                </form>
            </div>
        </section>
    </AdminLayout>
</template>
