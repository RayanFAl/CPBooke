<script setup>
import AdminLayout from '../../layouts/AdminLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useAdminLocale } from '../../composables/useAdminLocale';

const props = defineProps({
    dashboard: { type: Object, required: true },
    thresholds: { type: Object, required: true },
    weights: { type: Object, required: true },
});

const { t } = useAdminLocale();

const bandTone = (band) => {
    if (band === 'excellent') return 'border-emerald-200 bg-emerald-50 text-emerald-800';
    if (band === 'watch') return 'border-amber-200 bg-amber-50 text-amber-900';
    return 'border-rose-200 bg-rose-50 text-rose-900';
};

const apiTone = (status) => {
    if (status === 'online') return 'bg-emerald-100 text-emerald-800';
    if (status === 'degraded') return 'bg-amber-100 text-amber-900';
    if (status === 'offline') return 'bg-rose-100 text-rose-900';
    return 'bg-slate-100 text-slate-700';
};

const severityDot = (severity) => (severity === 'critical' ? '🔴' : '🟡');

const sortedProviders = computed(() =>
    [...(props.dashboard.providers ?? [])].sort((a, b) => a.health_score - b.health_score),
);

const formatTime = (value) => {
    if (!value) return '—';
    try {
        return new Date(value).toLocaleString();
    } catch {
        return value;
    }
};
</script>

<template>
    <AdminLayout>
        <Head :title="t('Provider Health')" />

        <section class="space-y-6">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.25em] text-cyan-700">{{ t('Operations') }}</p>
                <h2 class="mt-3 text-2xl font-semibold text-slate-950">{{ t('Provider Health') }}</h2>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                    {{ t('Network operations center for suppliers: API status, wallets, settlements, approvals, and live alerts before customers notice.') }}
                </p>
                <p class="mt-3 text-xs text-slate-500">
                    {{ t('Updated') }}: {{ formatTime(dashboard.generated_at) }}
                </p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs uppercase tracking-wide text-slate-500">{{ t('Providers') }}</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-950">{{ dashboard.summary.providers_total }}</p>
                </div>
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 shadow-sm">
                    <p class="text-xs uppercase tracking-wide text-emerald-700">{{ t('Excellent') }}</p>
                    <p class="mt-2 text-2xl font-semibold text-emerald-900">{{ dashboard.summary.excellent }}</p>
                </div>
                <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 shadow-sm">
                    <p class="text-xs uppercase tracking-wide text-amber-700">{{ t('Needs attention') }}</p>
                    <p class="mt-2 text-2xl font-semibold text-amber-900">{{ dashboard.summary.watch }}</p>
                </div>
                <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 shadow-sm">
                    <p class="text-xs uppercase tracking-wide text-rose-700">{{ t('Critical') }}</p>
                    <p class="mt-2 text-2xl font-semibold text-rose-900">{{ dashboard.summary.critical }}</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs uppercase tracking-wide text-slate-500">{{ t('Avg health score') }}</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-950">{{ dashboard.summary.average_score ?? '—' }}</p>
                </div>
            </div>

            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <h3 class="text-sm font-semibold text-slate-950">{{ t('Active alerts') }}</h3>
                    <span class="text-xs text-slate-500">
                        {{ dashboard.summary.critical_alerts }} {{ t('critical') }} · {{ dashboard.summary.active_alerts }} {{ t('total') }}
                    </span>
                </div>
                <ul v-if="dashboard.alerts.length" class="mt-4 space-y-2">
                    <li
                        v-for="(alert, index) in dashboard.alerts"
                        :key="`${alert.provider_id}-${alert.code}-${index}`"
                        class="flex gap-3 rounded-2xl border px-4 py-3 text-sm"
                        :class="alert.severity === 'critical' ? 'border-rose-200 bg-rose-50 text-rose-950' : 'border-amber-200 bg-amber-50 text-amber-950'"
                    >
                        <span aria-hidden="true">{{ severityDot(alert.severity) }}</span>
                        <div>
                            <p class="font-medium">{{ alert.provider_name }}</p>
                            <p class="mt-0.5 text-sm opacity-90">{{ alert.message }}</p>
                        </div>
                    </li>
                </ul>
                <p v-else class="mt-4 text-sm text-slate-500">{{ t('No active provider alerts.') }}</p>
            </div>

            <div class="grid gap-4 xl:grid-cols-2">
                <article
                    v-for="provider in sortedProviders"
                    :key="provider.id"
                    class="rounded-3xl border bg-white p-5 shadow-sm"
                    :class="bandTone(provider.health_band).split(' ').slice(0, 2).join(' ')"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-lg font-semibold text-slate-950">{{ provider.name }}</h3>
                            <p class="text-xs text-slate-500">{{ provider.key }} · {{ provider.integration_status }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-3xl font-semibold tabular-nums text-slate-950">{{ provider.health_score }}</p>
                            <p class="text-xs uppercase tracking-wide text-slate-600">{{ provider.health_band }}</p>
                        </div>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <span class="rounded-full px-2.5 py-1 text-xs font-medium uppercase" :class="apiTone(provider.api_status)">
                            API {{ provider.api_status }}
                        </span>
                        <span class="rounded-full bg-white/70 px-2.5 py-1 text-xs text-slate-700">
                            {{ t('Latency') }}: {{ provider.avg_latency_ms != null ? `${provider.avg_latency_ms} ms` : '—' }}
                        </span>
                        <span class="rounded-full bg-white/70 px-2.5 py-1 text-xs text-slate-700">
                            {{ t('Errors 1h') }}: {{ provider.error_rate_1h != null ? `${provider.error_rate_1h}%` : '—' }}
                        </span>
                    </div>

                    <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-slate-500">{{ t('Wallet') }}</dt>
                            <dd class="mt-1 font-medium text-slate-950">
                                {{ provider.wallet.balance ?? '—' }} {{ provider.wallet.currency }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-slate-500">{{ t('Credit remaining') }}</dt>
                            <dd class="mt-1 font-medium text-slate-950">{{ provider.credit_remaining ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-slate-500">{{ t('Last sync') }}</dt>
                            <dd class="mt-1 font-medium text-slate-950">{{ formatTime(provider.last_successful_sync_at) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-slate-500">{{ t('Failed ops 24h') }}</dt>
                            <dd class="mt-1 font-medium text-slate-950">{{ provider.failed_operations_24h }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-slate-500">{{ t('Settlement') }}</dt>
                            <dd class="mt-1 font-medium text-slate-950">
                                <template v-if="provider.settlement.latest_id">
                                    #{{ provider.settlement.latest_id }} · {{ provider.settlement.latest_status }}
                                </template>
                                <template v-else>—</template>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-slate-500">{{ t('Pending approvals') }}</dt>
                            <dd class="mt-1 font-medium text-slate-950">{{ provider.pending_approvals }}</dd>
                        </div>
                    </dl>

                    <div class="mt-4 flex flex-wrap gap-2 text-xs">
                        <Link
                            v-if="provider.wallet.id"
                            :href="route('admin.provider-wallets.show', provider.wallet.id)"
                            class="rounded-lg bg-slate-950 px-3 py-1.5 font-medium text-white hover:bg-slate-800"
                        >
                            {{ t('Wallet') }}
                        </Link>
                        <Link
                            :href="route('admin.settlements.index', { provider_id: provider.id })"
                            class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 font-medium text-slate-800 hover:bg-slate-50"
                        >
                            {{ t('Settlements') }}
                        </Link>
                        <Link
                            :href="route('admin.approvals.index', { status: 'pending' })"
                            class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 font-medium text-slate-800 hover:bg-slate-50"
                        >
                            {{ t('Approvals') }}
                        </Link>
                    </div>

                    <ul v-if="provider.alerts.length" class="mt-4 space-y-1 border-t border-black/5 pt-3 text-xs">
                        <li v-for="(alert, idx) in provider.alerts" :key="idx">
                            {{ severityDot(alert.severity) }} {{ alert.message }}
                        </li>
                    </ul>
                </article>
            </div>

            <p v-if="sortedProviders.length === 0" class="rounded-3xl border border-dashed border-slate-300 bg-white p-10 text-center text-slate-500">
                {{ t('No providers configured yet.') }}
            </p>

            <div class="rounded-3xl border border-slate-200 bg-slate-50 p-5 text-xs text-slate-600">
                <p class="font-semibold text-slate-800">{{ t('Health score weights') }}</p>
                <p class="mt-2">
                    API {{ weights.api }}% · {{ t('Wallet') }} {{ weights.wallet }}% · {{ t('Error rate') }} {{ weights.error_rate }}%
                    · {{ t('Settlement') }} {{ weights.settlement }}% · {{ t('Pending ops') }} {{ weights.pending }}%
                </p>
                <p class="mt-2">
                    {{ t('Critical wallet threshold') }}: {{ thresholds.wallet_critical }} ·
                    {{ t('Error rate warn') }}: {{ thresholds.error_rate_warn }}% ·
                    {{ t('API offline after') }}: {{ thresholds.api_offline_minutes }} {{ t('minutes') }}
                </p>
            </div>
        </section>
    </AdminLayout>
</template>
