<script setup>
import AdminLayout from '../../layouts/AdminLayout.vue';
import OrderStatusBadge from '../components/OrderStatusBadge.vue';
import PaymentStatusBadge from '../components/PaymentStatusBadge.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useAdminLocale } from '../../composables/useAdminLocale';

const props = defineProps({
    order: {
        type: Object,
        required: true,
    },
    statuses: {
        type: Array,
        required: true,
    },
    payment_statuses: {
        type: Array,
        required: true,
    },
});

const page = usePage();
const { locale, t } = useAdminLocale();
const permissions = computed(() => page.props.auth.user?.permissions ?? []);

const canUpdateStatus = computed(() => permissions.value.includes('orders.change-status'));
const canUpdateNotes = computed(() => permissions.value.includes('orders.update-notes'));
const canViewHistory = computed(() => permissions.value.includes('orders.view-history'));
const canViewFinancials = computed(() => (
    permissions.value.includes('orders.financials.view') || permissions.value.includes('finance.view')
));
const canViewSupport = computed(() => permissions.value.includes('support.view'));
const canViewUsers = computed(() => permissions.value.includes('users.view'));

const activeTab = ref('overview');
const timelineQuery = ref('');
const customerDrawerOpen = ref(false);
const customerContextLoading = ref(false);
const customerContextLoaded = ref(false);
const customerContextError = ref('');
const customerContext = ref({
    country: null,
    recentOrdersCount: null,
    activeTicketCount: null,
    lastActivity: null,
});

const form = useForm({
    status: props.order.status,
});

const paymentForm = useForm({
    payment_status: props.order.payment_status,
});

const notesForm = useForm({
    internal_notes: props.order.internal_notes ?? '',
});

const tabs = computed(() => {
    const items = [
        { id: 'overview', label: t('Overview') },
        { id: 'timeline', label: t('Timeline') },
    ];

    if (canViewFinancials.value) {
        items.push({ id: 'financials', label: t('Financials') });
    }

    items.push({ id: 'debug', label: t('Provider Debug') });
    items.push({ id: 'control', label: t('Internal Control') });

    return items;
});

const orderHealth = computed(() => {
    const hasError = Boolean(props.order.error_message);
    const failedPayment = ['failed', 'refunded'].includes(props.order.payment_status);
    const pendingPayment = ['unpaid', 'pending_payment', 'partially_refunded'].includes(props.order.payment_status);
    const stalled = ['draft', 'pending_payment', 'processing'].includes(props.order.status);

    if (hasError || failedPayment) {
        return {
            label: t('Critical'),
            tone: 'bg-rose-50 text-rose-700 ring-rose-200',
            description: hasError ? t('Provider or system error needs intervention.') : t('Payment flow is blocking the order.'),
        };
    }

    if (pendingPayment || stalled) {
        return {
            label: t('Warning'),
            tone: 'bg-amber-50 text-amber-700 ring-amber-200',
            description: t('The order still needs operational follow-up.'),
        };
    }

    return {
        label: t('OK'),
        tone: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        description: t('No active operational or payment blockers detected.'),
    };
});

const supportCreateLink = computed(() => route('admin.support.create', {
    user_id: props.order.customer?.id,
    order_id: props.order.id,
    category: 'booking_change',
    priority: props.order.error_message ? 'high' : 'medium',
    subject: `Order ${props.order.booking_reference || `#${props.order.id}`} follow-up`,
    first_message: `Customer follow-up requested for ${props.order.booking_reference || `order #${props.order.id}`}.`,
}));

const relatedTicketsLink = computed(() => route('admin.support.index', {
    user_id: props.order.customer?.id,
    order_id: props.order.id,
}));

const customerProfileLink = computed(() => canViewUsers.value && props.order.customer?.id
    ? route('admin.users.show', props.order.customer.id)
    : null);

const customerOrdersLink = computed(() => customerProfileLink.value ? `${customerProfileLink.value}#orders` : route('admin.orders.index'));

const smartActions = computed(() => {
    const actions = [];

    if (['failed', 'unpaid', 'pending_payment'].includes(props.order.payment_status) && canUpdateStatus.value) {
        actions.push({
            label: t('Retry Payment'),
            helper: t('Jump to payment controls'),
            kind: 'tab',
            target: 'control',
            tone: 'bg-amber-50 text-amber-800 ring-1 ring-amber-200',
        });
    }

    if (props.order.error_message) {
        actions.push({
            label: t('Reprocess Order'),
            helper: t('Inspect provider debug payloads'),
            kind: 'tab',
            target: 'debug',
            tone: 'bg-rose-50 text-rose-700 ring-1 ring-rose-200',
        });
    }

    if (canViewSupport.value && ['processing', 'pending_payment'].includes(props.order.status)) {
        actions.push({
            label: t('Create Support Ticket'),
            helper: t('Escalate from this order context'),
            kind: 'link',
            target: supportCreateLink.value,
            tone: 'bg-cyan-50 text-cyan-700 ring-1 ring-cyan-200',
        });
    }

    if (['completed', 'confirmed'].includes(props.order.status)) {
        actions.push({
            label: t('Export Snapshot'),
            helper: t('Download current order payload'),
            kind: 'export',
            target: null,
            tone: 'bg-slate-100 text-slate-800 ring-1 ring-slate-200',
        });
    }

    return actions;
});

const timelineEvents = computed(() => {
    const events = [
        {
            id: `created-${props.order.id}`,
            type: 'created',
            label: t('Order created'),
            description: `${t('Booking reference')}: ${props.order.booking_reference || `#${props.order.id}`} ${t('entered the system for')} ${props.order.customer?.name || t('the customer')}.`,
            timestamp: props.order.created_at,
            actor: t('System'),
            icon: 'OR',
            tone: 'bg-slate-100 text-slate-700',
        },
        {
            id: `payment-status-${props.order.id}`,
            type: 'payment',
            label: t('Payment state snapshot'),
            description: `${t('Current payment state is')} ${formatLabel(props.order.payment_status)}.`,
            timestamp: props.order.updated_at,
            actor: t('Finance'),
            icon: 'PY',
            tone: 'bg-emerald-50 text-emerald-700',
        },
        {
            id: `provider-request-${props.order.id}`,
            type: 'provider',
            label: t('Provider request payload captured'),
            description: t('Provider request data is available in the debug tab for operational inspection.'),
            timestamp: props.order.created_at,
            actor: t('System'),
            icon: 'RQ',
            tone: 'bg-cyan-50 text-cyan-700',
        },
        {
            id: `provider-response-${props.order.id}`,
            type: 'provider',
            label: t('Provider response payload captured'),
            description: t('Provider response data is available in the debug tab for troubleshooting.'),
            timestamp: props.order.updated_at,
            actor: props.order.provider_name || t('Provider'),
            icon: 'RS',
            tone: 'bg-cyan-50 text-cyan-700',
        },
    ];

    if (props.order.error_message) {
        events.push({
            id: `error-${props.order.id}`,
            type: 'error',
            label: t('Provider or processing error'),
            description: props.order.error_message,
            timestamp: props.order.updated_at,
            actor: props.order.provider_name || t('Provider'),
            icon: 'ER',
            tone: 'bg-rose-50 text-rose-700',
        });
    }

    props.order.transactions.forEach((transaction) => {
        events.push({
            id: `transaction-${transaction.id}`,
            type: 'financial',
            label: `${formatLabel(transaction.type)} ${t('transaction')}`,
            description: `${formatMoney(transaction.amount, transaction.currency)} ${t('recorded via')} ${formatLabel(transaction.source)}.`,
            timestamp: transaction.created_at,
            actor: t('Finance'),
            icon: 'FN',
            tone: 'bg-emerald-50 text-emerald-700',
        });
    });

    props.order.histories.forEach((entry) => {
        events.push({
            id: `history-${entry.id}`,
            type: 'history',
            label: `${formatLabel(entry.field)} ${t('updated')}`,
            description: `${entry.old_value || t('None')} -> ${entry.new_value || t('None')}`,
            timestamp: entry.created_at,
            actor: historyActor(entry),
            icon: 'AU',
            tone: 'bg-violet-50 text-violet-700',
        });
    });

    return events
        .sort((left, right) => new Date(right.timestamp || 0).getTime() - new Date(left.timestamp || 0).getTime())
        .map((event) => ({
            ...event,
            searchBlob: `${event.label} ${event.description} ${event.actor}`.toLowerCase(),
        }));
});

const filteredTimelineEvents = computed(() => {
    const query = timelineQuery.value.trim().toLowerCase();

    if (!query) {
        return timelineEvents.value;
    }

    return timelineEvents.value.filter((event) => event.searchBlob.includes(query));
});

const submit = () => {
    form.put(route('admin.orders.update-status', props.order.id), {
        preserveScroll: true,
    });
};

const submitNotes = () => {
    notesForm.put(route('admin.orders.update-notes', props.order.id), {
        preserveScroll: true,
    });
};

const submitPaymentStatus = () => {
    paymentForm.put(route('admin.orders.update-payment-status', props.order.id), {
        preserveScroll: true,
    });
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

const formatMoney = (amount, currency = 'LYD') => {
    if (amount === null || amount === undefined) {
        return t('Restricted');
    }

    return new Intl.NumberFormat(locale.value, {
        style: 'currency',
        currency: currency || 'LYD',
    }).format(Number(amount));
};

const prettyJson = (payload) => JSON.stringify(payload ?? {}, null, 2);

const formatLabel = (value) => {
    if (!value) {
        return t('Not available');
    }

    const normalizedValue = value.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());

    return t(normalizedValue);
};

const historyActor = (entry) => entry.user?.name || entry.user?.email || t('System');

const changeTab = (tabId) => {
    activeTab.value = tabId;
};

const runSmartAction = (action) => {
    if (action.kind === 'tab') {
        activeTab.value = action.target;
        return;
    }

    if (action.kind === 'export') {
        exportSnapshot();
    }
};

const exportSnapshot = () => {
    const blob = new Blob([JSON.stringify(props.order, null, 2)], {
        type: 'application/json',
    });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `${props.order.booking_reference || `order-${props.order.id}`}-snapshot.json`;
    link.click();
    URL.revokeObjectURL(url);
};

const openCustomerDrawer = async () => {
    customerDrawerOpen.value = true;

    if (customerContextLoaded.value || customerContextLoading.value || !canViewUsers.value || !props.order.customer?.id) {
        return;
    }

    customerContextLoading.value = true;
    customerContextError.value = '';

    try {
        const response = await fetch(route('admin.users.show', props.order.customer.id), {
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Inertia': 'true',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            throw new Error(t('Unable to load customer context.'));
        }

        const payload = await response.json();
        const user = payload?.props?.user ?? null;

        customerContext.value = {
            country: user?.country ?? null,
            recentOrdersCount: Array.isArray(user?.recent_orders) ? user.recent_orders.length : null,
            activeTicketCount: user?.support?.active_ticket_count ?? null,
            lastActivity: user?.recent_activities?.[0]?.created_at ?? user?.last_login_at ?? null,
        };
        customerContextLoaded.value = true;
    } catch (error) {
        customerContextError.value = t('Customer context could not be loaded from the existing profile payload.');
    } finally {
        customerContextLoading.value = false;
    }
};

const closeCustomerDrawer = () => {
    customerDrawerOpen.value = false;
};
</script>

<template>
    <Head :title="order.booking_reference || `${t('Order')} ${order.id}`" />

    <AdminLayout
        title="Order Control Center"
        description="Unify customer context, finance, provider debug, and operational control for a single booking order."
    >
        <section class="space-y-6">
            <div class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 bg-[radial-gradient(circle_at_top_left,_rgba(6,182,212,0.12),_transparent_38%),linear-gradient(180deg,_#ffffff,_#f8fafc)] px-6 py-6">
                    <div class="flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
                        <div class="min-w-0">
                            <p class="text-xs font-semibold uppercase tracking-[0.26em] text-cyan-700">{{ t('Order Intelligence Bar') }}</p>
                            <div class="mt-3 flex flex-wrap items-center gap-3">
                                <h2 class="text-3xl font-semibold tracking-tight text-slate-950">
                                    {{ order.booking_reference || `${t('Order')} #${order.id}` }}
                                </h2>
                                <OrderStatusBadge :status="order.status" />
                                <PaymentStatusBadge :status="order.payment_status" />
                                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-slate-700">
                                    {{ order.provider_name }}
                                </span>
                                <span class="rounded-full bg-cyan-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-cyan-700">
                                    {{ formatLabel(order.service_type) }}
                                </span>
                            </div>
                            <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-600">
                                {{ t('Move between customer context, timeline intelligence, finance, and internal control from one CRM-centered order workspace.') }}
                            </p>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2 xl:min-w-[25rem]">
                            <div class="rounded-[1.6rem] border border-slate-200 bg-white px-4 py-4 shadow-sm">
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ t('Order Health') }}</p>
                                <div class="mt-3 flex items-center gap-3">
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] ring-1" :class="orderHealth.tone">
                                        {{ orderHealth.label }}
                                    </span>
                                </div>
                                <p class="mt-3 text-sm leading-6 text-slate-600">{{ orderHealth.description }}</p>
                            </div>

                            <div class="rounded-[1.6rem] border border-slate-200 bg-white px-4 py-4 shadow-sm">
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ t('Smart Actions') }}</p>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <template v-for="action in smartActions" :key="action.label">
                                        <Link
                                            v-if="action.kind === 'link'"
                                            :href="action.target"
                                            class="inline-flex rounded-full px-3 py-2 text-xs font-semibold uppercase tracking-[0.16em]"
                                            :class="action.tone"
                                        >
                                            {{ action.label }}
                                        </Link>
                                        <button
                                            v-else
                                            type="button"
                                            class="inline-flex rounded-full px-3 py-2 text-xs font-semibold uppercase tracking-[0.16em]"
                                            :class="action.tone"
                                            @click="runSmartAction(action)"
                                        >
                                            {{ action.label }}
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-5 flex flex-wrap gap-3">
                        <Link
                            :href="route('admin.orders.index')"
                            class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                        >
                            {{ t('Back to orders') }}
                        </Link>
                        <button
                            type="button"
                            class="rounded-2xl border border-cyan-200 bg-cyan-50 px-4 py-3 text-sm font-medium text-cyan-700 transition hover:bg-cyan-100"
                            @click="openCustomerDrawer"
                        >
                            {{ t('View Customer') }}
                        </button>
                        <Link
                            v-if="canViewSupport"
                            :href="supportCreateLink"
                            class="rounded-2xl bg-slate-950 px-4 py-3 text-sm font-medium text-white transition hover:bg-slate-800"
                        >
                            {{ t('Create Support Ticket') }}
                        </Link>
                        <Link
                            v-if="canViewSupport"
                            :href="relatedTicketsLink"
                            class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                        >
                            {{ t('View Related Tickets') }}
                        </Link>
                    </div>
                </div>

                <div class="px-3 py-3">
                    <nav class="flex gap-2 overflow-x-auto">
                        <button
                            v-for="tab in tabs"
                            :key="tab.id"
                            type="button"
                            class="shrink-0 rounded-2xl px-4 py-3 text-sm font-medium transition"
                            :class="activeTab === tab.id ? 'bg-slate-950 text-white shadow-sm' : 'bg-slate-50 text-slate-600 hover:bg-slate-100'"
                            @click="changeTab(tab.id)"
                        >
                            {{ tab.label }}
                        </button>
                    </nav>
                </div>
            </div>

            <div v-if="activeTab === 'overview'" class="grid gap-6 xl:grid-cols-[minmax(0,1.15fr)_minmax(0,0.85fr)]">
                <div class="space-y-6">
                    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h3 class="text-lg font-semibold text-slate-950">{{ t('Overview') }}</h3>
                        <p class="mt-2 text-sm leading-6 text-slate-600">
                            {{ t('One place for the customer snapshot, provider context, and timestamps that define the order state.') }}
                        </p>
                        <dl class="mt-6 grid gap-5 sm:grid-cols-2">
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{{ t('Booking reference') }}</dt>
                                <dd class="mt-2 text-sm text-slate-900">{{ order.booking_reference || `${t('Order')} #${order.id}` }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{{ t('External booking ID') }}</dt>
                                <dd class="mt-2 text-sm text-slate-900">{{ order.external_booking_id || t('Not assigned yet') }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{{ t('Provider') }}</dt>
                                <dd class="mt-2 text-sm text-slate-900">{{ order.provider_name }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{{ t('Service type') }}</dt>
                                <dd class="mt-2 text-sm text-slate-900">{{ formatLabel(order.service_type) }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{{ t('Status') }}</dt>
                                <dd class="mt-2 text-sm text-slate-900">{{ formatLabel(order.status) }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{{ t('Payment status') }}</dt>
                                <dd class="mt-2 text-sm text-slate-900">{{ formatLabel(order.payment_status) }}</dd>
                            </div>
                            <div v-if="canViewFinancials">
                                <dt class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{{ t('Total amount') }}</dt>
                                <dd class="mt-2 text-sm text-slate-900">{{ formatMoney(order.total_amount, order.currency) }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{{ t('Created at') }}</dt>
                                <dd class="mt-2 text-sm text-slate-900">{{ formatDateTime(order.created_at) }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{{ t('Updated at') }}</dt>
                                <dd class="mt-2 text-sm text-slate-900">{{ formatDateTime(order.updated_at) }}</dd>
                            </div>
                            <div v-if="order.error_message" class="sm:col-span-2">
                                <dt class="text-xs font-semibold uppercase tracking-[0.2em] text-rose-600">{{ t('Error message') }}</dt>
                                <dd class="mt-2 text-sm leading-6 text-rose-700">{{ order.error_message }}</dd>
                            </div>
                        </dl>
                    </div>

                    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h3 class="text-lg font-semibold text-slate-950">{{ t('Customer context') }}</h3>
                                <p class="mt-2 text-sm leading-6 text-slate-600">
                                    {{ t('Link the booking to the customer record and move to the customer workspace when more context is needed.') }}
                                </p>
                            </div>
                            <button
                                type="button"
                                class="rounded-2xl border border-slate-200 px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                                @click="openCustomerDrawer"
                            >
                                {{ t('Open drawer') }}
                            </button>
                        </div>

                        <div class="mt-6 grid gap-5 sm:grid-cols-2">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{{ t('Customer name') }}</p>
                                <button
                                    type="button"
                                    class="mt-2 text-left text-sm font-semibold text-slate-950 underline-offset-4 hover:underline"
                                    @click="openCustomerDrawer"
                                >
                                    {{ order.customer.name }}
                                </button>
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{{ t('Customer email') }}</p>
                                <p class="mt-2 text-sm text-slate-900">{{ order.customer.email }}</p>
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{{ t('Customer phone') }}</p>
                                <p class="mt-2 text-sm text-slate-900">{{ order.customer.phone || t('Not provided') }}</p>
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{{ t('Customer workspace') }}</p>
                                <p class="mt-2 text-sm text-slate-900">
                                    {{ customerProfileLink ? t('Available from the customer drawer.') : t('Customer profile link is not available for this role.') }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="space-y-6">
                    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h3 class="text-lg font-semibold text-slate-950">{{ t('Control summary') }}</h3>
                        <div class="mt-5 space-y-4">
                            <div class="rounded-2xl bg-slate-50 p-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{{ t('Provider debugging') }}</p>
                                <p class="mt-2 text-sm text-slate-900">{{ t('Request, response, service payload, and errors are grouped in one dedicated tab.') }}</p>
                            </div>
                            <div class="rounded-2xl bg-slate-50 p-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{{ t('Support integration') }}</p>
                                <p class="mt-2 text-sm text-slate-900">{{ t('Create or inspect related tickets from the current order and customer context.') }}</p>
                            </div>
                            <div class="rounded-2xl bg-slate-50 p-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{{ t('Timeline driven review') }}</p>
                                <p class="mt-2 text-sm text-slate-900">{{ t('Status changes, finance events, provider exchanges, and future support signals converge in one timeline.') }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h3 class="text-lg font-semibold text-slate-950">{{ t('Quick links') }}</h3>
                        <div class="mt-5 grid gap-3">
                            <Link
                                v-if="canViewSupport"
                                :href="relatedTicketsLink"
                                class="flex items-center justify-between rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700 transition hover:bg-slate-50"
                            >
                                <span>{{ t('View Related Tickets') }}</span>
                                <span class="font-medium text-slate-950">{{ t('Open') }}</span>
                            </Link>
                            <Link
                                v-if="canViewSupport"
                                :href="supportCreateLink"
                                class="flex items-center justify-between rounded-2xl border border-cyan-200 bg-cyan-50 px-4 py-3 text-sm text-cyan-700 transition hover:bg-cyan-100"
                            >
                                <span>{{ t('Create Support Ticket') }}</span>
                                <span class="font-medium">{{ t('Open') }}</span>
                            </Link>
                            <button
                                type="button"
                                class="flex items-center justify-between rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700 transition hover:bg-slate-50"
                                @click="changeTab('control')"
                            >
                                <span>{{ t('Open internal control') }}</span>
                                <span class="font-medium text-slate-950">{{ t('Manage') }}</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else-if="activeTab === 'timeline'" class="space-y-6">
                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-slate-950">{{ t('Unified Timeline') }}</h3>
                            <p class="mt-2 text-sm leading-6 text-slate-600">
                                {{ t('Search across the order lifecycle, finance events, provider exchanges, and audit updates from one vertical timeline.') }}
                            </p>
                        </div>

                        <label class="block w-full lg:max-w-sm">
                            <span class="sr-only">{{ t('Search timeline') }}</span>
                            <input
                                v-model="timelineQuery"
                                type="text"
                                class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-cyan-600"
                                :placeholder="t('Search timeline, actor, event, or description')"
                            >
                        </label>
                    </div>

                    <div v-if="filteredTimelineEvents.length === 0" class="mt-6 rounded-2xl bg-slate-50 px-4 py-4 text-sm text-slate-600">
                        {{ t('No timeline events matched the current search.') }}
                    </div>

                    <div v-else class="mt-8 space-y-0">
                        <div
                            v-for="(event, index) in filteredTimelineEvents"
                            :key="event.id"
                            class="relative flex gap-4 pb-8"
                        >
                            <div class="relative flex w-12 shrink-0 justify-center">
                                <span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl text-xs font-semibold uppercase tracking-[0.16em]" :class="event.tone">
                                    {{ event.icon }}
                                </span>
                                <span v-if="index !== filteredTimelineEvents.length - 1" class="absolute top-12 h-[calc(100%-1rem)] w-px bg-slate-200" />
                            </div>

                            <div class="min-w-0 flex-1 rounded-3xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
                                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                    <div>
                                        <h4 class="text-sm font-semibold text-slate-950">{{ event.label }}</h4>
                                        <p class="mt-1 text-xs uppercase tracking-[0.18em] text-slate-500">{{ event.actor }}</p>
                                    </div>
                                    <p class="text-xs text-slate-500">{{ formatDateTime(event.timestamp) }}</p>
                                </div>
                                <p class="mt-3 text-sm leading-6 text-slate-600">{{ event.description }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else-if="activeTab === 'financials'" class="space-y-6">
                <div class="grid gap-6 xl:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
                    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h3 class="text-lg font-semibold text-slate-950">{{ t('Financial Summary') }}</h3>
                        <div class="mt-5 rounded-2xl bg-slate-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ t('Net Amount') }}</p>
                            <p class="mt-2 text-3xl font-semibold text-slate-950">
                                {{ formatMoney(order.financial_insight?.net_amount, order.financial_insight?.currency) }}
                            </p>
                        </div>

                        <dl class="mt-5 grid gap-4 sm:grid-cols-2">
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ t('Transactions') }}</dt>
                                <dd class="mt-2 text-sm text-slate-900">{{ order.transactions.length }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ t('Payment status') }}</dt>
                                <dd class="mt-2 text-sm text-slate-900">{{ formatLabel(order.payment_status) }}</dd>
                            </div>
                        </dl>
                    </div>

                    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h3 class="text-lg font-semibold text-slate-950">{{ t('Transactions') }}</h3>

                        <div v-if="order.transactions.length === 0" class="mt-4 rounded-2xl bg-slate-50 px-4 py-4 text-sm text-slate-600">
                            {{ t('No financial transactions are linked to this order yet.') }}
                        </div>

                        <div v-else class="mt-4 space-y-4">
                            <article
                                v-for="transaction in order.transactions"
                                :key="transaction.id"
                                class="rounded-2xl border border-slate-200 px-4 py-4"
                            >
                                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                    <div>
                                        <h4 class="text-sm font-semibold text-slate-950">{{ formatLabel(transaction.type) }}</h4>
                                        <p class="mt-1 text-xs uppercase tracking-[0.18em] text-slate-500">{{ formatLabel(transaction.source) }}</p>
                                    </div>
                                    <p class="text-xs text-slate-500">{{ formatDateTime(transaction.created_at) }}</p>
                                </div>
                                <div class="mt-4 flex items-center justify-between gap-4 rounded-2xl bg-slate-50 px-4 py-3">
                                    <span class="text-sm text-slate-600">{{ t('Amount') }}</span>
                                    <span class="text-sm font-semibold text-slate-950">{{ formatMoney(transaction.amount, transaction.currency) }}</span>
                                </div>
                            </article>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else-if="activeTab === 'debug'" class="space-y-6">
                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-slate-950">{{ t('Provider Debug') }}</h3>
                    <p class="mt-2 text-sm leading-6 text-slate-600">
                        {{ t('Collapse or expand payloads as needed instead of keeping raw JSON blocks open all the time.') }}
                    </p>

                    <div class="mt-6 space-y-4">
                        <details class="group rounded-2xl border border-slate-200 bg-slate-50 p-4" open>
                            <summary class="cursor-pointer list-none text-sm font-semibold text-slate-950">{{ t('Service payload JSON') }}</summary>
                            <pre class="mt-4 overflow-x-auto rounded-2xl bg-slate-950 p-4 text-xs leading-6 text-slate-100">{{ prettyJson(order.details) }}</pre>
                        </details>

                        <details class="group rounded-2xl border border-slate-200 bg-slate-50 p-4" open>
                            <summary class="cursor-pointer list-none text-sm font-semibold text-slate-950">{{ t('Provider request payload') }}</summary>
                            <pre class="mt-4 overflow-x-auto rounded-2xl bg-slate-950 p-4 text-xs leading-6 text-slate-100">{{ prettyJson(order.request_payload) }}</pre>
                        </details>

                        <details class="group rounded-2xl border border-slate-200 bg-slate-50 p-4" open>
                            <summary class="cursor-pointer list-none text-sm font-semibold text-slate-950">{{ t('Provider response payload') }}</summary>
                            <pre class="mt-4 overflow-x-auto rounded-2xl bg-slate-950 p-4 text-xs leading-6 text-slate-100">{{ prettyJson(order.response_payload) }}</pre>
                        </details>

                        <div class="rounded-2xl border px-4 py-4" :class="order.error_message ? 'border-rose-200 bg-rose-50' : 'border-slate-200 bg-slate-50'">
                            <p class="text-sm font-semibold" :class="order.error_message ? 'text-rose-700' : 'text-slate-700'">{{ t('Error channel') }}</p>
                            <p class="mt-2 text-sm leading-6" :class="order.error_message ? 'text-rose-700' : 'text-slate-600'">
                                {{ order.error_message || t('No provider or processing error is recorded for this order.') }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,0.9fr)]">
                <div class="space-y-6">
                    <div v-if="canUpdateStatus" class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h3 class="text-lg font-semibold text-slate-950">{{ t('Operational status') }}</h3>
                        <p class="mt-2 text-sm leading-6 text-slate-600">
                            {{ t('Change the lifecycle state when provider follow-up or manual admin control is required.') }}
                        </p>

                        <form class="mt-6 flex flex-col gap-4 sm:flex-row sm:items-end" @submit.prevent="submit">
                            <label class="space-y-2 text-sm font-medium text-slate-700 sm:min-w-72">
                                <span>{{ t('Status') }}</span>
                                <select
                                    v-model="form.status"
                                    class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-cyan-600"
                                    :disabled="statuses.length === 0"
                                >
                                    <option v-for="status in statuses" :key="status.name" :value="status.name">
                                        {{ t(status.label) }}
                                    </option>
                                </select>
                            </label>

                            <button
                                type="submit"
                                class="inline-flex items-center justify-center rounded-2xl bg-slate-950 px-4 py-3 text-sm font-medium text-white transition hover:bg-slate-800 disabled:opacity-60"
                                :disabled="form.processing || statuses.length === 0"
                            >
                                {{ t('Update status') }}
                            </button>
                        </form>
                    </div>

                    <div v-if="canUpdateStatus" class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h3 class="text-lg font-semibold text-slate-950">{{ t('Payment status') }}</h3>
                        <p class="mt-2 text-sm leading-6 text-slate-600">
                            {{ t('Payment flow stays independently controllable from the operational lifecycle.') }}
                        </p>

                        <form class="mt-6 flex flex-col gap-4 sm:flex-row sm:items-end" @submit.prevent="submitPaymentStatus">
                            <label class="space-y-2 text-sm font-medium text-slate-700 sm:min-w-72">
                                <span>{{ t('Payment status') }}</span>
                                <select
                                    v-model="paymentForm.payment_status"
                                    class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-cyan-600"
                                >
                                    <option v-for="status in payment_statuses" :key="status.name" :value="status.name">
                                        {{ t(status.label) }}
                                    </option>
                                </select>
                            </label>

                            <button
                                type="submit"
                                class="inline-flex items-center justify-center rounded-2xl bg-slate-950 px-4 py-3 text-sm font-medium text-white transition hover:bg-slate-800 disabled:opacity-60"
                                :disabled="paymentForm.processing"
                            >
                                {{ t('Update payment') }}
                            </button>
                        </form>
                    </div>

                    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h3 class="text-lg font-semibold text-slate-950">{{ t('Internal notes') }}</h3>
                        <form v-if="canUpdateNotes" class="mt-6 space-y-4" @submit.prevent="submitNotes">
                            <label class="block space-y-2 text-sm font-medium text-slate-700">
                                <span>{{ t('Notes') }}</span>
                                <textarea
                                    v-model="notesForm.internal_notes"
                                    rows="6"
                                    class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-cyan-600"
                                    :placeholder="t('Add an internal operational note')"
                                />
                            </label>

                            <button
                                type="submit"
                                class="inline-flex items-center justify-center rounded-2xl bg-slate-950 px-4 py-3 text-sm font-medium text-white transition hover:bg-slate-800 disabled:opacity-60"
                                :disabled="notesForm.processing"
                            >
                                {{ t('Save notes') }}
                            </button>
                        </form>

                        <div v-else class="mt-6 rounded-2xl bg-slate-50 px-4 py-4 text-sm leading-6 text-slate-700">
                            {{ order.internal_notes || t('No internal notes have been recorded yet.') }}
                        </div>
                    </div>
                </div>

                <div class="space-y-6">
                    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h3 class="text-lg font-semibold text-slate-950">{{ t('Audit Log') }}</h3>
                        <p class="mt-2 text-sm leading-6 text-slate-600">
                            {{ t('View status, payment, and note changes from a compact audit stream.') }}
                        </p>

                        <div v-if="!canViewHistory" class="mt-4 rounded-2xl bg-slate-50 px-4 py-4 text-sm text-slate-600">
                            {{ t('Audit logs require the order history permission and available history tables.') }}
                        </div>

                        <div v-else-if="order.histories.length === 0" class="mt-4 rounded-2xl bg-slate-50 px-4 py-4 text-sm text-slate-600">
                            {{ t('No tracked history entries are available for this order yet.') }}
                        </div>

                        <div v-else class="mt-4 space-y-4">
                            <article
                                v-for="entry in order.histories"
                                :key="entry.id"
                                class="rounded-2xl border border-slate-200 px-4 py-4"
                            >
                                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <h4 class="text-sm font-semibold text-slate-950">{{ formatLabel(entry.field) }}</h4>
                                        <p class="mt-1 text-xs uppercase tracking-[0.18em] text-slate-500">
                                            {{ formatLabel(entry.action) }} {{ t('by') }} {{ historyActor(entry) }}
                                        </p>
                                    </div>
                                    <p class="text-xs text-slate-500">{{ formatDateTime(entry.created_at) }}</p>
                                </div>

                                <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <dt class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ t('Old value') }}</dt>
                                        <dd class="mt-2 text-sm text-slate-700">{{ entry.old_value || t('None') }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ t('New value') }}</dt>
                                        <dd class="mt-2 text-sm text-slate-900">{{ entry.new_value || t('None') }}</dd>
                                    </div>
                                </dl>
                            </article>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <Teleport to="body">
            <Transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="customerDrawerOpen"
                    class="fixed inset-0 z-50 bg-slate-950/30 backdrop-blur-sm"
                    @click="closeCustomerDrawer"
                />
            </Transition>
        </Teleport>

        <Teleport to="body">
            <Transition
                enter-active-class="transition duration-300 ease-out"
                enter-from-class="translate-x-full"
                enter-to-class="translate-x-0"
                leave-active-class="transition duration-200 ease-in"
                leave-from-class="translate-x-0"
                leave-to-class="translate-x-full"
            >
                <aside
                    v-if="customerDrawerOpen"
                    class="fixed inset-y-0 right-0 z-[60] flex w-full max-w-md flex-col border-l border-slate-200 bg-white shadow-[0_20px_80px_-20px_rgba(15,23,42,0.45)]"
                >
                    <div class="border-b border-slate-200 bg-slate-50 px-6 py-5">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-cyan-700">{{ t('Customer Drawer') }}</p>
                                <h3 class="mt-2 text-2xl font-semibold text-slate-950">{{ order.customer.name }}</h3>
                                <p class="mt-2 text-sm text-slate-600">{{ order.customer.email }}</p>
                            </div>
                            <button
                                type="button"
                                class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-slate-200 text-slate-500 transition hover:bg-white hover:text-slate-900"
                                @click="closeCustomerDrawer"
                            >
                                <span class="sr-only">{{ t('Close drawer') }}</span>
                                <svg viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5">
                                    <path fill-rule="evenodd" d="M4.22 4.22a.75.75 0 011.06 0L10 8.94l4.72-4.72a.75.75 0 111.06 1.06L11.06 10l4.72 4.72a.75.75 0 11-1.06 1.06L10 11.06l-4.72 4.72a.75.75 0 11-1.06-1.06L8.94 10 4.22 5.28a.75.75 0 010-1.06z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div class="flex-1 overflow-y-auto px-6 py-6">
                        <div class="space-y-6">
                            <div class="rounded-[1.6rem] border border-slate-200 bg-slate-50 p-5">
                                <h4 class="text-lg font-semibold text-slate-950">{{ t('Customer profile') }}</h4>
                                <dl class="mt-5 grid gap-4">
                                    <div>
                                        <dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ t('Name') }}</dt>
                                        <dd class="mt-2 text-sm text-slate-900">{{ order.customer.name }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ t('Email') }}</dt>
                                        <dd class="mt-2 text-sm text-slate-900">{{ order.customer.email }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ t('Phone') }}</dt>
                                        <dd class="mt-2 text-sm text-slate-900">{{ order.customer.phone || t('Not provided') }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ t('Country') }}</dt>
                                        <dd class="mt-2 text-sm text-slate-900">
                                            {{ customerContext.country || (customerContextLoading ? t('Loading...') : t('Not available')) }}
                                        </dd>
                                    </div>
                                </dl>
                            </div>

                            <div class="rounded-[1.6rem] border border-slate-200 p-5">
                                <div class="flex items-center justify-between gap-3">
                                    <h4 class="text-lg font-semibold text-slate-950">{{ t('Quick stats') }}</h4>
                                    <span v-if="customerContextLoading" class="text-xs font-medium uppercase tracking-[0.16em] text-slate-400">{{ t('Loading') }}</span>
                                </div>
                                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                                    <div class="rounded-2xl bg-slate-50 p-4">
                                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ t('Recent orders') }}</p>
                                        <p class="mt-2 text-xl font-semibold text-slate-950">{{ customerContext.recentOrdersCount ?? '-' }}</p>
                                    </div>
                                    <div class="rounded-2xl bg-slate-50 p-4">
                                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ t('Active tickets') }}</p>
                                        <p class="mt-2 text-xl font-semibold text-slate-950">{{ customerContext.activeTicketCount ?? '-' }}</p>
                                    </div>
                                    <div class="rounded-2xl bg-slate-50 p-4 sm:col-span-2">
                                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ t('Last activity') }}</p>
                                        <p class="mt-2 text-sm font-medium text-slate-950">{{ formatDateTime(customerContext.lastActivity || order.updated_at) }}</p>
                                    </div>
                                </div>
                                <p v-if="customerContextError" class="mt-4 text-sm text-rose-600">{{ customerContextError }}</p>
                            </div>

                            <div class="rounded-[1.6rem] border border-slate-200 p-5">
                                <h4 class="text-lg font-semibold text-slate-950">{{ t('Actions') }}</h4>
                                <div class="mt-4 grid gap-3">
                                    <Link
                                        :href="customerOrdersLink"
                                        class="flex items-center justify-between rounded-2xl border border-slate-200 px-4 py-3 transition hover:bg-slate-50"
                                    >
                                        <span>
                                            <span class="block text-sm font-semibold text-slate-950">{{ t('View all orders') }}</span>
                                            <span class="mt-1 block text-xs text-slate-500">{{ t('Jump to the customer workspace and recent order list.') }}</span>
                                        </span>
                                        <span class="text-sm font-medium text-slate-600">{{ t('Open') }}</span>
                                    </Link>

                                    <Link
                                        v-if="canViewSupport"
                                        :href="relatedTicketsLink"
                                        class="flex items-center justify-between rounded-2xl border border-slate-200 px-4 py-3 transition hover:bg-slate-50"
                                    >
                                        <span>
                                            <span class="block text-sm font-semibold text-slate-950">{{ t('View support tickets') }}</span>
                                            <span class="mt-1 block text-xs text-slate-500">{{ t('Open support filtered by the customer email.') }}</span>
                                        </span>
                                        <span class="text-sm font-medium text-slate-600">{{ t('Open') }}</span>
                                    </Link>

                                    <Link
                                        v-if="canViewSupport"
                                        :href="supportCreateLink"
                                        class="flex items-center justify-between rounded-2xl border border-cyan-200 bg-cyan-50 px-4 py-3 transition hover:bg-cyan-100"
                                    >
                                        <span>
                                            <span class="block text-sm font-semibold text-cyan-800">{{ t('Create Support Ticket') }}</span>
                                            <span class="mt-1 block text-xs text-cyan-700">{{ t('Start a new ticket from this order context.') }}</span>
                                        </span>
                                        <span class="text-sm font-medium text-cyan-800">{{ t('Open') }}</span>
                                    </Link>

                                    <Link
                                        v-if="customerProfileLink"
                                        :href="customerProfileLink"
                                        class="flex items-center justify-between rounded-2xl border border-slate-200 px-4 py-3 transition hover:bg-slate-50"
                                    >
                                        <span>
                                            <span class="block text-sm font-semibold text-slate-950">{{ t('Open customer profile') }}</span>
                                            <span class="mt-1 block text-xs text-slate-500">{{ t('View the unified customer CRM page.') }}</span>
                                        </span>
                                        <span class="text-sm font-medium text-slate-600">{{ t('Open') }}</span>
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </div>
                </aside>
            </Transition>
        </Teleport>
    </AdminLayout>
</template>