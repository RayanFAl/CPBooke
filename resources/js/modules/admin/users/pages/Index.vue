<script setup>
import AccountTypeBadge from '../components/AccountTypeBadge.vue';
import AdminLayout from '../../layouts/AdminLayout.vue';
import RoleBadge from '../components/RoleBadge.vue';
import UserStatusBadge from '../components/UserStatusBadge.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

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

const filterForm = reactive({
    name: props.filters.name ?? '',
    email: props.filters.email ?? '',
    phone: props.filters.phone ?? '',
    account_type: props.filters.account_type ?? '',
    role: props.filters.role ?? '',
    status: props.filters.status ?? '',
});

const canCreateUsers = computed(() =>
    (page.props.auth.user?.permissions ?? []).includes('users.create'),
);

const canUpdateUsers = computed(() =>
    (page.props.auth.user?.permissions ?? []).includes('users.update'),
);

const roleLabelMap = computed(() =>
    Object.fromEntries(props.roles.map((role) => [role.name, role.label])),
);

const hasActiveFilters = computed(() =>
    Object.values(filterForm).some((value) => value !== ''),
);

const activeFilters = computed(() => {
    const filters = [];

    if (filterForm.name) {
        filters.push({ label: `Name: ${filterForm.name}` });
    }

    if (filterForm.email) {
        filters.push({ label: `Email: ${filterForm.email}` });
    }

    if (filterForm.phone) {
        filters.push({ label: `Phone: ${filterForm.phone}` });
    }

    if (filterForm.account_type) {
        filters.push({
            label: `Account type: ${filterForm.account_type === 'admin' ? 'Admin' : 'Customer'}`,
        });
    }

    if (filterForm.role) {
        filters.push({
            label: `Role: ${filterForm.role === 'unassigned' ? 'No role' : (roleLabelMap.value[filterForm.role] ?? filterForm.role)}`,
        });
    }

    if (filterForm.status) {
        filters.push({
            label: `Status: ${filterForm.status === 'active' ? 'Active' : 'Inactive'}`,
        });
    }

    return filters;
});

const serializeFilters = () => {
    const entries = Object.entries(filterForm).filter(([, value]) => value !== '');

    return Object.fromEntries(entries);
};

const applyFilters = () => {
    router.get(route('admin.users.index'), serializeFilters(), {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
};

const resetFilters = () => {
    filterForm.name = '';
    filterForm.email = '';
    filterForm.phone = '';
    filterForm.account_type = '';
    filterForm.role = '';
    filterForm.status = '';

    applyFilters();
};

const toggleStatus = (user) => {
    const actionLabel = user.is_active ? 'deactivate' : 'activate';

    if (!window.confirm(`Do you want to ${actionLabel} ${user.full_name}?`)) {
        return;
    }

    router.post(route('admin.users.toggle-status', user.id), {}, {
        preserveScroll: true,
    });
};

const formatDateTime = (value) => {
    if (!value) {
        return 'Never';
    }

    return new Intl.DateTimeFormat('en', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
};
</script>

<template>
    <Head title="Users" />

    <AdminLayout
        title="Users"
        description="Manage customers and administrative accounts clearly, while keeping account type separate from role-based access."
    >
        <section class="space-y-6">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.25em] text-cyan-700">
                            Users Directory
                        </p>
                        <h2 class="mt-2 text-2xl font-semibold text-slate-950">User accounts</h2>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">
                            Filter by account type, role, identity data, or status. Account type describes what kind of user this is, while role only applies to administrative permissions.
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <div class="rounded-2xl bg-slate-950 px-4 py-3 text-sm text-white">
                            {{ users.total }} total user{{ users.total === 1 ? '' : 's' }}
                        </div>
                        <Link
                            v-if="canCreateUsers"
                            :href="route('admin.users.create')"
                            class="inline-flex items-center justify-center rounded-2xl bg-cyan-600 px-4 py-3 text-sm font-medium text-white transition hover:bg-cyan-700"
                        >
                            Create admin user
                        </Link>
                    </div>
                </div>

                <form class="mt-6 grid gap-4 lg:grid-cols-6" @submit.prevent="applyFilters">
                    <label class="space-y-2 text-sm font-medium text-slate-700">
                        <span>Full name</span>
                        <input
                            v-model="filterForm.name"
                            type="text"
                            class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-cyan-600"
                            placeholder="Search by name"
                        >
                    </label>

                    <label class="space-y-2 text-sm font-medium text-slate-700">
                        <span>Email</span>
                        <input
                            v-model="filterForm.email"
                            type="text"
                            class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-cyan-600"
                            placeholder="Search by email"
                        >
                    </label>

                    <label class="space-y-2 text-sm font-medium text-slate-700">
                        <span>Phone</span>
                        <input
                            v-model="filterForm.phone"
                            type="text"
                            class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-cyan-600"
                            placeholder="Search by phone"
                        >
                    </label>

                    <label class="space-y-2 text-sm font-medium text-slate-700">
                        <span>Account type</span>
                        <select
                            v-model="filterForm.account_type"
                            class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-cyan-600"
                        >
                            <option value="">All account types</option>
                            <option value="customer">Customer</option>
                            <option value="admin">Admin</option>
                        </select>
                    </label>

                    <label class="space-y-2 text-sm font-medium text-slate-700">
                        <span>Role</span>
                        <select
                            v-model="filterForm.role"
                            class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-cyan-600"
                        >
                            <option value="">All roles</option>
                            <option v-for="role in roles" :key="role.name" :value="role.name">
                                {{ role.label }}
                            </option>
                            <option value="unassigned">No role</option>
                        </select>
                    </label>

                    <label class="space-y-2 text-sm font-medium text-slate-700">
                        <span>Status</span>
                        <select
                            v-model="filterForm.status"
                            class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-cyan-600"
                        >
                            <option value="">All statuses</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </label>

                    <div class="flex items-end gap-3 lg:col-span-5 lg:justify-end">
                        <button
                            type="submit"
                            class="inline-flex items-center justify-center rounded-2xl bg-slate-950 px-4 py-3 text-sm font-medium text-white transition hover:bg-slate-800"
                        >
                            Apply filters
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-2xl border border-slate-200 px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="!hasActiveFilters"
                            @click="resetFilters"
                        >
                            Reset
                        </button>
                    </div>
                </form>

                <div class="mt-4 flex flex-col gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm font-semibold text-slate-900">Classification rules</p>
                        <p class="mt-1 text-sm text-slate-600">
                            Account type shows whether the user belongs to the mobile app or the admin panel. Role applies only when the account type is admin.
                        </p>
                    </div>

                    <div v-if="activeFilters.length > 0" class="flex flex-wrap gap-2">
                        <span
                            v-for="filter in activeFilters"
                            :key="filter.label"
                            class="rounded-full bg-white px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-slate-700 ring-1 ring-slate-200"
                        >
                            {{ filter.label }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr class="text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">
                                <th class="px-6 py-4">User</th>
                                <th class="px-6 py-4">Account type</th>
                                <th class="px-6 py-4">Role</th>
                                <th class="px-6 py-4">Contact</th>
                                <th class="px-6 py-4">Country</th>
                                <th class="px-6 py-4">Status</th>
                                <th class="px-6 py-4">Last login</th>
                                <th class="px-6 py-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm text-slate-700">
                            <tr v-for="user in users.data" :key="user.id" class="align-top">
                                <td class="px-6 py-5">
                                    <div class="font-semibold text-slate-950">{{ user.full_name }}</div>
                                    <div class="mt-1 text-xs font-medium uppercase tracking-[0.2em] text-slate-500">
                                        {{ user.email }}
                                    </div>
                                </td>
                                <td class="px-6 py-5">
                                    <AccountTypeBadge :account-type="user.account_type" />
                                </td>
                                <td class="px-6 py-5">
                                    <RoleBadge :role="user.role" />
                                    <p
                                        v-if="user.account_type === 'customer'"
                                        class="mt-2 text-xs font-medium uppercase tracking-[0.18em] text-slate-400"
                                    >
                                        Customer accounts do not use admin roles.
                                    </p>
                                </td>
                                <td class="space-y-1 px-6 py-5">
                                    <div class="text-slate-900">{{ user.email }}</div>
                                    <div class="text-slate-500">{{ user.phone || 'No phone provided' }}</div>
                                </td>
                                <td class="px-6 py-5">{{ user.country || 'Not set' }}</td>
                                <td class="px-6 py-5">
                                    <UserStatusBadge :active="user.is_active" />
                                </td>
                                <td class="px-6 py-5 text-slate-500">{{ formatDateTime(user.last_login_at) }}</td>
                                <td class="px-6 py-5">
                                    <div class="flex flex-wrap gap-2">
                                        <Link
                                            :href="route('admin.users.show', user.id)"
                                            class="rounded-xl border border-slate-200 px-3 py-2 font-medium text-slate-700 transition hover:bg-slate-50"
                                        >
                                            View
                                        </Link>
                                        <Link
                                            :href="route('admin.users.edit', user.id)"
                                            v-if="canUpdateUsers"
                                            class="rounded-xl border border-cyan-200 px-3 py-2 font-medium text-cyan-700 transition hover:bg-cyan-50"
                                        >
                                            Edit
                                        </Link>
                                        <button
                                            type="button"
                                            v-if="canUpdateUsers"
                                            class="rounded-xl border border-amber-200 px-3 py-2 font-medium text-amber-700 transition hover:bg-amber-50"
                                            @click="toggleStatus(user)"
                                        >
                                            {{ user.is_active ? 'Deactivate' : 'Activate' }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="users.data.length === 0">
                                <td colspan="8" class="px-6 py-12 text-center text-sm text-slate-500">
                                    No users matched the current filters.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex flex-col gap-4 border-t border-slate-200 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm text-slate-500">
                        Showing {{ users.from ?? 0 }} to {{ users.to ?? 0 }} of {{ users.total }} users.
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