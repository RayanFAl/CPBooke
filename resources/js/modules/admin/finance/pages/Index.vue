<script setup>
import AdminModulePage from '../../components/AdminModulePage.vue';
import AdminLayout from '../../layouts/AdminLayout.vue';
import { useAdminLocale } from '../../composables/useAdminLocale';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    dashboard: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        required: true,
    },
    filter_options: {
        type: Object,
        required: true,
    },
    exports: {
        type: Object,
        required: true,
    },
    transactions: {
        type: Array,
        required: true,
    },
});

const { t } = useAdminLocale();

const filtersForm = useForm({
    date_from: props.filters.date_from ?? '',
    date_to: props.filters.date_to ?? '',
    service_type: props.filters.service_type ?? '',
    country: props.filters.country ?? '',
    provider_name: props.filters.provider_name ?? '',
});

const activeTab = ref('overview');

const analyticsRows = computed(() => {
    const labels = props.dashboard.analytics.time_series?.labels ?? [];
    const datasets = props.dashboard.analytics.time_series?.datasets ?? [];

    return labels.map((label, index) => ({
        date: label,
        payments: datasets.find((dataset) => dataset.key === 'payments')?.data?.[index] ?? '0.00',
        refunds: datasets.find((dataset) => dataset.key === 'refunds')?.data?.[index] ?? '0.00',
        net: datasets.find((dataset) => dataset.key === 'net')?.data?.[index] ?? '0.00',
    }));
});

const latestEvent = computed(() => props.dashboard.analytics.insights.latest_event ?? null);

const workspaceTabs = computed(() => [
    { id: 'overview', label: t('Overview'), count: 0 },
    { id: 'analytics', label: t('Analytics'), count: analyticsRows.value.length },
    { id: 'operations', label: t('Operations'), count: props.dashboard.activity.latest_transactions.length },
    { id: 'drilldown', label: t('Drill-Down'), count: props.dashboard.drilldown.orders.length },
]);

const validTabIds = computed(() => workspaceTabs.value.map((tab) => tab.id));

const kpiCards = computed(() => [
    { key: 'gross_revenue', label: t('Gross revenue'), value: props.dashboard.kpis.gross_revenue, tone: 'text-slate-950' },
    { key: 'recognized_revenue', label: t('Recognized revenue'), value: props.dashboard.kpis.recognized_revenue, tone: 'text-emerald-700' },
    { key: 'refunds', label: t('Refunds'), value: props.dashboard.kpis.refunds, tone: 'text-rose-700' },
    { key: 'payouts', label: t('Payouts'), value: props.dashboard.kpis.payouts, tone: 'text-slate-950' },
    { key: 'net_cash', label: t('Net cash'), value: props.dashboard.kpis.net_cash, tone: 'text-slate-950' },
    { key: 'gross_profit', label: t('Gross profit'), value: props.dashboard.kpis.gross_profit, tone: 'text-cyan-700' },
]);

const reconciliationCards = computed(() => [
    { key: 'transactions', label: t('Transactions'), value: props.dashboard.counts.transactions, tone: 'text-slate-950' },
    { key: 'missing_ledger', label: t('Missing ledger'), value: props.dashboard.reconciliation.counts.transactions_missing_ledger, tone: 'text-amber-700' },
    { key: 'unbalanced_ledger', label: t('Unbalanced ledger'), value: props.dashboard.reconciliation.counts.transactions_unbalanced, tone: 'text-rose-700' },
    { key: 'order_mismatches', label: t('Order mismatches'), value: props.dashboard.reconciliation.counts.order_payment_mismatches, tone: 'text-slate-950' },
    { key: 'total_anomalies', label: t('Total anomalies'), value: props.dashboard.reconciliation.counts.total, tone: 'text-cyan-700' },
]);

const normalizeTab = (value) => {
    if (value && validTabIds.value.includes(value)) {
        return value;
    }

    return 'overview';
};

const replaceHash = (tabId) => {
    if (typeof window === 'undefined') {
        return;
    }

    const nextTab = normalizeTab(tabId);
    const nextUrl = `${window.location.pathname}${window.location.search}#${nextTab}`;
    window.history.replaceState(window.history.state, '', nextUrl);
};

const syncTabFromHash = () => {
    if (typeof window === 'undefined') {
        return;
    }

    activeTab.value = normalizeTab(window.location.hash.replace('#', ''));
};

const formatValueLabel = (value) => {
    if (!value) {
        return t('Not available');
    }

    const normalized = String(value).replaceAll('_', ' ').trim();
    const lowerCased = normalized.toLowerCase();
    const titleCased = normalized.replace(/\b\w/g, (letter) => letter.toUpperCase());
    const lowerTranslation = t(lowerCased);

    if (lowerTranslation !== lowerCased) {
        return lowerTranslation;
    }

    const titleTranslation = t(titleCased);

    if (titleTranslation !== titleCased) {
        return titleTranslation;
    }

    return titleCased;
};

const applyFilters = () => {
    filtersForm.get(route('admin.finance.index'), {
        preserveState: true,
        preserveScroll: true,
    });
};

const runReconciliation = () => {
    useForm({}).post(route('admin.finance.reconcile'), {
        preserveScroll: true,
    });
};

const changeTab = (tabId) => {
    activeTab.value = normalizeTab(tabId);
    replaceHash(activeTab.value);
};

onMounted(() => {
    syncTabFromHash();

    if (typeof window !== 'undefined') {
        window.addEventListener('hashchange', syncTabFromHash);
    }
});

onBeforeUnmount(() => {
    if (typeof window !== 'undefined') {
        window.removeEventListener('hashchange', syncTabFromHash);
    }
});
</script>

<template>
    <AdminLayout
        title="Finance"
        description="Financial oversight shell for payouts, ledgers, settlements, and reporting."
    >
        <AdminModulePage
            eyebrow="Accounting"
            title="Finance"
            description="Financial truth, ledger integrity, reconciliation, and profitability now sit behind one auditable back-office surface."
        >
            <div class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 bg-[radial-gradient(circle_at_top_left,_rgba(14,165,233,0.12),_transparent_35%),linear-gradient(180deg,_#ffffff,_#f8fafc)] px-6 py-6">
                    <div class="flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
                        <div class="max-w-3xl">
                            <p class="text-xs font-semibold uppercase tracking-[0.26em] text-cyan-700">{{ t('Finance Workspace') }}</p>
                            <h2 class="mt-3 text-3xl font-semibold tracking-tight text-slate-950">{{ t('Financial control center') }}</h2>
                            <p class="mt-3 text-sm leading-6 text-slate-600">
                                {{ t('Move between overview, analytics, intelligence, operational controls, and order drill-down through link-aware tabs instead of one long finance screen.') }}
                            </p>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2 xl:min-w-[26rem]">
                            <div class="rounded-[1.6rem] border border-slate-200 bg-white px-4 py-4 shadow-sm">
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ t('Transactions') }}</p>
                                <p class="mt-2 text-2xl font-semibold text-slate-950">{{ dashboard.counts.transactions }}</p>
                                <p class="mt-2 text-sm text-slate-600">{{ t('Latest transaction activity is now isolated from analytics and BI blocks.') }}</p>
                            </div>
                            <div class="rounded-[1.6rem] border border-slate-200 bg-white px-4 py-4 shadow-sm">
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ t('Anomalies') }}</p>
                                <p class="mt-2 text-2xl font-semibold text-rose-700">{{ dashboard.reconciliation.counts.total }}</p>
                                <p class="mt-2 text-sm text-slate-600">{{ t('Ledger and payment-status issues stay grouped in the operations workspace.') }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="mt-5 flex flex-wrap gap-3">
                        <Link :href="exports.csv" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                            {{ t('Export CSV') }}
                        </Link>
                        <button type="button" class="rounded-2xl bg-slate-950 px-4 py-3 text-sm font-medium text-white transition hover:bg-slate-800" @click="runReconciliation">
                            {{ t('Run reconciliation') }}
                        </button>
                        <button type="button" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50" @click="changeTab('operations')">
                            {{ t('Open operations workspace') }}
                        </button>
                    </div>
                </div>

                <div class="border-b border-slate-200 px-6 py-5">
                    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                        <label class="text-sm text-slate-600">
                            <span class="block font-medium text-slate-700">{{ t('Date from') }}</span>
                            <input v-model="filtersForm.date_from" type="date" class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3" />
                        </label>
                        <label class="text-sm text-slate-600">
                            <span class="block font-medium text-slate-700">{{ t('Date to') }}</span>
                            <input v-model="filtersForm.date_to" type="date" class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3" />
                        </label>
                        <label class="text-sm text-slate-600">
                            <span class="block font-medium text-slate-700">{{ t('Service type') }}</span>
                            <select v-model="filtersForm.service_type" class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3">
                                <option value="">{{ t('All services') }}</option>
                                <option v-for="option in filter_options.service_types" :key="option.name" :value="option.name">{{ option.label }}</option>
                            </select>
                        </label>
                        <label class="text-sm text-slate-600">
                            <span class="block font-medium text-slate-700">{{ t('Country') }}</span>
                            <input v-model="filtersForm.country" type="text" maxlength="2" class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3 uppercase" />
                        </label>
                        <label class="text-sm text-slate-600">
                            <span class="block font-medium text-slate-700">{{ t('Provider') }}</span>
                            <input v-model="filtersForm.provider_name" type="text" class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3" />
                        </label>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-3">
                        <button type="button" class="inline-flex items-center justify-center rounded-2xl bg-slate-950 px-4 py-3 text-sm font-medium text-white transition hover:bg-slate-800" @click="applyFilters">
                            {{ t('Apply filters') }}
                        </button>
                    </div>
                </div>

                <div class="px-3 py-3">
                    <nav class="flex gap-2 overflow-x-auto">
                        <button
                            v-for="tab in workspaceTabs"
                            :key="tab.id"
                            type="button"
                            class="inline-flex shrink-0 items-center gap-2 rounded-2xl px-4 py-3 text-sm font-medium transition"
                            :class="activeTab === tab.id ? 'bg-slate-950 text-white shadow-sm' : 'bg-slate-50 text-slate-600 hover:bg-slate-100'"
                            @click="changeTab(tab.id)"
                        >
                            <span>{{ tab.label }}</span>
                            <span class="rounded-full px-2 py-0.5 text-xs" :class="activeTab === tab.id ? 'bg-white/10 text-white' : 'bg-white text-slate-500'">
                                {{ tab.count }}
                            </span>
                        </button>
                    </nav>
                </div>
            </div>

            <div v-if="activeTab === 'overview'" class="mt-4 space-y-4">
                <div class="grid gap-4 md:grid-cols-3 xl:grid-cols-6">
                    <article v-for="card in kpiCards" :key="card.key" class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">{{ card.label }}</p>
                        <p class="mt-3 text-3xl font-semibold" :class="card.tone">{{ card.value }}</p>
                    </article>
                </div>

                <section class="grid gap-4 xl:grid-cols-[minmax(0,1.2fr)_minmax(0,0.8fr)]">
                    <div class="space-y-4">
                        <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                            <h3 class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">{{ t('Reconciliation snapshot') }}</h3>
                            <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                                <article v-for="card in reconciliationCards" :key="card.key" class="rounded-2xl bg-slate-50 p-4">
                                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ card.label }}</p>
                                    <p class="mt-2 text-2xl font-semibold" :class="card.tone">{{ card.value }}</p>
                                </article>
                            </div>
                        </div>

                        <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                            <h3 class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">{{ t('Overview shortcuts') }}</h3>
                            <p class="mt-2 text-sm text-slate-600">{{ t('Jump directly into the workspace that matches the finance task in front of you.') }}</p>
                            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                                <button type="button" class="flex items-center justify-between rounded-2xl border border-slate-200 px-4 py-3 text-left text-sm text-slate-700 transition hover:bg-slate-50" @click="changeTab('analytics')">
                                    <span>{{ t('Open analytics workspace') }}</span>
                                    <span class="font-medium text-slate-950">{{ analyticsRows.length }}</span>
                                </button>
                                <button type="button" class="flex items-center justify-between rounded-2xl border border-slate-200 px-4 py-3 text-left text-sm text-slate-700 transition hover:bg-slate-50" @click="changeTab('operations')">
                                    <span>{{ t('Open operations workspace') }}</span>
                                    <span class="font-medium text-slate-950">{{ dashboard.activity.latest_transactions.length }}</span>
                                </button>
                                <button type="button" class="flex items-center justify-between rounded-2xl border border-slate-200 px-4 py-3 text-left text-sm text-slate-700 transition hover:bg-slate-50" @click="changeTab('drilldown')">
                                    <span>{{ t('Open order drill-down') }}</span>
                                    <span class="font-medium text-slate-950">{{ dashboard.drilldown.orders.length }}</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                            <h3 class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">{{ t('Latest financial event') }}</h3>
                            <div v-if="latestEvent" class="mt-4 grid gap-4 sm:grid-cols-2">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ t('Type') }}</p>
                                    <p class="mt-2 text-sm font-medium text-slate-950">{{ formatValueLabel(latestEvent.type) }}</p>
                                </div>
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ t('Source') }}</p>
                                    <p class="mt-2 text-sm font-medium text-slate-950">{{ formatValueLabel(latestEvent.source) }}</p>
                                </div>
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ t('Amount') }}</p>
                                    <p class="mt-2 text-sm font-medium text-slate-950">{{ latestEvent.amount }} {{ latestEvent.currency }}</p>
                                </div>
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ t('Occurred At') }}</p>
                                    <p class="mt-2 text-sm font-medium text-slate-950">{{ latestEvent.created_at }}</p>
                                </div>
                            </div>
                            <div v-else class="mt-4 rounded-2xl bg-slate-50 p-4 text-sm text-slate-500">
                                {{ t('No financial event is available yet.') }}
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <div v-else-if="activeTab === 'analytics'" class="mt-4 space-y-4">
                <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">{{ t('Last 7 Days') }}</p>
                            <h2 class="mt-2 text-lg font-semibold text-slate-950">{{ t('Time-Based Analytics') }}</h2>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ t('Revenue Last 7 Days') }}</p>
                                <p class="mt-2 text-xl font-semibold text-emerald-700">{{ dashboard.analytics.insights.revenue_last_7_days }}</p>
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ t('Refunds Last 7 Days') }}</p>
                                <p class="mt-2 text-xl font-semibold text-rose-700">{{ dashboard.analytics.insights.refunds_last_7_days }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="mt-5 overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm text-slate-700">
                            <thead class="bg-slate-50 text-xs uppercase tracking-[0.18em] text-slate-500">
                                <tr>
                                    <th class="px-4 py-3 text-left font-semibold">{{ t('Date') }}</th>
                                    <th class="px-4 py-3 text-left font-semibold">{{ t('Payments') }}</th>
                                    <th class="px-4 py-3 text-left font-semibold">{{ t('Refunds') }}</th>
                                    <th class="px-4 py-3 text-left font-semibold">{{ t('Net') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                <tr v-for="day in analyticsRows" :key="day.date">
                                    <td class="whitespace-nowrap px-4 py-3 font-medium text-slate-950">{{ day.date }}</td>
                                    <td class="whitespace-nowrap px-4 py-3">{{ day.payments }}</td>
                                    <td class="whitespace-nowrap px-4 py-3">{{ day.refunds }}</td>
                                    <td class="whitespace-nowrap px-4 py-3">{{ day.net }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="grid gap-4 xl:grid-cols-3">
                    <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h3 class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">{{ t('Type Breakdown') }}</h3>
                        <div class="mt-4 space-y-3">
                            <div v-for="segment in dashboard.analytics.segmentation.by_type" :key="segment.key" class="rounded-2xl bg-slate-50 px-4 py-3">
                                <div class="flex items-center justify-between gap-4">
                                    <p class="text-sm font-medium text-slate-950">{{ formatValueLabel(segment.key) }}</p>
                                    <p class="text-sm text-slate-600">{{ segment.total }}</p>
                                </div>
                                <p class="mt-1 text-xs uppercase tracking-[0.16em] text-slate-500">{{ t('Count transactions', { count: segment.count }) }}</p>
                            </div>
                        </div>
                    </article>

                    <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h3 class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">{{ t('Source Breakdown') }}</h3>
                        <div class="mt-4 space-y-3">
                            <div v-for="segment in dashboard.analytics.segmentation.by_source" :key="segment.key" class="rounded-2xl bg-slate-50 px-4 py-3">
                                <div class="flex items-center justify-between gap-4">
                                    <p class="text-sm font-medium text-slate-950">{{ formatValueLabel(segment.key) }}</p>
                                    <p class="text-sm text-slate-600">{{ segment.total }}</p>
                                </div>
                                <p class="mt-1 text-xs uppercase tracking-[0.16em] text-slate-500">{{ t('Count transactions', { count: segment.count }) }}</p>
                            </div>
                        </div>
                    </article>

                    <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h3 class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">{{ t('Currency Breakdown') }}</h3>
                        <div class="mt-4 space-y-3">
                            <div v-for="segment in dashboard.analytics.segmentation.by_currency" :key="segment.key" class="rounded-2xl bg-slate-50 px-4 py-3">
                                <div class="flex items-center justify-between gap-4">
                                    <p class="text-sm font-medium text-slate-950">{{ formatValueLabel(segment.key) }}</p>
                                    <p class="text-sm text-slate-600">{{ segment.total }}</p>
                                </div>
                                <p class="mt-1 text-xs uppercase tracking-[0.16em] text-slate-500">{{ t('Count transactions', { count: segment.count }) }}</p>
                            </div>
                        </div>
                    </article>
                </section>

                <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex flex-col gap-2">
                        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">{{ t('Business Intelligence') }}</p>
                        <h2 class="text-lg font-semibold text-slate-950">{{ t('Derived BI Layer') }}</h2>
                    </div>

                    <div class="mt-5 grid gap-4 lg:grid-cols-3">
                        <article class="rounded-2xl bg-slate-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ t('Refund Rate') }}</p>
                            <p class="mt-2 text-2xl font-semibold text-rose-700">{{ dashboard.analytics.bi.refund_rate.percentage }}%</p>
                            <p class="mt-1 text-xs uppercase tracking-[0.16em] text-slate-500">{{ t('Refunded from payments summary', { refunds: dashboard.analytics.bi.refund_rate.refunds_total, payments: dashboard.analytics.bi.refund_rate.payments_total }) }}</p>
                        </article>

                        <article class="rounded-2xl bg-slate-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ t('Average Order Value') }}</p>
                            <p class="mt-2 text-2xl font-semibold text-slate-950">{{ dashboard.analytics.bi.average_order_value.amount }}</p>
                            <p class="mt-1 text-xs uppercase tracking-[0.16em] text-slate-500">{{ t('Revenue orders summary', { count: dashboard.analytics.bi.average_order_value.orders_count }) }}</p>
                        </article>

                        <article class="rounded-2xl bg-slate-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ t('Monthly Growth') }}</p>
                            <p class="mt-2 text-2xl font-semibold text-emerald-700">{{ dashboard.analytics.bi.monthly_growth.change_percentage }}%</p>
                            <p class="mt-1 text-xs uppercase tracking-[0.16em] text-slate-500">{{ t('Current period vs previous period', { current: dashboard.analytics.bi.monthly_growth.current_period, previous: dashboard.analytics.bi.monthly_growth.previous_period }) }}</p>
                        </article>
                    </div>

                    <div class="mt-5 grid gap-4 xl:grid-cols-3">
                        <article class="rounded-2xl border border-slate-200 p-4">
                            <h3 class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">{{ t('Revenue Per Service') }}</h3>
                            <div class="mt-4 space-y-3">
                                <div v-for="segment in dashboard.analytics.bi.revenue_per_service" :key="segment.key" class="rounded-2xl bg-slate-50 px-4 py-3">
                                    <div class="flex items-center justify-between gap-4">
                                        <p class="text-sm font-medium text-slate-950">{{ formatValueLabel(segment.key) }}</p>
                                        <p class="text-sm text-slate-600">{{ segment.total }}</p>
                                    </div>
                                    <p class="mt-1 text-xs uppercase tracking-[0.16em] text-slate-500">{{ t('Count payment events', { count: segment.count }) }}</p>
                                </div>
                            </div>
                        </article>

                        <article class="rounded-2xl border border-slate-200 p-4">
                            <h3 class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">{{ t('Revenue Per Country') }}</h3>
                            <div class="mt-4 space-y-3">
                                <div v-for="segment in dashboard.analytics.bi.revenue_per_country" :key="segment.key" class="rounded-2xl bg-slate-50 px-4 py-3">
                                    <div class="flex items-center justify-between gap-4">
                                        <p class="text-sm font-medium text-slate-950">{{ formatValueLabel(segment.key) }}</p>
                                        <p class="text-sm text-slate-600">{{ segment.total }}</p>
                                    </div>
                                    <p class="mt-1 text-xs uppercase tracking-[0.16em] text-slate-500">{{ t('Count payment events', { count: segment.count }) }}</p>
                                </div>
                            </div>
                        </article>

                        <article class="rounded-2xl border border-slate-200 p-4">
                            <h3 class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">{{ t('Transaction Mix') }}</h3>
                            <div class="mt-4 space-y-3">
                                <div v-for="segment in dashboard.analytics.bi.transaction_mix" :key="segment.key" class="rounded-2xl bg-slate-50 px-4 py-3">
                                    <div class="flex items-center justify-between gap-4">
                                        <p class="text-sm font-medium text-slate-950">{{ formatValueLabel(segment.key) }}</p>
                                        <p class="text-sm text-slate-600">{{ segment.share }}%</p>
                                    </div>
                                    <p class="mt-1 text-xs uppercase tracking-[0.16em] text-slate-500">{{ t('Transaction mix summary', { count: segment.count, total: segment.total }) }}</p>
                                </div>
                            </div>
                        </article>
                    </div>
                </section>

                <section class="grid gap-4 xl:grid-cols-2">
                    <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h3 class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">{{ t('Profit Per Country') }}</h3>
                        <div class="mt-4 space-y-3">
                            <div v-for="segment in dashboard.intelligence.profit_per_country" :key="segment.key" class="rounded-2xl bg-slate-50 px-4 py-3">
                                <div class="flex items-center justify-between gap-4">
                                    <p class="text-sm font-medium text-slate-950">{{ formatValueLabel(segment.key) }}</p>
                                    <p class="text-sm text-slate-600">{{ segment.total }}</p>
                                </div>
                            </div>
                        </div>
                    </article>

                    <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h3 class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">{{ t('Commission By Provider') }}</h3>
                        <div class="mt-4 space-y-3">
                            <div v-for="segment in dashboard.intelligence.commission_breakdown_per_provider" :key="segment.key" class="rounded-2xl bg-slate-50 px-4 py-3">
                                <div class="flex items-center justify-between gap-4">
                                    <p class="text-sm font-medium text-slate-950">{{ formatValueLabel(segment.key) }}</p>
                                    <p class="text-sm text-slate-600">{{ segment.total }}</p>
                                </div>
                            </div>
                        </div>
                    </article>
                </section>

                <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h3 class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">{{ t('Refund Impact') }}</h3>
                    <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        <article class="rounded-2xl bg-slate-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ t('Refunds total') }}</p>
                            <p class="mt-2 text-xl font-semibold text-rose-700">{{ dashboard.intelligence.refund_impact.refunds_total }}</p>
                        </article>
                        <article class="rounded-2xl bg-slate-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ t('Impacted orders') }}</p>
                            <p class="mt-2 text-xl font-semibold text-slate-950">{{ dashboard.intelligence.refund_impact.impacted_orders_count }}</p>
                        </article>
                    </div>
                    <div class="mt-4 space-y-3">
                        <div v-for="segment in dashboard.intelligence.refund_impact.by_service" :key="segment.key" class="rounded-2xl bg-slate-50 px-4 py-3">
                            <div class="flex items-center justify-between gap-4">
                                <p class="text-sm font-medium text-slate-950">{{ formatValueLabel(segment.key) }}</p>
                                <p class="text-sm text-slate-600">{{ segment.total }}</p>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h3 class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">{{ t('Customer Lifetime Value') }}</h3>
                    <div v-if="dashboard.intelligence.customer_lifetime_value.length === 0" class="mt-4 rounded-2xl bg-slate-50 p-4 text-sm text-slate-500">
                        {{ t('No customer lifetime value rows are available for the current filter range.') }}
                    </div>
                    <div v-else class="mt-4 overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm text-slate-700">
                            <thead class="bg-slate-50 text-xs uppercase tracking-[0.18em] text-slate-500">
                                <tr>
                                    <th class="px-4 py-3 text-left font-semibold">{{ t('Customer') }}</th>
                                    <th class="px-4 py-3 text-left font-semibold">{{ t('Country') }}</th>
                                    <th class="px-4 py-3 text-left font-semibold">{{ t('Orders') }}</th>
                                    <th class="px-4 py-3 text-left font-semibold">{{ t('Net value') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                <tr v-for="row in dashboard.intelligence.customer_lifetime_value" :key="row.customer_id">
                                    <td class="px-4 py-3 font-medium text-slate-950">{{ row.customer_name || t('Unknown customer') }}</td>
                                    <td class="px-4 py-3">{{ formatValueLabel(row.country) }}</td>
                                    <td class="px-4 py-3">{{ row.orders_count }}</td>
                                    <td class="px-4 py-3">{{ row.net_value }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <div v-else-if="activeTab === 'operations'" class="mt-4 space-y-4">
                <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h3 class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">{{ t('Financial anomalies') }}</h3>
                    <div v-if="dashboard.reconciliation.items.length === 0" class="mt-4 rounded-2xl bg-slate-50 p-4 text-sm text-slate-500">
                        {{ t('No anomalies detected in the current filter range.') }}
                    </div>
                    <div v-else class="mt-4 space-y-3">
                        <div v-for="item in dashboard.reconciliation.items" :key="`${item.code}-${item.transaction?.id || item.order?.id}`" class="rounded-2xl border border-slate-200 p-4">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <p class="text-sm font-semibold text-slate-950">{{ formatValueLabel(item.code) }}</p>
                                    <p class="mt-1 text-sm text-slate-600">{{ item.message }}</p>
                                </div>
                                <span class="rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.16em]" :class="item.severity === 'critical' ? 'bg-rose-50 text-rose-700' : item.severity === 'high' ? 'bg-amber-50 text-amber-700' : 'bg-slate-100 text-slate-600'">
                                    {{ formatValueLabel(item.severity) }}
                                </span>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 px-5 py-4">
                        <h2 class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">{{ t('Ledger Preview') }}</h2>
                    </div>

                    <div class="overflow-x-auto border-b border-slate-200">
                        <table class="min-w-full divide-y divide-slate-200 text-sm text-slate-700">
                            <thead class="bg-slate-50 text-xs uppercase tracking-[0.18em] text-slate-500">
                                <tr>
                                    <th class="px-4 py-3 text-left font-semibold">{{ t('Transaction') }}</th>
                                    <th class="px-4 py-3 text-left font-semibold">{{ t('Debit') }}</th>
                                    <th class="px-4 py-3 text-left font-semibold">{{ t('Credit') }}</th>
                                    <th class="px-4 py-3 text-left font-semibold">{{ t('Amount') }}</th>
                                    <th class="px-4 py-3 text-left font-semibold">{{ t('Currency') }}</th>
                                    <th class="px-4 py-3 text-left font-semibold">{{ t('Reference') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                <tr v-for="entry in dashboard.activity.ledger_preview" :key="entry.transaction_id">
                                    <td class="whitespace-nowrap px-4 py-3 font-medium text-slate-950">#{{ entry.transaction_id }} / {{ formatValueLabel(entry.type) }}</td>
                                    <td class="whitespace-nowrap px-4 py-3">{{ entry.debit_account }}</td>
                                    <td class="whitespace-nowrap px-4 py-3">{{ entry.credit_account }}</td>
                                    <td class="whitespace-nowrap px-4 py-3">{{ entry.amount }}</td>
                                    <td class="whitespace-nowrap px-4 py-3">{{ entry.currency }}</td>
                                    <td class="whitespace-nowrap px-4 py-3">{{ formatValueLabel(entry.reference_type) }} #{{ entry.reference_id }}</td>
                                </tr>
                                <tr v-if="dashboard.activity.ledger_preview.length === 0">
                                    <td colspan="6" class="px-4 py-6 text-center text-slate-500">
                                        {{ t('No ledger preview is available yet.') }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="border-b border-slate-200 px-5 py-4">
                        <h2 class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">{{ t('Latest Transactions') }}</h2>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm text-slate-700">
                            <thead class="bg-slate-50 text-xs uppercase tracking-[0.18em] text-slate-500">
                                <tr>
                                    <th class="px-4 py-3 text-left font-semibold">{{ t('ID') }}</th>
                                    <th class="px-4 py-3 text-left font-semibold">{{ t('Type') }}</th>
                                    <th class="px-4 py-3 text-left font-semibold">{{ t('Amount') }}</th>
                                    <th class="px-4 py-3 text-left font-semibold">{{ t('Currency') }}</th>
                                    <th class="px-4 py-3 text-left font-semibold">{{ t('Source') }}</th>
                                    <th class="px-4 py-3 text-left font-semibold">{{ t('Order ID') }}</th>
                                    <th class="px-4 py-3 text-left font-semibold">{{ t('Created At') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                <tr v-for="transaction in dashboard.activity.latest_transactions" :key="transaction.id" class="align-top">
                                    <td class="whitespace-nowrap px-4 py-3 font-medium text-slate-950">{{ transaction.id }}</td>
                                    <td class="whitespace-nowrap px-4 py-3">{{ formatValueLabel(transaction.type) }}</td>
                                    <td class="whitespace-nowrap px-4 py-3">{{ transaction.amount }}</td>
                                    <td class="whitespace-nowrap px-4 py-3">{{ transaction.currency }}</td>
                                    <td class="whitespace-nowrap px-4 py-3">{{ formatValueLabel(transaction.source) }}</td>
                                    <td class="whitespace-nowrap px-4 py-3">{{ transaction.order_id ?? t('—') }}</td>
                                    <td class="whitespace-nowrap px-4 py-3">{{ transaction.created_at ?? t('—') }}</td>
                                </tr>
                                <tr v-if="dashboard.activity.latest_transactions.length === 0">
                                    <td colspan="7" class="px-4 py-6 text-center text-slate-500">
                                        {{ t('No financial transactions recorded yet.') }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <div v-else class="mt-4 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h2 class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">{{ t('Order Drill-Down') }}</h2>
                        <p class="mt-2 text-sm text-slate-600">{{ t('Order-level profitability and cash movement view.') }}</p>
                    </div>
                </div>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm text-slate-700">
                        <thead class="bg-slate-50 text-xs uppercase tracking-[0.18em] text-slate-500">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold">{{ t('Order') }}</th>
                                <th class="px-4 py-3 text-left font-semibold">{{ t('Provider') }}</th>
                                <th class="px-4 py-3 text-left font-semibold">{{ t('Service') }}</th>
                                <th class="px-4 py-3 text-left font-semibold">{{ t('Country') }}</th>
                                <th class="px-4 py-3 text-left font-semibold">{{ t('Payments') }}</th>
                                <th class="px-4 py-3 text-left font-semibold">{{ t('Refunds') }}</th>
                                <th class="px-4 py-3 text-left font-semibold">{{ t('Commissions') }}</th>
                                <th class="px-4 py-3 text-left font-semibold">{{ t('Payouts') }}</th>
                                <th class="px-4 py-3 text-left font-semibold">{{ t('Net Cash') }}</th>
                                <th class="px-4 py-3 text-left font-semibold">{{ t('Gross Profit') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <tr v-for="row in dashboard.drilldown.orders" :key="row.order_id">
                                <td class="px-4 py-3 font-medium text-slate-950">{{ row.booking_reference || row.order_id }}</td>
                                <td class="px-4 py-3">{{ row.provider_name || t('Unknown') }}</td>
                                <td class="px-4 py-3">{{ formatValueLabel(row.service_type) }}</td>
                                <td class="px-4 py-3">{{ formatValueLabel(row.country) }}</td>
                                <td class="px-4 py-3">{{ row.payments_total }}</td>
                                <td class="px-4 py-3">{{ row.refunds_total }}</td>
                                <td class="px-4 py-3">{{ row.commissions_total }}</td>
                                <td class="px-4 py-3">{{ row.payouts_total }}</td>
                                <td class="px-4 py-3">{{ row.net_cash }}</td>
                                <td class="px-4 py-3">{{ row.gross_profit }}</td>
                            </tr>
                            <tr v-if="dashboard.drilldown.orders.length === 0">
                                <td colspan="10" class="px-4 py-6 text-center text-slate-500">{{ t('No order-level finance rows were found.') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </AdminModulePage>
    </AdminLayout>
</template>
