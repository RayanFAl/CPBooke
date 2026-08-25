<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import AdminButton from '../../modules/admin/components/AdminButton.vue';
import AdminInput from '../../modules/admin/components/AdminInput.vue';
import { useAdminLocale } from '../../modules/admin/composables/useAdminLocale';
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({
    password: '',
});

const { t } = useAdminLocale();

const submit = () => {
    form.post(route('password.confirm'), {
        onFinish: () => form.reset(),
    });
};
</script>

<template>
    <GuestLayout>
        <Head :title="t('Confirm Password')" />

        <div class="mb-6">
            <h1 class="text-xl font-semibold text-slate-950">{{ t('Confirm Password') }}</h1>
            <p class="mt-2 text-sm leading-6 text-slate-600">
                {{ t('This is a secure area of the application. Please confirm your password before continuing.') }}
            </p>
        </div>

        <form class="space-y-4" @submit.prevent="submit">
            <AdminInput
                id="password"
                v-model="form.password"
                type="password"
                :label="t('Password')"
                required
                autocomplete="current-password"
                autofocus
                :error="form.errors.password"
            />

            <div class="flex justify-end pt-2">
                <AdminButton type="submit" :processing="form.processing">
                    {{ t('Confirm') }}
                </AdminButton>
            </div>
        </form>
    </GuestLayout>
</template>
