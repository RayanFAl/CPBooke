<script setup>
import AccountTypeBadge from '../components/AccountTypeBadge.vue';
import AdminLayout from '../../layouts/AdminLayout.vue';
import RoleBadge from '../components/RoleBadge.vue';
import UserStatusBadge from '../components/UserStatusBadge.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useAdminLocale } from '../../composables/useAdminLocale';
import { usePlatformCurrency } from '../../composables/usePlatformCurrency';

const props = defineProps({
    user: {
        type: Object,
        required: true,
    },
});

const { locale, t } = useAdminLocale();
const { defaultCurrency } = usePlatformCurrency();

const page = usePage();
const permissions = computed(() => page.props.auth.user?.permissions ?? []);
const canUpdateUsers = computed(() => permissions.value.includes('users.update'));
const canViewOrders = computed(() => permissions.value.includes('orders.view'));
const canViewLoyalty = computed(() => permissions.value.includes('loyalty.view'));
const canViewSupport = computed(() => permissions.value.includes('support.view'));
const activeSupportTicket = computed(() => props.user.support?.active_ticket ?? null);
const activeSupportTicketCount = computed(() => props.user.support?.active_ticket_count ?? 0);
const loyalty = computed(() => props.user.loyalty ?? {
    current_level: 0,
    current_tier: null,
    next_tier: null,
    progress_to_next_level: {
        percentage: 0,
        current_metrics: {
            lifetime_orders_count: 0,
            completed_orders_count: 0,
            lifetime_spend: '0.00',
            period_orders_count: 0,
            period_spend: '0.00',
        },
    },
    benefits_unlocked: [],
    history: [],
    last_calculated_at: null,
});
const supportIndexLink = computed(() => route('admin.support.index', props.user.email ? { search: props.user.email } : {}));
const activeSupportTicketLink = computed(() => activeSupportTicket.value
    ? route('admin.support.show', activeSupportTicket.value.id)
    : supportIndexLink.value);
const activeTab = ref('overview');

const workspaceTabs = computed(() => {
    const tabs = [
        { id: 'overview', label: t('Overview'), count: 0 },
        { id: 'orders', label: t('Orders'), count: props.user.recent_orders.length },
        { id: 'finance', label: t('Finance'), count: props.user.financial_transactions.length },
        { id: 'activity', label: t('Activity'), count: props.user.recent_activities.length },
    ];

    if (canViewSupport.value) {
        tabs.push({ id: 'support', label: t('Support'), count: activeSupportTicketCount.value });
    }

    if (canViewLoyalty.value) {
        tabs.push({ id: 'loyalty', label: t('Loyalty'), count: loyalty.value.history.length });
    }

    tabs.push({ id: 'access', label: t('Access'), count: props.user.permissions.length });

    return tabs;
});

const validTabIds = computed(() => workspaceTabs.value.map((tab) => tab.id));

const normalizeTab = (value) => {
    if (value && validTabIds.value.includes(value)) {
        return value;
    }

    return 'overview';
};

const replaceHash = (tabId) => {
    if (typeof window === 'undefined') {
        return;
    }

    const nextTab = normalizeTab(tabId);
    const nextUrl = `${window.location.pathname}${window.location.search}#${nextTab}`;
    window.history.replaceState(window.history.state, '', nextUrl);
};

const syncTabFromHash = () => {
    if (typeof window === 'undefined') {
        return;
    }

    activeTab.value = normalizeTab(window.location.hash.replace('#', ''));
};

const walletBalanceLabel = computed(() => {
    const balance = props.user.financial_summary?.wallet_balance;
    const currency = props.user.financial_summary?.currency || defaultCurrency.value;

    if (balance === null || balance === undefined) {
        return t('Not available');
    }

    return new Intl.NumberFormat(locale.value, {
        style: 'currency',
        currency,
    }).format(Number(balance));
});

const formatDateTime = (value) => {
    if (!value) {
        return t('Not available');
    }

    return new Intl.DateTimeFormat(locale.value, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
};

const formatValueLabel = (value) => {
    if (!value) {
        return t('Not available');
    }

    const normalized = String(value).replaceAll('_', ' ').trim();
    const lowerCased = normalized.toLowerCase();
    const titleCased = normalized.replace(/\b\w/g, (letter) => letter.toUpperCase());
    const lowerTranslation = t(lowerCased);

    if (lowerTranslation !== lowerCased) {
        return lowerTranslation;
    }

    const titleTranslation = t(titleCased);

    if (titleTranslation !== titleCased) {
        return titleTranslation;
    }

    return titleCased;
};

const conversationStateLabel = (value) => {
    if (value === 'waiting_for_support') {
        return t('Waiting for support');
    }

    if (value === 'waiting_for_customer') {
        return t('Waiting for customer');
    }

    return t('No conversation state');
};

const bubbleTone = (senderType) => (senderType === 'user'
    ? 'bg-slate-950 text-white'
    : 'border border-slate-200 bg-slate-50 text-slate-900');

const metaTone = (senderType) => (senderType === 'user' ? 'text-slate-300' : 'text-slate-500');

const toggleStatus = () => {
    const actionLabel = props.user.is_active ? t('deactivate') : t('activate');

    if (!window.confirm(t('Do you want to :action :name?', { action: actionLabel, name: props.user.full_name }))) {
        return;
    }

    router.post(route('admin.users.toggle-status', props.user.id), {}, {
        preserveScroll: true,
    });
};

const changeTab = (tabId) => {
    activeTab.value = normalizeTab(tabId);
    replaceHash(activeTab.value);
};

onMounted(() => {
    syncTabFromHash();

    if (typeof window !== 'undefined') {
        window.addEventListener('hashchange', syncTabFromHash);
    }
});

onBeforeUnmount(() => {
    if (typeof window !== 'undefined') {
        window.removeEventListener('hashchange', syncTabFromHash);
    }
});
</script>

<template>
    <Head :title="`${t('User')} ${user.full_name}`" />

    <AdminLayout
        title="User Profile"
        description="360-degree user snapshot with a clear separation between account classification and administrative access."
    >
        <section class="space-y-6">
            <div class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 bg-[radial-gradient(circle_at_top_left,_rgba(6,182,212,0.10),_transparent_38%),linear-gradient(180deg,_#ffffff,_#f8fafc)] px-6 py-6">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.25em] text-cyan-700">{{ t('User Workspace') }}</p>
                            <h2 class="mt-2 text-3xl font-semibold text-slate-950">{{ user.full_name }}</h2>
                            <div class="mt-3 flex flex-wrap items-center gap-3">
                                <UserStatusBadge :active="user.is_active" />
                                <AccountTypeBadge :account-type="user.account_type" />
                                <RoleBadge :role="user.role" />
                            </div>
                            <p class="mt-4 max-w-2xl text-sm leading-6 text-slate-600">
                                {{ t('Switch the user workspace by link-aware tabs so account details, orders, support, finance, and access are no longer stacked in one long page.') }}
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-3">
                            <Link
                                :href="route('admin.users.index')"
                                class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                            >
                                {{ t('Back to users') }}
                            </Link>
                            <Link
                                v-if="canUpdateUsers"
                                :href="route('admin.users.edit', user.id)"
                                class="rounded-2xl border border-cyan-200 bg-cyan-50 px-4 py-3 text-sm font-medium text-cyan-700 transition hover:bg-cyan-100"
                            >
                                {{ t('Edit user') }}
                            </Link>
                            <button
                                v-if="canUpdateUsers"
                                type="button"
                                class="rounded-2xl bg-slate-950 px-4 py-3 text-sm font-medium text-white transition hover:bg-slate-800"
                                @click="toggleStatus"
                            >
                                {{ user.is_active ? t('Deactivate account') : t('Activate account') }}
                            </button>
                        </div>
                    </div>

                    <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <div class="rounded-[1.6rem] border border-slate-200 bg-white px-4 py-4 shadow-sm">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ t('Orders') }}</p>
                            <p class="mt-2 text-2xl font-semibold text-slate-950">{{ user.recent_orders.length }}</p>
                            <p class="mt-2 text-sm text-slate-600">{{ t('Recent order cards moved into their own workspace tab.') }}</p>
                        </div>
                        <div class="rounded-[1.6rem] border border-slate-200 bg-white px-4 py-4 shadow-sm">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ t('Finance') }}</p>
                            <p class="mt-2 text-2xl font-semibold text-slate-950">{{ user.financial_transactions.length }}</p>
                            <p class="mt-2 text-sm text-slate-600">{{ t('Wallet state and transactions are isolated from profile details.') }}</p>
                        </div>
                        <div class="rounded-[1.6rem] border border-slate-200 bg-white px-4 py-4 shadow-sm">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ t('Activity') }}</p>
                            <p class="mt-2 text-2xl font-semibold text-slate-950">{{ user.recent_activities.length }}</p>
                            <p class="mt-2 text-sm text-slate-600">{{ t('Operational history is now separated into a dedicated activity view.') }}</p>
                        </div>
                        <div class="rounded-[1.6rem] border border-slate-200 bg-white px-4 py-4 shadow-sm">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ t('Support') }}</p>
                            <p class="mt-2 text-2xl font-semibold text-slate-950">{{ activeSupportTicketCount }}</p>
                            <p class="mt-2 text-sm text-slate-600">{{ t('Active conversations remain available without crowding the main profile view.') }}</p>
                        </div>
                    </div>
                </div>

                <div class="px-3 py-3">
                    <nav class="flex gap-2 overflow-x-auto">
                        <button
                            v-for="tab in workspaceTabs"
                            :key="tab.id"
                            type="button"
                            class="inline-flex shrink-0 items-center gap-2 rounded-2xl px-4 py-3 text-sm font-medium transition"
                            :class="activeTab === tab.id ? 'bg-slate-950 text-white shadow-sm' : 'bg-slate-50 text-slate-600 hover:bg-slate-100'"
                            @click="changeTab(tab.id)"
                        >
                            <span>{{ tab.label }}</span>
                            <span
                                class="rounded-full px-2 py-0.5 text-xs"
                                :class="activeTab === tab.id ? 'bg-white/10 text-white' : 'bg-white text-slate-500'"
                            >
                                {{ tab.count }}
                            </span>
                        </button>
                    </nav>
                </div>
            </div>

            <div v-if="activeTab === 'overview'" class="grid gap-6 xl:grid-cols-[minmax(0,1.2fr)_minmax(0,0.8fr)]">
                <div class="space-y-6">
                    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h3 class="text-lg font-semibold text-slate-950">{{ t('Personal details') }}</h3>
                        <dl class="mt-5 grid gap-5 sm:grid-cols-2">
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{{ t('Full name') }}</dt>
                                <dd class="mt-2 text-sm text-slate-900">{{ user.full_name }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{{ t('Email') }}</dt>
                                <dd class="mt-2 text-sm text-slate-900">{{ user.email }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{{ t('Phone') }}</dt>
                                <dd class="mt-2 text-sm text-slate-900">{{ user.phone || t('Not provided') }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{{ t('Country') }}</dt>
                                <dd class="mt-2 text-sm text-slate-900">{{ user.country || t('Not provided') }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{{ t('Account type') }}</dt>
                                <dd class="mt-2 text-sm text-slate-900">{{ user.account_type === 'admin' ? t('Admin') : t('Customer') }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{{ t('Role') }}</dt>
                                <dd class="mt-2 text-sm text-slate-900">{{ user.role?.label ? t(user.role.label) : t('No role assigned') }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{{ t('Created at') }}</dt>
                                <dd class="mt-2 text-sm text-slate-900">{{ formatDateTime(user.created_at) }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{{ t('Last login') }}</dt>
                                <dd class="mt-2 text-sm text-slate-900">{{ formatDateTime(user.last_login_at) }}</dd>
                            </div>
                        </dl>
                    </div>

                    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h3 class="text-lg font-semibold text-slate-950">{{ t('Workspace shortcuts') }}</h3>
                        <div class="mt-5 grid gap-3 sm:grid-cols-2">
                            <button
                                type="button"
                                class="flex items-center justify-between rounded-2xl border border-slate-200 px-4 py-3 text-left text-sm text-slate-700 transition hover:bg-slate-50"
                                @click="changeTab('orders')"
                            >
                                <span>{{ t('Open orders workspace') }}</span>
                                <span class="font-medium text-slate-950">{{ user.recent_orders.length }}</span>
                            </button>
                            <button
                                type="button"
                                class="flex items-center justify-between rounded-2xl border border-slate-200 px-4 py-3 text-left text-sm text-slate-700 transition hover:bg-slate-50"
                                @click="changeTab('finance')"
                            >
                                <span>{{ t('Open finance workspace') }}</span>
                                <span class="font-medium text-slate-950">{{ walletBalanceLabel }}</span>
                            </button>
                            <button
                                type="button"
                                class="flex items-center justify-between rounded-2xl border border-slate-200 px-4 py-3 text-left text-sm text-slate-700 transition hover:bg-slate-50"
                                @click="changeTab('activity')"
                            >
                                <span>{{ t('Open activity feed') }}</span>
                                <span class="font-medium text-slate-950">{{ user.recent_activities.length }}</span>
                            </button>
                            <button
                                type="button"
                                class="flex items-center justify-between rounded-2xl border border-slate-200 px-4 py-3 text-left text-sm text-slate-700 transition hover:bg-slate-50"
                                @click="changeTab(canViewLoyalty ? 'loyalty' : (canViewSupport ? 'support' : 'access'))"
                            >
                                <span>{{ canViewLoyalty ? t('Open loyalty workspace') : (canViewSupport ? t('Open support workspace') : t('Open access workspace')) }}</span>
                                <span class="font-medium text-slate-950">{{ canViewLoyalty ? loyalty.history.length : (canViewSupport ? activeSupportTicketCount : user.permissions.length) }}</span>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="space-y-6">
                    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h3 class="text-lg font-semibold text-slate-950">{{ t('Account health') }}</h3>
                        <div class="mt-5 space-y-4">
                            <div class="rounded-2xl bg-slate-50 p-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{{ t('Verification') }}</p>
                                <p class="mt-2 text-sm text-slate-900">
                                    {{ user.email_verified_at ? t('Verified :date', { date: formatDateTime(user.email_verified_at) }) : t('Email not verified') }}
                                </p>
                            </div>
                            <div class="rounded-2xl bg-slate-50 p-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{{ t('Status') }}</p>
                                <p class="mt-2 text-sm text-slate-900">
                                    {{ user.is_active ? t('Account is currently active and can sign in.') : t('Account is currently disabled and cannot sign in.') }}
                                </p>
                            </div>
                            <div class="rounded-2xl bg-slate-50 p-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{{ t('Administrative access') }}</p>
                                <p class="mt-2 text-sm text-slate-900">
                                    {{ user.account_type === 'admin' ? t('This account can access the admin panel according to its resolved permissions.') : t('This account is classified as a customer-facing user profile.') }}
                                </p>
                            </div>
                            <div v-if="canViewLoyalty" class="rounded-2xl bg-slate-50 p-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{{ t('Loyalty tier') }}</p>
                                <p class="mt-2 text-sm text-slate-900">
                                    {{ loyalty.current_tier ? `${loyalty.current_tier.name} / ${t('Level')} ${loyalty.current_level}` : t('No loyalty tier assigned yet.') }}
                                </p>
                                <p class="mt-2 text-sm text-slate-600">{{ t('Progress to next level: :percentage%', { percentage: loyalty.progress_to_next_level.percentage }) }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else-if="activeTab === 'orders'" class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-950">{{ t('Latest orders') }}</h3>
                        <p class="mt-2 text-sm leading-6 text-slate-600">{{ t('Recent bookings are separated into their own workspace so order cards no longer compete with support, finance, and access cards.') }}</p>
                    </div>
                    <Link
                        v-if="canViewOrders"
                        :href="route('admin.orders.index', user.email ? { search: user.email } : {})"
                        class="rounded-2xl border border-slate-200 px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                    >
                        {{ t('Open orders desk') }}
                    </Link>
                </div>

                <div class="mt-5 space-y-4">
                    <component
                        :is="canViewOrders ? Link : 'div'"
                        v-for="order in user.recent_orders"
                        :key="order.id"
                        :href="canViewOrders ? route('admin.orders.show', order.id) : undefined"
                        class="block rounded-2xl border border-slate-200 p-4 transition"
                        :class="canViewOrders ? 'hover:border-cyan-300 hover:bg-cyan-50/40' : ''"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <p class="font-medium text-slate-900">{{ order.booking_reference }}</p>
                                <p class="mt-2 text-sm text-slate-600">{{ formatValueLabel(order.service_type) }} / {{ formatValueLabel(order.status) }} / {{ formatValueLabel(order.payment_status) }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-medium text-slate-950">{{ order.amount }} {{ order.currency }}</p>
                                <p class="mt-2 text-xs uppercase tracking-[0.16em] text-slate-500">{{ formatDateTime(order.created_at) }}</p>
                            </div>
                        </div>
                    </component>
                    <p v-if="user.recent_orders.length === 0" class="text-sm text-slate-500">{{ t('No orders recorded for this user yet.') }}</p>
                </div>
            </div>

            <div v-else-if="activeTab === 'finance'" class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-slate-950">{{ t('Financial transactions') }}</h3>
                <div class="mt-5 rounded-2xl bg-slate-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{{ t('Wallet balance') }}</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-950">{{ walletBalanceLabel }}</p>
                    <p class="mt-2 text-sm text-slate-600">
                        {{ user.financial_summary?.has_wallet_data ? t("Calculated from the user's recorded financial transactions.") : t('Wallet data is unavailable because finance tables are not present in this environment.') }}
                    </p>
                </div>
                <div class="mt-5 space-y-4">
                    <div
                        v-for="transaction in user.financial_transactions"
                        :key="transaction.id"
                        class="rounded-2xl border border-slate-200 p-4"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <p class="font-medium text-slate-900">#{{ transaction.id }} / {{ formatValueLabel(transaction.type) }}</p>
                                <p class="mt-2 text-sm text-slate-600">{{ formatValueLabel(transaction.source) }} / {{ t('Order') }} #{{ transaction.order_id }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-medium text-slate-950">{{ transaction.amount }} {{ transaction.currency }}</p>
                                <p class="mt-2 text-xs uppercase tracking-[0.16em] text-slate-500">{{ formatDateTime(transaction.created_at) }}</p>
                            </div>
                        </div>
                    </div>
                    <p v-if="user.financial_transactions.length === 0" class="text-sm text-slate-500">{{ t('No financial transactions recorded for this user yet.') }}</p>
                </div>
            </div>

            <div v-else-if="activeTab === 'activity'" class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-slate-950">{{ t('Recent activity') }}</h3>
                <div class="mt-5 space-y-4">
                    <div
                        v-for="activity in user.recent_activities"
                        :key="activity.id"
                        class="rounded-2xl border border-slate-200 p-4"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <p class="font-medium text-slate-900">{{ formatValueLabel(activity.action) }} / {{ formatValueLabel(activity.field) }}</p>
                                <p class="mt-2 text-sm text-slate-600">
                                    {{ activity.booking_reference ? `${activity.booking_reference} / ` : '' }}{{ t('Order') }} #{{ activity.order_id }}
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-medium text-slate-950">{{ formatDateTime(activity.created_at) }}</p>
                            </div>
                        </div>
                    </div>
                    <p v-if="user.recent_activities.length === 0" class="text-sm text-slate-500">{{ t('No tracked activity recorded for this user yet.') }}</p>
                </div>
            </div>

            <div v-else-if="activeTab === 'loyalty' && canViewLoyalty" class="space-y-6">
                <div class="grid gap-6 xl:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
                    <div class="space-y-6">
                        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                            <h3 class="text-lg font-semibold text-slate-950">{{ t('Loyalty snapshot') }}</h3>
                            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                                <div class="rounded-2xl bg-slate-50 p-4">
                                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{{ t('Current tier') }}</p>
                                    <p class="mt-2 text-sm font-medium text-slate-950">{{ loyalty.current_tier?.name || t('Not assigned') }}</p>
                                    <p class="mt-2 text-sm text-slate-600">{{ loyalty.current_tier ? `${t('Level')} ${loyalty.current_level}` : t('The loyalty engine has not assigned a tier yet.') }}</p>
                                </div>
                                <div class="rounded-2xl bg-slate-50 p-4">
                                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{{ t('Next tier') }}</p>
                                    <p class="mt-2 text-sm font-medium text-slate-950">{{ loyalty.next_tier?.name || t('No higher tier') }}</p>
                                    <p class="mt-2 text-sm text-slate-600">{{ t('Progress: :percentage%', { percentage: loyalty.progress_to_next_level.percentage }) }}</p>
                                </div>
                            </div>
                            <dl class="mt-5 grid gap-4 sm:grid-cols-2">
                                <div>
                                    <dt class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{{ t('Completed orders') }}</dt>
                                    <dd class="mt-2 text-sm text-slate-900">{{ loyalty.progress_to_next_level.current_metrics.completed_orders_count }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{{ t('Lifetime orders') }}</dt>
                                    <dd class="mt-2 text-sm text-slate-900">{{ loyalty.progress_to_next_level.current_metrics.lifetime_orders_count }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{{ t('Lifetime spend') }}</dt>
                                    <dd class="mt-2 text-sm text-slate-900">{{ loyalty.progress_to_next_level.current_metrics.lifetime_spend }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{{ t('Last calculated') }}</dt>
                                    <dd class="mt-2 text-sm text-slate-900">{{ formatDateTime(loyalty.last_calculated_at) }}</dd>
                                </div>
                            </dl>
                        </div>

                        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                            <h3 class="text-lg font-semibold text-slate-950">{{ t('Unlocked benefits') }}</h3>
                            <div v-if="loyalty.benefits_unlocked.length === 0" class="mt-5 rounded-2xl bg-slate-50 p-4 text-sm text-slate-500">
                                {{ t('No loyalty benefits are unlocked for this user yet.') }}
                            </div>
                            <div v-else class="mt-5 space-y-3">
                                <div
                                    v-for="benefit in loyalty.benefits_unlocked"
                                    :key="benefit.id"
                                    class="rounded-2xl border border-slate-200 p-4"
                                >
                                    <div class="flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <p class="text-sm font-medium text-slate-950">{{ benefit.name }}</p>
                                            <p class="mt-2 text-sm text-slate-600">{{ benefit.description || t('No description configured.') }}</p>
                                        </div>
                                        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-slate-700">
                                            {{ formatValueLabel(benefit.benefit_type) }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h3 class="text-lg font-semibold text-slate-950">{{ t('Loyalty history') }}</h3>
                        <div v-if="loyalty.history.length === 0" class="mt-5 rounded-2xl bg-slate-50 p-4 text-sm text-slate-500">
                            {{ t('No loyalty history recorded for this user yet.') }}
                        </div>
                        <div v-else class="mt-5 space-y-4">
                            <div
                                v-for="entry in loyalty.history"
                                :key="entry.id"
                                class="rounded-2xl border border-slate-200 p-4"
                            >
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-medium text-slate-950">{{ formatValueLabel(entry.action) }}</p>
                                        <p class="mt-2 text-sm text-slate-600">{{ entry.from_tier?.name || t('No tier') }} / {{ entry.to_tier?.name || t('No tier') }}</p>
                                    </div>
                                    <p class="text-xs uppercase tracking-[0.16em] text-slate-500">{{ formatDateTime(entry.changed_at) }}</p>
                                </div>
                                <p v-if="entry.notes" class="mt-3 text-sm text-slate-600">{{ entry.notes }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else-if="activeTab === 'support' && canViewSupport" class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-950">{{ t('Active support conversation') }}</h3>
                        <p class="mt-2 text-sm leading-6 text-slate-600">
                            {{ t('Keep the latest live ticket context inside the customer profile for faster CRM-style handling.') }}
                        </p>
                    </div>

                    <Link
                        :href="activeSupportTicketLink"
                        class="rounded-2xl border border-slate-200 px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                    >
                        {{ activeSupportTicket ? t('Open full ticket') : t('Open support inbox') }}
                    </Link>
                </div>

                <div v-if="activeSupportTicket" class="mt-5 space-y-5">
                    <div class="rounded-2xl bg-slate-50 p-4">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-cyan-700">{{ activeSupportTicket.ticket_number }}</p>
                                <p class="mt-2 text-base font-semibold text-slate-950">{{ activeSupportTicket.subject }}</p>
                                <p class="mt-2 text-sm text-slate-600">
                                    {{ t('Updated :date', { date: formatDateTime(activeSupportTicket.updated_at) }) }}
                                </p>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <span class="rounded-full bg-cyan-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-cyan-700">
                                    {{ formatValueLabel(activeSupportTicket.status) }}
                                </span>
                                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-slate-700">
                                    {{ formatValueLabel(activeSupportTicket.priority) }}
                                </span>
                                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-slate-700">
                                    {{ conversationStateLabel(activeSupportTicket.conversation_state) }}
                                </span>
                            </div>
                        </div>

                        <div class="mt-4 grid gap-4 sm:grid-cols-2">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ t('Assigned agent') }}</p>
                                <p class="mt-2 text-sm text-slate-900">{{ activeSupportTicket.assignee?.name || t('Unassigned') }}</p>
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ t('Linked order') }}</p>
                                <p class="mt-2 text-sm text-slate-900">{{ activeSupportTicket.order?.reference || t('No linked order') }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div
                            v-for="message in activeSupportTicket.messages"
                            :key="message.id"
                            class="flex"
                            :class="message.sender_type === 'user' ? 'justify-end' : 'justify-start'"
                        >
                            <div class="max-w-[88%] rounded-[1.5rem] px-4 py-3 shadow-sm" :class="bubbleTone(message.sender_type)">
                                <div class="flex flex-wrap items-center gap-2 text-xs" :class="metaTone(message.sender_type)">
                                    <span class="font-semibold">{{ message.user?.name || message.user?.email || t('Unknown sender') }}</span>
                                    <span>{{ formatDateTime(message.created_at) }}</span>
                                </div>
                                <p class="mt-2 whitespace-pre-line text-sm leading-6">{{ message.message }}</p>
                                <p v-if="message.has_attachment" class="mt-3 text-xs font-medium" :class="metaTone(message.sender_type)">
                                    {{ t('Attachment') }}: {{ message.attachment_name || t('File attached') }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <p v-if="activeSupportTicketCount > 1" class="text-sm text-slate-500">
                        {{ t('This user has :count active tickets. The newest conversation is shown here.', { count: activeSupportTicketCount }) }}
                    </p>
                </div>

                <div v-else class="mt-5 rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-4">
                    <p class="font-medium text-slate-900">{{ t('No active support conversation') }}</p>
                    <p class="mt-2 text-sm leading-6 text-slate-600">
                        {{ t('This user does not currently have an open, in-progress, or waiting-customer ticket.') }}
                    </p>
                </div>
            </div>

            <div v-else class="space-y-6">
                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-slate-950">{{ t('Resolved permissions') }}</h3>
                    <div class="mt-5 flex flex-wrap gap-2">
                        <span
                            v-for="permission in user.permissions"
                            :key="permission"
                            class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-slate-700"
                        >
                            {{ permission }}
                        </span>
                    </div>
                    <p v-if="user.permissions.length === 0" class="mt-4 text-sm text-slate-500">{{ t('No resolved permissions were returned for this user.') }}</p>
                </div>

                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-slate-950">{{ t('Access summary') }}</h3>
                    <div class="mt-5 grid gap-4 sm:grid-cols-2">
                        <div class="rounded-2xl bg-slate-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{{ t('Account type') }}</p>
                            <p class="mt-2 text-sm text-slate-900">{{ user.account_type === 'admin' ? t('Admin workspace account') : t('Customer workspace account') }}</p>
                        </div>
                        <div class="rounded-2xl bg-slate-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{{ t('Role assignment') }}</p>
                            <p class="mt-2 text-sm text-slate-900">{{ user.role?.label ? t(user.role.label) : t('No role assigned') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </AdminLayout>
</template>