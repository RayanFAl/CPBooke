<script setup>
import AdminLayout from '../../layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import { useAdminLocale } from '../../composables/useAdminLocale';

const props = defineProps({
    suppliers: { type: Object, required: true },
    filters: { type: Object, required: true },
    can_manage: { type: Boolean, default: false },
});

const { t } = useAdminLocale();

const filterForm = reactive({
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
});

const applyFilters = () => {
    router.get(route('admin.suppliers.index'), {
        ...(filterForm.search.trim() ? { search: filterForm.search.trim() } : {}),
        ...(filterForm.status ? { status: filterForm.status } : {}),
    }, { preserveState: true, preserveScroll: true, replace: true });
};

const openSupplier = (supplier) => {
    router.visit(route('admin.suppliers.show', supplier.id));
};
</script>

<template>
    <AdminLayout>
        <Head :title="t('Suppliers')" />

        <section class="space-y-6">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.25em] text-cyan-700">{{ t('Commerce') }}</p>
                        <h2 class="mt-3 text-2xl font-semibold text-slate-950">{{ t('Suppliers') }}</h2>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">
                            {{ t('Manage supplier partners: contracts, commission, settlement cycle, credit limits, and integration status.') }}
                        </p>
                    </div>
                    <Link
                        v-if="can_manage"
                        :href="route('admin.suppliers.create')"
                        class="inline-flex items-center justify-center rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-medium text-white hover:bg-slate-800"
                    >
                        {{ t('Add supplier') }}
                    </Link>
                </div>
            </div>

            <form class="flex flex-col gap-3 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm md:flex-row" @submit.prevent="applyFilters">
                <input
                    v-model="filterForm.search"
                    type="search"
                    class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"
                    :placeholder="t('Search name, key, or email')"
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
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3">{{ t('Supplier') }}</th>
                            <th class="px-4 py-3">{{ t('Commission') }}</th>
                            <th class="px-4 py-3">{{ t('Settlement') }}</th>
                            <th class="px-4 py-3">{{ t('Integration') }}</th>
                            <th class="px-4 py-3">{{ t('Wallets') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr
                            v-for="supplier in suppliers.data"
                            :key="supplier.id"
                            class="cursor-pointer hover:bg-cyan-50/50"
                            role="link"
                            tabindex="0"
                            :aria-label="`${t('Open')} ${supplier.name}`"
                            @click="openSupplier(supplier)"
                            @keydown.enter.prevent="openSupplier(supplier)"
                            @keydown.space.prevent="openSupplier(supplier)"
                        >
                            <td class="px-4 py-3">
                                <p class="font-medium text-slate-950">{{ supplier.name }}</p>
                                <p class="text-xs text-slate-500">{{ supplier.key }} · {{ supplier.status }}</p>
                            </td>
                            <td class="px-4 py-3">{{ supplier.commission_rate != null ? `${supplier.commission_rate}%` : '—' }}</td>
                            <td class="px-4 py-3">{{ supplier.settlement_cycle }}</td>
                            <td class="px-4 py-3">{{ supplier.integration_status }}</td>
                            <td class="px-4 py-3">{{ supplier.wallets_count }}</td>
                        </tr>
                        <tr v-if="suppliers.data.length === 0">
                            <td colspan="5" class="px-4 py-10 text-center text-slate-500">{{ t('No suppliers yet.') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </AdminLayout>
</template>
