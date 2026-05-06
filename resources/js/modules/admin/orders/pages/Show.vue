<script setup>
import AdminLayout from '../../layouts/AdminLayout.vue';
import OrderStatusBadge from '../components/OrderStatusBadge.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    order: {
        type: Object,
        required: true,
    },
    statuses: {
        type: Array,
        required: true,
    },
});

const page = usePage();

const canUpdateStatus = computed(() =>
    (page.props.auth.user?.permissions ?? []).includes('orders.change-status'),
);

const canViewFinancials = computed(() => {
    const permissions = page.props.auth.user?.permissions ?? [];

    return permissions.includes('orders.financials.view') || permissions.includes('finance.view');
});

const form = useForm({
    status: props.order.status,
});

const submit = () => {
    form.put(route('admin.orders.update-status', props.order.id), {
        preserveScroll: true,
    });
};

const formatDateTime = (value) => {
    if (!value) {
        return 'Not available';
    }

    return new Intl.DateTimeFormat('en', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
};

const formatMoney = (amount, currency) => {
    if (amount === null || amount === undefined || !currency) {
        return 'Restricted';
    }

    return new Intl.NumberFormat('en', {
        style: 'currency',
        currency,
    }).format(Number(amount));
};

const prettyJson = (payload) => JSON.stringify(payload ?? {}, null, 2);
</script>

<template>
    <Head :title="order.booking_reference || `Order ${order.id}`" />

    <AdminLayout
        title="Order Details"
        description="Inspect provider exchange, customer identity, and operational status for a single booking order."
    >
        <section class="space-y-6">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.25em] text-cyan-700">
                            Booking Detail
                        </p>
                        <h2 class="mt-2 text-3xl font-semibold text-slate-950">
                            {{ order.booking_reference || `Order #${order.id}` }}
                        </h2>
                        <div class="mt-3 flex flex-wrap items-center gap-3">
                            <OrderStatusBadge :status="order.status" />
                            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-slate-700">
                                {{ order.provider_name }}
                            </span>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <Link
                            :href="route('admin.orders.index')"
                            class="rounded-2xl border border-slate-200 px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                        >
                            Back to orders
                        </Link>
                    </div>
                </div>
            </div>

            <div class="grid gap-6 xl:grid-cols-[minmax(0,1.15fr)_minmax(0,0.85fr)]">
                <div class="space-y-6">
                    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h3 class="text-lg font-semibold text-slate-950">Order snapshot</h3>
                        <dl class="mt-5 grid gap-5 sm:grid-cols-2">
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Customer</dt>
                                <dd class="mt-2 text-sm text-slate-900">{{ order.customer.name }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Customer email</dt>
                                <dd class="mt-2 text-sm text-slate-900">{{ order.customer.email }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Provider</dt>
                                <dd class="mt-2 text-sm text-slate-900">{{ order.provider_name }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">External booking ID</dt>
                                <dd class="mt-2 text-sm text-slate-900">{{ order.external_booking_id || 'Not assigned yet' }}</dd>
                            </div>
                            <div v-if="canViewFinancials">
                                <dt class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Total amount</dt>
                                <dd class="mt-2 text-sm text-slate-900">{{ formatMoney(order.total_amount, order.currency) }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Created at</dt>
                                <dd class="mt-2 text-sm text-slate-900">{{ formatDateTime(order.created_at) }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Updated at</dt>
                                <dd class="mt-2 text-sm text-slate-900">{{ formatDateTime(order.updated_at) }}</dd>
                            </div>
                            <div v-if="order.error_message" class="sm:col-span-2">
                                <dt class="text-xs font-semibold uppercase tracking-[0.2em] text-rose-600">Error message</dt>
                                <dd class="mt-2 text-sm text-rose-700">{{ order.error_message }}</dd>
                            </div>
                        </dl>
                    </div>

                    <div v-if="canUpdateStatus" class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h3 class="text-lg font-semibold text-slate-950">Operational status</h3>
                        <p class="mt-2 text-sm leading-6 text-slate-600">
                            Support and operations roles can move the order through manual lifecycle states when provider follow-up is required.
                        </p>

                        <form class="mt-6 flex flex-col gap-4 sm:flex-row sm:items-end" @submit.prevent="submit">
                            <label class="space-y-2 text-sm font-medium text-slate-700 sm:min-w-72">
                                <span>Status</span>
                                <select
                                    v-model="form.status"
                                    class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-cyan-600"
                                >
                                    <option v-for="status in statuses" :key="status.name" :value="status.name">
                                        {{ status.label }}
                                    </option>
                                </select>
                            </label>

                            <button
                                type="submit"
                                class="inline-flex items-center justify-center rounded-2xl bg-slate-950 px-4 py-3 text-sm font-medium text-white transition hover:bg-slate-800 disabled:opacity-60"
                                :disabled="form.processing"
                            >
                                Update status
                            </button>
                        </form>
                    </div>
                </div>

                <div class="space-y-6">
                    <div v-if="canUpdateStatus" class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h3 class="text-lg font-semibold text-slate-950">Provider request payload</h3>
                        <pre class="mt-4 overflow-x-auto rounded-2xl bg-slate-950 p-4 text-xs leading-6 text-slate-100">{{ prettyJson(order.request_payload) }}</pre>
                    </div>

                    <div v-if="canUpdateStatus" class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h3 class="text-lg font-semibold text-slate-950">Provider response payload</h3>
                        <pre class="mt-4 overflow-x-auto rounded-2xl bg-slate-950 p-4 text-xs leading-6 text-slate-100">{{ prettyJson(order.response_payload) }}</pre>
                    </div>

                    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h3 class="text-lg font-semibold text-slate-950">Access note</h3>
                        <p class="mt-3 text-sm leading-6 text-slate-600">
                            Finance-facing roles can review the monetary values, while support and operations roles additionally receive provider request and response payloads for troubleshooting.
                        </p>
                    </div>
                </div>
            </div>
        </section>
    </AdminLayout>
</template>