<script setup>
import AdminLayout from '../../layouts/AdminLayout.vue';
import OrderStatusBadge from '../components/OrderStatusBadge.vue';
import OrderProviderCell from '../components/OrderProviderCell.vue';
import PaymentStatusBadge from '../components/PaymentStatusBadge.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import { useAdminLocale } from '../../composables/useAdminLocale';

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
const { locale, t } = useAdminLocale();

const filterForm = reactive({
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
});

const canViewFinancials = computed(() => {
    const permissions = page.props.auth.user?.permissions ?? [];

    return permissions.includes('orders.financials.view') || permissions.includes('finance.view');
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

const formatMoney = (amount, currency = 'LYD') => {
    if (amount === null || amount === undefined) {
        return t('Restricted');
    }

    return new Intl.NumberFormat(locale.value, {
        style: 'currency',
        currency: currency || 'LYD',
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
        description="Monitor booking flow, filter operational states, and inspect each order without mixing support and finance concerns."
    >
        <section class="space-y-6">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.25em] text-cyan-700">
                            {{ t('Booking Operations') }}
                        </p>
                        <h2 class="mt-2 text-2xl font-semibold text-slate-950">{{ t('Orders pipeline') }}</h2>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">
                            {{ t('Review customer bookings, isolate operational states, and open each order for provider payloads, support actions, and financial context.') }}
                        </p>
                    </div>

                    <div class="rounded-2xl bg-slate-950 px-4 py-3 text-sm text-white">
                        {{ ordersCountLabel }}
                    </div>
                </div>

                <form class="mt-6 grid gap-4 md:grid-cols-[minmax(0,1fr)_minmax(0,16rem)_auto_auto] md:items-end" @submit.prevent="applyFilters">
                    <label class="space-y-2 text-sm font-medium text-slate-700">
                        <span>{{ t('Search') }}</span>
                        <input
                            v-model="filterForm.search"
                            type="search"
                            class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-cyan-600"
                            :placeholder="t('Reference, customer, provider, PNR, or ID')"
                        >
                    </label>

                    <label class="space-y-2 text-sm font-medium text-slate-700">
                        <span>{{ t('Status') }}</span>
                        <select
                            v-model="filterForm.status"
                            class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-cyan-600"
                        >
                            <option value="">{{ t('All statuses') }}</option>
                            <option v-for="status in statuses" :key="status.name" :value="status.name">
                                {{ t(status.label) }}
                            </option>
                        </select>
                    </label>

                    <button
                        type="submit"
                        class="inline-flex items-center justify-center rounded-2xl bg-slate-950 px-4 py-3 text-sm font-medium text-white transition hover:bg-slate-800"
                    >
                        {{ t('Apply filter') }}
                    </button>

                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-2xl border border-slate-200 px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                        @click="resetFilters"
                    >
                        {{ t('Reset') }}
                    </button>
                </form>
            </div>

            <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr class="text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">
                                <th class="px-6 py-4">{{ t('Reference') }}</th>
                                <th class="px-6 py-4">{{ t('Customer') }}</th>
                                <th class="px-6 py-4">{{ t('Provider') }}</th>
                                <th class="px-6 py-4">{{ t('Status') }}</th>
                                <th class="px-6 py-4">{{ t('Payment') }}</th>
                                <th v-if="canViewFinancials" class="px-6 py-4">{{ t('Amount') }}</th>
                                <th class="px-6 py-4">{{ t('Created') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm text-slate-700">
                            <tr
                                v-for="order in orders.data"
                                :key="order.id"
                                class="group cursor-pointer bg-white align-top transition duration-200 hover:bg-cyan-50/50"
                                role="link"
                                :aria-label="`${t('Open order')} ${order.booking_reference || `#${order.id}`}`"
                                tabindex="0"
                                @click="openOrderPage(order)"
                                @keydown.enter.prevent="openOrderPage(order)"
                                @keydown.space.prevent="openOrderPage(order)"
                            >
                                <td class="px-6 py-5">
                                    <div class="font-semibold text-slate-950">{{ order.booking_reference || `${t('Order')} #${order.id}` }}</div>
                                    <div class="mt-1 text-xs uppercase tracking-[0.18em] text-slate-500">
                                        {{ order.ticket_number || t('Awaiting ticket number') }}
                                    </div>
                                </td>
                                <td class="px-6 py-5">
                                    <div class="font-medium text-slate-900">{{ order.customer.name }}</div>
                                    <div class="mt-1 text-slate-500">{{ order.customer.email }}</div>
                                </td>
                                <td class="px-6 py-5">
                                    <OrderProviderCell
                                        :flight="order.flight"
                                        :hotel="order.hotel"
                                        :provider-name="order.provider_name"
                                    />
                                </td>
                                <td class="px-6 py-5">
                                    <OrderStatusBadge :status="order.status" />
                                    <div class="mt-2 text-xs uppercase tracking-[0.16em] text-slate-500">
                                        {{ formatServiceType(order.service_type) }}
                                    </div>
                                </td>
                                <td class="px-6 py-5">
                                    <PaymentStatusBadge :status="order.payment_status" />
                                </td>
                                <td v-if="canViewFinancials" class="px-6 py-5 font-medium text-slate-900">
                                    {{ formatMoney(order.total_amount, order.currency) }}
                                </td>
                                <td class="px-6 py-5 text-slate-500">{{ formatDateTime(order.created_at) }}</td>
                            </tr>

                            <tr v-if="orders.data.length === 0">
                                <td :colspan="canViewFinancials ? 7 : 6" class="px-6 py-12 text-center text-sm text-slate-500">
                                    {{ t('No orders matched the selected filters.') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex flex-col gap-4 border-t border-slate-200 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm text-slate-500">
                        {{ t('Showing') }} {{ orders.from ?? 0 }} {{ t('to') }} {{ orders.to ?? 0 }} {{ t('of') }} {{ orders.total }} {{ t('orders.') }}
                    </p>

                    <nav class="flex flex-wrap gap-2">
                        <component
                            :is="link.url ? Link : 'span'"
                            v-for="link in orders.links"
                            :key="`${link.label}-${link.url}`"
                            :href="link.url"
                            class="rounded-xl px-3 py-2 text-sm font-medium transition"
                            :class="link.active ? 'bg-slate-950 text-white' : 'border border-slate-200 text-slate-600 hover:bg-slate-50'"
                            v-html="link.label"
                        />
                    </nav>
                </div>
            </div>
        </section>
    </AdminLayout>
</template>