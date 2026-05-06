<script setup>
import AccountTypeBadge from '../components/AccountTypeBadge.vue';
import AdminLayout from '../../layouts/AdminLayout.vue';
import RoleBadge from '../components/RoleBadge.vue';
import UserStatusBadge from '../components/UserStatusBadge.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    user: {
        type: Object,
        required: true,
    },
    placeholders: {
        type: Object,
        required: true,
    },
});

const page = usePage();
const canUpdateUsers = computed(() =>
    (page.props.auth.user?.permissions ?? []).includes('users.update'),
);

const formatDateTime = (value) => {
    if (!value) {
        return 'Not available';
    }

    return new Intl.DateTimeFormat('en', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
};

const toggleStatus = () => {
    const actionLabel = props.user.is_active ? 'deactivate' : 'activate';

    if (!window.confirm(`Do you want to ${actionLabel} ${props.user.full_name}?`)) {
        return;
    }

    router.post(route('admin.users.toggle-status', props.user.id), {}, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="`User ${user.full_name}`" />

    <AdminLayout
        title="User Profile"
        description="360-degree user snapshot with a clear separation between account classification and administrative access."
    >
        <section class="space-y-6">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.25em] text-cyan-700">
                            Identity
                        </p>
                        <h2 class="mt-2 text-3xl font-semibold text-slate-950">{{ user.full_name }}</h2>
                        <div class="mt-3 flex flex-wrap items-center gap-3">
                            <UserStatusBadge :active="user.is_active" />
                            <AccountTypeBadge :account-type="user.account_type" />
                            <RoleBadge :role="user.role" />
                        </div>
                        <p class="mt-4 max-w-2xl text-sm leading-6 text-slate-600">
                            Account type defines whether this user belongs to the customer app or the admin panel. Role only controls administrative permissions when the account type is admin.
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <Link
                            :href="route('admin.users.index')"
                            class="rounded-2xl border border-slate-200 px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                        >
                            Back to users
                        </Link>
                        <Link
                            v-if="canUpdateUsers"
                            :href="route('admin.users.edit', user.id)"
                            class="rounded-2xl border border-cyan-200 px-4 py-3 text-sm font-medium text-cyan-700 transition hover:bg-cyan-50"
                        >
                            Edit user
                        </Link>
                        <button
                            v-if="canUpdateUsers"
                            type="button"
                            class="rounded-2xl bg-slate-950 px-4 py-3 text-sm font-medium text-white transition hover:bg-slate-800"
                            @click="toggleStatus"
                        >
                            {{ user.is_active ? 'Deactivate account' : 'Activate account' }}
                        </button>
                    </div>
                </div>
            </div>

            <div class="grid gap-6 xl:grid-cols-[minmax(0,1.2fr)_minmax(0,0.8fr)]">
                <div class="space-y-6">
                    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h3 class="text-lg font-semibold text-slate-950">Personal details</h3>
                        <dl class="mt-5 grid gap-5 sm:grid-cols-2">
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Full name</dt>
                                <dd class="mt-2 text-sm text-slate-900">{{ user.full_name }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Email</dt>
                                <dd class="mt-2 text-sm text-slate-900">{{ user.email }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Phone</dt>
                                <dd class="mt-2 text-sm text-slate-900">{{ user.phone || 'Not provided' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Country</dt>
                                <dd class="mt-2 text-sm text-slate-900">{{ user.country || 'Not provided' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Account type</dt>
                                <dd class="mt-2 text-sm text-slate-900">{{ user.account_type === 'admin' ? 'Admin' : 'Customer' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Role</dt>
                                <dd class="mt-2 text-sm text-slate-900">{{ user.role?.label ?? 'No role assigned' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Created at</dt>
                                <dd class="mt-2 text-sm text-slate-900">{{ formatDateTime(user.created_at) }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Last login</dt>
                                <dd class="mt-2 text-sm text-slate-900">{{ formatDateTime(user.last_login_at) }}</dd>
                            </div>
                        </dl>
                    </div>

                    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h3 class="text-lg font-semibold text-slate-950">Latest orders</h3>
                        <div class="mt-5 grid gap-4 md:grid-cols-2">
                            <div
                                v-for="entry in placeholders.orders"
                                :key="entry.title"
                                class="rounded-2xl border border-dashed border-slate-300 p-4"
                            >
                                <p class="font-medium text-slate-900">{{ entry.title }}</p>
                                <p class="mt-2 text-sm leading-6 text-slate-600">{{ entry.description }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h3 class="text-lg font-semibold text-slate-950">Payments</h3>
                        <div class="mt-5 grid gap-4 md:grid-cols-2">
                            <div
                                v-for="entry in placeholders.payments"
                                :key="entry.title"
                                class="rounded-2xl border border-dashed border-slate-300 p-4"
                            >
                                <p class="font-medium text-slate-900">{{ entry.title }}</p>
                                <p class="mt-2 text-sm leading-6 text-slate-600">{{ entry.description }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="space-y-6">
                    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h3 class="text-lg font-semibold text-slate-950">Resolved permissions</h3>
                        <div class="mt-5 flex flex-wrap gap-2">
                            <span
                                v-for="permission in user.permissions"
                                :key="permission"
                                class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-slate-700"
                            >
                                {{ permission }}
                            </span>
                        </div>
                    </div>

                    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h3 class="text-lg font-semibold text-slate-950">Account health</h3>
                        <div class="mt-5 space-y-4">
                            <div class="rounded-2xl bg-slate-50 p-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Verification</p>
                                <p class="mt-2 text-sm text-slate-900">
                                    {{ user.email_verified_at ? `Verified ${formatDateTime(user.email_verified_at)}` : 'Email not verified' }}
                                </p>
                            </div>
                            <div class="rounded-2xl bg-slate-50 p-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Status</p>
                                <p class="mt-2 text-sm text-slate-900">
                                    {{ user.is_active ? 'Account is currently active and can sign in.' : 'Account is currently disabled and cannot sign in.' }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h3 class="text-lg font-semibold text-slate-950">Support tickets</h3>
                        <div class="mt-5 space-y-4">
                            <div
                                v-for="entry in placeholders.tickets"
                                :key="entry.title"
                                class="rounded-2xl border border-dashed border-slate-300 p-4"
                            >
                                <p class="font-medium text-slate-900">{{ entry.title }}</p>
                                <p class="mt-2 text-sm leading-6 text-slate-600">{{ entry.description }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </AdminLayout>
</template>