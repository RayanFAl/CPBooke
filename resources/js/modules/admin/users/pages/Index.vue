<script setup>
import AccountTypeBadge from '../components/AccountTypeBadge.vue';
import AdminLayout from '../../layouts/AdminLayout.vue';
import RoleBadge from '../components/RoleBadge.vue';
import UserStatusBadge from '../components/UserStatusBadge.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';
import { useAdminLocale } from '../../composables/useAdminLocale';

const props = defineProps({
    users: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        required: true,
    },
    roles: {
        type: Array,
        required: true,
    },
});

const page = usePage();
const { locale, t } = useAdminLocale();
const usersCountLabel = computed(() => `${users.value.total} ${t(users.value.total === 1 ? 'total user' : 'total users')}`);

const filterForm = reactive({
    search: props.filters.email ?? props.filters.phone ?? props.filters.name ?? '',
    status: props.filters.status ?? '',
    country: '',
    role: props.filters.role ?? '',
    created_from: '',
    created_to: '',
});

const localUsers = ref([]);

const cloneUser = (user) => ({ ...user });

watch(
    () => props.users.data,
    (users) => {
        localUsers.value = users.map(cloneUser);
    },
    { immediate: true },
);

const permissions = computed(() => page.props.auth.user?.permissions ?? []);

const canCreateUsers = computed(() => permissions.value.includes('users.create'));

const roleLabelMap = computed(() =>
    Object.fromEntries(props.roles.map((role) => [role.name, role.label])),
);

const countryOptions = computed(() => {
    return [...new Set(localUsers.value.map((user) => user.country).filter(Boolean))].sort((left, right) =>
        left.localeCompare(right),
    );
});

const summaryMetrics = computed(() => {
    const total = props.users.total ?? localUsers.value.length;
    const active = localUsers.value.filter((user) => user.is_active).length;
    const inactive = localUsers.value.filter((user) => !user.is_active).length;
    const admins = localUsers.value.filter((user) => user.account_type === 'admin').length;

    return [
        { label: t('Total users'), value: total },
        { label: t('Active on page'), value: active },
        { label: t('Inactive on page'), value: inactive },
        { label: t('Admins on page'), value: admins },
    ];
});

const matchesDateRange = (user) => {
    if (!filterForm.created_from && !filterForm.created_to) {
        return true;
    }

    if (!user.created_at) {
        return false;
    }

    const createdDate = user.created_at.slice(0, 10);

    if (filterForm.created_from && createdDate < filterForm.created_from) {
        return false;
    }

    if (filterForm.created_to && createdDate > filterForm.created_to) {
        return false;
    }

    return true;
};

const displayedUsers = computed(() => {
    return localUsers.value.filter((user) => {
        if (filterForm.country && user.country !== filterForm.country) {
            return false;
        }

        return matchesDateRange(user);
    });
});

const hasActiveFilters = computed(() =>
    Object.values(filterForm).some((value) => value !== ''),
);

const activeFilterChips = computed(() => {
    const chips = [];

    if (filterForm.search) {
        chips.push(`${t('Search')}: ${filterForm.search}`);
    }

    if (filterForm.status) {
        chips.push(`${t('Status')}: ${t(filterForm.status === 'active' ? 'Active' : 'Inactive')}`);
    }

    if (filterForm.country) {
        chips.push(`${t('Country')}: ${filterForm.country}`);
    }

    if (filterForm.role) {
        chips.push(`${t('Role')}: ${t(filterForm.role === 'unassigned' ? 'No role' : (roleLabelMap.value[filterForm.role] ?? filterForm.role))}`);
    }

    if (filterForm.created_from || filterForm.created_to) {
        chips.push(`${t('Created')}: ${filterForm.created_from || t('Any')} ${t('to')} ${filterForm.created_to || t('Any')}`);
    }

    return chips;
});

const smartSearchPayload = () => {
    const payload = {};
    const search = filterForm.search.trim();

    if (!search) {
        return payload;
    }

    if (search.includes('@')) {
        payload.email = search;

        return payload;
    }

    if (/^[+()\d\s-]{4,}$/.test(search)) {
        payload.phone = search;

        return payload;
    }

    payload.name = search;

    return payload;
};

const applyFilters = () => {
    router.get(
        route('admin.users.index'),
        {
            ...smartSearchPayload(),
            ...(filterForm.status ? { status: filterForm.status } : {}),
            ...(filterForm.role ? { role: filterForm.role } : {}),
        },
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        },
    );
};

const resetFilters = () => {
    filterForm.search = '';
    filterForm.status = '';
    filterForm.country = '';
    filterForm.role = '';
    filterForm.created_from = '';
    filterForm.created_to = '';

    router.get(route('admin.users.index'), {}, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
};

const formatDateTime = (value) => {
    if (!value) {
        return t('Never');
    }

    return new Intl.DateTimeFormat(locale.value, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
};

const relativeTime = (value) => {
    if (!value) {
        return t('Never');
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

const openUserPage = (user) => {
    router.visit(route('admin.users.show', user.id), {
        preserveScroll: true,
    });
};

const users = computed(() => props.users);
</script>

<template>
    <Head :title="t('Users')" />

    <AdminLayout
        title="Users"
        description="Browse the users directory with compact filters and open each profile directly from the table."
    >
        <section class="space-y-6">
            <div class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white p-6 text-slate-900 shadow-sm">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.25em] text-cyan-700">
                            {{ t('Admin Smart Grid') }}
                        </p>
                        <h2 class="mt-2 text-2xl font-semibold text-slate-950">{{ t('Users workspace') }}</h2>
                        <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                            {{ t('Scan account health at a glance, then open the full user profile directly from any row in the table.') }}
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <div class="rounded-2xl bg-slate-950 px-4 py-3 text-sm text-white">
                            {{ usersCountLabel }}
                        </div>
                        <Link
                            v-if="canCreateUsers"
                            :href="route('admin.users.create')"
                            class="inline-flex items-center justify-center rounded-2xl bg-cyan-600 px-4 py-3 text-sm font-medium text-white transition hover:bg-cyan-700"
                        >
                            {{ t('Create admin user') }}
                        </Link>
                    </div>
                </div>

                <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <article
                        v-for="metric in summaryMetrics"
                        :key="metric.label"
                        class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4"
                    >
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ metric.label }}</p>
                        <p class="mt-2 text-2xl font-semibold text-slate-950">{{ metric.value }}</p>
                    </article>
                </div>
            </div>

            <div class="sticky top-0 z-20 overflow-hidden rounded-[2rem] border border-slate-200 bg-white/95 p-3 shadow-sm backdrop-blur">
                <form class="flex items-center gap-2 overflow-x-auto pb-1" @submit.prevent="applyFilters">
                    <label class="flex h-14 min-w-[18rem] shrink-0 items-center gap-3 rounded-2xl border border-slate-200 bg-white px-4">
                        <span class="text-sm font-medium text-slate-600">{{ t('Search') }}</span>
                        <input
                            v-model="filterForm.search"
                            type="text"
                            class="min-w-0 flex-1 border-0 bg-transparent p-0 text-sm text-slate-900 outline-none placeholder:text-slate-400 focus:ring-0"
                            :placeholder="t('Name, email, or phone')"
                        >
                    </label>

                    <label class="flex h-14 min-w-[8.75rem] shrink-0 items-center gap-3 rounded-2xl border border-slate-200 bg-white px-4">
                        <span class="text-sm font-medium text-slate-600">{{ t('Status') }}</span>
                        <select
                            v-model="filterForm.status"
                            class="min-w-0 flex-1 border-0 bg-transparent p-0 text-sm text-slate-900 outline-none focus:ring-0"
                        >
                            <option value="">{{ t('All') }}</option>
                            <option value="active">{{ t('Active') }}</option>
                            <option value="inactive">{{ t('Inactive') }}</option>
                        </select>
                    </label>

                    <label class="flex h-14 min-w-[9.5rem] shrink-0 items-center gap-3 rounded-2xl border border-slate-200 bg-white px-4">
                        <span class="text-sm font-medium text-slate-600">{{ t('Country') }}</span>
                        <select
                            v-model="filterForm.country"
                            class="min-w-0 flex-1 border-0 bg-transparent p-0 text-sm text-slate-900 outline-none focus:ring-0"
                        >
                            <option value="">{{ t('All') }}</option>
                            <option v-for="country in countryOptions" :key="country" :value="country">
                                {{ country }}
                            </option>
                        </select>
                    </label>

                    <label class="flex h-14 min-w-[9rem] shrink-0 items-center gap-3 rounded-2xl border border-slate-200 bg-white px-4">
                        <span class="text-sm font-medium text-slate-600">{{ t('Role') }}</span>
                        <select
                            v-model="filterForm.role"
                            class="min-w-0 flex-1 border-0 bg-transparent p-0 text-sm text-slate-900 outline-none focus:ring-0"
                        >
                            <option value="">{{ t('All') }}</option>
                            <option v-for="role in roles" :key="role.name" :value="role.name">
                                {{ t(role.label) }}
                            </option>
                            <option value="unassigned">{{ t('No role') }}</option>
                        </select>
                    </label>

                    <div class="flex h-14 min-w-[20rem] shrink-0 items-center gap-3 rounded-2xl border border-slate-200 bg-white px-4">
                        <span class="text-sm font-medium text-slate-600">{{ t('Date range') }}</span>
                        <div class="flex min-w-0 flex-1 items-center gap-2">
                            <input
                                v-model="filterForm.created_from"
                                type="date"
                                class="min-w-0 flex-1 rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-cyan-600"
                            >
                            <span class="text-sm text-slate-300">-</span>
                            <input
                                v-model="filterForm.created_to"
                                type="date"
                                class="min-w-0 flex-1 rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-cyan-600"
                            >
                        </div>
                    </div>

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
                    <span
                        v-for="chip in activeFilterChips"
                        :key="chip"
                        class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-slate-700"
                    >
                        {{ chip }}
                    </span>
                </div>
            </div>

            <div class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr class="text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">
                                <th class="px-6 py-4">{{ t('User') }}</th>
                                <th class="px-6 py-4">{{ t('Type') }}</th>
                                <th class="px-6 py-4">{{ t('Role') }}</th>
                                <th class="px-6 py-4">{{ t('Country') }}</th>
                                <th class="px-6 py-4">{{ t('Status') }}</th>
                                <th class="px-6 py-4">{{ t('Last login') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm text-slate-700">
                            <tr
                                v-for="user in displayedUsers"
                                :key="user.id"
                                class="group cursor-pointer bg-white align-top transition duration-200 hover:bg-cyan-50/50"
                                role="link"
                                :aria-label="`${t('Open profile for')} ${user.full_name || user.email}`"
                                tabindex="0"
                                @click="openUserPage(user)"
                                @keydown.enter.prevent="openUserPage(user)"
                                @keydown.space.prevent="openUserPage(user)"
                            >
                                <td class="px-6 py-5">
                                    <div class="flex items-start gap-4">
                                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-slate-950 text-sm font-semibold uppercase tracking-[0.18em] text-white">
                                            {{ (user.full_name || user.email).slice(0, 2) }}
                                        </div>
                                        <div>
                                            <div class="font-semibold text-slate-950">{{ user.full_name }}</div>
                                            <div class="mt-1 text-sm text-slate-600">{{ user.email }}</div>
                                            <div class="mt-1 text-xs uppercase tracking-[0.18em] text-slate-400">
                                                {{ user.phone || t('No phone provided') }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-5">
                                    <AccountTypeBadge :account-type="user.account_type" />
                                </td>
                                <td class="px-6 py-5">
                                    <RoleBadge :role="user.role" />
                                </td>
                                <td class="px-6 py-5 text-slate-600">{{ user.country || t('Not set') }}</td>
                                <td class="px-6 py-5">
                                    <div class="flex items-center gap-3">
                                        <span class="inline-flex h-2.5 w-2.5 rounded-full" :class="user.is_active ? 'bg-emerald-500 shadow-[0_0_0_4px_rgba(16,185,129,0.12)]' : 'bg-slate-400 shadow-[0_0_0_4px_rgba(148,163,184,0.14)]'" />
                                        <UserStatusBadge :active="user.is_active" />
                                    </div>
                                </td>
                                <td class="px-6 py-5">
                                    <div class="font-medium text-slate-900">{{ relativeTime(user.last_login_at) }}</div>
                                    <div class="mt-1 text-xs text-slate-500">{{ formatDateTime(user.last_login_at) }}</div>
                                </td>
                            </tr>

                            <tr v-if="displayedUsers.length === 0">
                                <td colspan="6" class="px-6 py-14 text-center text-sm text-slate-500">
                                    {{ t('No users matched the current filter bar.') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex flex-col gap-4 border-t border-slate-200 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm text-slate-500">
                        {{ t('Showing') }} {{ users.from ?? 0 }} {{ t('to') }} {{ users.to ?? 0 }} {{ t('of') }} {{ users.total }} {{ t('users.') }}
                    </p>

                    <nav class="flex flex-wrap gap-2">
                        <component
                            :is="link.url ? Link : 'span'"
                            v-for="link in users.links"
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