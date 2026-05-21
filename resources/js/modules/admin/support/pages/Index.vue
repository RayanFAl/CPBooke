<script setup>
import AdminLayout from '../../layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
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

const { locale, t } = useAdminLocale();

const filterForm = reactive({
    status: props.filters.status ?? '',
    priority: props.filters.priority ?? '',
    category: props.filters.category ?? '',
    assigned_agent_id: props.filters.assigned_agent_id ?? '',
    user_id: props.filters.user_id ?? '',
    order_id: props.filters.order_id ?? '',
    search: props.filters.search ?? '',
    sort: props.filters.sort ?? 'latest',
});

const applyFilters = () => {
    router.get(route('admin.support.index'), {
        ...(filterForm.status ? { status: filterForm.status } : {}),
        ...(filterForm.priority ? { priority: filterForm.priority } : {}),
        ...(filterForm.category ? { category: filterForm.category } : {}),
        ...(filterForm.assigned_agent_id ? { assigned_agent_id: filterForm.assigned_agent_id } : {}),
        ...(filterForm.user_id ? { user_id: filterForm.user_id } : {}),
        ...(filterForm.order_id ? { order_id: filterForm.order_id } : {}),
        ...(filterForm.search ? { search: filterForm.search } : {}),
        ...(filterForm.sort ? { sort: filterForm.sort } : {}),
    }, {
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
    applyFilters();
};

const openTicketPage = (ticket) => {
    router.visit(route('admin.support.show', ticket.id), {
        preserveScroll: true,
    });
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

const slaBadgeClass = (status) => {
    if (status === 'overdue') {
        return 'bg-rose-100 text-rose-700';
    }

    if (status === 'at_risk') {
        return 'bg-amber-100 text-amber-700';
    }

    return 'bg-emerald-100 text-emerald-700';
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

const unreadRowClass = (ticket) => {
    if (ticket.has_unread_for_admin) {
        return 'bg-rose-50/70';
    }

    return 'bg-white';
};

const ticketsCountLabel = () => `${props.tickets.total} ${t(props.tickets.total === 1 ? 'ticket' : 'tickets')}`;
</script>

<template>
    <Head :title="t('Support')" />

    <AdminLayout
        title="Support"
        description="Review support tickets in an inbox tuned for chat workflows, unread triage, and fast thread handoff."
    >
        <section class="space-y-6">
            <div class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
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

                    <div class="rounded-2xl bg-slate-950 px-4 py-3 text-sm text-white">
                        {{ ticketsCountLabel() }}
                    </div>
                </div>

                <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <article class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ t('Open') }}</p>
                        <p class="mt-2 text-2xl font-semibold text-slate-950">{{ counters.open }}</p>
                    </article>
                    <article class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ t('In Progress') }}</p>
                        <p class="mt-2 text-2xl font-semibold text-slate-950">{{ counters.in_progress }}</p>
                    </article>
                    <article class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ t('Waiting Customer') }}</p>
                        <p class="mt-2 text-2xl font-semibold text-slate-950">{{ counters.waiting_customer }}</p>
                    </article>
                    <article class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ t('Resolved') }}</p>
                        <p class="mt-2 text-2xl font-semibold text-slate-950">{{ counters.resolved }}</p>
                    </article>
                </div>

                <form class="mt-6 grid gap-4 xl:grid-cols-[minmax(0,1.35fr)_repeat(4,minmax(0,1fr))_minmax(0,1fr)_auto_auto]" @submit.prevent="applyFilters">
                    <label class="space-y-2 text-sm font-medium text-slate-700 xl:col-span-2">
                        <span>{{ t('Search') }}</span>
                        <input
                            v-model="filterForm.search"
                            type="text"
                            class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-cyan-300"
                            :placeholder="t('Ticket ID, customer name, or email')"
                        >
                    </label>

                    <label class="space-y-2 text-sm font-medium text-slate-700">
                        <span>{{ t('Status') }}</span>
                        <select
                            v-model="filterForm.status"
                            class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-cyan-300"
                        >
                            <option value="">{{ t('All statuses') }}</option>
                            <option v-for="status in status_options" :key="status.name" :value="status.name">
                                {{ t(status.label) }}
                            </option>
                        </select>
                    </label>

                    <label class="space-y-2 text-sm font-medium text-slate-700">
                        <span>{{ t('Priority') }}</span>
                        <select
                            v-model="filterForm.priority"
                            class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-cyan-300"
                        >
                            <option value="">{{ t('All priorities') }}</option>
                            <option v-for="priority in priority_options" :key="priority.name" :value="priority.name">
                                {{ t(priority.label) }}
                            </option>
                        </select>
                    </label>

                    <label class="space-y-2 text-sm font-medium text-slate-700">
                        <span>{{ t('Category') }}</span>
                        <select
                            v-model="filterForm.category"
                            class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-cyan-300"
                        >
                            <option value="">{{ t('All categories') }}</option>
                            <option v-for="category in category_options" :key="category.name" :value="category.name">
                                {{ t(category.label) }}
                            </option>
                        </select>
                    </label>

                    <label class="space-y-2 text-sm font-medium text-slate-700">
                        <span>{{ t('Assigned agent') }}</span>
                        <select
                            v-model="filterForm.assigned_agent_id"
                            class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-cyan-300"
                        >
                            <option value="">{{ t('All agents') }}</option>
                            <option v-for="agent in agents" :key="agent.id" :value="agent.id">
                                {{ agent.name }}
                            </option>
                        </select>
                    </label>

                    <label class="space-y-2 text-sm font-medium text-slate-700">
                        <span>{{ t('Sort') }}</span>
                        <select
                            v-model="filterForm.sort"
                            class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-cyan-300"
                        >
                            <option v-for="sort in sort_options" :key="sort.name" :value="sort.name">
                                {{ t(sort.label) }}
                            </option>
                        </select>
                    </label>

                    <button
                        type="submit"
                        class="inline-flex items-center justify-center self-end rounded-2xl bg-white px-4 py-3 text-sm font-medium text-slate-950 transition hover:bg-slate-100"
                    >
                        {{ t('Apply') }}
                    </button>

                    <button
                        type="button"
                        class="inline-flex items-center justify-center self-end rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                        @click="resetFilters"
                    >
                        {{ t('Reset') }}
                    </button>
                </form>

                <div class="mt-6">
                    <Link
                        :href="route('admin.support.create')"
                        class="inline-flex rounded-2xl bg-white px-4 py-3 text-sm font-medium text-slate-950 transition hover:bg-slate-100"
                    >
                        {{ t('Create ticket') }}
                    </Link>
                </div>
            </div>

            <div class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm">
                <div class="overflow-hidden">
                    <table class="min-w-full table-fixed divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr class="text-left text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500">
                                <th class="w-[14%] px-2 py-3">{{ t('Ticket') }}</th>
                                <th class="w-[12%] px-2 py-3">{{ t('Customer') }}</th>
                                <th class="w-[7%] px-2 py-3">{{ t('Order') }}</th>
                                <th class="w-[7%] px-2 py-3">{{ t('Category') }}</th>
                                <th class="w-[7%] px-2 py-3">{{ t('Priority') }}</th>
                                <th class="w-[8%] px-2 py-3">{{ t('Status') }}</th>
                                <th class="w-[18%] px-2 py-3">{{ t('Conversation') }}</th>
                                <th class="w-[6%] px-2 py-3">{{ t('SLA') }}</th>
                                <th class="w-[7%] px-2 py-3">{{ t('Assigned') }}</th>
                                <th class="w-[8%] px-2 py-3">{{ t('Created') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-[12px] leading-5 text-slate-700">
                            <tr
                                v-for="ticket in tickets.data"
                                :key="ticket.id"
                                class="group cursor-pointer align-top transition duration-200 hover:bg-slate-50/90 focus-within:bg-slate-50/90"
                                :class="unreadRowClass(ticket)"
                                role="link"
                                tabindex="0"
                                @click="openTicketPage(ticket)"
                                @keyup.enter="openTicketPage(ticket)"
                                @keyup.space.prevent="openTicketPage(ticket)"
                            >
                                <td class="px-2 py-3 align-top">
                                    <div class="font-semibold text-slate-950">{{ ticket.ticket_number }}</div>
                                    <div class="mt-1 truncate text-slate-600">{{ ticket.subject }}</div>
                                </td>
                                <td class="px-2 py-3 align-top">
                                    <div class="truncate font-medium text-slate-900">{{ ticket.user.name || t('Unknown user') }}</div>
                                    <div class="mt-1 truncate text-slate-500">{{ ticket.user.email || t('No email') }}</div>
                                </td>
                                <td class="px-2 py-3 align-top">
                                    <span v-if="ticket.order" class="truncate text-slate-900">{{ ticket.order.reference }}</span>
                                    <span v-else class="text-slate-400">No linked order</span>
                                </td>
                                <td class="px-2 py-3 align-top">{{ formatLabel(ticket.category) }}</td>
                                <td class="px-2 py-3 align-top">{{ formatLabel(ticket.priority) }}</td>
                                <td class="px-2 py-3 align-top">{{ formatLabel(ticket.status) }}</td>
                                <td class="px-2 py-3 align-top">
                                    <div class="flex items-center gap-2">
                                        <span
                                            v-if="ticket.has_unread_for_admin"
                                            class="inline-flex h-2.5 w-2.5 rounded-full bg-rose-500 shadow-[0_0_0_4px_rgba(244,63,94,0.12)]"
                                        />
                                        <div class="font-medium text-slate-900">{{ conversationStateLabel(ticket.conversation_state) }}</div>
                                    </div>
                                    <div class="mt-2 flex items-center gap-2">
                                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[9px] font-semibold uppercase tracking-[0.12em] text-slate-700">
                                            {{ senderLabel(ticket.last_sender_type) }}
                                        </span>
                                        <span class="text-[11px] text-slate-500">{{ relativeTime(ticket.last_message_at) }}</span>
                                    </div>
                                    <p class="mt-2 max-w-[10rem] truncate text-slate-600">{{ ticket.last_message || t('No messages yet') }}</p>
                                </td>
                                <td class="px-2 py-3 align-top">
                                    <span
                                        class="inline-flex rounded-full px-2 py-0.5 text-[9px] font-semibold uppercase tracking-[0.12em]"
                                        :class="slaBadgeClass(ticket.sla_status)"
                                    >
                                        {{ formatLabel(ticket.sla_status) }}
                                    </span>
                                </td>
                                <td class="px-2 py-3 align-top">
                                    <span v-if="ticket.assignee" class="truncate text-slate-900">{{ ticket.assignee.name }}</span>
                                    <span v-else class="text-slate-400">{{ t('Unassigned') }}</span>
                                </td>
                                <td class="px-2 py-3 align-top text-slate-500">
                                    <div>{{ relativeTime(ticket.created_at) }}</div>
                                    <div class="mt-1 text-[10px] text-slate-400">{{ formatDateTime(ticket.created_at) }}</div>
                                </td>
                            </tr>

                            <tr v-if="tickets.data.length === 0">
                                <td colspan="10" class="px-4 py-12 text-center text-sm text-slate-500">
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