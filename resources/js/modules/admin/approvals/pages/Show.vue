<script setup>
import AdminLayout from '../../layouts/AdminLayout.vue';
import SystemTimeline from '../../components/SystemTimeline.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useAdminLocale } from '../../composables/useAdminLocale';

const props = defineProps({
    approval: { type: Object, required: true },
    system_timeline: { type: Array, default: () => [] },
    can_approve: { type: Boolean, default: false },
});

const { t } = useAdminLocale();

const formatTime = (value) => {
    if (!value) return '—';
    try {
        return new Date(value).toLocaleString();
    } catch {
        return value;
    }
};

const approve = () => {
    router.post(route('admin.approvals.approve', props.approval.id));
};

const reject = () => {
    const reason = window.prompt(t('Rejection reason'));
    if (!reason || !reason.trim()) {
        return;
    }

    router.post(route('admin.approvals.reject', props.approval.id), {
        rejection_reason: reason.trim(),
    });
};

const retry = () => {
    router.post(route('admin.approvals.retry', props.approval.id));
};
</script>

<template>
    <AdminLayout>
        <Head :title="`${t('Approval')} #${approval.id}`" />

        <section class="space-y-6">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <Link :href="route('admin.approvals.index')" class="text-sm font-medium text-cyan-700 hover:text-cyan-800">
                    ← {{ t('Approvals') }}
                </Link>
                <h2 class="mt-3 text-2xl font-semibold text-slate-950">{{ t('Approval') }} #{{ approval.id }}</h2>
                <p class="mt-2 text-sm text-slate-600">
                    {{ approval.type }} · {{ approval.entity_type }} #{{ approval.entity_id }} · {{ approval.status }}
                </p>

                <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="rounded-2xl bg-slate-50 p-3 text-sm">
                        <p class="text-xs uppercase text-slate-500">{{ t('Requested by') }}</p>
                        <p class="mt-1 font-medium text-slate-900">{{ approval.requested_by || '—' }}</p>
                    </div>
                    <div class="rounded-2xl bg-slate-50 p-3 text-sm">
                        <p class="text-xs uppercase text-slate-500">{{ t('Approved by') }}</p>
                        <p class="mt-1 font-medium text-slate-900">{{ approval.approved_by || '—' }}</p>
                    </div>
                    <div class="rounded-2xl bg-slate-50 p-3 text-sm">
                        <p class="text-xs uppercase text-slate-500">{{ t('Created') }}</p>
                        <p class="mt-1 font-medium text-slate-900">{{ formatTime(approval.created_at) }}</p>
                    </div>
                    <div class="rounded-2xl bg-slate-50 p-3 text-sm">
                        <p class="text-xs uppercase text-slate-500">{{ t('Executed') }}</p>
                        <p class="mt-1 font-medium text-slate-900">{{ formatTime(approval.executed_at) }}</p>
                    </div>
                </div>

                <p class="mt-4 text-sm text-slate-700"><span class="font-medium">{{ t('Reason') }}:</span> {{ approval.reason || '—' }}</p>
                <p v-if="approval.rejection_reason" class="mt-2 text-sm text-rose-700">
                    <span class="font-medium">{{ t('Rejection reason') }}:</span> {{ approval.rejection_reason }}
                </p>
                <p v-if="approval.execution_error" class="mt-2 text-sm text-rose-700">
                    <span class="font-medium">{{ t('Execution error') }}:</span> {{ approval.execution_error }}
                </p>

                <div v-if="can_approve" class="mt-5 flex flex-wrap gap-2">
                    <button
                        v-if="approval.status === 'pending'"
                        type="button"
                        class="rounded-xl bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800"
                        @click="approve"
                    >
                        {{ t('Approve') }}
                    </button>
                    <button
                        v-if="approval.status === 'pending'"
                        type="button"
                        class="rounded-xl bg-rose-700 px-4 py-2 text-sm font-medium text-white hover:bg-rose-800"
                        @click="reject"
                    >
                        {{ t('Reject') }}
                    </button>
                    <button
                        v-if="approval.status === 'failed'"
                        type="button"
                        class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800"
                        @click="retry"
                    >
                        {{ t('Retry execution') }}
                    </button>
                </div>
            </div>

            <SystemTimeline :events="system_timeline" />
        </section>
    </AdminLayout>
</template>
