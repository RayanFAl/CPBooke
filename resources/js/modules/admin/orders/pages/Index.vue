<script setup>
import AdminLayout from '../../layouts/AdminLayout.vue';
import AdminEmptyState from '../../components/AdminEmptyState.vue';
import AdminTableSkeleton from '../../components/AdminTableSkeleton.vue';
import OrderStatusBadge from '../components/OrderStatusBadge.vue';
import OrderProviderCell from '../components/OrderProviderCell.vue';
import PaymentStatusBadge from '../components/PaymentStatusBadge.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import { useAdminLocale } from '../../composables/useAdminLocale';
import { usePlatformCurrency } from '../../composables/usePlatformCurrency';
import { useAdminNavigation } from '../../composables/useAdminNavigation';

const props = defineProps({
    orders: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        required: true,
    },
    statuses: {
        type: Array,
        required: true,
    },
});

const page = usePage();
const { locale, t, paginationLabel } = useAdminLocale();
const { defaultCurrency } = usePlatformCurrency();
const { navigating } = useAdminNavigation();

const filterForm = reactive({
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
});

const canViewFinancials = computed(() => {
    const permissions = page.props.auth.user?.permissions ?? [];

    return permissions.includes('orders.financials.view') || permissions.includes('finance.view');
});

const canAgentBook = computed(() => {
    const permissions = page.props.auth.user?.permissions ?? [];

    return permissions.includes('orders.agent-book');
});

const applyFilters = () => {
    router.get(route('admin.orders.index'), {
        ...(filterForm.search.trim() ? { search: filterForm.search.trim() } : {}),
        ...(filterForm.status ? { status: filterForm.status } : {}),
    }, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
};

const resetFilters = () => {
    filterForm.search = '';
    filterForm.status = '';
    applyFilters();
};

const formatMoney = (amount, currency = null) => {
    if (amount === null || amount === undefined) {
        return t('Restricted');
    }

    return new Intl.NumberFormat(locale.value, {
        style: 'currency',
        currency: currency || defaultCurrency.value,
    }).format(Number(amount));
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

const formatServiceType = (value) => {
    if (!value) {
        return t('Legacy');
    }

    const normalizedValue = value.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());

    return t(normalizedValue);
};

const ordersCountLabel = computed(() => `${props.orders.total} ${t(props.orders.total === 1 ? 'total order' : 'total orders')}`);

const openOrderPage = (order) => {
    router.visit(route('admin.orders.show', order.id));
};
</script>

<template>
    <Head :title="t('Orders')" />

    <AdminLayout
        title="Orders"
        description="Filter bookings and open any order for details."
    >
        <section class="space-y-5">
            <div class="admin-panel p-5 sm:p-6">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="admin-eyebrow">{{ t('Booking Operations') }}</p>
                        <h2 class="mt-1.5 text-xl font-semibold tracking-tight text-slate-950">{{ t('Orders pipeline') }}</h2>
                    </div>
                    <div class="flex flex-wrap items-center gap-2.5">
                        <Link
                            v-if="canAgentBook"
                            :href="route('admin.orders.book')"
                            class="rounded-xl border border-[#2f6db8]/20 bg-[#2f6db8]/5 px-3.5 py-2 text-sm font-semibold text-[#2f6db8] transition hover:bg-[#2f6db8]/10"
                        >
                            {{ t('Book for customer') }}
                        </Link>
                        <div class="rounded-xl bg-[#2f6db8] px-3.5 py-2 text-sm font-medium text-white">
                            {{ ordersCountLabel }}
                        </div>
                    </div>
                </div>

                <form class="mt-5 grid gap-3 md:grid-cols-[minmax(0,1fr)_minmax(0,14rem)_auto_auto] md:items-end" @submit.prevent="applyFilters">
                    <label class="space-y-1.5 text-sm font-medium text-slate-700">
                        <span>{{ t('Search') }}</span>
                        <input
                            v-model="filterForm.search"
                            type="search"
                            class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-[#2f6db8] focus:ring-2 focus:ring-[#2f6db8]/20"
                            :placeholder="t('Reference, customer, provider, PNR, or ID')"
                        >
                    </label>

                    <label class="space-y-1.5 text-sm font-medium text-slate-700">
                        <span>{{ t('Status') }}</span>
                        <select
                            v-model="filterForm.status"
                            class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm text-slate-900 outline-none transition focus:border-[#2f6db8] focus:ring-2 focus:ring-[#2f6db8]/20"
                        >
                            <option value="">{{ t('All statuses') }}</option>
                            <option v-for="status in statuses" :key="status.name" :value="status.name">
                                {{ t(status.label) }}
                            </option>
                        </select>
                    </label>

                    <button
                        type="submit"
                        class="inline-flex items-center justify-center rounded-xl bg-[#2f6db8] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[#255991]"
                    >
                        {{ t('Apply filter') }}
                    </button>

                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                        @click="resetFilters"
                    >
                        {{ t('Reset') }}
                    </button>
                </form>
            </div>

            <AdminTableSkeleton
                v-if="navigating"
                :columns="canViewFinancials ? 7 : 6"
            />

            <div v-else class="admin-panel overflow-hidden">
                <div class="hidden overflow-x-auto md:block">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50/80">
                            <tr class="text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
                                <th class="px-5 py-3.5">{{ t('Reference') }}</th>
                                <th class="px-5 py-3.5">{{ t('Customer') }}</th>
                                <th class="px-5 py-3.5">{{ t('Provider') }}</th>
                                <th class="px-5 py-3.5">{{ t('Status') }}</th>
                                <th class="px-5 py-3.5">{{ t('Payment') }}</th>
                                <th v-if="canViewFinancials" class="px-5 py-3.5">{{ t('Amount') }}</th>
                                <th class="px-5 py-3.5">{{ t('Created') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm text-slate-700">
                            <tr
                                v-for="order in orders.data"
                                :key="order.id"
                                class="admin-row-hover group cursor-pointer bg-white align-top transition duration-150"
                                role="link"
                                :aria-label="`${t('Open order')} ${order.booking_reference || `#${order.id}`}`"
                                tabindex="0"
                                @click="openOrderPage(order)"
                                @keydown.enter.prevent="openOrderPage(order)"
                                @keydown.space.prevent="openOrderPage(order)"
                            >
                                <td class="px-5 py-4">
                                    <div class="font-semibold text-slate-950">{{ order.booking_reference || `${t('Order')} #${order.id}` }}</div>
                                    <div class="mt-1 text-xs uppercase tracking-[0.14em] text-slate-500">
                                        {{ order.ticket_number || t('Awaiting ticket number') }}
                                    </div>
                                </td>
                                <td class="px-5 py-4">
                                    <div class="font-medium text-slate-900">{{ order.customer.name }}</div>
                                    <div class="mt-1 text-slate-500">{{ order.customer.email }}</div>
                                </td>
                                <td class="px-5 py-4">
                                    <OrderProviderCell
                                        :flight="order.flight"
                                        :hotel="order.hotel"
                                        :provider-name="order.provider_name"
                                    />
                                </td>
                                <td class="px-5 py-4">
                                    <OrderStatusBadge :status="order.status" />
                                    <div class="mt-2 text-xs uppercase tracking-[0.14em] text-slate-500">
                                        {{ formatServiceType(order.service_type) }}
                                    </div>
                                </td>
                                <td class="px-5 py-4">
                                    <PaymentStatusBadge :status="order.payment_status" />
                                </td>
                                <td v-if="canViewFinancials" class="px-5 py-4 font-medium text-slate-900">
                                    {{ formatMoney(order.total_amount, order.currency) }}
                                </td>
                                <td class="px-5 py-4 text-slate-500">{{ formatDateTime(order.created_at) }}</td>
                            </tr>

                            <tr v-if="orders.data.length === 0">
                                <td :colspan="canViewFinancials ? 7 : 6" class="px-5 py-6">
                                    <AdminEmptyState
                                        title="No orders matched the selected filters."
                                        description="Try adjusting your search or status filters to find the booking you need."
                                        icon="search"
                                    />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="orders.data.length === 0" class="md:hidden">
                    <AdminEmptyState
                        title="No orders matched the selected filters."
                        description="Try adjusting your search or status filters to find the booking you need."
                        icon="search"
                    />
                </div>

                <div v-else class="divide-y divide-slate-100 md:hidden">
                    <button
                        v-for="order in orders.data"
                        :key="`mobile-${order.id}`"
                        type="button"
                        class="admin-row-hover block w-full px-4 py-4 text-start transition"
                        @click="openOrderPage(order)"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-semibold text-slate-950">{{ order.booking_reference || `${t('Order')} #${order.id}` }}</p>
                                <p class="mt-1 text-xs uppercase tracking-[0.14em] text-slate-500">
                                    {{ order.ticket_number || t('Awaiting ticket number') }}
                                </p>
                            </div>
                            <OrderStatusBadge :status="order.status" />
                        </div>

                        <div class="mt-3 space-y-1 text-sm">
                            <p class="font-medium text-slate-900">{{ order.customer.name }}</p>
                            <p class="text-slate-500">{{ order.customer.email }}</p>
                        </div>

                        <div class="mt-3 flex flex-wrap items-center gap-2 text-xs">
                            <PaymentStatusBadge :status="order.payment_status" />
                            <span class="rounded-lg bg-slate-100 px-2.5 py-1 uppercase tracking-[0.12em] text-slate-600">
                                {{ formatServiceType(order.service_type) }}
                            </span>
                            <span v-if="canViewFinancials" class="font-medium text-slate-900">
                                {{ formatMoney(order.total_amount, order.currency) }}
                            </span>
                        </div>

                        <p class="mt-3 text-xs text-slate-500">{{ formatDateTime(order.created_at) }}</p>
                    </button>
                </div>

                <div class="flex flex-col gap-3 border-t border-slate-200 px-5 py-3.5 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm text-slate-500">
                        {{ t('Showing') }} {{ orders.from ?? 0 }} {{ t('to') }} {{ orders.to ?? 0 }} {{ t('of') }} {{ orders.total }} {{ t('orders.') }}
                    </p>

                    <nav class="flex flex-wrap gap-2">
                        <component
                            :is="link.url ? Link : 'span'"
                            v-for="link in orders.links"
                            :key="`${link.label}-${link.url}`"
                            :href="link.url"
                            class="rounded-lg px-3 py-1.5 text-sm font-medium transition"
                            :class="link.active ? 'bg-[#2f6db8] text-white' : 'border border-slate-200 text-slate-600 hover:bg-slate-50'"
                            v-html="paginationLabel(link.label)"
                        />
                    </nav>
                </div>
            </div>
        </section>
    </AdminLayout>
</template>