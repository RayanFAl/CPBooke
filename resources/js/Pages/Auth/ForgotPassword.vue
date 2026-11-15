<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import AdminInput from '../../modules/admin/components/AdminInput.vue';
import { useAdminLocale } from '../../modules/admin/composables/useAdminLocale';
import { Head, Link, useForm } from '@inertiajs/vue3';

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

        <div class="mb-7">
            <p class="text-xs font-semibold uppercase tracking-[0.28em] text-[#2f6db8]">
                {{ t('Staff access') }}
            </p>
            <h1 class="mt-2 text-[1.65rem] font-semibold tracking-tight text-slate-950">
                {{ t('Forgot Password') }}
            </h1>
            <p class="mt-1.5 text-sm text-slate-500">
                {{ t('Forgot your password? No problem. Just let us know your email address and we will email you a password reset link that will allow you to choose a new one.') }}
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
                {{ t('Email Password Reset Link') }}
            </button>

            <Link
                :href="route('login')"
                class="block text-center text-sm font-medium text-[#2f6db8] hover:text-[#255991]"
            >
                {{ t('Log in') }}
            </Link>
        </form>
    </GuestLayout>
</template>
