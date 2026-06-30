<script setup>
import AdminLayout from '../../layouts/AdminLayout.vue';
import AirportFormFields from '../components/AirportFormFields.vue';
import { emptyAirportForm } from '../utils/airportForm';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useAdminLocale } from '../../composables/useAdminLocale';

defineProps({
    usesFullSchema: {
        type: Boolean,
        default: true,
    },
});

const { t } = useAdminLocale();

const form = useForm(emptyAirportForm());

const submit = () => {
    form.post(route('admin.airports.store'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="t('Create airport')" />

    <AdminLayout
        title="Create airport"
        description="Add a new airport record into the shared admin master data catalog."
    >
        <section class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-2xl font-semibold text-slate-950">{{ t('New airport record') }}</h2>
                <p class="mt-2 text-sm text-slate-600">{{ t('All airport fields are available for entry.') }}</p>

                <form class="mt-8 space-y-6" @submit.prevent="submit">
                    <AirportFormFields :form="form" />

                    <div class="flex flex-wrap items-center gap-3">
                        <button
                            type="submit"
                            class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-800"
                            :disabled="form.processing"
                        >
                            {{ t('Save') }}
                        </button>
                        <Link
                            :href="route('admin.airports.index')"
                            class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                        >
                            {{ t('Cancel') }}
                        </Link>
                    </div>
                </form>
            </div>

            <aside class="space-y-6">
                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-slate-950">{{ t('Data guidance') }}</h3>
                    <ul class="mt-4 space-y-3 text-sm leading-6 text-slate-600">
                        <li>{{ t('Provide at least one IATA or ICAO code when possible.') }}</li>
                        <li>{{ t('Fill English names first, then Arabic and French translations.') }}</li>
                        <li>{{ t('Keep country ISO2 aligned with the country names.') }}</li>
                    </ul>
                </div>
            </aside>
        </section>
    </AdminLayout>
</template>
