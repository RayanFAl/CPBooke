<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import AdminButton from '../../modules/admin/components/AdminButton.vue';
import AdminInput from '../../modules/admin/components/AdminInput.vue';
import { useAdminLocale } from '../../modules/admin/composables/useAdminLocale';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const { t } = useAdminLocale();

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head :title="t('Admin Login')" />

        <div class="mb-6">
            <h1 class="text-xl font-semibold text-slate-950">{{ t('Admin Login') }}</h1>
            <p class="mt-1 text-sm text-slate-600">
                {{ t('Sign in with an administrator account to access the Booke control panel.') }}
            </p>
        </div>

        <div v-if="status" class="mb-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
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

            <AdminInput
                id="password"
                v-model="form.password"
                type="password"
                :label="t('Password')"
                required
                autocomplete="current-password"
                :error="form.errors.password"
            />

            <label class="flex items-center gap-2 text-sm text-slate-600">
                <Checkbox name="remember" v-model:checked="form.remember" />
                <span>{{ t('Remember me') }}</span>
            </label>

            <div class="flex flex-wrap items-center justify-between gap-3 pt-2">
                <Link
                    v-if="canResetPassword"
                    :href="route('password.request')"
                    class="text-sm font-medium text-cyan-700 transition hover:text-cyan-800"
                >
                    {{ t('Forgot your password?') }}
                </Link>

                <AdminButton type="submit" :processing="form.processing">
                    {{ t('Log in') }}
                </AdminButton>
            </div>
        </form>
    </GuestLayout>
</template>
