<script setup>
import AccountTypeBadge from '../components/AccountTypeBadge.vue';
import AdminLayout from '../../layouts/AdminLayout.vue';
import RoleBadge from '../components/RoleBadge.vue';
import SystemTimeline from '../../components/SystemTimeline.vue';
import UserStatusBadge from '../components/UserStatusBadge.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useAdminLocale } from '../../composables/useAdminLocale';
import { usePlatformCurrency } from '../../composables/usePlatformCurrency';
import { useAdminConfirm } from '../../composables/useAdminConfirm';

const props = defineProps({
    user: {
        type: Object,
        required: true,
    },
});

const { locale, t, backArrow, forwardArrow } = useAdminLocale();
const { defaultCurrency } = usePlatformCurrency();
const { confirm } = useAdminConfirm();

const page = usePage();
const permissions = computed(() => page.props.auth.user?.permissions ?? []);
const canUpdateUsers = computed(() => permissions.value.includes('users.update'));
const currentUserId = computed(() => page.props.auth.user?.id ?? null);
const canDeleteTeamMember = computed(() =>
    canUpdateUsers.value
    && !isCustomerProfile.value
    && currentUserId.value !== null
    && currentUserId.value !== props.user.id,
);
const canViewOrders = computed(() => permissions.value.includes('orders.view'));
const canViewLoyalty = computed(() => permissions.value.includes('loyalty.view'));
const canViewSupport = computed(() => permissions.value.includes('support.view'));
const activeSupportTicket = computed(() => props.user.support?.active_ticket ?? null);
const activeSupportTicketCount = computed(() => props.user.support?.active_ticket_count ?? 0);
const crm = computed(() => props.user.crm ?? {
    stats: {
        login_count: 0,
        active_session_count: 0,
        search_count: 0,
        price_alert_count: 0,
        notification_count: 0,
        unread_notification_count: 0,
        notification_log_count: 0,
        timeline_count: 0,
        ticket_count: 0,
        saved_passenger_count: 0,
        favorite_count: 0,
        ai_search_count: 0,
    },
    timeline: [],
    searches: [],
    price_alerts: [],
    notifications: [],
    notification_logs: [],
    sessions: [],
    session_history: [],
    devices: [],
    support_tickets: [],
    wallets: [],
    saved_passengers: [],
    saved_addresses: [],
    saved_vehicles: [],
    favorites: [],
    ai_searches: [],
});
const activityFilter = ref('all');
const filteredTimeline = computed(() => {
    const events = crm.value.timeline ?? [];

    if (activityFilter.value === 'all') {
        return events;
    }

    return events.filter((event) => event.category === activityFilter.value
        || (activityFilter.value === 'notification' && event.category === 'alert'));
});
const canViewCustomerWallets = computed(() => permissions.value.includes('customer-wallets.view'));
const canManageCustomerWallets = computed(() => permissions.value.includes('customer-wallets.manage'));
const isCustomerProfile = computed(() => props.user.account_type === 'customer');
const activityFilters = computed(() => {
    if (!isCustomerProfile.value) {
        return [
            { id: 'all', label: t('All') },
            { id: 'login', label: t('Logins') },
        ];
    }

    return [
        { id: 'all', label: t('All') },
        { id: 'login', label: t('Logins') },
        { id: 'search', label: t('Searches') },
        { id: 'notification', label: t('Notifications') },
        { id: 'order', label: t('Orders') },
        { id: 'support', label: t('Support') },
        { id: 'finance', label: t('Finance') },
        { id: 'profile', label: t('Profile') },
    ];
});
const directoryRoute = computed(() => (isCustomerProfile.value ? 'admin.customers.index' : 'admin.team.index'));
const directoryLabel = computed(() => (isCustomerProfile.value ? t('Back to customers') : t('Back to team')));
const profileTitle = computed(() => (isCustomerProfile.value ? 'Customer profile' : 'Team member'));
const profileDescription = computed(() => (isCustomerProfile.value
    ? 'CRM snapshot with wallet, bookings, and support in one place.'
    : 'Control Panel staff profile with roles and permissions. No customer wallet.'));
const canDepositToWallet = computed(() => isCustomerProfile.value && canManageCustomerWallets.value);
const primaryWallet = computed(() => crm.value.wallets?.[0] ?? null);

const formatWalletMoney = (amount, currency = 'LYD') => new Intl.NumberFormat(locale.value, {
    style: 'currency',
    currency,
}).format(Number(amount ?? 0));

const primaryWalletBalanceLabel = computed(() => {
    if (!primaryWallet.value) {
        return formatWalletMoney(0, defaultCurrency.value);
    }

    return formatWalletMoney(primaryWallet.value.balance, primaryWallet.value.currency);
});
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
    if (!isCustomerProfile.value) {
        return [
            { id: 'overview', label: t('Overview'), count: 0 },
            { id: 'activity', label: t('Activity'), count: crm.value.stats.timeline_count },
            { id: 'sessions', label: t('Sessions'), count: crm.value.stats.login_count },
            { id: 'access', label: t('Access'), count: props.user.permissions.length },
        ];
    }

    const tabs = [
        { id: 'overview', label: t('Overview'), count: 0 },
        { id: 'activity', label: t('Activity'), count: crm.value.stats.timeline_count },
        { id: 'searches', label: t('Searches'), count: crm.value.stats.search_count + crm.value.stats.price_alert_count },
        { id: 'notifications', label: t('Notifications'), count: crm.value.stats.notification_count },
        { id: 'sessions', label: t('Sessions'), count: crm.value.stats.login_count },
        { id: 'orders', label: t('Orders'), count: props.user.recent_orders.length },
        { id: 'finance', label: t('Finance'), count: props.user.financial_transactions.length + crm.value.wallets.length },
        { id: 'profile', label: t('Travel profile'), count: crm.value.stats.saved_passenger_count + crm.value.stats.favorite_count },
    ];

    if (canViewSupport.value) {
        tabs.push({ id: 'support', label: t('Support'), count: activeSupportTicketCount.value });
    }

    if (canViewLoyalty.value) {
        tabs.push({ id: 'loyalty', label: t('Loyalty'), count: loyalty.value.history.length });
    }

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

const startAddMoney = (walletId = null) => {
    if (walletId) {
        router.visit(`${route('admin.customer-wallets.show', walletId)}?action=add-money`);

        return;
    }

    const existingWalletId = crm.value.wallets?.[0]?.id ?? null;

    if (existingWalletId) {
        router.visit(`${route('admin.customer-wallets.show', existingWalletId)}?action=add-money`);

        return;
    }

    router.post(route('admin.users.customer-wallet.add-money', props.user.id));
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

const toggleStatus = async () => {
    const actionLabel = props.user.is_active ? t('deactivate') : t('activate');

    if (!await confirm({
        title: 'Confirm action',
        message: t('Do you want to :action :name?', { action: actionLabel, name: props.user.full_name }),
        confirmLabel: 'Confirm',
    })) {
        return;
    }

    router.post(route('admin.users.toggle-status', props.user.id), {}, {
        preserveScroll: true,
    });
};

const deleteTeamMember = async () => {
    if (!await confirm({
        title: t('Delete team member'),
        message: t('Permanently delete :name from the team? This cannot be undone.', { name: props.user.full_name }),
        confirmLabel: 'Delete',
        variant: 'danger',
    })) {
        return;
    }

    router.delete(route('admin.users.destroy', props.user.id));
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
    <Head :title="`${t(isCustomerProfile ? 'Customer' : 'Team member')} ${user.full_name}`" />

    <AdminLayout
        :title="profileTitle"
        :description="profileDescription"
    >
        <section class="space-y-6">
            <div class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 bg-[radial-gradient(circle_at_top_left,_rgba(6,182,212,0.10),_transparent_38%),linear-gradient(180deg,_#ffffff,_#f8fafc)] px-6 py-6">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.25em] text-cyan-700">
                                {{ t(isCustomerProfile ? 'Customer Workspace' : 'Team Workspace') }}
                            </p>
                            <h2 class="mt-2 text-3xl font-semibold text-slate-950">{{ user.full_name }}</h2>
                            <div class="mt-3 flex flex-wrap items-center gap-3">
                                <UserStatusBadge :active="user.is_active" />
                                <AccountTypeBadge :account-type="user.account_type" />
                                <RoleBadge v-if="!isCustomerProfile" :role="user.role" />
                            </div>
                            <p class="mt-4 max-w-2xl text-sm leading-6 text-slate-600">
                                {{ t(isCustomerProfile
                                    ? 'Every search, login, notification, booking, and profile action for this customer is collected in one CRM workspace.'
                                    : 'Roles and permissions for this Control Panel account. Customer wallet and travel CRM are not used here.') }}
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-3">
                            <Link
                                :href="route(directoryRoute)"
                                class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                            >
                                {{ backArrow }} {{ directoryLabel }}
                            </Link>
                            <Link
                                v-if="canUpdateUsers && !isCustomerProfile"
                                :href="route('admin.users.edit', user.id)"
                                class="rounded-2xl border border-cyan-200 bg-cyan-50 px-4 py-3 text-sm font-medium text-cyan-700 transition hover:bg-cyan-100"
                            >
                                {{ t('Edit user') }}
                            </Link>
                            <Link
                                v-if="canUpdateUsers && isCustomerProfile"
                                :href="route('admin.customers.edit', user.id)"
                                class="rounded-2xl border border-cyan-200 bg-cyan-50 px-4 py-3 text-sm font-medium text-cyan-700 transition hover:bg-cyan-100"
                            >
                                {{ t('Edit identity') }}
                            </Link>
                            <button
                                v-if="canUpdateUsers"
                                type="button"
                                class="rounded-2xl bg-slate-950 px-4 py-3 text-sm font-medium text-white transition hover:bg-slate-800"
                                @click="toggleStatus"
                            >
                                {{ user.is_active ? t('Deactivate account') : t('Activate account') }}
                            </button>
                            <button
                                v-if="canDeleteTeamMember"
                                type="button"
                                class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700 transition hover:bg-rose-100"
                                @click="deleteTeamMember"
                            >
                                {{ t('Delete team member') }}
                            </button>
                        </div>
                    </div>

                    <div class="mt-5 grid gap-3 sm:grid-cols-2" :class="isCustomerProfile ? 'xl:grid-cols-6' : 'xl:grid-cols-3'">
                        <div class="rounded-[1.6rem] border border-slate-200 bg-white px-4 py-4 shadow-sm">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ t('Logins') }}</p>
                            <p class="mt-2 text-2xl font-semibold text-slate-950">{{ crm.stats.login_count }}</p>
                            <p class="mt-2 text-sm text-slate-600">{{ t('Recorded sign-ins and issued sessions.') }}</p>
                        </div>
                        <div v-if="isCustomerProfile" class="rounded-[1.6rem] border border-slate-200 bg-white px-4 py-4 shadow-sm">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ t('Searches') }}</p>
                            <p class="mt-2 text-2xl font-semibold text-slate-950">{{ crm.stats.search_count }}</p>
                            <p class="mt-2 text-sm text-slate-600">{{ t('Flight searches and AI travel lookups.') }}</p>
                        </div>
                        <div v-if="isCustomerProfile" class="rounded-[1.6rem] border border-slate-200 bg-white px-4 py-4 shadow-sm">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ t('Notifications') }}</p>
                            <p class="mt-2 text-2xl font-semibold text-slate-950">{{ crm.stats.notification_count }}</p>
                            <p class="mt-2 text-sm text-slate-600">{{ t(':count unread in the passenger inbox.', { count: crm.stats.unread_notification_count }) }}</p>
                        </div>
                        <div v-if="isCustomerProfile" class="rounded-[1.6rem] border border-slate-200 bg-white px-4 py-4 shadow-sm">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ t('Orders') }}</p>
                            <p class="mt-2 text-2xl font-semibold text-slate-950">{{ user.recent_orders.length }}</p>
                            <p class="mt-2 text-sm text-slate-600">{{ t('Latest bookings on this customer profile.') }}</p>
                        </div>
                        <div class="rounded-[1.6rem] border border-slate-200 bg-white px-4 py-4 shadow-sm">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ t('Activity') }}</p>
                            <p class="mt-2 text-2xl font-semibold text-slate-950">{{ crm.stats.timeline_count }}</p>
                            <p class="mt-2 text-sm text-slate-600">{{ t(isCustomerProfile ? 'Unified timeline of every tracked customer action.' : 'Sign-in history and staff activity for this account.') }}</p>
                        </div>
                        <div v-if="isCustomerProfile" class="rounded-[1.6rem] border border-slate-200 bg-white px-4 py-4 shadow-sm">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ t('Support') }}</p>
                            <p class="mt-2 text-2xl font-semibold text-slate-950">{{ crm.stats.ticket_count || activeSupportTicketCount }}</p>
                            <p class="mt-2 text-sm text-slate-600">{{ t('All support tickets opened by this customer.') }}</p>
                        </div>
                        <div v-else class="rounded-[1.6rem] border border-slate-200 bg-white px-4 py-4 shadow-sm">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ t('Access') }}</p>
                            <p class="mt-2 text-2xl font-semibold text-slate-950">{{ user.permissions.length }}</p>
                            <p class="mt-2 text-sm text-slate-600">{{ t('Resolved permissions for this Control Panel account.') }}</p>
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
                        <div class="flex items-start justify-between gap-3">
                            <h3 class="text-lg font-semibold text-slate-950">{{ t('Personal details') }}</h3>
                            <Link
                                v-if="canUpdateUsers && isCustomerProfile"
                                :href="route('admin.customers.edit', user.id)"
                                class="text-sm font-medium text-cyan-700 hover:text-cyan-800"
                            >
                                {{ t('Edit identity') }}
                            </Link>
                        </div>
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
                            <div v-if="!isCustomerProfile">
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
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{{ t('Login count') }}</dt>
                                <dd class="mt-2 text-sm text-slate-900">{{ crm.stats.login_count }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{{ t('Preferred language') }}</dt>
                                <dd class="mt-2 text-sm text-slate-900">{{ user.preferred_locale ? formatValueLabel(user.preferred_locale) : t('Not provided') }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{{ t('Phone verification') }}</dt>
                                <dd class="mt-2 text-sm text-slate-900">{{ user.phone_verified_at ? formatDateTime(user.phone_verified_at) : t('Not verified') }}</dd>
                            </div>
                        </dl>
                    </div>

                    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h3 class="text-lg font-semibold text-slate-950">{{ t('Workspace shortcuts') }}</h3>
                        <div class="mt-5 grid gap-3 sm:grid-cols-2">
                            <button
                                type="button"
                                class="flex items-center justify-between rounded-2xl border border-slate-200 px-4 py-3 text-left text-sm text-slate-700 transition hover:bg-slate-50"
                                @click="changeTab('activity')"
                            >
                                <span>{{ t('Open activity feed') }}</span>
                                <span class="font-medium text-slate-950">{{ crm.stats.timeline_count }}</span>
                            </button>
                            <button
                                v-if="isCustomerProfile"
                                type="button"
                                class="flex items-center justify-between rounded-2xl border border-slate-200 px-4 py-3 text-left text-sm text-slate-700 transition hover:bg-slate-50"
                                @click="changeTab('searches')"
                            >
                                <span>{{ t('Open searches workspace') }}</span>
                                <span class="font-medium text-slate-950">{{ crm.stats.search_count }}</span>
                            </button>
                            <button
                                v-if="isCustomerProfile"
                                type="button"
                                class="flex items-center justify-between rounded-2xl border border-slate-200 px-4 py-3 text-left text-sm text-slate-700 transition hover:bg-slate-50"
                                @click="changeTab('notifications')"
                            >
                                <span>{{ t('Open notifications workspace') }}</span>
                                <span class="font-medium text-slate-950">{{ crm.stats.notification_count }}</span>
                            </button>
                            <button
                                type="button"
                                class="flex items-center justify-between rounded-2xl border border-slate-200 px-4 py-3 text-left text-sm text-slate-700 transition hover:bg-slate-50"
                                @click="changeTab('sessions')"
                            >
                                <span>{{ t('Open sessions workspace') }}</span>
                                <span class="font-medium text-slate-950">{{ crm.stats.login_count }}</span>
                            </button>
                            <button
                                v-if="isCustomerProfile"
                                type="button"
                                class="flex items-center justify-between rounded-2xl border border-slate-200 px-4 py-3 text-left text-sm text-slate-700 transition hover:bg-slate-50"
                                @click="changeTab('orders')"
                            >
                                <span>{{ t('Open orders workspace') }}</span>
                                <span class="font-medium text-slate-950">{{ user.recent_orders.length }}</span>
                            </button>
                            <button
                                v-if="isCustomerProfile"
                                type="button"
                                class="flex items-center justify-between rounded-2xl border border-slate-200 px-4 py-3 text-left text-sm text-slate-700 transition hover:bg-slate-50"
                                @click="changeTab('finance')"
                            >
                                <span>{{ t('Open finance workspace') }}</span>
                                <span class="font-medium text-slate-950">{{ walletBalanceLabel }}</span>
                            </button>
                            <button
                                v-if="!isCustomerProfile"
                                type="button"
                                class="flex items-center justify-between rounded-2xl border border-slate-200 px-4 py-3 text-left text-sm text-slate-700 transition hover:bg-slate-50"
                                @click="changeTab('access')"
                            >
                                <span>{{ t('Open access workspace') }}</span>
                                <span class="font-medium text-slate-950">{{ user.permissions.length }}</span>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="space-y-6">
                    <div
                        v-if="isCustomerProfile && (canDepositToWallet || canViewCustomerWallets)"
                        class="overflow-hidden rounded-3xl border border-emerald-100 bg-white shadow-sm"
                    >
                        <div class="bg-gradient-to-br from-emerald-50 via-white to-slate-50 px-6 py-6">
                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-700">{{ t('Wallet') }}</p>
                            <p class="mt-3 text-3xl font-semibold tracking-tight text-slate-950">
                                {{ primaryWalletBalanceLabel }}
                            </p>
                            <p class="mt-2 text-sm text-slate-500">
                                {{ primaryWallet ? `${primaryWallet.wallet_number} · ${formatValueLabel(primaryWallet.status)}` : t('No wallet yet') }}
                            </p>
                            <div class="mt-5 flex flex-col gap-2">
                                <button
                                    v-if="canDepositToWallet"
                                    type="button"
                                    class="inline-flex items-center justify-center rounded-2xl bg-emerald-700 px-4 py-3 text-sm font-semibold text-white transition hover:bg-emerald-800"
                                    @click="startAddMoney(primaryWallet?.id)"
                                >
                                    {{ t('Deposit') }}
                                </button>
                                <Link
                                    v-if="canViewCustomerWallets && primaryWallet"
                                    :href="route('admin.customer-wallets.show', primaryWallet.id)"
                                    class="inline-flex items-center justify-center rounded-2xl px-4 py-2 text-sm font-medium text-slate-600 transition hover:bg-white hover:text-slate-950"
                                >
                                    {{ t('View ledger') }}
                                </Link>
                            </div>
                        </div>
                    </div>

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
                            <div v-if="isCustomerProfile && canViewLoyalty" class="rounded-2xl bg-slate-50 p-4">
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

            <div v-else-if="activeTab === 'finance'" class="space-y-6">
                <div v-if="crm.wallets.length" class="space-y-4">
                    <div
                        v-for="wallet in crm.wallets"
                        :key="wallet.id"
                        class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm"
                    >
                        <div class="flex flex-col gap-5 p-6 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-700">{{ t('Wallet') }}</p>
                                <p class="mt-2 text-3xl font-semibold text-slate-950">{{ formatWalletMoney(wallet.balance, wallet.currency) }}</p>
                                <p class="mt-2 text-sm text-slate-500">{{ wallet.wallet_number }} · {{ formatValueLabel(wallet.status) }}</p>
                            </div>
                            <div class="flex flex-col gap-2 sm:items-end">
                                <button
                                    v-if="canManageCustomerWallets"
                                    type="button"
                                    class="inline-flex items-center justify-center rounded-2xl bg-emerald-700 px-5 py-3 text-sm font-semibold text-white transition hover:bg-emerald-800"
                                    @click="startAddMoney(wallet.id)"
                                >
                                    {{ t('Deposit') }}
                                </button>
                                <Link
                                    v-if="canViewCustomerWallets"
                                    :href="route('admin.customer-wallets.show', wallet.id)"
                                    class="text-center text-sm font-medium text-slate-500 hover:text-slate-800"
                                >
                                    {{ t('View ledger') }}
                                </Link>
                            </div>
                        </div>
                        <div class="space-y-3 border-t border-slate-100 px-6 py-5">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">{{ t('Recent activity') }}</p>
                            <div
                                v-for="transaction in wallet.transactions"
                                :key="transaction.id"
                                class="flex flex-wrap items-start justify-between gap-4 rounded-2xl bg-slate-50 px-4 py-3"
                            >
                                <div>
                                    <p class="font-medium text-slate-900">{{ transaction.summary || formatValueLabel(transaction.type) }}</p>
                                    <p v-if="transaction.note" class="mt-1 text-sm text-slate-500">{{ transaction.note }}</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm font-semibold text-slate-950">{{ transaction.signed_amount }} {{ transaction.currency }}</p>
                                    <p class="mt-1 text-xs text-slate-400">{{ formatDateTime(transaction.created_at) }}</p>
                                </div>
                            </div>
                            <p v-if="wallet.transactions.length === 0" class="text-sm text-slate-500">{{ t('No wallet ledger rows yet.') }}</p>
                        </div>
                    </div>
                </div>

                <div
                    v-else-if="canManageCustomerWallets"
                    class="rounded-3xl border border-dashed border-emerald-200 bg-emerald-50/40 p-6"
                >
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-700">{{ t('Wallet') }}</p>
                    <p class="mt-3 text-lg font-semibold text-slate-950">{{ t('No wallet yet') }}</p>
                    <p class="mt-2 max-w-md text-sm text-slate-600">
                        {{ t('Deposit to create this customer wallet and record the first top-up.') }}
                    </p>
                    <button
                        type="button"
                        class="mt-5 inline-flex items-center justify-center rounded-2xl bg-emerald-700 px-5 py-3 text-sm font-semibold text-white transition hover:bg-emerald-800"
                        @click="startAddMoney()"
                    >
                        {{ t('Deposit') }}
                    </button>
                </div>

                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-slate-950">{{ t('Financial transactions') }}</h3>
                    <div class="mt-5 rounded-2xl bg-slate-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{{ t('Order payments total') }}</p>
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
            </div>

            <div v-else-if="activeTab === 'activity'" class="space-y-4">
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="filter in activityFilters"
                        :key="filter.id"
                        type="button"
                        class="rounded-full px-4 py-2 text-sm font-medium transition"
                        :class="activityFilter === filter.id ? 'bg-slate-950 text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50'"
                        @click="activityFilter = filter.id"
                    >
                        {{ filter.label }}
                    </button>
                </div>
                <SystemTimeline
                    :events="filteredTimeline"
                    title="Customer activity"
                    description="Every search, login, notification, booking, wallet movement, and profile action recorded for this customer."
                    empty-text="No tracked activity recorded for this user yet."
                />
            </div>

            <div v-else-if="activeTab === 'searches'" class="grid gap-6 xl:grid-cols-2">
                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-slate-950">{{ t('Flight searches') }}</h3>
                    <p class="mt-2 text-sm text-slate-600">{{ t('Routes this customer looked up, including abandoned and converted searches.') }}</p>
                    <div class="mt-5 space-y-4">
                        <div
                            v-for="search in crm.searches"
                            :key="search.id"
                            class="rounded-2xl border border-slate-200 p-4"
                        >
                            <div class="flex flex-wrap items-start justify-between gap-4">
                                <div>
                                    <p class="font-medium text-slate-900">{{ search.route }}</p>
                                    <p class="mt-2 text-sm text-slate-600">
                                        {{ search.departure_date || t('No date') }}
                                        <span v-if="search.return_date"> {{ forwardArrow }} {{ search.return_date }}</span>
                                        <span v-if="search.last_seen_price"> · {{ search.last_seen_price }} {{ search.currency }}</span>
                                    </p>
                                </div>
                                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-slate-700">
                                    {{ formatValueLabel(search.status) }}
                                </span>
                            </div>
                            <p class="mt-3 text-xs uppercase tracking-[0.16em] text-slate-500">{{ formatDateTime(search.last_searched_at) }}</p>
                        </div>
                        <p v-if="crm.searches.length === 0" class="text-sm text-slate-500">{{ t('No searches recorded for this customer yet.') }}</p>
                    </div>
                </div>

                <div class="space-y-6">
                    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h3 class="text-lg font-semibold text-slate-950">{{ t('Price alerts') }}</h3>
                        <div class="mt-5 space-y-4">
                            <div
                                v-for="alert in crm.price_alerts"
                                :key="alert.id"
                                class="rounded-2xl border border-slate-200 p-4"
                            >
                                <div class="flex flex-wrap items-start justify-between gap-4">
                                    <div>
                                        <p class="font-medium text-slate-900">{{ alert.route }}</p>
                                        <p class="mt-2 text-sm text-slate-600">{{ t('Target') }}: {{ alert.target_price }} {{ alert.currency }}</p>
                                    </div>
                                    <span class="rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em]" :class="alert.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'">
                                        {{ alert.is_active ? t('Active') : t('Inactive') }}
                                    </span>
                                </div>
                                <p class="mt-3 text-xs uppercase tracking-[0.16em] text-slate-500">
                                    {{ alert.last_triggered_at ? t('Last hit :date', { date: formatDateTime(alert.last_triggered_at) }) : t('Not triggered yet') }}
                                </p>
                            </div>
                            <p v-if="crm.price_alerts.length === 0" class="text-sm text-slate-500">{{ t('No price alerts set by this customer.') }}</p>
                        </div>
                    </div>

                    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h3 class="text-lg font-semibold text-slate-950">{{ t('AI travel searches') }}</h3>
                        <div class="mt-5 space-y-4">
                            <div
                                v-for="log in crm.ai_searches"
                                :key="log.id"
                                class="rounded-2xl border border-slate-200 p-4"
                            >
                                <p class="font-medium text-slate-900">{{ formatValueLabel(log.intent || log.mode || 'AI') }}</p>
                                <p class="mt-2 text-sm text-slate-600">{{ log.message || t('No prompt stored.') }}</p>
                                <p class="mt-3 text-xs uppercase tracking-[0.16em] text-slate-500">{{ formatDateTime(log.created_at) }}</p>
                            </div>
                            <p v-if="crm.ai_searches.length === 0" class="text-sm text-slate-500">{{ t('No AI travel searches recorded yet.') }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else-if="activeTab === 'notifications'" class="grid gap-6 xl:grid-cols-2">
                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-slate-950">{{ t('Passenger inbox') }}</h3>
                    <p class="mt-2 text-sm text-slate-600">{{ t('What was sent to this customer and whether they opened it.') }}</p>
                    <div class="mt-5 space-y-4">
                        <div
                            v-for="notification in crm.notifications"
                            :key="notification.id"
                            class="rounded-2xl border border-slate-200 p-4"
                        >
                            <div class="flex flex-wrap items-start justify-between gap-4">
                                <div>
                                    <p class="font-medium text-slate-900">{{ notification.title || formatValueLabel(notification.template_code) }}</p>
                                    <p class="mt-2 text-sm text-slate-600">{{ notification.message }}</p>
                                </div>
                                <span class="rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em]" :class="notification.is_unread ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700'">
                                    {{ notification.is_unread ? t('Unread') : t('Read') }}
                                </span>
                            </div>
                            <p class="mt-3 text-xs uppercase tracking-[0.16em] text-slate-500">
                                {{ formatValueLabel(notification.category) }} · {{ formatDateTime(notification.created_at) }}
                            </p>
                        </div>
                        <p v-if="crm.notifications.length === 0" class="text-sm text-slate-500">{{ t('No notifications were sent to this customer yet.') }}</p>
                    </div>
                </div>

                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-slate-950">{{ t('Delivery log') }}</h3>
                    <p class="mt-2 text-sm text-slate-600">{{ t('Push, email, and in-app delivery attempts.') }}</p>
                    <div class="mt-5 space-y-4">
                        <div
                            v-for="log in crm.notification_logs"
                            :key="log.id"
                            class="rounded-2xl border border-slate-200 p-4"
                        >
                            <div class="flex flex-wrap items-start justify-between gap-4">
                                <div>
                                    <p class="font-medium text-slate-900">{{ formatValueLabel(log.template_code) }}</p>
                                    <p class="mt-2 text-sm text-slate-600">{{ formatValueLabel(log.channel) }} · {{ log.subject || t('No subject') }}</p>
                                </div>
                                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-slate-700">
                                    {{ formatValueLabel(log.status) }}
                                </span>
                            </div>
                            <p class="mt-3 text-xs uppercase tracking-[0.16em] text-slate-500">{{ formatDateTime(log.sent_at || log.created_at) }}</p>
                        </div>
                        <p v-if="crm.notification_logs.length === 0" class="text-sm text-slate-500">{{ t('No delivery attempts recorded yet.') }}</p>
                    </div>
                </div>
            </div>

            <div v-else-if="activeTab === 'sessions'" class="grid gap-6 xl:grid-cols-2">
                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-slate-950">{{ t('Sign-ins') }}</h3>
                    <div class="mt-5 grid gap-4 sm:grid-cols-2">
                        <div class="rounded-2xl bg-slate-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{{ t('Login count') }}</p>
                            <p class="mt-2 text-2xl font-semibold text-slate-950">{{ crm.stats.login_count }}</p>
                        </div>
                        <div class="rounded-2xl bg-slate-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{{ t('Last login') }}</p>
                            <p class="mt-2 text-sm font-medium text-slate-950">{{ formatDateTime(user.last_login_at) }}</p>
                        </div>
                    </div>
                    <div class="mt-5 space-y-4">
                        <div
                            v-for="session in crm.session_history"
                            :key="session.id"
                            class="rounded-2xl border border-slate-200 p-4"
                        >
                            <div class="flex flex-wrap items-start justify-between gap-4">
                                <div>
                                    <p class="font-medium text-slate-900">{{ session.device_name || t('Unknown device') }}</p>
                                    <p class="mt-2 text-sm text-slate-600">{{ session.revoked_at ? t('Revoked') : t('Active or expired') }}</p>
                                </div>
                                <p class="text-xs uppercase tracking-[0.16em] text-slate-500">{{ formatDateTime(session.created_at) }}</p>
                            </div>
                        </div>
                        <p v-if="crm.session_history.length === 0" class="text-sm text-slate-500">{{ t('No session history recorded yet.') }}</p>
                    </div>
                </div>

                <div class="space-y-6">
                    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h3 class="text-lg font-semibold text-slate-950">{{ t('Active sessions') }}</h3>
                        <div class="mt-5 space-y-4">
                            <div
                                v-for="session in crm.sessions"
                                :key="session.id"
                                class="rounded-2xl border border-slate-200 p-4"
                            >
                                <p class="font-medium text-slate-900">{{ session.device_name || t('Unknown device') }}</p>
                                <p class="mt-2 text-sm text-slate-600">{{ t('Last used') }}: {{ formatDateTime(session.last_used_at) }}</p>
                            </div>
                            <p v-if="crm.sessions.length === 0" class="text-sm text-slate-500">{{ t('No active app sessions.') }}</p>
                        </div>
                    </div>

                    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h3 class="text-lg font-semibold text-slate-950">{{ t('Push devices') }}</h3>
                        <div class="mt-5 space-y-4">
                            <div
                                v-for="device in crm.devices"
                                :key="device.id"
                                class="rounded-2xl border border-slate-200 p-4"
                            >
                                <div class="flex flex-wrap items-start justify-between gap-4">
                                    <div>
                                        <p class="font-medium text-slate-900">{{ formatValueLabel(device.platform || device.channel) }}</p>
                                        <p class="mt-2 text-sm text-slate-600">{{ device.app_version || t('No app version') }}</p>
                                    </div>
                                    <span class="rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em]" :class="device.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'">
                                        {{ device.is_active ? t('Active') : t('Inactive') }}
                                    </span>
                                </div>
                                <p class="mt-3 text-xs uppercase tracking-[0.16em] text-slate-500">{{ t('Last seen') }}: {{ formatDateTime(device.last_seen_at) }}</p>
                            </div>
                            <p v-if="crm.devices.length === 0" class="text-sm text-slate-500">{{ t('No push devices registered.') }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else-if="activeTab === 'profile'" class="grid gap-6 xl:grid-cols-2">
                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-slate-950">{{ t('Saved passengers') }}</h3>
                    <div class="mt-5 space-y-4">
                        <div
                            v-for="passenger in crm.saved_passengers"
                            :key="passenger.id"
                            class="rounded-2xl border border-slate-200 p-4"
                        >
                            <div class="flex flex-wrap items-start justify-between gap-4">
                                <div>
                                    <p class="font-medium text-slate-900">{{ passenger.name }}</p>
                                    <p class="mt-2 text-sm text-slate-600">{{ formatValueLabel(passenger.type) }} · {{ passenger.nationality || t('No nationality') }}</p>
                                </div>
                                <span v-if="passenger.is_default" class="rounded-full bg-cyan-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-cyan-700">{{ t('Default') }}</span>
                            </div>
                        </div>
                        <p v-if="crm.saved_passengers.length === 0" class="text-sm text-slate-500">{{ t('No saved passengers yet.') }}</p>
                    </div>
                </div>

                <div class="space-y-6">
                    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h3 class="text-lg font-semibold text-slate-950">{{ t('Favorites') }}</h3>
                        <div class="mt-5 space-y-4">
                            <div
                                v-for="favorite in crm.favorites"
                                :key="favorite.id"
                                class="rounded-2xl border border-slate-200 p-4"
                            >
                                <p class="font-medium text-slate-900">{{ favorite.title }}</p>
                                <p class="mt-2 text-sm text-slate-600">{{ formatValueLabel(favorite.type) }} · {{ formatValueLabel(favorite.status) }}</p>
                            </div>
                            <p v-if="crm.favorites.length === 0" class="text-sm text-slate-500">{{ t('No favorites saved yet.') }}</p>
                        </div>
                    </div>

                    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h3 class="text-lg font-semibold text-slate-950">{{ t('Saved addresses') }}</h3>
                        <div class="mt-5 space-y-4">
                            <div
                                v-for="address in crm.saved_addresses"
                                :key="address.id"
                                class="rounded-2xl border border-slate-200 p-4"
                            >
                                <p class="font-medium text-slate-900">{{ address.title || t('Address') }}</p>
                                <p class="mt-2 text-sm text-slate-600">{{ address.address }}</p>
                            </div>
                            <p v-if="crm.saved_addresses.length === 0" class="text-sm text-slate-500">{{ t('No saved addresses yet.') }}</p>
                        </div>
                    </div>

                    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h3 class="text-lg font-semibold text-slate-950">{{ t('Saved vehicles') }}</h3>
                        <div class="mt-5 space-y-4">
                            <div
                                v-for="vehicle in crm.saved_vehicles"
                                :key="vehicle.id"
                                class="rounded-2xl border border-slate-200 p-4"
                            >
                                <p class="font-medium text-slate-900">{{ vehicle.label }}</p>
                                <p class="mt-2 text-sm text-slate-600">{{ formatValueLabel(vehicle.type) }}</p>
                            </div>
                            <p v-if="crm.saved_vehicles.length === 0" class="text-sm text-slate-500">{{ t('No saved vehicles yet.') }}</p>
                        </div>
                    </div>
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

                <div v-if="crm.support_tickets.length" class="mt-6 space-y-3">
                    <h4 class="text-sm font-semibold text-slate-950">{{ t('All tickets') }}</h4>
                    <Link
                        v-for="ticket in crm.support_tickets"
                        :key="ticket.id"
                        :href="route('admin.support.show', ticket.id)"
                        class="block rounded-2xl border border-slate-200 p-4 transition hover:border-cyan-300 hover:bg-cyan-50/40"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <p class="font-medium text-slate-900">{{ ticket.ticket_number }} · {{ ticket.subject }}</p>
                                <p class="mt-2 text-sm text-slate-600">{{ formatValueLabel(ticket.category) }} / {{ formatValueLabel(ticket.priority) }}</p>
                            </div>
                            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-slate-700">
                                {{ formatValueLabel(ticket.status) }}
                            </span>
                        </div>
                    </Link>
                </div>
            </div>

            <div v-else-if="activeTab === 'access'" class="space-y-6">
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