<script setup>
import AdminLayout from '../../layouts/AdminLayout.vue';
import AdminButton from '../../components/AdminButton.vue';
import AdminInput from '../../components/AdminInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useAdminLocale } from '../../composables/useAdminLocale';

const props = defineProps({
    user: {
        type: Object,
        required: true,
    },
});

const { t, backArrow } = useAdminLocale();

const form = useForm({
    full_name: props.user.full_name ?? '',
    email: props.user.email ?? '',
    phone: props.user.phone ?? '',
    country: props.user.country ?? '',
});

const submit = () => {
    form.put(route('admin.customers.update', props.user.id), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="`${t('Edit customer')} ${user.full_name}`" />

    <AdminLayout
        title="Edit customer"
        description="Correct the name, phone, or email used on tickets and wallet statements."
    >
        <section class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.25em] text-cyan-700">
                    {{ t('Customer identity') }}
                </p>
                <h2 class="mt-2 text-2xl font-semibold text-slate-950">{{ user.full_name }}</h2>
                <p class="mt-2 text-sm leading-6 text-slate-600">
                    {{ t('Use this when a ticket has the wrong passenger name or the customer cannot log in because the phone or email is wrong.') }}
                </p>

                <form class="mt-8 space-y-5" @submit.prevent="submit">
                    <AdminInput
                        v-model="form.full_name"
                        :label="t('Full name')"
                        required
                        :error="form.errors.full_name"
                    />
                    <AdminInput
                        v-model="form.email"
                        type="email"
                        :label="t('Email')"
                        required
                        :error="form.errors.email"
                    />
                    <div class="grid gap-5 md:grid-cols-2">
                        <AdminInput
                            v-model="form.phone"
                            :label="t('Phone')"
                            :error="form.errors.phone"
                        />
                        <AdminInput
                            v-model="form.country"
                            :label="t('Country')"
                            :error="form.errors.country"
                        />
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <AdminButton type="submit" :processing="form.processing">
                            {{ t('Save changes') }}
                        </AdminButton>
                        <Link
                            :href="route('admin.customers.show', user.id)"
                            class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                        >
                            {{ backArrow }} {{ t('Back to customer') }}
                        </Link>
                    </div>
                </form>
            </div>

            <aside class="space-y-6">
                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-slate-950">{{ t('What this changes') }}</h3>
                    <ul class="mt-4 space-y-3 text-sm leading-6 text-slate-600">
                        <li>{{ t('The app login email and the name shown on tickets.') }}</li>
                        <li>{{ t('Phone used for OTP and support contact.') }}</li>
                        <li>{{ t('Roles and passwords are not changed here.') }}</li>
                    </ul>
                </div>
            </aside>
        </section>
    </AdminLayout>
</template>
