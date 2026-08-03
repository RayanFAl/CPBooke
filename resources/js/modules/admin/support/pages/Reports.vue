<script setup>
import AdminLayout from '../../layouts/AdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
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
    exports: {
        type: Object,
        required: true,
    },
});

const { locale, t } = useAdminLocale();

const filtersForm = useForm({
    date_from: props.filters.date_from ?? '',
    date_to: props.filters.date_to ?? '',
    status_after: props.filters.status_after ?? '',
    resolution_type: props.filters.resolution_type ?? '',
    agent_id: props.filters.agent_id ?? '',
});

const summaryCards = computed(() => [
    { key: 'total_reports', label: t('Total reports'), value: props.dashboard.summary?.total_reports ?? 0 },
    { key: 'average_handling_minutes', label: t('Average handling minutes'), value: props.dashboard.summary?.average_handling_minutes ?? 0 },
    { key: 'reopen_rate', label: t('Reopen rate'), value: `${props.dashboard.summary?.reopen_rate ?? 0}%` },
    { key: 'escalation_rate', label: t('Escalation rate'), value: `${props.dashboard.summary?.escalation_rate ?? 0}%` },
]);

const formatLabel = (value) => {
    if (!value) {
        return t('Not available');
    }

    return t(String(value).replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase()));
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

const applyFilters = () => {
    filtersForm.get(route('admin.support.reports.index'), {
        preserveScroll: true,
        preserveState: true,
    });
};

const resetFilters = () => {
    filtersForm.date_from = '';
    filtersForm.date_to = '';
    filtersForm.status_after = '';
    filtersForm.resolution_type = '';
    filtersForm.agent_id = '';
    applyFilters();
};
</script>

<template>
    <Head :title="t('Support reports')" />

    <AdminLayout
        title="Support reports"
        description="Review closed and resolved tickets, export CSV reports, and monitor agent performance."
    >
        <section class="space-y-6">
            <div class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.25em] text-cyan-700">
                            {{ t('Support') }}
                        </p>
                        <h2 class="mt-2 text-2xl font-semibold text-slate-950">{{ t('Support reports') }}</h2>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">
                            {{ t('Review closed and resolved tickets, export CSV reports, and monitor agent performance.') }}
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <Link
                            :href="route('admin.support.index')"
                            class="inline-flex items-center justify-center rounded-2xl border border-slate-200 px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                        >
                            {{ t('Support inbox') }}
                        </Link>
                        <a
                            v-if="dashboard.available"
                            :href="exports.csv"
                            class="inline-flex items-center justify-center rounded-2xl bg-cyan-600 px-4 py-3 text-sm font-medium text-white transition hover:bg-cyan-700"
                        >
                            {{ t('Export CSV') }}
                        </a>
                    </div>
                </div>

                <form class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-5" @submit.prevent="applyFilters">
                    <label class="block">
                        <span class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ t('Date from') }}</span>
                        <input v-model="filtersForm.date_from" type="date" class="mt-2 block w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-cyan-600">
                    </label>
                    <label class="block">
                        <span class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ t('Date to') }}</span>
                        <input v-model="filtersForm.date_to" type="date" class="mt-2 block w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-cyan-600">
                    </label>
                    <label class="block">
                        <span class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ t('Resolution Type') }}</span>
                        <select v-model="filtersForm.resolution_type" class="mt-2 block w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-cyan-600">
                            <option value="">{{ t('All resolution types') }}</option>
                            <option v-for="option in dashboard.filter_options.resolution_types" :key="option.name" :value="option.name">
                                {{ t(option.label) }}
                            </option>
                        </select>
                    </label>
                    <label class="block">
                        <span class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ t('Status After') }}</span>
                        <select v-model="filtersForm.status_after" class="mt-2 block w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-cyan-600">
                            <option value="">{{ t('All outcomes') }}</option>
                            <option v-for="option in dashboard.filter_options.status_after" :key="option.name" :value="option.name">
                                {{ t(option.label) }}
                            </option>
                        </select>
                    </label>
                    <label class="block">
                        <span class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ t('Agent') }}</span>
                        <select v-model="filtersForm.agent_id" class="mt-2 block w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-cyan-600">
                            <option value="">{{ t('All agents') }}</option>
                            <option v-for="agent in dashboard.filter_options.agents" :key="agent.id" :value="agent.id">
                                {{ agent.name }}
                            </option>
                        </select>
                    </label>
                    <div class="flex flex-wrap items-end gap-3 md:col-span-2 xl:col-span-5">
                        <button type="submit" class="inline-flex items-center justify-center rounded-2xl bg-slate-950 px-4 py-3 text-sm font-medium text-white transition hover:bg-slate-800">
                            {{ t('Apply filters') }}
                        </button>
                        <button type="button" class="inline-flex items-center justify-center rounded-2xl border border-slate-200 px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50" @click="resetFilters">
                            {{ t('Reset filters') }}
                        </button>
                    </div>
                </form>
            </div>

            <div v-if="!dashboard.available" class="rounded-[2rem] border border-dashed border-slate-300 bg-white p-8 text-center shadow-sm">
                <h3 class="text-lg font-semibold text-slate-950">{{ t('Reports unavailable') }}</h3>
                <p class="mt-2 text-sm text-slate-600">
                    {{ t('Resolution reports will appear here once tickets are resolved or closed with a captured report.') }}
                </p>
            </div>

            <template v-else>
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <article v-for="card in summaryCards" :key="card.key" class="rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ card.label }}</p>
                        <p class="mt-3 text-2xl font-semibold text-slate-950">{{ card.value }}</p>
                    </article>
                </div>

                <div class="grid gap-6 xl:grid-cols-2">
                    <section class="rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-sm">
                        <h3 class="text-sm font-semibold text-slate-950">{{ t('By resolution type') }}</h3>
                        <ul class="mt-4 space-y-3">
                            <li v-for="item in dashboard.by_resolution_type" :key="item.key" class="flex items-center justify-between rounded-2xl bg-slate-50 px-4 py-3 text-sm">
                                <span>{{ formatLabel(item.key) }}</span>
                                <strong>{{ item.count }}</strong>
                            </li>
                            <li v-if="!dashboard.by_resolution_type.length" class="text-sm text-slate-500">{{ t('Not available') }}</li>
                        </ul>
                    </section>

                    <section class="rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-sm">
                        <h3 class="text-sm font-semibold text-slate-950">{{ t('Top root causes') }}</h3>
                        <ul class="mt-4 space-y-3">
                            <li v-for="item in dashboard.top_root_causes" :key="item.root_cause" class="rounded-2xl bg-slate-50 px-4 py-3 text-sm">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="font-medium text-slate-900">{{ item.root_cause }}</span>
                                    <strong>{{ item.count }}</strong>
                                </div>
                            </li>
                            <li v-if="!dashboard.top_root_causes.length" class="text-sm text-slate-500">{{ t('Not available') }}</li>
                        </ul>
                    </section>

                    <section class="rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-sm xl:col-span-2">
                        <h3 class="text-sm font-semibold text-slate-950">{{ t('Agent performance') }}</h3>
                        <div class="mt-4 overflow-x-auto">
                            <table class="min-w-full text-left text-sm">
                                <thead class="text-xs uppercase tracking-[0.18em] text-slate-500">
                                    <tr>
                                        <th class="px-4 py-3">{{ t('Agent') }}</th>
                                        <th class="px-4 py-3">{{ t('Resolved tickets') }}</th>
                                        <th class="px-4 py-3">{{ t('Average handling minutes') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="agent in dashboard.agent_performance" :key="agent.agent_id" class="border-t border-slate-100">
                                        <td class="px-4 py-3 text-slate-900">{{ agent.agent_name }}</td>
                                        <td class="px-4 py-3 text-slate-700">{{ agent.resolved_tickets }}</td>
                                        <td class="px-4 py-3 text-slate-700">{{ agent.average_handling_minutes }}</td>
                                    </tr>
                                    <tr v-if="!dashboard.agent_performance.length">
                                        <td colspan="3" class="px-4 py-6 text-slate-500">{{ t('Not available') }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>

                <section class="rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="text-sm font-semibold text-slate-950">{{ t('Recent reports') }}</h3>
                        <p class="text-xs text-slate-500">{{ formatDateTime(dashboard.generated_at) }}</p>
                    </div>
                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full text-left text-sm">
                            <thead class="text-xs uppercase tracking-[0.18em] text-slate-500">
                                <tr>
                                    <th class="px-4 py-3">{{ t('Ticket') }}</th>
                                    <th class="px-4 py-3">{{ t('Customer') }}</th>
                                    <th class="px-4 py-3">{{ t('Agent') }}</th>
                                    <th class="px-4 py-3">{{ t('Resolution Type') }}</th>
                                    <th class="px-4 py-3">{{ t('Status After') }}</th>
                                    <th class="px-4 py-3">{{ t('Resolved At') }}</th>
                                    <th class="px-4 py-3">{{ t('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="report in dashboard.recent_reports" :key="report.id" class="border-t border-slate-100">
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-slate-900">{{ report.ticket_number }}</div>
                                        <div class="text-xs text-slate-500">{{ report.ticket_subject }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-slate-700">{{ report.customer_name || t('Not available') }}</td>
                                    <td class="px-4 py-3 text-slate-700">{{ report.agent_name || t('Not available') }}</td>
                                    <td class="px-4 py-3 text-slate-700">{{ formatLabel(report.resolution_type) }}</td>
                                    <td class="px-4 py-3 text-slate-700">{{ formatLabel(report.status_after) }}</td>
                                    <td class="px-4 py-3 text-slate-700">{{ formatDateTime(report.resolved_at) }}</td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap gap-2">
                                            <Link
                                                :href="route('admin.support.show', report.ticket_id)"
                                                class="rounded-full border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-50"
                                            >
                                                {{ t('View') }}
                                            </Link>
                                            <a
                                                v-if="report.print_url"
                                                :href="report.print_url"
                                                target="_blank"
                                                rel="noopener"
                                                class="rounded-full bg-slate-950 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-slate-800"
                                            >
                                                {{ t('Print report') }}
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!dashboard.recent_reports.length">
                                    <td colspan="7" class="px-4 py-6 text-slate-500">{{ t('Not available') }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </template>
        </section>
    </AdminLayout>
</template>
