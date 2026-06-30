<script setup>
import AdminLayout from '../../layouts/AdminLayout.vue';
import OrderTicketPanel from '../components/OrderTicketPanel.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
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

const activeTab = ref('ticket');
const timelineQuery = ref('');
const notesPanelOpen = ref(false);
const statusPanelOpen = ref(false);
const paymentPanelOpen = ref(false);
const actionsMenuOpen = ref(false);
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

const showProviderDebug = computed(() => Boolean(props.order.error_message));

const tabs = computed(() => {
    const items = [
        { id: 'ticket', label: t('Order') },
    ];

    if (canViewFinancials.value) {
        items.push({ id: 'financials', label: t('Financials') });
    }

    if (showProviderDebug.value) {
        items.push({ id: 'debug', label: t('Provider Debug') });
    }

    items.push({ id: 'timeline', label: t('Timeline') });

    return items;
});

watch(showProviderDebug, (visible) => {
    if (!visible && activeTab.value === 'debug') {
        activeTab.value = 'ticket';
    }
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

const showHealthAlert = computed(() => orderHealth.value.label !== t('OK'));

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
        const actorName = historyActor(entry);
        const isNotes = entry.field === 'internal_notes';
        const oldValue = isNotes
            ? (entry.old_value || t('None'))
            : formatLabel(entry.old_value || t('None'));
        const newValue = isNotes
            ? (entry.new_value || t('None'))
            : formatLabel(entry.new_value || t('None'));

        events.push({
            id: `history-${entry.id}`,
            type: 'history',
            label: isNotes ? t('Internal notes updated') : `${formatLabel(entry.field)} ${t('updated')}`,
            description: `${oldValue} → ${newValue}`,
            timestamp: entry.created_at,
            actor: actorName,
            icon: isNotes ? 'NT' : 'AU',
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
        onSuccess: () => {
            statusPanelOpen.value = false;
        },
    });
};

const submitNotes = () => {
    notesForm.put(route('admin.orders.update-notes', props.order.id), {
        preserveScroll: true,
        onSuccess: () => {
            notesPanelOpen.value = false;
        },
    });
};

const submitPaymentStatus = () => {
    paymentForm.put(route('admin.orders.update-payment-status', props.order.id), {
        preserveScroll: true,
        onSuccess: () => {
            paymentPanelOpen.value = false;
        },
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
    actionsMenuOpen.value = false;
};

const closeActionPanels = () => {
    statusPanelOpen.value = false;
    paymentPanelOpen.value = false;
    notesPanelOpen.value = false;
};

const openStatusPanel = () => {
    activeTab.value = 'ticket';
    closeActionPanels();
    statusPanelOpen.value = true;
    actionsMenuOpen.value = false;
};

const openPaymentPanel = () => {
    activeTab.value = 'ticket';
    closeActionPanels();
    paymentPanelOpen.value = true;
    actionsMenuOpen.value = false;
};

const openNotesPanel = () => {
    activeTab.value = 'ticket';
    closeActionPanels();
    notesPanelOpen.value = true;
    actionsMenuOpen.value = false;
};

const openCustomerFromMenu = () => {
    actionsMenuOpen.value = false;
    openCustomerDrawer();
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

const handleTicketAction = ({ action, ticket }) => {
    // Handle ticket actions like cancel or reschedule.
    // This would typically open a modal or make an API call.
    console.log('Ticket action:', action, ticket);
    alert(`${t(action === 'reschedule' ? 'Reschedule' : 'Cancel')} action triggered for ticket`);
};
</script>

<template>
    <Head :title="order.booking_reference || `${t('Order')} ${order.id}`" />

    <AdminLayout
        :title="order.booking_reference || `${t('Order')} #${order.id}`"
        description=""
    >
        <section class="space-y-4">
            <div class="overflow-visible rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-4 py-4 sm:px-5">
                    <Link
                        :href="route('admin.orders.index')"
                        class="text-sm font-medium text-slate-500 transition hover:text-slate-800"
                    >
                        ← {{ t('Back to orders') }}
                    </Link>

                    <div
                        v-if="showHealthAlert"
                        class="mt-4 rounded-lg border px-4 py-3"
                        :class="orderHealth.label === t('Critical') ? 'border-rose-200 bg-rose-50' : 'border-amber-200 bg-amber-50'"
                    >
                        <p class="text-sm font-semibold text-slate-950">{{ orderHealth.label }}</p>
                        <p class="mt-1 text-sm text-slate-700">{{ orderHealth.description }}</p>
                    </div>

                    <div v-if="order.error_message" class="mt-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-rose-700">{{ t('Error message') }}</p>
                        <p class="mt-1 text-sm text-rose-800">{{ order.error_message }}</p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-3 py-2 sm:px-4">
                    <nav class="flex gap-1 overflow-x-auto">
                        <button
                            v-for="tab in tabs"
                            :key="tab.id"
                            type="button"
                            class="shrink-0 rounded-lg px-3 py-2 text-sm font-medium transition"
                            :class="activeTab === tab.id ? 'bg-slate-950 text-white' : 'text-slate-600 hover:bg-slate-100'"
                            @click="changeTab(tab.id)"
                        >
                            {{ tab.label }}
                        </button>
                    </nav>

                    <div class="relative z-30 flex flex-wrap items-center gap-2">
                        <Link
                            v-if="canViewSupport"
                            :href="supportCreateLink"
                            class="inline-flex items-center rounded-lg bg-slate-950 px-3 py-2 text-sm font-medium text-white transition hover:bg-slate-800"
                        >
                            {{ t('New ticket') }}
                        </Link>
                        <button
                            type="button"
                            class="inline-flex items-center rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                            @click="exportSnapshot"
                        >
                            {{ t('Export') }}
                        </button>
                        <div class="relative">
                            <button
                                type="button"
                                class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 text-slate-600 transition hover:bg-slate-50 hover:text-slate-900"
                                :aria-expanded="actionsMenuOpen"
                                :aria-label="t('More')"
                                @click="actionsMenuOpen = !actionsMenuOpen"
                            >
                                <svg viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5" aria-hidden="true">
                                    <path d="M3 10a1.5 1.5 0 113 0 1.5 1.5 0 01-3 0zM8.5 10a1.5 1.5 0 113 0 1.5 1.5 0 01-3 0zM14 10a1.5 1.5 0 113 0 1.5 1.5 0 01-3 0z" />
                                </svg>
                            </button>
                            <div
                                v-if="actionsMenuOpen"
                                class="absolute right-0 bottom-full z-50 mb-1 min-w-[11rem] overflow-hidden rounded-lg border border-slate-200 bg-white py-1 shadow-lg"
                            >
                            <button
                                type="button"
                                class="block w-full px-4 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-50"
                                @click="openCustomerFromMenu"
                            >
                                {{ t('Customer') }}
                            </button>
                            <button
                                v-if="canUpdateStatus"
                                type="button"
                                class="block w-full px-4 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-50"
                                @click="openStatusPanel"
                            >
                                {{ t('Update status') }}
                            </button>
                            <button
                                v-if="canUpdateStatus"
                                type="button"
                                class="block w-full px-4 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-50"
                                @click="openPaymentPanel"
                            >
                                {{ t('Update payment') }}
                            </button>
                            <Link
                                v-if="canViewSupport"
                                :href="relatedTicketsLink"
                                class="block px-4 py-2 text-sm text-slate-700 transition hover:bg-slate-50"
                                @click="actionsMenuOpen = false"
                            >
                                {{ t('Tickets') }}
                            </Link>
                            <button
                                v-if="canUpdateNotes"
                                type="button"
                                class="block w-full px-4 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-50"
                                @click="openNotesPanel"
                            >
                                {{ t('Notes') }}
                            </button>
                            <button
                                v-if="showProviderDebug"
                                type="button"
                                class="block w-full px-4 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-50"
                                @click="changeTab('debug')"
                            >
                                {{ t('Debug') }}
                            </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <template v-if="activeTab === 'ticket'">
                <div class="space-y-3">
                    <div
                        v-if="statusPanelOpen && canUpdateStatus"
                        class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"
                    >
                        <div class="flex items-center justify-between gap-3 border-b border-slate-100 pb-3">
                            <h3 class="text-sm font-semibold text-slate-950">{{ t('Operational status') }}</h3>
                            <button
                                type="button"
                                class="text-xs font-medium text-slate-500 transition hover:text-slate-800"
                                @click="statusPanelOpen = false"
                            >
                                {{ t('Close') }}
                            </button>
                        </div>

                        <form class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end" @submit.prevent="submit">
                            <label class="flex-1 space-y-1 text-xs font-medium text-slate-600">
                                <span>{{ t('Operational status') }}</span>
                                <select
                                    v-model="form.status"
                                    class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900"
                                    :disabled="statuses.length === 0"
                                >
                                    <option v-for="status in statuses" :key="status.name" :value="status.name">
                                        {{ t(status.label) }}
                                    </option>
                                </select>
                            </label>
                            <button
                                type="submit"
                                class="inline-flex items-center justify-center rounded-lg bg-slate-950 px-3 py-2 text-sm font-medium text-white transition hover:bg-slate-800 disabled:opacity-60"
                                :disabled="form.processing || statuses.length === 0"
                            >
                                {{ t('Update status') }}
                            </button>
                        </form>
                    </div>

                    <div
                        v-if="paymentPanelOpen && canUpdateStatus"
                        class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"
                    >
                        <div class="flex items-center justify-between gap-3 border-b border-slate-100 pb-3">
                            <h3 class="text-sm font-semibold text-slate-950">{{ t('Payment status') }}</h3>
                            <button
                                type="button"
                                class="text-xs font-medium text-slate-500 transition hover:text-slate-800"
                                @click="paymentPanelOpen = false"
                            >
                                {{ t('Close') }}
                            </button>
                        </div>

                        <form class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end" @submit.prevent="submitPaymentStatus">
                            <label class="flex-1 space-y-1 text-xs font-medium text-slate-600">
                                <span>{{ t('Payment status') }}</span>
                                <select
                                    v-model="paymentForm.payment_status"
                                    class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900"
                                >
                                    <option v-for="status in payment_statuses" :key="status.name" :value="status.name">
                                        {{ t(status.label) }}
                                    </option>
                                </select>
                            </label>
                            <button
                                type="submit"
                                class="inline-flex items-center justify-center rounded-lg bg-slate-950 px-3 py-2 text-sm font-medium text-white transition hover:bg-slate-800 disabled:opacity-60"
                                :disabled="paymentForm.processing"
                            >
                                {{ t('Update payment') }}
                            </button>
                        </form>
                    </div>

                <OrderTicketPanel
                    :ticket="order.ticket"
                    :currency="order.currency"
                    :booked-by-clickable="Boolean(order.customer?.id)"
                    @booked-by-click="openCustomerDrawer"
                    @action-click="handleTicketAction"
                />

                <div
                    v-if="notesPanelOpen"
                    class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"
                >
                    <div class="flex items-center justify-between gap-3 border-b border-slate-100 pb-3">
                        <h3 class="text-sm font-semibold text-slate-950">{{ t('Internal notes') }}</h3>
                        <button
                            type="button"
                            class="text-xs font-medium text-slate-500 transition hover:text-slate-800"
                            @click="notesPanelOpen = false"
                        >
                            {{ t('Close') }}
                        </button>
                    </div>

                    <form v-if="canUpdateNotes" class="mt-4 space-y-3" @submit.prevent="submitNotes">
                        <textarea
                            v-model="notesForm.internal_notes"
                            rows="5"
                            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-slate-400"
                            :placeholder="t('Add an internal operational note')"
                        />
                        <button
                            type="submit"
                            class="inline-flex items-center justify-center rounded-lg bg-slate-950 px-3 py-2 text-xs font-medium text-white transition hover:bg-slate-800 disabled:opacity-60"
                            :disabled="notesForm.processing"
                        >
                            {{ t('Save notes') }}
                        </button>
                    </form>

                    <p v-else class="mt-4 text-sm leading-6 text-slate-700">
                        {{ order.internal_notes || t('No internal notes have been recorded yet.') }}
                    </p>
                </div>
                </div>
            </template>

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