<script setup>
import AdminLayout from '../../layouts/AdminLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { useAdminLocale } from '../../composables/useAdminLocale';

const props = defineProps({
    dashboard: { type: Object, required: true },
    can_manage: { type: Boolean, default: false },
});

const { t } = useAdminLocale();

const statusTone = (status) => {
    if (status === 'ok') return 'border-emerald-200 bg-emerald-50 text-emerald-900';
    if (status === 'warn') return 'border-amber-200 bg-amber-50 text-amber-950';
    return 'border-rose-200 bg-rose-50 text-rose-950';
};

const statusDot = (status) => {
    if (status === 'ok') return '🟢';
    if (status === 'warn') return '🟡';
    return '🔴';
};

const severityDot = (severity) => (severity === 'critical' ? '🔴' : '🟡');

const formatTime = (value) => {
    if (!value) return '—';
    try {
        return new Date(value).toLocaleString();
    } catch {
        return value;
    }
};

const runProbes = () => {
    router.post(route('admin.monitoring.run-probes'));
};

const signalCards = [
    { key: 'queue_jobs', label: 'Queue Jobs' },
    { key: 'failed_jobs', label: 'Failed Jobs' },
    { key: 'exceptions_1h', label: 'Exceptions (1h)' },
    { key: 'slow_requests_1h', label: 'Slow Requests (1h)' },
    { key: 'api_errors_1h', label: 'API Errors (1h)' },
    { key: 'wallet_alerts', label: 'Wallet Alerts' },
    { key: 'settlement_alerts', label: 'Settlement Alerts' },
    { key: 'email_failures_24h', label: 'Email Failures' },
    { key: 'whatsapp_failures_24h', label: 'WhatsApp Failures' },
    { key: 'pending_approvals', label: 'Pending Approvals' },
];
</script>

<template>
    <AdminLayout>
        <Head :title="t('Monitoring')" />

        <section class="space-y-6">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.25em] text-cyan-700">{{ t('Platform') }}</p>
                        <h2 class="mt-3 text-2xl font-semibold text-slate-950">{{ t('Monitoring') }}</h2>
                        <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                            {{ t('System health, queues, failures, and operational alerts — know within a minute if something stops.') }}
                        </p>
                        <p class="mt-3 text-xs text-slate-500">{{ t('Updated') }}: {{ formatTime(dashboard.generated_at) }}</p>
                    </div>
                    <button
                        v-if="can_manage"
                        type="button"
                        class="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-medium text-white hover:bg-slate-800"
                        @click="runProbes"
                    >
                        {{ t('Run health probes') }}
                    </button>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
                    <p class="text-xs uppercase tracking-wide text-emerald-700">{{ t('Healthy') }}</p>
                    <p class="mt-2 text-2xl font-semibold text-emerald-950">{{ dashboard.summary.services_ok }}</p>
                </div>
                <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4">
                    <p class="text-xs uppercase tracking-wide text-amber-700">{{ t('Warnings') }}</p>
                    <p class="mt-2 text-2xl font-semibold text-amber-950">{{ dashboard.summary.services_warn }}</p>
                </div>
                <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4">
                    <p class="text-xs uppercase tracking-wide text-rose-700">{{ t('Critical') }}</p>
                    <p class="mt-2 text-2xl font-semibold text-rose-950">{{ dashboard.summary.services_fail }}</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-4">
                    <p class="text-xs uppercase tracking-wide text-slate-500">{{ t('Active alerts') }}</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-950">{{ dashboard.summary.active_alerts }}</p>
                </div>
            </div>

            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-sm font-semibold text-slate-950">{{ t('Service health') }}</h3>
                <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    <article
                        v-for="service in dashboard.services"
                        :key="service.key"
                        class="rounded-2xl border p-4"
                        :class="statusTone(service.status)"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="font-semibold">{{ statusDot(service.status) }} {{ service.label }}</p>
                                <p class="mt-1 text-sm opacity-90">{{ service.message }}</p>
                            </div>
                            <p class="text-xs tabular-nums opacity-70">
                                {{ service.latency_ms != null ? `${service.latency_ms} ms` : '—' }}
                            </p>
                        </div>
                    </article>
                </div>
            </div>

            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-sm font-semibold text-slate-950">{{ t('Operational signals') }}</h3>
                <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                    <div
                        v-for="card in signalCards"
                        :key="card.key"
                        class="rounded-2xl border border-slate-200 bg-slate-50 p-4"
                    >
                        <p class="text-xs uppercase tracking-wide text-slate-500">{{ t(card.label) }}</p>
                        <p class="mt-2 text-xl font-semibold text-slate-950">{{ dashboard.signals[card.key] }}</p>
                    </div>
                </div>
            </div>

            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-sm font-semibold text-slate-950">{{ t('Active alerts') }}</h3>
                <ul v-if="dashboard.alerts.length" class="mt-4 space-y-2">
                    <li
                        v-for="(alert, index) in dashboard.alerts"
                        :key="`${alert.code}-${index}`"
                        class="rounded-2xl border px-4 py-3 text-sm"
                        :class="alert.severity === 'critical' ? 'border-rose-200 bg-rose-50 text-rose-950' : 'border-amber-200 bg-amber-50 text-amber-950'"
                    >
                        {{ severityDot(alert.severity) }} {{ alert.message }}
                    </li>
                </ul>
                <p v-else class="mt-4 text-sm text-slate-500">{{ t('No active alerts.') }}</p>
            </div>

            <div class="grid gap-4 xl:grid-cols-2">
                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-sm font-semibold text-slate-950">{{ t('Recent exceptions') }}</h3>
                    <ul class="mt-4 space-y-2 text-sm">
                        <li v-for="event in dashboard.recent_exceptions" :key="event.id" class="border-b border-slate-100 pb-2">
                            <p class="font-medium text-slate-900">{{ event.message }}</p>
                            <p class="text-xs text-slate-500">{{ formatTime(event.created_at) }}</p>
                        </li>
                        <li v-if="!dashboard.recent_exceptions.length" class="text-slate-500">{{ t('None yet.') }}</li>
                    </ul>
                </div>
                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-sm font-semibold text-slate-950">{{ t('Recent slow requests') }}</h3>
                    <ul class="mt-4 space-y-2 text-sm">
                        <li v-for="event in dashboard.recent_slow_requests" :key="event.id" class="border-b border-slate-100 pb-2">
                            <p class="font-medium text-slate-900">{{ event.message }}</p>
                            <p class="text-xs text-slate-500">{{ formatTime(event.created_at) }}</p>
                        </li>
                        <li v-if="!dashboard.recent_slow_requests.length" class="text-slate-500">{{ t('None yet.') }}</li>
                    </ul>
                </div>
            </div>
        </section>
    </AdminLayout>
</template>
