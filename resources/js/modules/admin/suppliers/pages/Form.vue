<script setup>
import AdminLayout from '../../layouts/AdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useAdminLocale } from '../../composables/useAdminLocale';

const props = defineProps({
    supplier: { type: Object, default: null },
    options: { type: Object, required: true },
});

const { t } = useAdminLocale();
const isEdit = computed(() => Boolean(props.supplier?.id));

const form = useForm({
    name: props.supplier?.name ?? '',
    legal_name: props.supplier?.legal_name ?? '',
    key: props.supplier?.key ?? '',
    status: props.supplier?.status ?? 'active',
    commission_rate: props.supplier?.commission_rate ?? '',
    settlement_cycle: props.supplier?.settlement_cycle ?? 'monthly',
    credit_limit: props.supplier?.credit_limit ?? '',
    default_currency: props.supplier?.default_currency ?? 'LYD',
    contact_name: props.supplier?.contact_name ?? '',
    contact_email: props.supplier?.contact_email ?? '',
    contact_phone: props.supplier?.contact_phone ?? '',
    integration_status: props.supplier?.integration_status ?? 'not_configured',
    contract_starts_at: props.supplier?.contract_starts_at ?? '',
    contract_ends_at: props.supplier?.contract_ends_at ?? '',
    contract_notes: props.supplier?.contract_notes ?? '',
    notes: props.supplier?.notes ?? '',
    website: props.supplier?.website ?? '',
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
        <Head :title="isEdit ? t('Edit supplier') : t('Add supplier')" />

        <section class="mx-auto max-w-3xl space-y-6">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-2xl font-semibold text-slate-950">{{ isEdit ? t('Edit supplier') : t('Add supplier') }}</h2>
                <p class="mt-2 text-sm text-slate-600">{{ t('Commercial profile used for wallets, cost calculation, and future settlements.') }}</p>
            </div>

            <form class="space-y-4 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm" @submit.prevent="submit">
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">{{ t('Name') }}</label>
                        <input v-model="form.name" type="text" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm" required>
                        <p v-if="form.errors.name" class="mt-1 text-sm text-rose-600">{{ form.errors.name }}</p>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">{{ t('Key') }}</label>
                        <input v-model="form.key" type="text" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm" required :disabled="isEdit">
                        <p v-if="form.errors.key" class="mt-1 text-sm text-rose-600">{{ form.errors.key }}</p>
                    </div>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium">{{ t('Legal name') }}</label>
                    <input v-model="form.legal_name" type="text" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                </div>

                <div class="grid gap-4 md:grid-cols-3">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">{{ t('Status') }}</label>
                        <select v-model="form.status" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                            <option v-for="status in options.statuses" :key="status" :value="status">{{ status }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">{{ t('Commission %') }}</label>
                        <input v-model="form.commission_rate" type="number" min="0" max="100" step="0.01" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">{{ t('Settlement cycle') }}</label>
                        <select v-model="form.settlement_cycle" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                            <option v-for="cycle in options.settlement_cycles" :key="cycle" :value="cycle">{{ cycle }}</option>
                        </select>
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-3">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">{{ t('Credit limit') }}</label>
                        <input v-model="form.credit_limit" type="number" min="0" step="0.01" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">{{ t('Currency') }}</label>
                        <input v-model="form.default_currency" type="text" maxlength="3" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm uppercase">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">{{ t('Integration status') }}</label>
                        <select v-model="form.integration_status" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                            <option v-for="status in options.integration_statuses" :key="status" :value="status">{{ status }}</option>
                        </select>
                    </div>
                </div>

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
                    <label class="mb-1.5 block text-sm font-medium">{{ t('Website') }}</label>
                    <input v-model="form.website" type="url" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm" placeholder="https://">
                    <p v-if="form.errors.website" class="mt-1 text-sm text-rose-600">{{ form.errors.website }}</p>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium">{{ t('Contract notes') }}</label>
                    <textarea v-model="form.contract_notes" rows="3" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm" />
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium">{{ t('Internal notes') }}</label>
                    <textarea v-model="form.notes" rows="3" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm" />
                </div>

                <div class="flex gap-3 pt-2">
                    <button type="submit" class="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-medium text-white hover:bg-slate-800" :disabled="form.processing">
                        {{ isEdit ? t('Save changes') : t('Create supplier') }}
                    </button>
                    <Link :href="route('admin.suppliers.index')" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700">
                        {{ t('Cancel') }}
                    </Link>
                </div>
            </form>
        </section>
    </AdminLayout>
</template>
