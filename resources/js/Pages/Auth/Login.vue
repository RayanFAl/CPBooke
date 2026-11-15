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

        <div class="mb-7">
            <p class="text-xs font-semibold uppercase tracking-[0.28em] text-[#2f6db8]">
                {{ t('Staff access') }}
            </p>
            <h1 class="mt-2 text-[1.65rem] font-semibold tracking-tight text-slate-950">
                {{ t('Admin Login') }}
            </h1>
            <p class="mt-1.5 text-sm text-slate-500">
                {{ t('Sign in with an administrator account to access the Booke control panel.') }}
            </p>
        </div>

        <div
            v-if="status"
            class="mb-5 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800"
        >
            {{ status }}
        </div>

        <form class="space-y-5" @submit.prevent="submit">
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

            <div class="space-y-2">
                <div class="flex items-center justify-between gap-3">
                    <span class="text-sm font-medium text-slate-700">{{ t('Password') }}</span>
                    <Link
                        v-if="canResetPassword"
                        :href="route('password.request')"
                        class="text-sm font-medium text-[#2f6db8] transition hover:text-[#255991]"
                    >
                        {{ t('Forgot your password?') }}
                    </Link>
                </div>
                <AdminInput
                    id="password"
                    v-model="form.password"
                    type="password"
                    required
                    autocomplete="current-password"
                    :error="form.errors.password"
                />
            </div>

            <label class="flex items-center gap-2.5 text-sm text-slate-600">
                <Checkbox name="remember" v-model:checked="form.remember" />
                <span>{{ t('Remember me') }}</span>
            </label>

            <button
                type="submit"
                class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-[#2f6db8] px-4 py-3 text-sm font-semibold text-white transition hover:bg-[#255991] focus:outline-none focus:ring-2 focus:ring-[#2f6db8]/40 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
                :disabled="form.processing"
                :aria-busy="form.processing || undefined"
            >
                <svg
                    v-if="form.processing"
                    class="h-4 w-4 animate-spin"
                    viewBox="0 0 24 24"
                    fill="none"
                    aria-hidden="true"
                >
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                </svg>
                {{ t('Log in') }}
            </button>
        </form>
    </GuestLayout>
</template>
