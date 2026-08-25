<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import AdminButton from '../../modules/admin/components/AdminButton.vue';
import AdminInput from '../../modules/admin/components/AdminInput.vue';
import { useAdminLocale } from '../../modules/admin/composables/useAdminLocale';
import { Head, useForm } from '@inertiajs/vue3';

defineProps({
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
});

const { t } = useAdminLocale();

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <GuestLayout>
        <Head :title="t('Forgot Password')" />

        <div class="mb-6">
            <h1 class="text-xl font-semibold text-slate-950">{{ t('Forgot Password') }}</h1>
            <p class="mt-2 text-sm leading-6 text-slate-600">
                {{ t('Forgot your password? No problem. Just let us know your email address and we will email you a password reset link that will allow you to choose a new one.') }}
            </p>
        </div>

        <div
            v-if="status"
            class="mb-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800"
        >
            {{ status }}
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

            <div class="flex justify-end pt-2">
                <AdminButton type="submit" :processing="form.processing">
                    {{ t('Email Password Reset Link') }}
                </AdminButton>
            </div>
        </form>
    </GuestLayout>
</template>
