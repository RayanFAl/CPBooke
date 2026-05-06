<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AdminLayout from '../../layouts/AdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    user: {
        type: Object,
        required: true,
    },
    roles: {
        type: Array,
        required: true,
    },
});

const form = useForm({
    full_name: props.user.full_name ?? '',
    email: props.user.email ?? '',
    phone: props.user.phone ?? '',
    country: props.user.country ?? '',
    role: props.user.role ?? '',
});

const submit = () => {
    form.put(route('admin.users.update', props.user.id), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="`Edit ${user.full_name}`" />

    <AdminLayout
        title="Edit User"
        description="Update customer identity and contact information without leaving the admin module boundary."
    >
        <section class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.25em] text-cyan-700">
                        User Editor
                    </p>
                    <h2 class="mt-2 text-2xl font-semibold text-slate-950">{{ user.full_name }}</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-600">
                        This form updates the canonical profile fields used by the users directory and profile view.
                    </p>
                </div>

                <form class="mt-8 space-y-6" @submit.prevent="submit">
                    <div>
                        <InputLabel for="full_name" value="Full name" />
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
                        <InputLabel for="email" value="Email" />
                        <TextInput
                            id="email"
                            v-model="form.email"
                            type="email"
                            class="mt-2 block w-full"
                            required
                        />
                        <InputError class="mt-2" :message="form.errors.email" />
                    </div>

                    <div class="grid gap-6 md:grid-cols-2">
                        <div>
                            <InputLabel for="phone" value="Phone" />
                            <TextInput
                                id="phone"
                                v-model="form.phone"
                                type="text"
                                class="mt-2 block w-full"
                            />
                            <InputError class="mt-2" :message="form.errors.phone" />
                        </div>

                        <div>
                            <InputLabel for="country" value="Country" />
                            <TextInput
                                id="country"
                                v-model="form.country"
                                type="text"
                                class="mt-2 block w-full"
                            />
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

                    <div class="flex flex-wrap items-center gap-3">
                        <PrimaryButton :disabled="form.processing" :class="{ 'opacity-25': form.processing }">
                            Save changes
                        </PrimaryButton>
                        <Link
                            :href="route('admin.users.show', user.id)"
                            class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                        >
                            Cancel
                        </Link>
                    </div>
                </form>
            </div>

            <aside class="space-y-6">
                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-slate-950">Editing scope</h3>
                    <ul class="mt-4 space-y-3 text-sm leading-6 text-slate-600">
                        <li>Full name updates the canonical user display name across the admin area.</li>
                        <li>Email remains unique and validated at the backend layer.</li>
                        <li>Phone and country stay optional to support incomplete customer profiles.</li>
                        <li>Role changes are validated against the current operator's RBAC scope.</li>
                    </ul>
                </div>

                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-slate-950">Operational note</h3>
                    <p class="mt-3 text-sm leading-6 text-slate-600">
                        Account activation is controlled separately from the listing and profile pages to preserve a clear audit trail for status changes.
                    </p>
                </div>
            </aside>
        </section>
    </AdminLayout>
</template>