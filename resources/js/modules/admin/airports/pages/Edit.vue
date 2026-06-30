<script setup>
import AdminLayout from '../../layouts/AdminLayout.vue';
import AirportFormFields from '../components/AirportFormFields.vue';
import AirportFeaturedStar from '../components/AirportFeaturedStar.vue';
import { airportFormFromRecord } from '../utils/airportForm';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { useAdminLocale } from '../../composables/useAdminLocale';

const props = defineProps({
    airport: {
        type: Object,
        required: true,
    },
    usesFullSchema: {
        type: Boolean,
        default: true,
    },
    isFeatured: {
        type: Boolean,
        default: false,
    },
    featuredOrder: {
        type: Number,
        default: null,
    },
    featuredCount: {
        type: Number,
        default: 0,
    },
    maxFeaturedAirports: {
        type: Number,
        default: 10,
    },
});

const { t } = useAdminLocale();

const form = useForm(
    props.usesFullSchema
        ? airportFormFromRecord(props.airport)
        : {
            name: props.airport.name ?? '',
            code: props.airport.code ?? '',
            city: props.airport.city ?? '',
            country: props.airport.country ?? '',
        },
);

const submit = () => {
    form.put(route('admin.airports.update', props.airport.airport_key), {
        preserveScroll: true,
    });
};

const destroyAirport = () => {
    if (!window.confirm(t('Confirm airport deletion?'))) {
        return;
    }

    router.delete(route('admin.airports.destroy', props.airport.airport_key));
};
</script>

<template>
    <Head :title="t('Edit airport')" />

    <AdminLayout
        title="Edit airport"
        description="View all airport details and update any field from one screen."
    >
        <section class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-2xl font-semibold text-slate-950">{{ t('Edit airport record') }}</h2>
                <p class="mt-2 text-sm text-slate-600">{{ t('All airport details are shown below and can be edited.') }}</p>

                <form class="mt-8 space-y-6" @submit.prevent="submit">
                    <AirportFormFields v-if="usesFullSchema" :form="form" />

                    <template v-else>
                        <div>
                            <label class="text-sm font-medium text-slate-700">{{ t('Name') }}</label>
                            <input v-model="form.name" type="text" class="mt-2 block w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm" required>
                        </div>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="text-sm font-medium text-slate-700">{{ t('Code') }}</label>
                                <input v-model="form.code" type="text" class="mt-2 block w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm">
                            </div>
                            <div>
                                <label class="text-sm font-medium text-slate-700">{{ t('City') }}</label>
                                <input v-model="form.city" type="text" class="mt-2 block w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm">
                            </div>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-slate-700">{{ t('Country') }}</label>
                            <input v-model="form.country" type="text" class="mt-2 block w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm">
                        </div>
                    </template>

                    <div class="flex flex-wrap items-center gap-3">
                        <button
                            type="submit"
                            class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-800"
                            :disabled="form.processing"
                        >
                            {{ t('Update') }}
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
                <div
                    v-if="usesFullSchema"
                    class="rounded-3xl border border-amber-200 bg-amber-50/40 p-6 shadow-sm"
                >
                    <h3 class="text-lg font-semibold text-slate-950">{{ t('Best locations') }}</h3>
                    <p class="mt-2 text-sm text-slate-600">{{ t('Pin this airport to show it first in the mobile app.') }}</p>
                    <div class="mt-4">
                        <AirportFeaturedStar
                            :airport-key="props.airport.airport_key"
                            :is-featured="isFeatured"
                            :featured-order="featuredOrder"
                            :featured-count="featuredCount"
                            :max-featured="maxFeaturedAirports"
                            show-label
                        />
                    </div>
                </div>

                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-slate-950">{{ t('Record info') }}</h3>
                    <p class="mt-3 text-sm text-slate-600">
                        {{ t('Airport ID') }}: {{ props.airport.airport_key }}
                    </p>
                </div>

                <div class="rounded-3xl border border-rose-200 bg-rose-50/60 p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-rose-950">{{ t('Danger zone') }}</h3>
                    <p class="mt-2 text-sm leading-6 text-rose-800/80">
                        {{ t('Deleting this airport removes it from the directory permanently.') }}
                    </p>
                    <button
                        type="button"
                        class="mt-4 w-full rounded-xl border border-rose-300 bg-white px-4 py-2.5 text-sm font-semibold text-rose-700 transition hover:bg-rose-100"
                        @click="destroyAirport"
                    >
                        {{ t('Delete airport') }}
                    </button>
                </div>
            </aside>
        </section>
    </AdminLayout>
</template>
