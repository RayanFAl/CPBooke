<script setup>
import AdminLayout from '../../layouts/AdminLayout.vue';
import SupportPriorityBadge from '../components/SupportPriorityBadge.vue';
import SupportSlaBadge from '../components/SupportSlaBadge.vue';
import SupportStatusBadge from '../components/SupportStatusBadge.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { useAdminLocale } from '../../composables/useAdminLocale';

const props = defineProps({
    tickets: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        required: true,
    },
    counters: {
        type: Object,
        required: true,
    },
    status_options: {
        type: Array,
        required: true,
    },
    priority_options: {
        type: Array,
        required: true,
    },
    category_options: {
        type: Array,
        required: true,
    },
    sort_options: {
        type: Array,
        required: true,
    },
    agents: {
        type: Array,
        required: true,
    },
});

const page = usePage();
const { locale, t } = useAdminLocale();
const filtersReady = ref(false);
const assigningTicketId = ref(null);
let searchDebounceTimer = null;
let refreshIntervalId = null;

const currentUserId = computed(() => page.props.auth.user?.id ?? null);

const filterForm = reactive({
    status: props.filters.status ?? '',
    priority: props.filters.priority ?? '',
    category: props.filters.category ?? '',
    assigned_agent_id: props.filters.assigned_agent_id ?? '',
    user_id: props.filters.user_id ?? '',
    order_id: props.filters.order_id ?? '',
    search: props.filters.search ?? '',
    sort: props.filters.sort ?? 'latest',
    queue: props.filters.queue ?? 'all',
});

const filterPayload = () => ({
    ...(filterForm.status ? { status: filterForm.status } : {}),
    ...(filterForm.priority ? { priority: filterForm.priority } : {}),
    ...(filterForm.category ? { category: filterForm.category } : {}),
    ...(filterForm.assigned_agent_id ? { assigned_agent_id: filterForm.assigned_agent_id } : {}),
    ...(filterForm.user_id ? { user_id: filterForm.user_id } : {}),
    ...(filterForm.order_id ? { order_id: filterForm.order_id } : {}),
    ...(filterForm.search.trim() ? { search: filterForm.search.trim() } : {}),
    ...(filterForm.sort ? { sort: filterForm.sort } : {}),
    ...(filterForm.queue && filterForm.queue !== 'all' ? { queue: filterForm.queue } : {}),
});

const applyFilters = () => {
    router.get(route('admin.support.index'), filterPayload(), {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
};

const resetFilters = () => {
    filterForm.status = '';
    filterForm.priority = '';
    filterForm.category = '';
    filterForm.assigned_agent_id = '';
    filterForm.user_id = '';
    filterForm.order_id = '';
    filterForm.search = '';
    filterForm.sort = 'latest';
    filterForm.queue = 'all';
    applyFilters();
};

const applyQueue = (queue) => {
    filterForm.queue = filterForm.queue === queue ? 'all' : queue;
    applyFilters();
};

const applyStatusFromMetric = (status) => {
    filterForm.status = filterForm.status === status ? '' : status;
    applyFilters();
};

const openTicketPage = (ticket) => {
    router.visit(route('admin.support.show', ticket.id), {
        preserveScroll: true,
    });
};

const refreshInbox = () => {
    if (document.visibilityState !== 'visible') {
        return;
    }

    router.reload({
        only: ['tickets', 'counters', 'filters'],
        preserveScroll: true,
        preserveState: true,
    });
};

const assignToMe = (ticket, event) => {
    event.stopPropagation();

    if (!currentUserId.value || assigningTicketId.value !== null) {
        return;
    }

    assigningTicketId.value = ticket.id;

    router.put(route('admin.support.assign', ticket.id), {
        assigned_agent_id: currentUserId.value,
    }, {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => {
            assigningTicketId.value = null;
        },
    });
};

const canAssignToMe = (ticket) => {
    if (!currentUserId.value) {
        return false;
    }

    return ticket.assignee?.id !== currentUserId.value;
};

const relativeTime = (value) => {
    if (!value) {
        return t('now');
    }

    const diff = new Date(value).getTime() - Date.now();
    const absSeconds = Math.abs(Math.round(diff / 1000));
    const formatter = new Intl.RelativeTimeFormat(locale.value, { numeric: 'auto' });

    if (absSeconds < 60) {
        return formatter.format(Math.round(diff / 1000), 'second');
    }

    const absMinutes = Math.abs(Math.round(diff / 60000));

    if (absMinutes < 60) {
        return formatter.format(Math.round(diff / 60000), 'minute');
    }

    const absHours = Math.abs(Math.round(diff / 3600000));

    if (absHours < 24) {
        return formatter.format(Math.round(diff / 3600000), 'hour');
    }

    return formatter.format(Math.round(diff / 86400000), 'day');
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

const formatLabel = (value) => {
    if (!value) {
        return t('Not available');
    }

    const normalizedValue = value.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());

    return t(normalizedValue);
};

const conversationStateLabel = (state) => {
    if (state === 'waiting_for_support') {
        return t('Waiting for support');
    }

    if (state === 'waiting_for_customer') {
        return t('Waiting for customer');
    }

    return t('No recent activity');
};

const senderLabel = (senderType) => {
    if (senderType === 'agent') {
        return t('Support');
    }

    if (senderType === 'user') {
        return t('Customer');
    }

    return t('No sender');
};

const ticketsCountLabel = computed(() => `${props.tickets.total} ${t(props.tickets.total === 1 ? 'ticket' : 'tickets')}`);

const summaryMetrics = computed(() => [
    { label: t('Open'), value: props.counters.open, status: 'open' },
    { label: t('In Progress'), value: props.counters.in_progress, status: 'in_progress' },
    { label: t('Waiting Customer'), value: props.counters.waiting_customer, status: 'waiting_customer' },
    { label: t('Resolved'), value: props.counters.resolved, status: 'resolved' },
]);

const queueOptions = computed(() => [
    { name: 'all', label: t('All tickets'), count: null },
    { name: 'unread', label: t('Unread'), count: props.counters.unread ?? 0 },
    { name: 'unassigned', label: t('Unassigned'), count: props.counters.unassigned ?? 0 },
    { name: 'mine', label: t('My tickets'), count: null },
    { name: 'sla_risk', label: t('SLA risk'), count: null },
]);

const hasActiveFilters = computed(() =>
    Object.entries(filterForm).some(([key, value]) => {
        if (key === 'sort') {
            return value !== 'latest';
        }

        if (key === 'queue') {
            return value !== 'all';
        }

        return value !== '';
    }),
);

const activeFilterChips = computed(() => {
    const chips = [];

    if (filterForm.queue && filterForm.queue !== 'all') {
        const queue = queueOptions.value.find((option) => option.name === filterForm.queue);
        chips.push({ key: 'queue', label: `${t('Queue')}: ${queue?.label ?? filterForm.queue}` });
    }

    if (filterForm.search) {
        chips.push({ key: 'search', label: `${t('Search')}: ${filterForm.search}` });
    }

    if (filterForm.status) {
        const status = props.status_options.find((option) => option.name === filterForm.status);
        chips.push({ key: 'status', label: `${t('Status')}: ${t(status?.label ?? filterForm.status)}` });
    }

    if (filterForm.priority) {
        const priority = props.priority_options.find((option) => option.name === filterForm.priority);
        chips.push({ key: 'priority', label: `${t('Priority')}: ${t(priority?.label ?? filterForm.priority)}` });
    }

    if (filterForm.category) {
        const category = props.category_options.find((option) => option.name === filterForm.category);
        chips.push({ key: 'category', label: `${t('Category')}: ${t(category?.label ?? filterForm.category)}` });
    }

    if (filterForm.assigned_agent_id) {
        const agent = props.agents.find((entry) => String(entry.id) === String(filterForm.assigned_agent_id));
        chips.push({ key: 'assigned_agent_id', label: `${t('Assigned agent')}: ${agent?.name ?? filterForm.assigned_agent_id}` });
    }

    if (filterForm.user_id) {
        chips.push({ key: 'user_id', label: `${t('Customer')}: #${filterForm.user_id}` });
    }

    if (filterForm.order_id) {
        chips.push({ key: 'order_id', label: `${t('Order ID')}: ${filterForm.order_id}` });
    }

    if (filterForm.sort && filterForm.sort !== 'latest') {
        const sort = props.sort_options.find((option) => option.name === filterForm.sort);
        chips.push({ key: 'sort', label: `${t('Sort')}: ${t(sort?.label ?? filterForm.sort)}` });
    }

    return chips;
});

const clearFilterChip = (key) => {
    if (key === 'queue') {
        filterForm.queue = 'all';
    } else if (key === 'sort') {
        filterForm.sort = 'latest';
    } else {
        filterForm[key] = '';
    }

    applyFilters();
};

watch(
    () => filterForm.search,
    () => {
        if (!filtersReady.value) {
            return;
        }

        clearTimeout(searchDebounceTimer);
        searchDebounceTimer = setTimeout(() => applyFilters(), 400);
    },
);

watch(
    () => [
        filterForm.status,
        filterForm.priority,
        filterForm.category,
        filterForm.assigned_agent_id,
        filterForm.sort,
        filterForm.queue,
    ],
    () => {
        if (!filtersReady.value) {
            return;
        }

        applyFilters();
    },
);

onMounted(() => {
    filtersReady.value = true;
    window.addEventListener('focus', refreshInbox);
    refreshIntervalId = window.setInterval(refreshInbox, 60000);
});

onBeforeUnmount(() => {
    clearTimeout(searchDebounceTimer);

    if (refreshIntervalId !== null) {
        window.clearInterval(refreshIntervalId);
    }

    window.removeEventListener('focus', refreshInbox);
});
</script>

<template>
    <Head :title="t('Support')" />

    <AdminLayout
        title="Support"
        description="Review support tickets in an inbox tuned for chat workflows, unread triage, and fast thread handoff."
    >
        <section class="space-y-6">
            <div class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white p-6 text-slate-900 shadow-sm">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.25em] text-cyan-700">
                            {{ t('Care Desk') }}
                        </p>
                        <h2 class="mt-2 text-2xl font-semibold text-slate-950">{{ t('Support inbox') }}</h2>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">
                            {{ t('Prioritize unread conversations, scan the latest reply instantly, and jump into live chat threads with less cognitive overhead.') }}
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <div class="rounded-2xl bg-slate-950 px-4 py-3 text-sm text-white">
                            {{ ticketsCountLabel }}
                        </div>
                        <Link
                            :href="route('admin.support.reports.index')"
                            class="inline-flex items-center justify-center rounded-2xl border border-slate-200 px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                        >
                            {{ t('Support reports') }}
                        </Link>
                        <Link
                            :href="route('admin.support.create')"
                            class="inline-flex items-center justify-center rounded-2xl bg-cyan-600 px-4 py-3 text-sm font-medium text-white transition hover:bg-cyan-700"
                        >
                            {{ t('Create ticket') }}
                        </Link>
                    </div>
                </div>

                <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <button
                        v-for="metric in summaryMetrics"
                        :key="metric.label"
                        type="button"
                        class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 text-left transition hover:bg-slate-100"
                        :class="filterForm.status === metric.status ? 'border-cyan-600 ring-1 ring-cyan-600/30' : ''"
                        @click="applyStatusFromMetric(metric.status)"
                    >
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ metric.label }}</p>
                        <p class="mt-2 text-2xl font-semibold text-slate-950">{{ metric.value }}</p>
                    </button>
                </div>
            </div>

            <div class="sticky top-0 z-20 overflow-hidden rounded-[2rem] border border-slate-200 bg-white/95 p-3 shadow-sm backdrop-blur">
                <div class="mb-3 flex flex-wrap gap-2">
                    <button
                        v-for="queue in queueOptions"
                        :key="queue.name"
                        type="button"
                        class="inline-flex h-10 items-center gap-2 rounded-2xl border px-4 text-sm font-medium transition"
                        :class="filterForm.queue === queue.name ? 'border-slate-950 bg-slate-950 text-white' : 'border-slate-200 text-slate-700 hover:bg-slate-50'"
                        @click="applyQueue(queue.name)"
                    >
                        <span>{{ queue.label }}</span>
                        <span
                            v-if="queue.count !== null"
                            class="rounded-full px-2 py-0.5 text-xs font-semibold"
                            :class="filterForm.queue === queue.name ? 'bg-white/15 text-white' : 'bg-slate-100 text-slate-600'"
                        >
                            {{ queue.count }}
                        </span>
                    </button>
                </div>

                <form class="flex items-center gap-2 overflow-x-auto pb-1" @submit.prevent="applyFilters">
                    <label class="flex h-14 min-w-[18rem] shrink-0 items-center gap-3 rounded-2xl border border-slate-200 bg-white px-4">
                        <span class="text-sm font-medium text-slate-600">{{ t('Search') }}</span>
                        <input
                            v-model="filterForm.search"
                            type="search"
                            class="min-w-0 flex-1 border-0 bg-transparent p-0 text-sm text-slate-900 outline-none placeholder:text-slate-400 focus:ring-0"
                            :placeholder="t('Ticket ID, customer name, or email')"
                        >
                    </label>

                    <label class="flex h-14 min-w-[9rem] shrink-0 items-center gap-3 rounded-2xl border border-slate-200 bg-white px-4">
                        <span class="text-sm font-medium text-slate-600">{{ t('Status') }}</span>
                        <select
                            v-model="filterForm.status"
                            class="min-w-0 flex-1 border-0 bg-transparent p-0 text-sm text-slate-900 outline-none focus:ring-0"
                        >
                            <option value="">{{ t('All statuses') }}</option>
                            <option v-for="status in status_options" :key="status.name" :value="status.name">
                                {{ t(status.label) }}
                            </option>
                        </select>
                    </label>

                    <label class="flex h-14 min-w-[9rem] shrink-0 items-center gap-3 rounded-2xl border border-slate-200 bg-white px-4">
                        <span class="text-sm font-medium text-slate-600">{{ t('Priority') }}</span>
                        <select
                            v-model="filterForm.priority"
                            class="min-w-0 flex-1 border-0 bg-transparent p-0 text-sm text-slate-900 outline-none focus:ring-0"
                        >
                            <option value="">{{ t('All priorities') }}</option>
                            <option v-for="priority in priority_options" :key="priority.name" :value="priority.name">
                                {{ t(priority.label) }}
                            </option>
                        </select>
                    </label>

                    <label class="flex h-14 min-w-[9.5rem] shrink-0 items-center gap-3 rounded-2xl border border-slate-200 bg-white px-4">
                        <span class="text-sm font-medium text-slate-600">{{ t('Category') }}</span>
                        <select
                            v-model="filterForm.category"
                            class="min-w-0 flex-1 border-0 bg-transparent p-0 text-sm text-slate-900 outline-none focus:ring-0"
                        >
                            <option value="">{{ t('All categories') }}</option>
                            <option v-for="category in category_options" :key="category.name" :value="category.name">
                                {{ t(category.label) }}
                            </option>
                        </select>
                    </label>

                    <label class="flex h-14 min-w-[10.5rem] shrink-0 items-center gap-3 rounded-2xl border border-slate-200 bg-white px-4">
                        <span class="text-sm font-medium text-slate-600">{{ t('Assigned agent') }}</span>
                        <select
                            v-model="filterForm.assigned_agent_id"
                            class="min-w-0 flex-1 border-0 bg-transparent p-0 text-sm text-slate-900 outline-none focus:ring-0"
                        >
                            <option value="">{{ t('All agents') }}</option>
                            <option v-for="agent in agents" :key="agent.id" :value="agent.id">
                                {{ agent.name }}
                            </option>
                        </select>
                    </label>

                    <label class="flex h-14 min-w-[8.5rem] shrink-0 items-center gap-3 rounded-2xl border border-slate-200 bg-white px-4">
                        <span class="text-sm font-medium text-slate-600">{{ t('Sort') }}</span>
                        <select
                            v-model="filterForm.sort"
                            class="min-w-0 flex-1 border-0 bg-transparent p-0 text-sm text-slate-900 outline-none focus:ring-0"
                        >
                            <option v-for="sort in sort_options" :key="sort.name" :value="sort.name">
                                {{ t(sort.label) }}
                            </option>
                        </select>
                    </label>

                    <button
                        type="submit"
                        class="inline-flex h-10 shrink-0 items-center justify-center rounded-2xl bg-slate-950 px-5 text-sm font-medium text-white transition hover:bg-slate-800"
                    >
                        {{ t('Apply') }}
                    </button>

                    <button
                        type="button"
                        class="inline-flex h-10 shrink-0 items-center justify-center rounded-2xl border border-slate-200 px-5 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:opacity-60"
                        :disabled="!hasActiveFilters"
                        @click="resetFilters"
                    >
                        {{ t('Reset') }}
                    </button>
                </form>

                <div v-if="activeFilterChips.length" class="mt-3 flex flex-wrap gap-2">
                    <button
                        v-for="chip in activeFilterChips"
                        :key="chip.key"
                        type="button"
                        class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-slate-700 transition hover:bg-slate-200"
                        @click="clearFilterChip(chip.key)"
                    >
                        {{ chip.label }} ×
                    </button>
                </div>
            </div>

            <div class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr class="text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">
                                <th class="px-6 py-4">{{ t('Ticket') }}</th>
                                <th class="px-6 py-4">{{ t('Customer') }}</th>
                                <th class="px-6 py-4">{{ t('Order') }}</th>
                                <th class="px-6 py-4">{{ t('Category') }}</th>
                                <th class="px-6 py-4">{{ t('Priority') }}</th>
                                <th class="px-6 py-4">{{ t('Status') }}</th>
                                <th class="px-6 py-4">{{ t('Conversation') }}</th>
                                <th class="px-6 py-4">{{ t('SLA') }}</th>
                                <th class="px-6 py-4">{{ t('Assigned') }}</th>
                                <th class="px-6 py-4">{{ t('Created') }}</th>
                                <th class="px-6 py-4">{{ t('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm text-slate-700">
                            <tr
                                v-for="ticket in tickets.data"
                                :key="ticket.id"
                                class="group cursor-pointer bg-white align-top transition duration-200 hover:bg-cyan-50/50"
                                :class="ticket.has_unread_for_admin ? 'bg-rose-50/50 hover:bg-rose-50/70' : ''"
                                role="link"
                                :aria-label="`${t('Open full ticket')} ${ticket.ticket_number}`"
                                tabindex="0"
                                @click="openTicketPage(ticket)"
                                @keydown.enter.prevent="openTicketPage(ticket)"
                                @keydown.space.prevent="openTicketPage(ticket)"
                            >
                                <td class="px-6 py-5">
                                    <div class="flex items-start gap-3">
                                        <span
                                            v-if="ticket.has_unread_for_admin"
                                            class="mt-2 inline-flex h-2.5 w-2.5 shrink-0 rounded-full bg-rose-500 shadow-[0_0_0_4px_rgba(244,63,94,0.12)]"
                                        />
                                        <div>
                                            <div class="font-semibold text-slate-950">{{ ticket.ticket_number }}</div>
                                            <div class="mt-1 text-slate-600">{{ ticket.subject }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-5">
                                    <div class="font-medium text-slate-900">{{ ticket.user.name || t('Unknown user') }}</div>
                                    <div class="mt-1 text-slate-500">{{ ticket.user.email || t('No email') }}</div>
                                </td>
                                <td class="px-6 py-5">
                                    <span v-if="ticket.order" class="text-slate-900">{{ ticket.order.reference }}</span>
                                    <span v-else class="text-slate-400">{{ t('No linked order') }}</span>
                                </td>
                                <td class="px-6 py-5 text-slate-600">{{ formatLabel(ticket.category) }}</td>
                                <td class="px-6 py-5">
                                    <SupportPriorityBadge :priority="ticket.priority" />
                                </td>
                                <td class="px-6 py-5">
                                    <SupportStatusBadge :status="ticket.status" />
                                </td>
                                <td class="px-6 py-5">
                                    <div class="font-medium text-slate-900">{{ conversationStateLabel(ticket.conversation_state) }}</div>
                                    <div class="mt-2 flex flex-wrap items-center gap-2">
                                        <span class="text-xs uppercase tracking-[0.16em] text-slate-500">
                                            {{ senderLabel(ticket.last_sender_type) }}
                                        </span>
                                        <span class="text-xs text-slate-400">·</span>
                                        <span class="text-xs text-slate-500">{{ relativeTime(ticket.last_message_at) }}</span>
                                    </div>
                                    <p class="mt-2 max-w-xs truncate text-slate-600">{{ ticket.last_message || t('No messages yet') }}</p>
                                </td>
                                <td class="px-6 py-5">
                                    <SupportSlaBadge :status="ticket.sla_status" />
                                </td>
                                <td class="px-6 py-5">
                                    <span v-if="ticket.assignee" class="text-slate-900">{{ ticket.assignee.name }}</span>
                                    <span v-else class="text-slate-400">{{ t('Unassigned') }}</span>
                                </td>
                                <td class="px-6 py-5">
                                    <div class="font-medium text-slate-900">{{ relativeTime(ticket.created_at) }}</div>
                                    <div class="mt-1 text-xs text-slate-500">{{ formatDateTime(ticket.created_at) }}</div>
                                </td>
                                <td class="px-6 py-5">
                                    <button
                                        v-if="canAssignToMe(ticket)"
                                        type="button"
                                        class="inline-flex items-center justify-center rounded-2xl border border-slate-200 px-3 py-2 text-xs font-medium text-slate-700 transition hover:bg-slate-50 disabled:opacity-60"
                                        :disabled="assigningTicketId === ticket.id"
                                        @click="assignToMe(ticket, $event)"
                                    >
                                        {{ assigningTicketId === ticket.id ? t('Assigning...') : t('Assign to me') }}
                                    </button>
                                </td>
                            </tr>

                            <tr v-if="tickets.data.length === 0">
                                <td colspan="11" class="px-6 py-14 text-center text-sm text-slate-500">
                                    {{ t('No support tickets are available yet.') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="tickets.links?.length" class="flex flex-col gap-4 border-t border-slate-200 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm text-slate-500">
                        {{ t('Showing results summary', {
                            from: tickets.from ?? 0,
                            to: tickets.to ?? 0,
                            total: tickets.total,
                        }) }}
                    </p>

                    <nav class="flex flex-wrap gap-2">
                        <component
                            :is="link.url ? Link : 'span'"
                            v-for="link in tickets.links"
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
