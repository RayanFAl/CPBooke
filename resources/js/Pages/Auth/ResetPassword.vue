<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import AdminButton from '../../modules/admin/components/AdminButton.vue';
import AdminInput from '../../modules/admin/components/AdminInput.vue';
import { useAdminLocale } from '../../modules/admin/composables/useAdminLocale';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    email: {
        type: String,
        required: true,
    },
    token: {
        type: String,
        required: true,
    },
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const { t } = useAdminLocale();

const submit = () => {
    form.post(route('password.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head :title="t('Reset Password')" />

        <div class="mb-6">
            <h1 class="text-xl font-semibold text-slate-950">{{ t('Reset Password') }}</h1>
        </div>

        <form class="space-y-4" @submit.prevent="submit">
            <AdminInput
                id="email"
                v-model="form.email"
                type="email"
                :label="t('Email')"
                required
                autofocus
                autocomplete="username"
                :error="form.errors.email"
            />

            <AdminInput
                id="password"
                v-model="form.password"
                type="password"
                :label="t('Password')"
                required
                autocomplete="new-password"
                :error="form.errors.password"
            />

            <AdminInput
                id="password_confirmation"
                v-model="form.password_confirmation"
                type="password"
                :label="t('Confirm Password')"
                required
                autocomplete="new-password"
                :error="form.errors.password_confirmation"
            />

            <div class="flex justify-end pt-2">
                <AdminButton type="submit" :processing="form.processing">
                    {{ t('Reset Password') }}
                </AdminButton>
            </div>
        </form>
    </GuestLayout>
</template>
