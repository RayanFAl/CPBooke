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

const initialRole = props.roles[0]?.name ?? '';

const form = useForm({
    full_name: '',
    email: '',
    phone: '',
    password: '',
    password_confirmation: '',
    role: initialRole,
    permissions: props.rolePermissions[initialRole] ?? [],
});

const isSuperAdminRole = computed(() => form.role === 'super_admin');

watch(
    () => form.role,
    (role) => {
        if (role === 'super_admin') {
            form.permissions = [];
            return;
        }

        form.permissions = [...(props.rolePermissions[role] ?? [])];
    },
);

const submit = () => {
    form.post(route('admin.users.store'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="t('Create Admin User')" />

    <AdminLayout
        title="Create Admin User"
        description="Create an administrative account and bind it to an RBAC role from the start."
    >
        <section class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.25em] text-cyan-700">
                        {{ t('Admin Onboarding') }}
                    </p>
                    <h2 class="mt-2 text-2xl font-semibold text-slate-950">{{ t('New back-office account') }}</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-600">
                        {{ t('Each new administrative user must be created with an explicit role so module access stays deterministic from day one.') }}
                    </p>
                </div>

                <form class="mt-8 space-y-6" @submit.prevent="submit">
                    <div>
                        <InputLabel for="full_name" :value="t('Full name')" />
                        <TextInput id="full_name" v-model="form.full_name" type="text" class="mt-2 block w-full" required autofocus />
                        <InputError class="mt-2" :message="form.errors.full_name" />
                    </div>

                    <div>
                        <InputLabel for="email" :value="t('Email')" />
                        <TextInput id="email" v-model="form.email" type="email" class="mt-2 block w-full" required />
                        <InputError class="mt-2" :message="form.errors.email" />
                    </div>

                    <div>
                        <InputLabel for="phone" :value="t('Phone')" />
                        <TextInput id="phone" v-model="form.phone" type="text" class="mt-2 block w-full" />
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

                    <div class="grid gap-6 md:grid-cols-2">
                        <div>
                            <InputLabel for="password" :value="t('Password')" />
                            <TextInput id="password" v-model="form.password" type="password" class="mt-2 block w-full" required />
                            <InputError class="mt-2" :message="form.errors.password" />
                        </div>

                        <div>
                            <InputLabel for="password_confirmation" :value="t('Confirm password')" />
                            <TextInput
                                id="password_confirmation"
                                v-model="form.password_confirmation"
                                type="password"
                                class="mt-2 block w-full"
                                required
                            />
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <PrimaryButton :disabled="form.processing" :class="{ 'opacity-25': form.processing }">
                            {{ t('Create admin user') }}
                        </PrimaryButton>
                        <Link
                            :href="route('admin.team.index')"
                            class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                        >
                            {{ t('Cancel') }}
                        </Link>
                    </div>
                </form>
            </div>

            <aside class="space-y-6">
                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-slate-950">{{ t('Role rules') }}</h3>
                    <ul class="mt-4 space-y-3 text-sm leading-6 text-slate-600">
                        <li>{{ t('Super admin remains restricted to super admin operators only.') }}</li>
                        <li>{{ t('Admin receives operational access but not settings ownership.') }}</li>
                        <li>{{ t('Team member is intended for narrower back-office access.') }}</li>
                        <li>{{ t('Use the permission checklist to fine-tune access beyond the selected role template.') }}</li>
                    </ul>
                </div>
            </aside>
        </section>
    </AdminLayout>
</template>
