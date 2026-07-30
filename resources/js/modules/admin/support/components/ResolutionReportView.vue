<script setup>
import { computed } from 'vue';
import { useAdminLocale } from '../../composables/useAdminLocale';

const props = defineProps({
    report: {
        type: Object,
        default: null,
    },
    ticketId: {
        type: [Number, String],
        default: null,
    },
});

const { locale, t } = useAdminLocale();

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

const statCards = computed(() => {
    if (!props.report) {
        return [];
    }

    return [
        { label: t('Resolution Type'), value: formatLabel(props.report.resolution_type) },
        { label: t('Status After'), value: formatLabel(props.report.status_after) },
        { label: t('Handling Minutes'), value: String(props.report.handling_minutes ?? 0) },
        { label: t('Reopened Count'), value: String(props.report.reopened_count ?? 0) },
    ];
});
</script>

<template>
    <div v-if="report" class="space-y-5">
        <div class="flex flex-wrap items-center justify-end gap-3">
            <a
                v-if="ticketId"
                :href="route('admin.support.resolution-report.print', ticketId)"
                target="_blank"
                rel="noopener"
                class="inline-flex items-center justify-center rounded-full bg-slate-950 px-4 py-2 text-xs font-semibold uppercase tracking-[0.16em] text-white transition hover:bg-slate-800"
            >
                {{ t('Print report') }}
            </a>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div v-for="card in statCards" :key="card.label" class="rounded-2xl bg-slate-50 px-4 py-4">
                <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">{{ card.label }}</p>
                <p class="mt-2 text-sm font-semibold text-slate-950">{{ card.value }}</p>
            </div>
        </div>

        <dl class="grid gap-4 sm:grid-cols-2">
            <div>
                <dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ t('Resolved At') }}</dt>
                <dd class="mt-2 text-sm text-slate-900">{{ formatDateTime(report.resolved_at) }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ t('Agent') }}</dt>
                <dd class="mt-2 text-sm text-slate-900">{{ report.agent?.name || t('System') }}</dd>
            </div>
        </dl>

        <div class="grid gap-4 lg:grid-cols-2">
            <section class="rounded-2xl border border-slate-200 p-4">
                <h4 class="text-sm font-semibold text-slate-950">{{ t('Root Cause') }}</h4>
                <p class="mt-3 whitespace-pre-line text-sm leading-6 text-slate-700">{{ report.root_cause || t('Not available') }}</p>
            </section>
            <section class="rounded-2xl border border-slate-200 p-4">
                <h4 class="text-sm font-semibold text-slate-950">{{ t('Actions Taken') }}</h4>
                <p class="mt-3 whitespace-pre-line text-sm leading-6 text-slate-700">{{ report.actions_taken || t('Not available') }}</p>
            </section>
            <section class="rounded-2xl border border-slate-200 p-4 lg:col-span-2">
                <h4 class="text-sm font-semibold text-slate-950">{{ t('Resolution Summary') }}</h4>
                <p class="mt-3 whitespace-pre-line text-sm leading-6 text-slate-700">{{ report.resolution_summary || t('Not available') }}</p>
            </section>
            <section class="rounded-2xl border border-slate-200 p-4">
                <h4 class="text-sm font-semibold text-slate-950">{{ t('Customer Visible Notes') }}</h4>
                <p class="mt-3 whitespace-pre-line text-sm leading-6 text-slate-700">{{ report.customer_visible_notes || t('Not available') }}</p>
            </section>
            <section class="rounded-2xl border border-slate-200 p-4">
                <h4 class="text-sm font-semibold text-slate-950">{{ t('Internal Notes') }}</h4>
                <p class="mt-3 whitespace-pre-line text-sm leading-6 text-slate-700">{{ report.internal_notes || t('Not available') }}</p>
            </section>
        </div>
    </div>

    <div v-else class="rounded-2xl bg-slate-50 px-4 py-4 text-sm text-slate-600">
        {{ t('No resolution report has been captured for this ticket yet.') }}
    </div>
</template>