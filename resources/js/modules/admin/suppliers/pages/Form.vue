<script setup>
import AdminLayout from '../../layouts/AdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useAdminLocale } from '../../composables/useAdminLocale';
import { usePlatformCurrency } from '../../composables/usePlatformCurrency';

const props = defineProps({
    supplier: { type: Object, default: null },
    options: { type: Object, required: true },
    next: { type: String, default: null },
});

const { t, settlementCycleLabel } = useAdminLocale();
const { defaultCurrency } = usePlatformCurrency();
const isEdit = computed(() => Boolean(props.supplier?.id));
const continueToWallet = computed(() => props.next === 'wallet' && !isEdit.value);

const form = useForm({
    name: props.supplier?.name ?? '',
    legal_name: props.supplier?.legal_name ?? '',
    key: props.supplier?.key ?? '',
    status: props.supplier?.status ?? 'active',
    commission_rate: props.supplier?.commission_rate ?? '',
    settlement_cycle: props.supplier?.settlement_cycle ?? 'monthly',
    credit_limit: props.supplier?.credit_limit ?? '',
    default_currency: props.supplier?.default_currency ?? defaultCurrency.value,
    contact_name: props.supplier?.contact_name ?? '',
    contact_email: props.supplier?.contact_email ?? '',
    contact_phone: props.supplier?.contact_phone ?? '',
    contract_starts_at: props.supplier?.contract_starts_at ?? '',
    contract_ends_at: props.supplier?.contract_ends_at ?? '',
    contract_notes: props.supplier?.contract_notes ?? '',
    notes: props.supplier?.notes ?? '',
    website: props.supplier?.website ?? '',
    next: continueToWallet.value ? 'wallet' : '',
});

const submit = () => {
    if (isEdit.value) {
        form.put(route('admin.suppliers.update', props.supplier.id));
        return;
    }

    form.post(route('admin.suppliers.store'));
};
</script>

<template>
    <AdminLayout>
        <Head :title="isEdit ? t('Edit provider') : t('Add provider')" />

        <section class="mx-auto max-w-3xl space-y-6">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-2xl font-semibold text-slate-950">{{ isEdit ? t('Edit provider') : t('Add provider') }}</h2>
                <p class="mt-2 text-sm text-slate-600">
                    {{ t('Fill the essentials now. Contact and contract can wait.') }}
                </p>
            </div>

            <form class="space-y-6" @submit.prevent="submit">
                <section class="space-y-4 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-base font-semibold text-slate-950">{{ t('Essential details') }}</h3>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium">{{ t('Name') }}</label>
                        <input v-model="form.name" type="text" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm" required>
                        <p v-if="form.errors.name" class="mt-1 text-sm text-rose-600">{{ form.errors.name }}</p>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium">{{ t('Commission %') }}</label>
                            <input v-model="form.commission_rate" type="number" min="0" max="100" step="0.01" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium">{{ t('Settlement cycle') }}</label>
                            <select v-model="form.settlement_cycle" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                                <option v-for="cycle in options.settlement_cycles" :key="cycle" :value="cycle">
                                    {{ settlementCycleLabel(cycle) }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium">{{ t('Default currency') }}</label>
                            <input v-model="form.default_currency" type="text" maxlength="3" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm uppercase">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium">{{ t('Credit limit') }}</label>
                            <input v-model="form.credit_limit" type="number" min="0" step="0.01" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                        </div>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium">{{ t('Status') }}</label>
                        <select v-model="form.status" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm md:max-w-xs">
                            <option v-for="status in options.statuses" :key="status" :value="status">{{ status }}</option>
                        </select>
                    </div>

                    <details class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <summary class="cursor-pointer text-sm font-medium text-slate-800">{{ t('Advanced options') }}</summary>
                        <div class="mt-4 space-y-4">
                            <div>
                                <label class="mb-1.5 block text-sm font-medium">{{ t('Key') }}</label>
                                <input v-model="form.key" type="text" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm" required :disabled="isEdit">
                                <p v-if="form.errors.key" class="mt-1 text-sm text-rose-600">{{ form.errors.key }}</p>
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-medium">{{ t('Legal name') }}</label>
                                <input v-model="form.legal_name" type="text" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                            </div>
                        </div>
                    </details>
                </section>

                <details class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <summary class="cursor-pointer text-base font-semibold text-slate-950">{{ t('Optional contact & contract') }}</summary>
                    <div class="mt-4 space-y-4">
                        <div class="grid gap-4 md:grid-cols-3">
                            <div>
                                <label class="mb-1.5 block text-sm font-medium">{{ t('Contact name') }}</label>
                                <input v-model="form.contact_name" type="text" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-medium">{{ t('Contact email') }}</label>
                                <input v-model="form.contact_email" type="email" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-medium">{{ t('Contact phone') }}</label>
                                <input v-model="form.contact_phone" type="text" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                            </div>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium">{{ t('Website') }}</label>
                            <input v-model="form.website" type="url" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm" placeholder="https://">
                        </div>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="mb-1.5 block text-sm font-medium">{{ t('Contract starts') }}</label>
                                <input v-model="form.contract_starts_at" type="date" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-medium">{{ t('Contract ends') }}</label>
                                <input v-model="form.contract_ends_at" type="date" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                            </div>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium">{{ t('Internal notes') }}</label>
                            <textarea v-model="form.notes" rows="3" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm" />
                        </div>
                    </div>
                </details>

                <div class="flex gap-3">
                    <button type="submit" class="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-medium text-white hover:bg-slate-800" :disabled="form.processing">
                        {{ continueToWallet ? t('Create provider and open ledger') : (isEdit ? t('Save changes') : t('Create provider')) }}
                    </button>
                    <Link
                        :href="continueToWallet ? route('admin.provider-wallets.create') : route('admin.suppliers.index')"
                        class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700"
                    >
                        {{ t('Cancel') }}
                    </Link>
                </div>
            </form>
        </section>
    </AdminLayout>
</template>
