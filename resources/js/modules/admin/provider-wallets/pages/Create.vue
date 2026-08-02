<script setup>
import AdminLayout from '../../layouts/AdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useAdminLocale } from '../../composables/useAdminLocale';
import { usePlatformCurrency } from '../../composables/usePlatformCurrency';

const props = defineProps({
    providers: {
        type: Array,
        default: () => [],
    },
    environments: {
        type: Array,
        default: () => ['production', 'sandbox'],
    },
    default_allow_negative: {
        type: Boolean,
        default: true,
    },
    selected_provider_id: {
        type: [Number, String],
        default: null,
    },
});

const { t } = useAdminLocale();
const { defaultCurrency } = usePlatformCurrency();

const walletForm = useForm({
    provider_id: props.selected_provider_id || props.providers[0]?.id || '',
    currency: defaultCurrency.value,
    environment: 'production',
    low_balance_threshold: '',
    allow_negative: props.default_allow_negative,
});

const providerForm = useForm({
    name: '',
    key: '',
    status: 'active',
});

const submitWallet = () => {
    walletForm.post(route('admin.provider-wallets.store'));
};

const submitProvider = () => {
    providerForm.post(route('admin.provider-wallets.providers.store'), {
        preserveScroll: true,
        onSuccess: () => providerForm.reset('name', 'key'),
    });
};
</script>

<template>
    <AdminLayout>
        <Head :title="t('Create provider wallet')" />

        <section class="mx-auto max-w-3xl space-y-6">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.25em] text-cyan-700">
                    {{ t('Finance') }}
                </p>
                <h2 class="mt-3 text-2xl font-semibold text-slate-950">{{ t('Create provider wallet') }}</h2>
                <p class="mt-2 text-sm leading-6 text-slate-600">
                    {{ t('Create a prepaid balance ledger for a flight supplier company.') }}
                </p>
            </div>

            <form class="space-y-4 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm" @submit.prevent="submitProvider">
                <h3 class="text-base font-semibold text-slate-950">{{ t('Add provider') }}</h3>
                <p class="text-sm text-slate-600">{{ t('Register a supplier/API before creating wallets for it.') }}</p>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-800">{{ t('Provider name') }}</label>
                        <input
                            v-model="providerForm.name"
                            type="text"
                            class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"
                            placeholder="BookNow"
                        >
                        <p v-if="providerForm.errors.name" class="mt-1 text-sm text-rose-600">{{ providerForm.errors.name }}</p>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-800">{{ t('Provider key') }}</label>
                        <input
                            v-model="providerForm.key"
                            type="text"
                            class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"
                            placeholder="booknow"
                        >
                        <p v-if="providerForm.errors.key" class="mt-1 text-sm text-rose-600">{{ providerForm.errors.key }}</p>
                    </div>
                </div>

                <button
                    type="submit"
                    class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-800 hover:bg-slate-50 disabled:opacity-60"
                    :disabled="providerForm.processing"
                >
                    {{ t('Save provider') }}
                </button>
            </form>

            <form class="space-y-4 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm" @submit.prevent="submitWallet">
                <h3 class="text-base font-semibold text-slate-950">{{ t('Create wallet') }}</h3>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-800">{{ t('Provider') }}</label>
                    <select
                        v-model="walletForm.provider_id"
                        class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"
                        required
                    >
                        <option disabled value="">{{ t('Select provider') }}</option>
                        <option
                            v-for="provider in providers"
                            :key="provider.id"
                            :value="provider.id"
                        >
                            {{ provider.name }} ({{ provider.key }})
                        </option>
                    </select>
                    <p v-if="walletForm.errors.provider_id" class="mt-1 text-sm text-rose-600">{{ walletForm.errors.provider_id }}</p>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-800">{{ t('Currency') }}</label>
                        <input
                            v-model="walletForm.currency"
                            type="text"
                            maxlength="3"
                            class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm uppercase"
                            :placeholder="defaultCurrency"
                        >
                        <p v-if="walletForm.errors.currency" class="mt-1 text-sm text-rose-600">{{ walletForm.errors.currency }}</p>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-800">{{ t('Environment') }}</label>
                        <select
                            v-model="walletForm.environment"
                            class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"
                        >
                            <option v-for="environment in environments" :key="environment" :value="environment">
                                {{ environment }}
                            </option>
                        </select>
                        <p v-if="walletForm.errors.environment" class="mt-1 text-sm text-rose-600">{{ walletForm.errors.environment }}</p>
                    </div>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-800">{{ t('Low balance threshold') }}</label>
                    <input
                        v-model="walletForm.low_balance_threshold"
                        type="number"
                        step="0.01"
                        class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"
                        :placeholder="t('Optional')"
                    >
                    <p v-if="walletForm.errors.low_balance_threshold" class="mt-1 text-sm text-rose-600">{{ walletForm.errors.low_balance_threshold }}</p>
                </div>

                <label class="flex items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm text-slate-700">
                    <input
                        v-model="walletForm.allow_negative"
                        type="checkbox"
                        class="mt-0.5"
                    >
                    <span>
                        <span class="block font-medium text-slate-900">{{ t('Allow negative balance') }}</span>
                        <span class="mt-1 block text-slate-600">{{ t('If unchecked, debits are rejected when funds are insufficient.') }}</span>
                    </span>
                </label>

                <div class="flex gap-3 pt-2">
                    <button
                        type="submit"
                        class="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-medium text-white hover:bg-slate-800 disabled:opacity-60"
                        :disabled="walletForm.processing || providers.length === 0"
                    >
                        {{ t('Create wallet') }}
                    </button>
                    <Link
                        :href="route('admin.provider-wallets.index')"
                        class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
                    >
                        {{ t('Cancel') }}
                    </Link>
                </div>
            </form>
        </section>
    </AdminLayout>
</template>
