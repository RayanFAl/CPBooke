<script setup>
import AdminLayout from '../../layouts/AdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useAdminLocale } from '../../composables/useAdminLocale';

const props = defineProps({
    providers: { type: Array, default: () => [] },
    default_currency: { type: String, default: 'LYD' },
});

const { t } = useAdminLocale();

const form = useForm({
    provider_id: props.providers[0]?.id ?? '',
    period_start: '',
    period_end: '',
    currency: props.default_currency,
    notes: '',
});

const submit = () => {
    form.post(route('admin.settlements.store'));
};
</script>

<template>
    <AdminLayout>
        <Head :title="t('Create settlement period')" />

        <section class="mx-auto max-w-2xl space-y-6">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <Link :href="route('admin.settlements.index')" class="text-sm font-medium text-cyan-700 hover:text-cyan-800">
                    ← {{ t('Settlements') }}
                </Link>
                <h2 class="mt-4 text-2xl font-semibold text-slate-950">{{ t('Create settlement period') }}</h2>
                <p class="mt-2 text-sm text-slate-600">
                    {{ t('Orders with supplier cost in this date range will be loaded automatically.') }}
                </p>
            </div>

            <form class="space-y-4 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm" @submit.prevent="submit">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">{{ t('Provider') }}</label>
                    <select v-model="form.provider_id" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                        <option v-for="provider in providers" :key="provider.id" :value="provider.id">
                            {{ provider.name }} ({{ provider.key }})
                        </option>
                    </select>
                    <p v-if="form.errors.provider_id" class="mt-1 text-xs text-rose-600">{{ form.errors.provider_id }}</p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">{{ t('Period start') }}</label>
                        <input v-model="form.period_start" type="date" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                        <p v-if="form.errors.period_start" class="mt-1 text-xs text-rose-600">{{ form.errors.period_start }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">{{ t('Period end') }}</label>
                        <input v-model="form.period_end" type="date" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                        <p v-if="form.errors.period_end" class="mt-1 text-xs text-rose-600">{{ form.errors.period_end }}</p>
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">{{ t('Currency') }}</label>
                    <input v-model="form.currency" type="text" maxlength="3" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm uppercase">
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">{{ t('Notes') }}</label>
                    <textarea v-model="form.notes" rows="3" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm" />
                </div>

                <button
                    type="submit"
                    class="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-medium text-white hover:bg-slate-800 disabled:opacity-60"
                    :disabled="form.processing"
                >
                    {{ t('Create period') }}
                </button>
            </form>
        </section>
    </AdminLayout>
</template>
