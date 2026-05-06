<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AdminLayout from '../../layouts/AdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    roles: {
        type: Array,
        required: true,
    },
});

const form = useForm({
    full_name: '',
    email: '',
    phone: '',
    country: '',
    password: '',
    password_confirmation: '',
    role: props.roles[0]?.name ?? '',
});

const submit = () => {
    form.post(route('admin.users.store'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Create Admin User" />

    <AdminLayout
        title="Create Admin User"
        description="Create an administrative account and bind it to an RBAC role from the start."
    >
        <section class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.25em] text-cyan-700">
                        Admin Onboarding
                    </p>
                    <h2 class="mt-2 text-2xl font-semibold text-slate-950">New back-office account</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-600">
                        Each new administrative user must be created with an explicit role so module access stays deterministic from day one.
                    </p>
                </div>

                <form class="mt-8 space-y-6" @submit.prevent="submit">
                    <div>
                        <InputLabel for="full_name" value="Full name" />
                        <TextInput id="full_name" v-model="form.full_name" type="text" class="mt-2 block w-full" required autofocus />
                        <InputError class="mt-2" :message="form.errors.full_name" />
                    </div>

                    <div>
                        <InputLabel for="email" value="Email" />
                        <TextInput id="email" v-model="form.email" type="email" class="mt-2 block w-full" required />
                        <InputError class="mt-2" :message="form.errors.email" />
                    </div>

                    <div class="grid gap-6 md:grid-cols-2">
                        <div>
                            <InputLabel for="phone" value="Phone" />
                            <TextInput id="phone" v-model="form.phone" type="text" class="mt-2 block w-full" />
                            <InputError class="mt-2" :message="form.errors.phone" />
                        </div>

                        <div>
                            <InputLabel for="country" value="Country" />
                            <TextInput id="country" v-model="form.country" type="text" class="mt-2 block w-full" />
                            <InputError class="mt-2" :message="form.errors.country" />
                        </div>
                    </div>

                    <div>
                        <InputLabel for="role" value="Role" />
                        <select
                            id="role"
                            v-model="form.role"
                            class="mt-2 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            required
                        >
                            <option value="" disabled>Select a role</option>
                            <option v-for="role in roles" :key="role.name" :value="role.name">
                                {{ role.label }}
                            </option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.role" />
                    </div>

                    <div class="grid gap-6 md:grid-cols-2">
                        <div>
                            <InputLabel for="password" value="Password" />
                            <TextInput id="password" v-model="form.password" type="password" class="mt-2 block w-full" required />
                            <InputError class="mt-2" :message="form.errors.password" />
                        </div>

                        <div>
                            <InputLabel for="password_confirmation" value="Confirm password" />
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
                            Create admin user
                        </PrimaryButton>
                        <Link
                            :href="route('admin.users.index')"
                            class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                        >
                            Cancel
                        </Link>
                    </div>
                </form>
            </div>

            <aside class="space-y-6">
                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-slate-950">Role rules</h3>
                    <ul class="mt-4 space-y-3 text-sm leading-6 text-slate-600">
                        <li>Super admin remains restricted to super admin operators only.</li>
                        <li>Admin receives operational access but not settings ownership.</li>
                        <li>Team member is intended for narrower back-office access.</li>
                    </ul>
                </div>
            </aside>
        </section>
    </AdminLayout>
</template>