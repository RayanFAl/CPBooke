<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AdminLayout from '../../layouts/AdminLayout.vue';
import { useAdminLocale } from '../../composables/useAdminLocale';

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
});

const { locale, t } = useAdminLocale();

const filtersForm = useForm({
    date_from: props.filters.date_from ?? '',
    date_to: props.filters.date_to ?? '',
    module: props.filters.module ?? 'rbac',
});

const tabs = computed(() => props.filter_options.modules ?? []);

const governanceAreas = {
    rbac: {
        title: 'Access and permissions',
        description: 'Review who accessed admin features and whether sensitive permission changes happened.',
        helper: 'Recent permission activity and sensitive changes.',
        eventsTitle: 'Latest access events',
        eventsDescription: 'See the latest permission checks and configuration changes.',
        emptyState: 'No access events found for the selected range.',
    },
    finance: {
        title: 'Financial integrity',
        description: 'Check reconciliation state, critical anomalies, and revenue drift before issues reach reporting.',
        helper: 'Anomalies, reconciliation, and drift in one view.',
        eventsTitle: 'Latest finance issues',
        eventsDescription: 'See the newest anomalies and reconciliation-related findings.',
        emptyState: 'No finance issues found for the selected range.',
    },
    notifications: {
        title: 'Notification delivery',
        description: 'See failed sends, channel health, and recent delivery attempts.',
        helper: 'Delivery failures and channel readiness.',
        eventsTitle: 'Latest delivery events',
        eventsDescription: 'See the newest sends, failures, and template activity.',
        emptyState: 'No notification events found for the selected range.',
    },
    loyalty: {
        title: 'Loyalty changes',
        description: 'Track tier upgrades, benefit unlocks, and recent loyalty movement.',
        helper: 'Tier movement and unlock activity.',
        eventsTitle: 'Latest loyalty events',
        eventsDescription: 'See the newest upgrades, benefit unlocks, and tier movements.',
        emptyState: 'No loyalty events found for the selected range.',
    },
};

const cards = computed(() => [
    { key: 'rbac', ...props.dashboard.rbac.kpi, helper: governanceAreas.rbac.helper },
    { key: 'finance', ...props.dashboard.finance.kpi, helper: governanceAreas.finance.helper },
    { key: 'notifications', ...props.dashboard.notifications.kpi, helper: governanceAreas.notifications.helper },
    { key: 'loyalty', ...props.dashboard.loyalty.kpi, helper: governanceAreas.loyalty.helper },
]);

const activeTab = computed(() => filtersForm.module || 'rbac');

const currentArea = computed(() => governanceAreas[activeTab.value] ?? governanceAreas.rbac);

const tabMap = computed(() => ({
    rbac: {
        summary: props.dashboard.rbac.summary_24h,
        events: props.dashboard.rbac.events,
    },
    finance: {
        summary: props.dashboard.finance.summary_24h,
        events: props.dashboard.finance.events,
        meta: props.dashboard.finance.last_reconcile,
    },
    notifications: {
        summary: props.dashboard.notifications.summary_24h,
        events: props.dashboard.notifications.events,
        channels: props.dashboard.notifications.channels,
    },
    loyalty: {
        summary: props.dashboard.loyalty.summary_24h,
        events: props.dashboard.loyalty.events,
    },
}));

const currentTab = computed(() => tabMap.value[activeTab.value] ?? tabMap.value.rbac);

const summaryLabelMap = {
    rbac: {
        events: 'Access events',
        unique_users: 'Unique users',
        sensitive_actions: 'Sensitive changes',
    },
    finance: {
        reconciliation_status: 'Reconciliation',
        critical_anomalies: 'Critical issues',
        revenue_drift: 'Revenue drift',
    },
    notifications: {
        sent: 'Delivered',
        failed: 'Failed',
        success_rate: 'Success rate',
    },
    loyalty: {
        tier_changes: 'Tier changes',
        upgrades: 'Upgrades',
        benefit_unlocks: 'Benefit unlocks',
    },
};

const summaryEntries = computed(() => {
    const labels = summaryLabelMap[activeTab.value] ?? {};

    return Object.entries(currentTab.value.summary ?? {}).map(([key, value]) => ({
        key,
        label: t(labels[key] ?? pretty(key)),
        value: key === 'success_rate' && value !== null && value !== undefined ? `${value}%` : pretty(value),
    }));
});

const applyFilters = () => {
    filtersForm.get(route('admin.governance.dashboard'), {
        preserveState: true,
        preserveScroll: true,
    });
};

const switchTab = (module) => {
    filtersForm.module = module;
    applyFilters();
};

const normalizeLabel = (value) => String(value).replaceAll('_', ' ').trim();

const pretty = (value) => {
    if (!value) {
        return t('Not available');
    }

    const rawValue = String(value).trim();
    const normalizedValue = normalizeLabel(rawValue);
    const titleizedValue = normalizedValue.replace(/\b\w/g, (letter) => letter.toUpperCase());

    for (const candidate of [rawValue, normalizedValue, titleizedValue]) {
        const translatedValue = t(candidate);

        if (translatedValue !== candidate) {
            return translatedValue;
        }
    }

    return titleizedValue;
};

const localizeDelta = (value) => {
    if (!value) {
        return '';
    }

    const normalizedValue = String(value).trim();
    const failedMatch = normalizedValue.match(/^(\d+) failed in last 24h$/i);

    if (failedMatch) {
        return t(':count failed in last 24h', { count: failedMatch[1] });
    }

    const countMatch = normalizedValue.match(/^(\d+) in last 24h$/i);

    if (countMatch) {
        return t(':count in last 24h', { count: countMatch[1] });
    }

    const driftMatch = normalizedValue.match(/^([\d.]+)% revenue drift$/i);

    if (driftMatch) {
        return t(':value% revenue drift', { value: driftMatch[1] });
    }

    return t(normalizedValue);
};

const formatDateTime = (value) => {
    if (!value) {
        return t('Not available');
    }

    return new Intl.DateTimeFormat(locale.value, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
};

const eventReference = (event) => {
    if (activeTab.value === 'rbac') {
        return event.actor || t('System');
    }

    if (activeTab.value === 'finance') {
        return event.reference || event.code || t('Not available');
    }

    if (activeTab.value === 'notifications') {
        return event.recipient || event.template_code || t('Unknown user');
    }

    return event.user || event.to_tier || event.action || t('Unknown user');
};

const eventDetails = (event) => {
    if (activeTab.value === 'rbac') {
        return [pretty(event.action), event.permission].filter(Boolean).join(' / ') || t('Not available');
    }

    if (activeTab.value === 'finance') {
        return event.message || event.code || t('Not available');
    }

    if (activeTab.value === 'notifications') {
        return [pretty(event.channel), event.failure_reason || event.template_code].filter(Boolean).join(' / ') || t('Not available');
    }

    return [pretty(event.action), event.from_tier, event.to_tier].filter(Boolean).join(' / ') || t('Not available');
};

const statusClass = (status) => {
    if (['healthy', 'configured', 'active', 'observed'].includes(status)) {
        return 'bg-emerald-50 text-emerald-700';
    }

    if (['warning', 'sensitive', 'fallback'].includes(status)) {
        return 'bg-amber-50 text-amber-700';
    }

    if (status === 'critical') {
        return 'bg-rose-50 text-rose-700';
    }

    return 'bg-slate-100 text-slate-600';
};
</script>

<template>
    <AdminLayout title="Governance" description="Centralized control center for access oversight, finance integrity, delivery health, and loyalty movement.">
        <section class="space-y-6">
            <section class="grid gap-4 xl:grid-cols-[1.25fr,0.75fr]">
                <article class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-[0.25em] text-cyan-700">{{ t('Governance overview') }}</p>
                    <h2 class="mt-3 text-2xl font-semibold text-slate-950">{{ t('One screen for risk and control') }}</h2>
                    <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-600">
                        {{ t('Track permissions, financial anomalies, notification delivery, and loyalty changes without jumping between modules.') }}
                    </p>
                </article>

                <article class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">{{ t('How to use this page') }}</h3>
                    <div class="mt-4 space-y-3 text-sm text-slate-600">
                        <p>{{ t('Choose a date range and module to narrow the signal.') }}</p>
                        <p>{{ t('Read the summary first to understand the health of the selected area.') }}</p>
                        <p>{{ t('Use the event list to inspect who, what, and when.') }}</p>
                    </div>
                </article>
            </section>

            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="mb-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{{ t('Filters') }}</p>
                    <p class="mt-2 text-sm text-slate-600">{{ t('Narrow the time window and switch the operational area you want to review.') }}</p>
                </div>

                <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                    <div class="grid flex-1 gap-3 md:grid-cols-3">
                        <label class="text-sm text-slate-600">
                            <span class="block font-medium text-slate-700">{{ t('Date from') }}</span>
                            <input v-model="filtersForm.date_from" type="date" class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3" />
                        </label>
                        <label class="text-sm text-slate-600">
                            <span class="block font-medium text-slate-700">{{ t('Date to') }}</span>
                            <input v-model="filtersForm.date_to" type="date" class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3" />
                        </label>
                        <label class="text-sm text-slate-600">
                            <span class="block font-medium text-slate-700">{{ t('Module') }}</span>
                            <select v-model="filtersForm.module" class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3">
                                <option v-for="option in filter_options.modules" :key="option.name" :value="option.name">{{ t(option.label) }}</option>
                            </select>
                        </label>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <button type="button" class="inline-flex items-center justify-center rounded-2xl bg-slate-950 px-4 py-3 text-sm font-medium text-white transition hover:bg-slate-800" @click="applyFilters">
                            {{ t('Apply filters') }}
                        </button>
                    </div>
                </div>
            </section>

            <section class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <article v-for="card in cards" :key="card.key" class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">{{ t(card.label) }}</p>
                            <p class="mt-3 text-3xl font-semibold text-slate-950">{{ card.value }}</p>
                        </div>
                        <span class="rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.16em]" :class="statusClass(card.status)">
                            {{ pretty(card.status) }}
                        </span>
                    </div>
                    <p class="mt-3 text-sm font-medium text-slate-700">{{ t(card.helper) }}</p>
                    <p class="mt-2 text-sm text-slate-600">{{ localizeDelta(card.delta) }}</p>
                </article>
            </section>

            <section class="mt-4 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap gap-3 border-b border-slate-200 pb-4">
                    <button v-for="tab in tabs" :key="tab.name" type="button" class="rounded-2xl px-4 py-2 text-sm font-medium transition" :class="activeTab === tab.name ? 'bg-slate-950 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'" @click="switchTab(tab.name)">
                        {{ t(tab.label) }}
                    </button>
                </div>

                <div class="mt-5 grid gap-4 xl:grid-cols-[1.1fr,0.9fr]">
                    <article class="rounded-3xl bg-slate-50 p-5">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{{ t('Selected area') }}</p>
                        <div class="mt-2 flex items-center justify-between gap-3">
                            <h2 class="text-lg font-semibold text-slate-950">{{ t(currentArea.title) }}</h2>
                            <span v-if="currentTab.meta?.created_at" class="text-xs uppercase tracking-[0.16em] text-slate-500">
                                {{ t('Last reconcile') }}: {{ formatDateTime(currentTab.meta.created_at) }}
                            </span>
                        </div>
                        <p class="mt-2 text-sm leading-6 text-slate-600">{{ t(currentArea.description) }}</p>

                        <div class="mt-4 grid gap-3 md:grid-cols-3">
                            <div v-for="item in summaryEntries" :key="item.key" class="rounded-2xl bg-white px-4 py-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ item.label }}</p>
                                <p class="mt-2 text-xl font-semibold text-slate-950">{{ item.value }}</p>
                            </div>
                        </div>

                        <div v-if="currentTab.channels?.length" class="mt-4 rounded-2xl bg-white p-4">
                            <h3 class="text-sm font-semibold uppercase tracking-[0.16em] text-slate-500">{{ t('Channel Breakdown') }}</h3>
                            <p class="mt-2 text-sm text-slate-600">{{ t('Configured channels should stay green. Fallback means the channel is not fully configured.') }}</p>
                            <div class="mt-3 space-y-3">
                                <div v-for="channel in currentTab.channels" :key="channel.channel" class="flex items-center justify-between gap-3 rounded-2xl bg-slate-50 px-4 py-3">
                                    <div>
                                        <p class="text-sm font-medium text-slate-950">{{ pretty(channel.channel) }}</p>
                                        <p class="mt-1 text-xs uppercase tracking-[0.16em] text-slate-500">{{ channel.count }} {{ t('events') }}</p>
                                    </div>
                                    <span class="rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.16em]" :class="statusClass(channel.status)">
                                        {{ pretty(channel.status) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </article>

                    <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{{ t('Latest records') }}</p>
                        <h2 class="mt-2 text-lg font-semibold text-slate-950">{{ t(currentArea.eventsTitle) }}</h2>
                        <p class="mt-2 text-sm leading-6 text-slate-600">{{ t(currentArea.eventsDescription) }}</p>
                        <div v-if="currentTab.events.length === 0" class="mt-4 rounded-2xl bg-slate-50 p-4 text-sm text-slate-500">
                            {{ t(currentArea.emptyState) }}
                        </div>
                        <div v-else class="mt-4 overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-200 text-sm text-slate-700">
                                <thead class="bg-slate-50 text-xs uppercase tracking-[0.18em] text-slate-500">
                                    <tr>
                                        <th class="px-4 py-3 text-left font-semibold">{{ t('Reference') }}</th>
                                        <th class="px-4 py-3 text-left font-semibold">{{ t('State') }}</th>
                                        <th class="px-4 py-3 text-left font-semibold">{{ t('Details') }}</th>
                                        <th class="px-4 py-3 text-left font-semibold">{{ t('Time') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 bg-white">
                                    <tr v-for="event in currentTab.events" :key="event.id">
                                        <td class="px-4 py-3 font-medium text-slate-950">
                                            {{ eventReference(event) }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.16em]" :class="statusClass(event.status || event.severity || event.action)">
                                                {{ pretty(event.status || event.severity || event.action) }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-slate-600">{{ eventDetails(event) }}</td>
                                        <td class="px-4 py-3">{{ formatDateTime(event.created_at) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </article>
                </div>
            </section>
        </section>
    </AdminLayout>
</template>