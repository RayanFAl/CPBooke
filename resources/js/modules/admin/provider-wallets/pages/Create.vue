<script setup>
import AdminLayout from '../../layouts/AdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import { useAdminLocale } from '../../composables/useAdminLocale';
import { usePlatformCurrency } from '../../composables/usePlatformCurrency';

const props = defineProps({
    providers: { type: Array, default: () => [] },
    environments: { type: Array, default: () => ['production', 'sandbox'] },
    default_allow_negative: { type: Boolean, default: true },
    selected_provider_id: { type: [Number, String], default: null },
    can_manage_suppliers: { type: Boolean, default: false },
});

const { t } = useAdminLocale();
const { defaultCurrency } = usePlatformCurrency();

const initialProviderId = props.selected_provider_id || props.providers[0]?.id || '';
const initialProvider = props.providers.find(
    (provider) => String(provider.id) === String(initialProviderId),
);

const walletForm = useForm({
    provider_id: initialProviderId,
    currency: initialProvider?.default_currency || defaultCurrency.value,
    environment: 'production',
    low_balance_threshold: '',
    allow_negative: props.default_allow_negative,
});

const hasProviders = computed(() => props.providers.length > 0);

watch(
    () => walletForm.provider_id,
    (providerId) => {
        const provider = props.providers.find(
            (item) => String(item.id) === String(providerId),
        );

        if (provider?.default_currency) {
            walletForm.currency = provider.default_currency;
        }
    },
);

const submitWallet = () => {
    walletForm.post(route('admin.provider-wallets.store'));
};
</script>

<template>
    <AdminLayout>
        <Head :title="t('Create ledger wallet')" />

        <section class="mx-auto max-w-2xl space-y-6">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.25em] text-cyan-700">{{ t('Step 2') }}</p>
                <h2 class="mt-3 text-2xl font-semibold text-slate-950">{{ t('Create ledger wallet') }}</h2>
                <p class="mt-2 text-sm text-slate-600">{{ t('Choose a provider, then create their ledger wallet.') }}</p>
            </div>

            <div v-if="!hasProviders" class="rounded-3xl border border-amber-200 bg-amber-50 p-6 shadow-sm">
                <h3 class="text-base font-semibold text-amber-950">{{ t('No providers yet') }}</h3>
                <p class="mt-2 text-sm text-amber-900">{{ t('Create a full provider profile first, then return here to add a wallet.') }}</p>
                <Link
                    v-if="can_manage_suppliers"
                    :href="route('admin.suppliers.create', { next: 'wallet' })"
                    class="mt-4 inline-flex rounded-xl bg-amber-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-amber-950"
                >
                    {{ t('Add provider') }}
                </Link>
            </div>

            <form v-else class="space-y-4 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm" @submit.prevent="submitWallet">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-800">{{ t('Provider') }}</label>
                    <select v-model="walletForm.provider_id" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm" required>
                        <option disabled value="">{{ t('Select provider') }}</option>
                        <option v-for="provider in providers" :key="provider.id" :value="provider.id">
                            {{ provider.name }}
                        </option>
                    </select>
                    <p v-if="walletForm.errors.provider_id" class="mt-1 text-sm text-rose-600">{{ walletForm.errors.provider_id }}</p>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-800">{{ t('Currency') }}</label>
                    <input
                        v-model="walletForm.currency"
                        type="text"
                        maxlength="3"
                        class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm uppercase md:max-w-xs"
                    >
                    <p v-if="walletForm.errors.currency" class="mt-1 text-sm text-rose-600">{{ walletForm.errors.currency }}</p>
                </div>

                <details class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                    <summary class="cursor-pointer text-sm font-medium text-slate-800">{{ t('Advanced options') }}</summary>
                    <div class="mt-4 space-y-4">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-slate-800">{{ t('Low balance threshold') }}</label>
                            <input v-model="walletForm.low_balance_threshold" type="number" step="0.01" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm" :placeholder="t('Optional')">
                        </div>
                        <label class="flex items-start gap-3 text-sm text-slate-700">
                            <input v-model="walletForm.allow_negative" type="checkbox" class="mt-0.5">
                            <span>{{ t('Allow negative balance') }}</span>
                        </label>
                    </div>
                </details>

                <div class="flex gap-3 pt-2">
                    <button type="submit" class="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-medium text-white hover:bg-slate-800 disabled:opacity-60" :disabled="walletForm.processing">
                        {{ t('Create wallet') }}
                    </button>
                    <Link :href="route('admin.suppliers.index')" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                        {{ t('Cancel') }}
                    </Link>
                </div>
            </form>
        </section>
    </AdminLayout>
</template>
