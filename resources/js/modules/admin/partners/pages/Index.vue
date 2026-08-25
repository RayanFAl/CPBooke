<script setup>
import AdminLayout from '../../layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import { useAdminLocale } from '../../composables/useAdminLocale';

const props = defineProps({
    partners: { type: Object, required: true },
    filters: { type: Object, required: true },
    can_manage: { type: Boolean, default: false },
});

const { t } = useAdminLocale();

const filterForm = reactive({
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
});

const applyFilters = () => {
    router.get(route('admin.partners.index'), {
        ...(filterForm.search.trim() ? { search: filterForm.search.trim() } : {}),
        ...(filterForm.status ? { status: filterForm.status } : {}),
    }, { preserveState: true, preserveScroll: true, replace: true });
};

const openPartner = (partner) => {
    router.visit(route('admin.partners.show', partner.id));
};

const statusLabel = (status) => {
    const labels = {
        active: 'Active',
        inactive: 'Inactive',
    };

    return t(labels[status] || status);
};
</script>

<template>
    <AdminLayout>
        <Head :title="t('Partners')" />

        <section class="space-y-6">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.25em] text-cyan-700">{{ t('Integrations') }}</p>
                        <h2 class="mt-3 text-2xl font-semibold text-slate-950">{{ t('Partners') }}</h2>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">
                            {{ t('Manage partner API keys and outbound webhooks for order and refund events.') }}
                        </p>
                    </div>
                    <Link
                        v-if="can_manage"
                        :href="route('admin.partners.create')"
                        class="inline-flex items-center justify-center rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-medium text-white hover:bg-slate-800"
                    >
                        {{ t('Add partner') }}
                    </Link>
                </div>
            </div>

            <form class="flex flex-col gap-3 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm md:flex-row" @submit.prevent="applyFilters">
                <input
                    v-model="filterForm.search"
                    type="search"
                    class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"
                    :placeholder="t('Search name, slug, or email')"
                >
                <select v-model="filterForm.status" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                    <option value="">{{ t('All statuses') }}</option>
                    <option value="active">{{ t('Active') }}</option>
                    <option value="inactive">{{ t('Inactive') }}</option>
                </select>
                <button type="submit" class="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-medium text-white hover:bg-slate-800">
                    {{ t('Search') }}
                </button>
            </form>

            <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-start text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3">{{ t('Partner') }}</th>
                            <th class="px-4 py-3">{{ t('Status') }}</th>
                            <th class="px-4 py-3">{{ t('API keys') }}</th>
                            <th class="px-4 py-3">{{ t('Webhooks') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr
                            v-for="partner in partners.data"
                            :key="partner.id"
                            class="cursor-pointer hover:bg-cyan-50/50"
                            role="link"
                            tabindex="0"
                            @click="openPartner(partner)"
                            @keydown.enter.prevent="openPartner(partner)"
                        >
                            <td class="px-4 py-3">
                                <p class="font-medium text-slate-950">{{ partner.name }}</p>
                                <p class="text-xs text-slate-500">{{ partner.slug }}</p>
                            </td>
                            <td class="px-4 py-3">{{ statusLabel(partner.status) }}</td>
                            <td class="px-4 py-3">{{ partner.api_keys_count }}</td>
                            <td class="px-4 py-3">{{ partner.webhooks_count }}</td>
                        </tr>
                        <tr v-if="!partners.data.length">
                            <td colspan="4" class="px-4 py-8 text-center text-slate-500">{{ t('No partners yet.') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </AdminLayout>
</template>
