<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import PermissionChecklist from '../components/PermissionChecklist.vue';
import AdminLayout from '../../layouts/AdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import { useAdminLocale } from '../../composables/useAdminLocale';

const props = defineProps({
    user: {
        type: Object,
        required: true,
    },
    roles: {
        type: Array,
        required: true,
    },
    permissionGroups: {
        type: Array,
        required: true,
    },
    rolePermissions: {
        type: Object,
        required: true,
    },
});

const { t } = useAdminLocale();

const form = useForm({
    full_name: props.user.full_name ?? '',
    email: props.user.email ?? '',
    phone: props.user.phone ?? '',
    role: props.user.role ?? '',
    permissions: props.user.permissions ?? [],
});

const isSuperAdminRole = computed(() => form.role === 'super_admin');

watch(
    () => form.role,
    (role, previousRole) => {
        if (role === previousRole) {
            return;
        }

        if (role === 'super_admin') {
            form.permissions = [];
            return;
        }

        form.permissions = [...(props.rolePermissions[role] ?? [])];
    },
);

const submit = () => {
    form.put(route('admin.users.update', props.user.id), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="`${t('Edit')} ${user.full_name}`" />

    <AdminLayout
        title="Edit User"
        description="Update customer identity and contact information without leaving the admin module boundary."
    >
        <section class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.25em] text-cyan-700">
                        {{ t('User Editor') }}
                    </p>
                    <h2 class="mt-2 text-2xl font-semibold text-slate-950">{{ user.full_name }}</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-600">
                        {{ t('This form updates the canonical profile fields used by the users directory and profile view.') }}
                    </p>
                </div>

                <form class="mt-8 space-y-6" @submit.prevent="submit">
                    <div>
                        <InputLabel for="full_name" :value="t('Full name')" />
                        <TextInput
                            id="full_name"
                            v-model="form.full_name"
                            type="text"
                            class="mt-2 block w-full"
                            required
                            autofocus
                        />
                        <InputError class="mt-2" :message="form.errors.full_name" />
                    </div>

                    <div>
                        <InputLabel for="email" :value="t('Email')" />
                        <TextInput
                            id="email"
                            v-model="form.email"
                            type="email"
                            class="mt-2 block w-full"
                            required
                        />
                        <InputError class="mt-2" :message="form.errors.email" />
                    </div>

                    <div>
                        <InputLabel for="phone" :value="t('Phone')" />
                        <TextInput
                            id="phone"
                            v-model="form.phone"
                            type="text"
                            class="mt-2 block w-full"
                        />
                        <InputError class="mt-2" :message="form.errors.phone" />
                    </div>

                    <div>
                        <InputLabel for="role" :value="t('Role')" />
                        <select
                            id="role"
                            v-model="form.role"
                            class="mt-2 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            required
                        >
                            <option value="" disabled>{{ t('Select a role') }}</option>
                            <option v-for="role in roles" :key="role.name" :value="role.name">
                                {{ t(role.label) }}
                            </option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.role" />
                    </div>

                    <PermissionChecklist
                        v-model="form.permissions"
                        :permission-groups="permissionGroups"
                        :disabled="isSuperAdminRole"
                        :error="form.errors.permissions"
                    />

                    <div class="flex flex-wrap items-center gap-3">
                        <PrimaryButton :disabled="form.processing" :class="{ 'opacity-25': form.processing }">
                            {{ t('Save changes') }}
                        </PrimaryButton>
                        <Link
                            :href="route('admin.team.show', user.id)"
                            class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                        >
                            {{ t('Cancel') }}
                        </Link>
                    </div>
                </form>
            </div>

            <aside class="space-y-6">
                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-slate-950">{{ t('Editing scope') }}</h3>
                    <ul class="mt-4 space-y-3 text-sm leading-6 text-slate-600">
                        <li>{{ t('Full name updates the canonical user display name across the admin area.') }}</li>
                        <li>{{ t('Email remains unique and validated at the backend layer.') }}</li>
                        <li>{{ t('Phone stays optional for staff contact details.') }}</li>
                        <li>{{ t("Role changes are validated against the current operator's RBAC scope.") }}</li>
                        <li>{{ t('Permission changes take effect immediately after saving this form.') }}</li>
                    </ul>
                </div>

                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-slate-950">{{ t('Operational note') }}</h3>
                    <p class="mt-3 text-sm leading-6 text-slate-600">
                        {{ t('Account activation is controlled separately from the listing and profile pages to preserve a clear audit trail for status changes.') }}
                    </p>
                </div>
            </aside>
        </section>
    </AdminLayout>
</template>
