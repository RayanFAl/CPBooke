<script setup>
import AdminLayout from '../../layouts/AdminLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useAdminLocale } from '../../composables/useAdminLocale';

const props = defineProps({
    dashboard: {
        type: Object,
        required: true,
    },
});

const accentMap = {
    emerald: 'from-white via-emerald-50/70 to-slate-50 text-slate-700 ring-slate-200',
    sky: 'from-white via-sky-50/80 to-slate-50 text-slate-700 ring-slate-200',
    amber: 'from-white via-amber-50/80 to-stone-50 text-slate-700 ring-slate-200',
    rose: 'from-white via-rose-50/75 to-slate-50 text-slate-700 ring-slate-200',
};

const spotlightToneMap = {
    good: 'bg-emerald-50 text-emerald-700 ring-emerald-100',
    warn: 'bg-amber-50 text-amber-700 ring-amber-100',
    critical: 'bg-rose-50 text-rose-700 ring-rose-100',
};

const overviewCards = computed(() => props.dashboard.overview ?? []);
const spotlightCards = computed(() => props.dashboard.spotlights ?? []);
const latestOrders = computed(() => props.dashboard.latest_orders ?? []);
const { locale, t } = useAdminLocale();

const orderTrend = computed(() => props.dashboard.charts?.orders_trend ?? []);
const revenueTrend = computed(() => props.dashboard.charts?.revenue_trend ?? []);
const statusBreakdown = computed(() => props.dashboard.charts?.status_breakdown ?? []);
const serviceBreakdown = computed(() => props.dashboard.charts?.service_breakdown ?? []);
const supportBreakdown = computed(() => props.dashboard.charts?.support_breakdown ?? []);

const orderTrendPath = computed(() => buildLinePath(orderTrend.value.map((item) => item.value)));
const revenueTrendPath = computed(() => buildLinePath(revenueTrend.value.map((item) => item.value)));

const maxStatusValue = computed(() => Math.max(...statusBreakdown.value.map((item) => item.value), 1));
const maxServiceValue = computed(() => Math.max(...serviceBreakdown.value.map((item) => item.value), 1));
const maxSupportValue = computed(() => Math.max(...supportBreakdown.value.map((item) => item.value), 1));

const generatedAtLabel = computed(() => formatDateTime(props.dashboard.generated_at));

function buildLinePath(values) {
    if (!values.length) {
        return '';
    }

    const width = 320;
    const height = 120;
    const maxValue = Math.max(...values, 1);
    const stepX = values.length > 1 ? width / (values.length - 1) : width;

    return values
        .map((value, index) => {
            const x = Number((index * stepX).toFixed(2));
            const y = Number((height - ((value / maxValue) * height)).toFixed(2));

            return `${index === 0 ? 'M' : 'L'} ${x} ${y}`;
        })
        .join(' ');
}

function metricValue(value, format) {
    if (format === 'currency') {
        return new Intl.NumberFormat(locale.value, {
            style: 'currency',
            currency: 'LYD',
            maximumFractionDigits: 0,
        }).format(Number(value ?? 0));
    }

    if (format === 'percent') {
        return `${Number(value ?? 0).toFixed(1)}%`;
    }

    return new Intl.NumberFormat(locale.value).format(Number(value ?? 0));
}

function formatLabel(value) {
    if (!value) {
        return t('Unknown');
    }

    const normalizedValue = String(value)
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());

    return t(normalizedValue) ?? normalizedValue;
}

function formatMoney(value, currency = 'LYD') {
    return new Intl.NumberFormat(locale.value, {
        style: 'currency',
        currency: currency || 'LYD',
        maximumFractionDigits: 0,
    }).format(Number(value ?? 0));
}

function formatDateTime(value) {
    if (!value) {
        return t('Not available');
    }

    return new Intl.DateTimeFormat(locale.value, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

function barWidth(value, maxValue) {
    return `${Math.max((Number(value ?? 0) / Math.max(maxValue, 1)) * 100, 6)}%`;
}
</script>

<template>
    <Head :title="t('Dashboard')" />

    <AdminLayout
        title="Dashboard"
        description="Live commercial, operational, and support signals for the admin team."
    >
        <section class="space-y-6">
            <div class="overflow-hidden rounded-[2rem] border border-slate-200 bg-[radial-gradient(circle_at_top_left,_rgba(191,219,254,0.9),_transparent_28%),radial-gradient(circle_at_top_right,_rgba(226,232,240,0.95),_transparent_30%),linear-gradient(180deg,_#ffffff_0%,_#f8fafc_100%)] p-6 text-slate-950 shadow-[0_20px_60px_-35px_rgba(15,23,42,0.16)]">
                <div class="flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between">
                    <div class="max-w-3xl">
                        <p class="text-xs font-semibold uppercase tracking-[0.28em] text-sky-700/80">
                            {{ t('Mission Control') }}
                        </p>
                        <h2 class="mt-3 font-serif text-3xl tracking-tight text-slate-950 md:text-4xl">
                            {{ t('Commercial pulse, support pressure, and order flow in one view.') }}
                        </h2>
                        <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-600 md:text-base">
                            {{ t('The dashboard now surfaces trend lines, operational load, order distribution, and a direct line into the newest bookings.') }}
                        </p>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-3">
                        <article
                            v-for="item in spotlightCards"
                            :key="item.label"
                            class="rounded-2xl border border-slate-200 bg-white/80 px-4 py-4 shadow-[0_12px_30px_-24px_rgba(15,23,42,0.28)] backdrop-blur"
                        >
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">
                                {{ t(item.label) }}
                            </p>
                            <div class="mt-3 flex items-center gap-3">
                                <p class="text-2xl font-semibold text-slate-950">
                                    {{ metricValue(item.value, item.format) }}
                                </p>
                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1"
                                    :class="spotlightToneMap[item.tone]"
                                >
                                    {{ formatLabel(item.tone) }}
                                </span>
                            </div>
                        </article>
                    </div>
                </div>

                <div class="mt-6 flex flex-wrap items-center gap-3 text-sm text-slate-600">
                    <span class="inline-flex items-center rounded-full border border-slate-200 bg-white px-3 py-1.5 shadow-sm">
                        {{ t('Last sync:') }} {{ generatedAtLabel }}
                    </span>
                    <Link :href="route('admin.orders.index')" class="inline-flex items-center rounded-full border border-slate-200 bg-white px-3 py-1.5 text-slate-700 transition hover:border-sky-200 hover:bg-sky-50 hover:text-sky-700">
                        {{ t('Open orders desk') }}
                    </Link>
                    <Link :href="route('admin.support.index')" class="inline-flex items-center rounded-full border border-slate-200 bg-white px-3 py-1.5 text-slate-700 transition hover:border-slate-300 hover:bg-slate-50">
                        {{ t('Open support inbox') }}
                    </Link>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <article
                    v-for="card in overviewCards"
                    :key="card.key"
                    class="overflow-hidden rounded-[1.75rem] border border-slate-200 bg-gradient-to-br p-5 shadow-[0_18px_40px_-30px_rgba(15,23,42,0.18)]"
                    :class="accentMap[card.accent]"
                >
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">
                        {{ t(card.label) }}
                    </p>
                    <p class="mt-4 text-3xl font-semibold text-slate-950">
                        {{ metricValue(card.value, card.format) }}
                    </p>
                    <p class="mt-3 text-sm leading-6 text-slate-600">
                        {{ t(card.helper) }}
                    </p>
                </article>
            </div>

            <div class="grid gap-6 xl:grid-cols-[minmax(0,1.6fr)_minmax(320px,0.9fr)]">
                <div class="grid gap-6">
                    <div class="grid gap-6 lg:grid-cols-2">
                        <article class="rounded-[1.8rem] border border-slate-200 bg-white p-5 shadow-[0_18px_40px_-30px_rgba(15,23,42,0.16)]">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{{ t('Orders Trend') }}</p>
                                    <h3 class="mt-2 text-xl font-semibold text-slate-950">{{ t('Last 7 days') }}</h3>
                                </div>
                                <span class="rounded-full bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-700 ring-1 ring-slate-200">
                                    {{ t('Orders') }}
                                </span>
                            </div>

                            <div class="mt-6 rounded-[1.5rem] border border-slate-200 bg-[linear-gradient(180deg,_#ffffff_0%,_#f8fafc_100%)] p-4 text-slate-900">
                                <svg viewBox="0 0 320 140" class="h-40 w-full">
                                    <defs>
                                        <linearGradient id="ordersLineGradient" x1="0%" y1="0%" x2="100%" y2="0%">
                                            <stop offset="0%" stop-color="#60a5fa" />
                                            <stop offset="100%" stop-color="#94a3b8" />
                                        </linearGradient>
                                    </defs>
                                    <path d="M 0 120 L 320 120" stroke="rgba(148,163,184,0.28)" stroke-width="1" />
                                    <path v-if="orderTrendPath" :d="orderTrendPath" fill="none" stroke="url(#ordersLineGradient)" stroke-linecap="round" stroke-width="6" />
                                </svg>

                                <div class="mt-4 grid grid-cols-7 gap-2 text-center text-xs text-slate-500">
                                    <div v-for="point in orderTrend" :key="point.label">
                                        <p class="font-semibold text-slate-950">{{ point.value }}</p>
                                        <p class="mt-1">{{ point.label }}</p>
                                    </div>
                                </div>
                            </div>
                        </article>

                        <article class="rounded-[1.8rem] border border-slate-200 bg-white p-5 shadow-[0_18px_40px_-30px_rgba(15,23,42,0.16)]">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{{ t('Revenue Trend') }}</p>
                                    <h3 class="mt-2 text-xl font-semibold text-slate-950">{{ t('Captured order value') }}</h3>
                                </div>
                                <span class="rounded-full bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-700 ring-1 ring-slate-200">
                                    {{ t('Revenue') }}
                                </span>
                            </div>

                            <div class="mt-6 rounded-[1.5rem] border border-slate-200 bg-[linear-gradient(180deg,_#ffffff_0%,_#f8fafc_100%)] p-4 text-slate-900">
                                <svg viewBox="0 0 320 140" class="h-40 w-full">
                                    <defs>
                                        <linearGradient id="revenueLineGradient" x1="0%" y1="0%" x2="100%" y2="0%">
                                            <stop offset="0%" stop-color="#86efac" />
                                            <stop offset="100%" stop-color="#93c5fd" />
                                        </linearGradient>
                                    </defs>
                                    <path d="M 0 120 L 320 120" stroke="rgba(148,163,184,0.28)" stroke-width="1" />
                                    <path v-if="revenueTrendPath" :d="revenueTrendPath" fill="none" stroke="url(#revenueLineGradient)" stroke-linecap="round" stroke-width="6" />
                                </svg>

                                <div class="mt-4 grid grid-cols-7 gap-2 text-center text-[11px] text-slate-500">
                                    <div v-for="point in revenueTrend" :key="point.label">
                                        <p class="font-semibold text-slate-950">{{ metricValue(point.value, 'currency') }}</p>
                                        <p class="mt-1">{{ point.label }}</p>
                                    </div>
                                </div>
                            </div>
                        </article>
                    </div>

                    <div class="grid gap-6 lg:grid-cols-2">
                        <article class="rounded-[1.8rem] border border-slate-200 bg-white p-5 shadow-[0_18px_40px_-30px_rgba(15,23,42,0.16)]">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{{ t('Status Mix') }}</p>
                                    <h3 class="mt-2 text-xl font-semibold text-slate-950">{{ t('Order distribution') }}</h3>
                                </div>
                                <Link :href="route('admin.orders.index')" class="text-sm font-medium text-slate-600 hover:text-slate-950">
                                    {{ t('View orders') }}
                                </Link>
                            </div>

                            <div class="mt-6 space-y-4">
                                <div v-for="item in statusBreakdown" :key="item.label" class="space-y-2">
                                    <div class="flex items-center justify-between gap-4 text-sm">
                                        <span class="font-medium text-slate-700">{{ formatLabel(item.label) }}</span>
                                        <span class="font-semibold text-slate-950">{{ metricValue(item.value, 'number') }}</span>
                                    </div>
                                    <div class="h-3 overflow-hidden rounded-full bg-slate-100">
                                        <div class="h-full rounded-full bg-gradient-to-r from-sky-300 via-sky-200 to-slate-300" :style="{ width: barWidth(item.value, maxStatusValue) }" />
                                    </div>
                                </div>
                                <p v-if="statusBreakdown.length === 0" class="text-sm text-slate-500">{{ t('No order distribution data yet.') }}</p>
                            </div>
                        </article>

                        <article class="rounded-[1.8rem] border border-slate-200 bg-white p-5 shadow-[0_18px_40px_-30px_rgba(15,23,42,0.16)]">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{{ t('Service Mix') }}</p>
                                    <h3 class="mt-2 text-xl font-semibold text-slate-950">{{ t('What customers buy') }}</h3>
                                </div>
                                <span class="rounded-full bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-700 ring-1 ring-slate-200">
                                    {{ t('Product mix') }}
                                </span>
                            </div>

                            <div class="mt-6 space-y-4">
                                <div v-for="item in serviceBreakdown" :key="item.label" class="space-y-2">
                                    <div class="flex items-center justify-between gap-4 text-sm">
                                        <span class="font-medium text-slate-700">{{ formatLabel(item.label) }}</span>
                                        <span class="font-semibold text-slate-950">{{ metricValue(item.value, 'number') }}</span>
                                    </div>
                                    <div class="h-3 overflow-hidden rounded-full bg-slate-100">
                                        <div class="h-full rounded-full bg-gradient-to-r from-amber-200 via-orange-100 to-stone-200" :style="{ width: barWidth(item.value, maxServiceValue) }" />
                                    </div>
                                </div>
                                <p v-if="serviceBreakdown.length === 0" class="text-sm text-slate-500">{{ t('No service mix data yet.') }}</p>
                            </div>
                        </article>
                    </div>
                </div>

                <div class="grid gap-6">
                    <article class="rounded-[1.8rem] border border-slate-200 bg-white p-5 shadow-[0_18px_40px_-30px_rgba(15,23,42,0.16)]">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{{ t('Support Pressure') }}</p>
                                <h3 class="mt-2 text-xl font-semibold text-slate-950">{{ t('Inbox balance') }}</h3>
                            </div>
                            <Link :href="route('admin.support.index')" class="text-sm font-medium text-slate-600 hover:text-slate-950">
                                {{ t('Open inbox') }}
                            </Link>
                        </div>

                        <div class="mt-6 space-y-4">
                            <div v-for="item in supportBreakdown" :key="item.label" class="space-y-2">
                                <div class="flex items-center justify-between gap-4 text-sm">
                                    <span class="font-medium text-slate-700">{{ formatLabel(item.label) }}</span>
                                    <span class="font-semibold text-slate-950">{{ metricValue(item.value, 'number') }}</span>
                                </div>
                                <div class="h-3 overflow-hidden rounded-full bg-slate-100">
                                    <div class="h-full rounded-full bg-gradient-to-r from-rose-200 via-pink-100 to-slate-200" :style="{ width: barWidth(item.value, maxSupportValue) }" />
                                </div>
                            </div>
                            <p v-if="supportBreakdown.length === 0" class="text-sm text-slate-500">{{ t('No support tickets recorded yet.') }}</p>
                        </div>
                    </article>

                    <article class="rounded-[1.8rem] border border-slate-200 bg-white p-5 shadow-[0_18px_40px_-30px_rgba(15,23,42,0.16)]">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{{ t('Newest Orders') }}</p>
                                <h3 class="mt-2 text-xl font-semibold text-slate-950">{{ t('Fresh booking activity') }}</h3>
                            </div>
                            <Link :href="route('admin.orders.index')" class="text-sm font-medium text-slate-600 hover:text-slate-950">
                                {{ t('View all') }}
                            </Link>
                        </div>

                        <div class="mt-6 space-y-3">
                            <Link
                                v-for="order in latestOrders"
                                :key="order.id"
                                :href="route('admin.orders.show', order.id)"
                                class="block rounded-2xl border border-slate-200 bg-white/90 p-4 transition hover:border-slate-300 hover:bg-slate-50"
                            >
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <p class="font-semibold text-slate-950">{{ order.reference }}</p>
                                        <p class="mt-1 text-sm text-slate-600">{{ order.customer_name }}</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-sm font-semibold text-slate-950">{{ formatMoney(order.amount, order.currency) }}</p>
                                        <p class="mt-1 text-xs text-slate-500">{{ formatDateTime(order.created_at) }}</p>
                                    </div>
                                </div>
                                <div class="mt-3 flex flex-wrap gap-2 text-xs font-semibold uppercase tracking-[0.16em]">
                                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-slate-700">{{ formatLabel(order.status) }}</span>
                                    <span v-if="order.payment_status" class="rounded-full bg-slate-100 px-2.5 py-1 text-slate-700">{{ formatLabel(order.payment_status) }}</span>
                                </div>
                            </Link>

                            <p v-if="latestOrders.length === 0" class="text-sm text-slate-500">
                                {{ t('No recent orders available yet.') }}
                            </p>
                        </div>
                    </article>
                </div>
            </div>
        </section>
    </AdminLayout>
</template>