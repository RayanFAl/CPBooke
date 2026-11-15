<script setup>
import AdminLayout from '../../layouts/AdminLayout.vue';
import AdminSparkline from '../../components/AdminSparkline.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useAdminLocale } from '../../composables/useAdminLocale';
import { usePlatformCurrency } from '../../composables/usePlatformCurrency';
import { useAdminNavigation } from '../../composables/useAdminNavigation';
import AdminDashboardSkeleton from '../../components/AdminDashboardSkeleton.vue';

const props = defineProps({
    dashboard: {
        type: Object,
        required: true,
    },
});

const accentBorder = {
    emerald: 'border-s-[#2f6db8]',
    sky: 'border-s-[#2f6db8]',
    amber: 'border-s-[#f0b429]',
    rose: 'border-s-rose-400',
    violet: 'border-s-[#2f6db8]',
};

const spotlightToneMap = {
    good: 'bg-emerald-50 text-emerald-700',
    warn: 'bg-amber-50 text-amber-800',
    critical: 'bg-rose-50 text-rose-700',
};

const overviewCards = computed(() => props.dashboard.overview ?? []);
const spotlightCards = computed(() => props.dashboard.spotlights ?? []);
const latestOrders = computed(() => props.dashboard.latest_orders ?? []);
const appPulse = computed(() => props.dashboard.app_pulse ?? {});
const appPulseCards = computed(() => appPulse.value.cards ?? []);
const appPulseSpotlights = computed(() => appPulse.value.spotlights ?? []);
const { locale, t } = useAdminLocale();
const { defaultCurrency } = usePlatformCurrency();
const platformCurrency = defaultCurrency;
const { navigating } = useAdminNavigation();

const orderTrend = computed(() => props.dashboard.charts?.orders_trend ?? []);
const revenueTrend = computed(() => props.dashboard.charts?.revenue_trend ?? []);
const statusBreakdown = computed(() => props.dashboard.charts?.status_breakdown ?? []);
const serviceBreakdown = computed(() => props.dashboard.charts?.service_breakdown ?? []);
const supportBreakdown = computed(() => props.dashboard.charts?.support_breakdown ?? []);
const downloadsTrend = computed(() => appPulse.value.charts?.downloads_trend ?? []);
const searchesTrend = computed(() => appPulse.value.charts?.searches_trend ?? []);
const signupsTrend = computed(() => appPulse.value.charts?.signups_trend ?? []);
const platformMix = computed(() => appPulse.value.charts?.platform_mix ?? []);
const topRoutes = computed(() => appPulse.value.charts?.top_routes ?? []);
const conversion = computed(() => appPulse.value.conversion ?? {
    searched: 0,
    viewed_price: 0,
    booked: 0,
    search_to_price_rate: 0,
    search_to_book_rate: 0,
    price_to_book_rate: 0,
});
const conversionMax = computed(() => Math.max(
    conversion.value.searched,
    conversion.value.viewed_price,
    conversion.value.booked,
    1,
));

const orderTrendPath = computed(() => buildLinePath(orderTrend.value.map((item) => item.value)));
const revenueTrendPath = computed(() => buildLinePath(revenueTrend.value.map((item) => item.value)));
const downloadsTrendPath = computed(() => buildLinePath(downloadsTrend.value.map((item) => item.value)));
const searchesTrendPath = computed(() => buildLinePath(searchesTrend.value.map((item) => item.value)));

const maxStatusValue = computed(() => Math.max(...statusBreakdown.value.map((item) => item.value), 1));
const maxServiceValue = computed(() => Math.max(...serviceBreakdown.value.map((item) => item.value), 1));
const maxSupportValue = computed(() => Math.max(...supportBreakdown.value.map((item) => item.value), 1));
const maxPlatformValue = computed(() => Math.max(...platformMix.value.map((item) => item.value), 1));
const maxRouteValue = computed(() => Math.max(...topRoutes.value.map((item) => item.value), 1));

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
            currency: defaultCurrency.value || 'LYD',
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

function formatMoney(value, currency = null) {
    return new Intl.NumberFormat(locale.value, {
        style: 'currency',
        currency: currency || defaultCurrency.value || 'LYD',
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

const openOrdersDesk = () => {
    router.visit(route('admin.orders.index'));
};

const openFinanceDesk = () => {
    router.visit(route('admin.finance.index'));
};
</script>

<template>
    <Head :title="t('Dashboard')" />

    <AdminLayout
        title="Dashboard"
        description="Orders, revenue, support, and app activity."
    >
        <section class="space-y-5">
            <!-- Top bar -->
            <div class="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5">
                <div class="flex flex-wrap items-center gap-2">
                    <span
                        v-for="item in spotlightCards"
                        :key="item.label"
                        class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2"
                    >
                        <span class="text-xs font-medium text-slate-500">{{ t(item.label) }}</span>
                        <span class="text-sm font-semibold text-slate-950">{{ metricValue(item.value, item.format) }}</span>
                        <span
                            class="rounded-md px-1.5 py-0.5 text-[10px] font-semibold uppercase"
                            :class="spotlightToneMap[item.tone]"
                        >
                            {{ formatLabel(item.tone) }}
                        </span>
                    </span>
                </div>

                <div class="flex flex-wrap items-center gap-2 text-sm">
                    <span class="text-slate-500">{{ t('Last sync:') }} {{ generatedAtLabel }}</span>
                    <Link
                        :href="route('admin.orders.index')"
                        class="rounded-xl bg-[#2f6db8] px-3 py-2 text-sm font-semibold text-white transition hover:bg-[#255991]"
                    >
                        {{ t('Orders') }}
                    </Link>
                    <Link
                        :href="route('admin.support.index')"
                        class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                    >
                        {{ t('Support') }}
                    </Link>
                </div>
            </div>

            <AdminDashboardSkeleton v-if="navigating" />

            <template v-else>
                <!-- Overview metrics -->
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <article
                        v-for="card in overviewCards"
                        :key="card.key"
                        class="min-w-0 rounded-2xl border border-slate-200 border-s-4 bg-white p-4"
                        :class="accentBorder[card.accent] || accentBorder.sky"
                    >
                        <p class="text-xs font-medium text-slate-500">{{ t(card.label) }}</p>
                        <p class="mt-2 break-words text-2xl font-semibold text-slate-950">
                            {{ metricValue(card.value, card.format) }}
                        </p>
                    </article>
                </div>

                <!-- App pulse -->
                <section class="space-y-4 rounded-2xl border border-slate-200 bg-white p-4 sm:p-5">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-950">{{ t('App pulse') }}</h2>
                            <p class="mt-0.5 text-sm text-slate-500">{{ t('Downloads, installs, and in-app searches.') }}</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <Link
                                :href="route('admin.mobile-app.index')"
                                class="rounded-xl border border-slate-200 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
                            >
                                {{ t('Mobile app releases') }}
                            </Link>
                            <Link
                                :href="route('admin.customers.index')"
                                class="rounded-xl border border-slate-200 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
                            >
                                {{ t('Open customers') }}
                            </Link>
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <article
                            v-for="card in appPulseCards"
                            :key="card.key"
                            class="rounded-xl border border-slate-200 bg-slate-50/80 p-4"
                        >
                            <p class="text-xs font-medium text-slate-500">{{ t(card.label) }}</p>
                            <p class="mt-2 text-2xl font-semibold text-slate-950">
                                {{ metricValue(card.value, 'number') }}
                            </p>
                            <p class="mt-1 text-xs text-slate-500">
                                <span class="font-semibold text-slate-800">{{ metricValue(card.delta, 'number') }}</span>
                                {{ t(card.delta_label) }}
                            </p>
                        </article>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <article
                            v-for="item in appPulseSpotlights"
                            :key="item.label"
                            class="rounded-xl border border-slate-200 px-4 py-3"
                        >
                            <p class="text-xs font-medium text-slate-500">{{ t(item.label) }}</p>
                            <p class="mt-1 text-xl font-semibold text-slate-950">
                                {{ metricValue(item.value, 'number') }}
                            </p>
                        </article>
                    </div>

                    <!-- Conversion -->
                    <article class="rounded-xl border border-slate-200 p-4">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <h3 class="font-semibold text-slate-950">{{ t('Search → price → book') }}</h3>
                            <p class="text-sm text-slate-600">
                                <span class="font-semibold text-slate-900">{{ metricValue(conversion.search_to_book_rate, 'percent') }}</span>
                                {{ t('search to book') }}
                            </p>
                        </div>
                        <div class="mt-4 grid gap-4 md:grid-cols-3">
                            <div class="space-y-2">
                                <div class="flex justify-between text-sm">
                                    <span class="text-slate-600">{{ t('Searched') }}</span>
                                    <span class="font-semibold text-slate-950">{{ metricValue(conversion.searched, 'number') }}</span>
                                </div>
                                <div class="h-2 overflow-hidden rounded-full bg-slate-100">
                                    <div class="h-full rounded-full bg-[#2f6db8]" :style="{ width: barWidth(conversion.searched, conversionMax) }" />
                                </div>
                            </div>
                            <div class="space-y-2">
                                <div class="flex justify-between gap-2 text-sm">
                                    <span class="text-slate-600">{{ t('Saw a price') }}</span>
                                    <span class="font-semibold text-slate-950">{{ metricValue(conversion.viewed_price, 'number') }} · {{ metricValue(conversion.search_to_price_rate, 'percent') }}</span>
                                </div>
                                <div class="h-2 overflow-hidden rounded-full bg-slate-100">
                                    <div class="h-full rounded-full bg-[#f0b429]" :style="{ width: barWidth(conversion.viewed_price, conversionMax) }" />
                                </div>
                            </div>
                            <div class="space-y-2">
                                <div class="flex justify-between gap-2 text-sm">
                                    <span class="text-slate-600">{{ t('Booked') }}</span>
                                    <span class="font-semibold text-slate-950">{{ metricValue(conversion.booked, 'number') }} · {{ metricValue(conversion.price_to_book_rate, 'percent') }}</span>
                                </div>
                                <div class="h-2 overflow-hidden rounded-full bg-slate-100">
                                    <div class="h-full rounded-full bg-emerald-500" :style="{ width: barWidth(conversion.booked, conversionMax) }" />
                                </div>
                            </div>
                        </div>
                    </article>

                    <div class="grid gap-4 lg:grid-cols-2">
                        <article class="rounded-xl border border-slate-200 p-4">
                            <div class="mb-3 flex items-center justify-between">
                                <h3 class="font-semibold text-slate-950">{{ t('APK downloads') }}</h3>
                                <span class="text-xs font-medium text-slate-500">{{ t('Last 7 days') }}</span>
                            </div>
                            <AdminSparkline
                                :points="downloadsTrend"
                                :line-path="downloadsTrendPath"
                                gradient-id="downloadsLineGradient"
                                :locale="locale"
                                stroke-from="#2f6db8"
                                stroke-to="#8ec5ff"
                            />
                            <div class="mt-3 grid grid-cols-7 gap-1 text-center text-[11px] text-slate-500">
                                <div v-for="point in downloadsTrend" :key="`dl-${point.label}`" class="min-w-0">
                                    <p class="truncate font-semibold text-slate-900">{{ point.value }}</p>
                                    <p class="truncate">{{ point.label }}</p>
                                </div>
                            </div>
                        </article>

                        <article class="rounded-xl border border-slate-200 p-4">
                            <div class="mb-3 flex items-center justify-between">
                                <h3 class="font-semibold text-slate-950">{{ t('In-app flight searches') }}</h3>
                                <span class="text-xs font-medium text-slate-500">{{ t('Last 7 days') }}</span>
                            </div>
                            <AdminSparkline
                                :points="searchesTrend"
                                :line-path="searchesTrendPath"
                                gradient-id="searchesLineGradient"
                                :locale="locale"
                                stroke-from="#f0b429"
                                stroke-to="#fbbf24"
                            />
                            <div class="mt-3 grid grid-cols-7 gap-1 text-center text-[11px] text-slate-500">
                                <div v-for="point in searchesTrend" :key="`sr-${point.label}`" class="min-w-0">
                                    <p class="truncate font-semibold text-slate-900">{{ point.value }}</p>
                                    <p class="truncate">{{ point.label }}</p>
                                </div>
                            </div>
                        </article>
                    </div>

                    <article class="rounded-xl border border-slate-200 p-4">
                        <div class="mb-3 flex items-center justify-between">
                            <h3 class="font-semibold text-slate-950">{{ t('App signups') }}</h3>
                            <span class="text-xs font-medium text-slate-500">{{ t('Last 7 days') }}</span>
                        </div>
                        <div class="grid grid-cols-7 gap-2">
                            <div
                                v-for="point in signupsTrend"
                                :key="`su-${point.label}`"
                                class="rounded-lg bg-slate-50 px-1 py-2.5 text-center"
                            >
                                <p class="text-base font-semibold text-slate-950">{{ point.value }}</p>
                                <p class="mt-0.5 text-[10px] text-slate-500">{{ point.label }}</p>
                            </div>
                        </div>
                    </article>

                    <div class="grid gap-4 lg:grid-cols-2">
                        <article class="rounded-xl border border-slate-200 p-4">
                            <h3 class="mb-4 font-semibold text-slate-950">{{ t('Installed platforms') }}</h3>
                            <div class="space-y-3">
                                <div v-for="item in platformMix" :key="item.label" class="space-y-1.5">
                                    <div class="flex justify-between text-sm">
                                        <span class="text-slate-600">{{ t(item.label) }}</span>
                                        <span class="font-semibold text-slate-950">{{ metricValue(item.value, 'number') }}</span>
                                    </div>
                                    <div class="h-2 overflow-hidden rounded-full bg-slate-100">
                                        <div class="h-full rounded-full bg-[#2f6db8]" :style="{ width: barWidth(item.value, maxPlatformValue) }" />
                                    </div>
                                </div>
                                <p v-if="platformMix.length === 0" class="text-sm text-slate-500">{{ t('No installed devices recorded yet.') }}</p>
                            </div>
                        </article>

                        <article class="rounded-xl border border-slate-200 p-4">
                            <h3 class="mb-4 font-semibold text-slate-950">{{ t('Top routes') }}</h3>
                            <div class="space-y-3">
                                <div v-for="item in topRoutes" :key="item.label" class="space-y-1.5">
                                    <div class="flex justify-between gap-3 text-sm">
                                        <span class="truncate text-slate-600">{{ item.label }}</span>
                                        <span class="shrink-0 font-semibold text-slate-950">{{ metricValue(item.value, 'number') }}</span>
                                    </div>
                                    <div class="h-2 overflow-hidden rounded-full bg-slate-100">
                                        <div class="h-full rounded-full bg-[#f0b429]" :style="{ width: barWidth(item.value, maxRouteValue) }" />
                                    </div>
                                </div>
                                <p v-if="topRoutes.length === 0" class="text-sm text-slate-500">{{ t('No in-app searches recorded yet.') }}</p>
                            </div>
                        </article>
                    </div>
                </section>

                <!-- Commerce + ops -->
                <div class="grid gap-4 xl:grid-cols-[minmax(0,1.55fr)_minmax(0,0.9fr)]">
                    <div class="grid gap-4">
                        <div class="grid gap-4 lg:grid-cols-2">
                            <article class="rounded-2xl border border-slate-200 bg-white p-4">
                                <div class="mb-3 flex items-center justify-between">
                                    <h3 class="font-semibold text-slate-950">{{ t('Orders Trend') }}</h3>
                                    <span class="text-xs text-slate-500">{{ t('Last 7 days') }}</span>
                                </div>
                                <AdminSparkline
                                    :points="orderTrend"
                                    :line-path="orderTrendPath"
                                    gradient-id="ordersLineGradient"
                                    :locale="locale"
                                    stroke-from="#2f6db8"
                                    stroke-to="#8ec5ff"
                                    @point-click="openOrdersDesk"
                                />
                                <div class="mt-3 grid grid-cols-7 gap-1 text-center text-[11px] text-slate-500">
                                    <div v-for="point in orderTrend" :key="point.label" class="min-w-0">
                                        <p class="truncate font-semibold text-slate-900">{{ point.value }}</p>
                                        <p class="truncate">{{ point.label }}</p>
                                    </div>
                                </div>
                            </article>

                            <article class="rounded-2xl border border-slate-200 bg-white p-4">
                                <div class="mb-3 flex items-center justify-between">
                                    <h3 class="font-semibold text-slate-950">{{ t('Revenue Trend') }}</h3>
                                    <span class="text-xs text-slate-500">{{ t('Last 7 days') }}</span>
                                </div>
                                <AdminSparkline
                                    :points="revenueTrend"
                                    :line-path="revenueTrendPath"
                                    gradient-id="revenueLineGradient"
                                    format="currency"
                                    :locale="locale"
                                    :currency="platformCurrency"
                                    stroke-from="#2f6db8"
                                    stroke-to="#f0b429"
                                    @point-click="openFinanceDesk"
                                />
                                <div class="mt-3 grid grid-cols-7 gap-1 text-center text-[10px] text-slate-500">
                                    <div v-for="point in revenueTrend" :key="point.label" class="min-w-0">
                                        <p class="truncate font-semibold text-slate-900">{{ metricValue(point.value, 'currency') }}</p>
                                        <p class="truncate">{{ point.label }}</p>
                                    </div>
                                </div>
                            </article>
                        </div>

                        <div class="grid gap-4 lg:grid-cols-2">
                            <article class="rounded-2xl border border-slate-200 bg-white p-4">
                                <div class="mb-4 flex items-center justify-between">
                                    <h3 class="font-semibold text-slate-950">{{ t('Order distribution') }}</h3>
                                    <Link :href="route('admin.orders.index')" class="text-sm font-medium text-[#2f6db8] hover:text-[#255991]">
                                        {{ t('View orders') }}
                                    </Link>
                                </div>
                                <div class="space-y-3">
                                    <div v-for="item in statusBreakdown" :key="item.label" class="space-y-1.5">
                                        <div class="flex justify-between text-sm">
                                            <span class="text-slate-600">{{ formatLabel(item.label) }}</span>
                                            <span class="font-semibold text-slate-950">{{ metricValue(item.value, 'number') }}</span>
                                        </div>
                                        <div class="h-2 overflow-hidden rounded-full bg-slate-100">
                                            <div class="h-full rounded-full bg-[#2f6db8]" :style="{ width: barWidth(item.value, maxStatusValue) }" />
                                        </div>
                                    </div>
                                    <p v-if="statusBreakdown.length === 0" class="text-sm text-slate-500">{{ t('No order distribution data yet.') }}</p>
                                </div>
                            </article>

                            <article class="rounded-2xl border border-slate-200 bg-white p-4">
                                <h3 class="mb-4 font-semibold text-slate-950">{{ t('What customers buy') }}</h3>
                                <div class="space-y-3">
                                    <div v-for="item in serviceBreakdown" :key="item.label" class="space-y-1.5">
                                        <div class="flex justify-between text-sm">
                                            <span class="text-slate-600">{{ formatLabel(item.label) }}</span>
                                            <span class="font-semibold text-slate-950">{{ metricValue(item.value, 'number') }}</span>
                                        </div>
                                        <div class="h-2 overflow-hidden rounded-full bg-slate-100">
                                            <div class="h-full rounded-full bg-[#f0b429]" :style="{ width: barWidth(item.value, maxServiceValue) }" />
                                        </div>
                                    </div>
                                    <p v-if="serviceBreakdown.length === 0" class="text-sm text-slate-500">{{ t('No service mix data yet.') }}</p>
                                </div>
                            </article>
                        </div>
                    </div>

                    <div class="grid gap-4">
                        <article class="rounded-2xl border border-slate-200 bg-white p-4">
                            <div class="mb-4 flex items-center justify-between">
                                <h3 class="font-semibold text-slate-950">{{ t('Inbox balance') }}</h3>
                                <Link :href="route('admin.support.index')" class="text-sm font-medium text-[#2f6db8] hover:text-[#255991]">
                                    {{ t('Open inbox') }}
                                </Link>
                            </div>
                            <div class="space-y-3">
                                <div v-for="item in supportBreakdown" :key="item.label" class="space-y-1.5">
                                    <div class="flex justify-between text-sm">
                                        <span class="text-slate-600">{{ formatLabel(item.label) }}</span>
                                        <span class="font-semibold text-slate-950">{{ metricValue(item.value, 'number') }}</span>
                                    </div>
                                    <div class="h-2 overflow-hidden rounded-full bg-slate-100">
                                        <div class="h-full rounded-full bg-rose-400" :style="{ width: barWidth(item.value, maxSupportValue) }" />
                                    </div>
                                </div>
                                <p v-if="supportBreakdown.length === 0" class="text-sm text-slate-500">{{ t('No support tickets recorded yet.') }}</p>
                            </div>
                        </article>

                        <article class="rounded-2xl border border-slate-200 bg-white p-4">
                            <div class="mb-4 flex items-center justify-between">
                                <h3 class="font-semibold text-slate-950">{{ t('Newest Orders') }}</h3>
                                <Link :href="route('admin.orders.index')" class="text-sm font-medium text-[#2f6db8] hover:text-[#255991]">
                                    {{ t('View all') }}
                                </Link>
                            </div>

                            <div class="space-y-2.5">
                                <Link
                                    v-for="order in latestOrders"
                                    :key="order.id"
                                    :href="route('admin.orders.show', order.id)"
                                    class="block rounded-xl border border-slate-200 px-3.5 py-3 transition hover:border-[#2f6db8]/40 hover:bg-slate-50"
                                >
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="truncate font-semibold text-slate-950">{{ order.reference }}</p>
                                            <p class="mt-0.5 truncate text-sm text-slate-500">{{ order.customer_name }}</p>
                                        </div>
                                        <div class="shrink-0 text-end">
                                            <p class="text-sm font-semibold text-slate-950">{{ formatMoney(order.amount, order.currency) }}</p>
                                            <p class="mt-0.5 text-xs text-slate-400">{{ formatDateTime(order.created_at) }}</p>
                                        </div>
                                    </div>
                                    <div class="mt-2 flex flex-wrap gap-1.5">
                                        <span class="rounded-md bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-700">
                                            {{ formatLabel(order.status) }}
                                        </span>
                                        <span
                                            v-if="order.payment_status"
                                            class="rounded-md bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-700"
                                        >
                                            {{ formatLabel(order.payment_status) }}
                                        </span>
                                    </div>
                                </Link>

                                <p v-if="latestOrders.length === 0" class="text-sm text-slate-500">
                                    {{ t('No recent orders available yet.') }}
                                </p>
                            </div>
                        </article>
                    </div>
                </div>
            </template>
        </section>
    </AdminLayout>
</template>
