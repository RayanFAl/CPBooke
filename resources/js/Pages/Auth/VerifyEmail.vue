<script setup>
import { computed } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import AdminButton from '../../modules/admin/components/AdminButton.vue';
import { useAdminLocale } from '../../modules/admin/composables/useAdminLocale';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    status: {
        type: String,
    },
});

const form = useForm({});
const { t } = useAdminLocale();

const submit = () => {
    form.post(route('verification.send'));
};

const verificationLinkSent = computed(
    () => props.status === 'verification-link-sent',
);
</script>

<template>
    <GuestLayout>
        <Head :title="t('Email Verification')" />

        <div class="mb-6">
            <h1 class="text-xl font-semibold text-slate-950">{{ t('Email Verification') }}</h1>
            <p class="mt-2 text-sm leading-6 text-slate-600">
                {{ t('Thanks for signing up! Before getting started, could you verify your email address by clicking on the link we just emailed to you? If you did not receive the email, we will gladly send you another.') }}
            </p>
        </div>

        <div
            v-if="verificationLinkSent"
            class="mb-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800"
        >
            {{ t('A new verification link has been sent to the email address you provided during registration.') }}
        </div>

        <form class="flex flex-wrap items-center justify-between gap-3" @submit.prevent="submit">
            <AdminButton type="submit" :processing="form.processing">
                {{ t('Resend Verification Email') }}
            </AdminButton>

            <Link
                :href="route('logout')"
                method="post"
                as="button"
                class="text-sm font-medium text-cyan-700 transition hover:text-cyan-800"
            >
                {{ t('Log Out') }}
            </Link>
        </form>
    </GuestLayout>
</template>
