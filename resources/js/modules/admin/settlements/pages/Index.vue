<script setup>
import AdminLayout from '../../layouts/AdminLayout.vue';
import AdminButton from '../../components/AdminButton.vue';
import AdminEmptyState from '../../components/AdminEmptyState.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import { useAdminLocale } from '../../composables/useAdminLocale';

const props = defineProps({
    settlements: { type: Object, required: true },
    filters: { type: Object, required: true },
    providers: { type: Array, default: () => [] },
    can_manage: { type: Boolean, default: false },
});

const { t, forwardArrow } = useAdminLocale();

const filterForm = reactive({
    status: props.filters.status ?? '',
    provider_id: props.filters.provider_id ?? '',
});

const applyFilters = () => {
    router.get(route('admin.settlements.index'), {
        ...(filterForm.status ? { status: filterForm.status } : {}),
        ...(filterForm.provider_id ? { provider_id: filterForm.provider_id } : {}),
    }, { preserveState: true, preserveScroll: true, replace: true });
};

const openSettlement = (row) => {
    router.visit(route('admin.settlements.show', row.id));
};
</script>

<template>
    <AdminLayout>
        <Head :title="t('Settlements')" />

        <section class="space-y-6">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.25em] text-cyan-700">{{ t('Finance') }}</p>
                        <h2 class="mt-3 text-2xl font-semibold text-slate-950">{{ t('Settlements') }}</h2>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">
                            {{ t('Compare expected supplier cost, wallet debits, and supplier invoices by period — then resolve and close.') }}
                        </p>
                    </div>
                    <Link
                        v-if="can_manage"
                        :href="route('admin.settlements.create')"
                        class="inline-flex items-center justify-center rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-medium text-white hover:bg-slate-800"
                    >
                        {{ t('Create period') }}
                    </Link>
                </div>
            </div>

            <form class="flex flex-col gap-3 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm md:flex-row" @submit.prevent="applyFilters">
                <select v-model="filterForm.provider_id" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                    <option value="">{{ t('All providers') }}</option>
                    <option v-for="provider in providers" :key="provider.id" :value="provider.id">
                        {{ provider.name }}
                    </option>
                </select>
                <select v-model="filterForm.status" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                    <option value="">{{ t('All statuses') }}</option>
                    <option value="draft">draft</option>
                    <option value="open">open</option>
                    <option value="pending_review">pending_review</option>
                    <option value="approved">approved</option>
                    <option value="closed">closed</option>
                    <option value="reopened">reopened</option>
                </select>
                <button type="submit" class="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-medium text-white hover:bg-slate-800">
                    {{ t('Filter') }}
                </button>
            </form>

            <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3">{{ t('Provider') }}</th>
                            <th class="px-4 py-3">{{ t('Period') }}</th>
                            <th class="px-4 py-3">{{ t('Expected') }}</th>
                            <th class="px-4 py-3">{{ t('Invoice') }}</th>
                            <th class="px-4 py-3">{{ t('Difference') }}</th>
                            <th class="px-4 py-3">{{ t('Review') }}</th>
                            <th class="px-4 py-3">{{ t('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr
                            v-for="row in settlements.data"
                            :key="row.id"
                            class="cursor-pointer hover:bg-cyan-50/50"
                            role="link"
                            tabindex="0"
                            :aria-label="`${t('Open')} ${row.provider_name}`"
                            @click="openSettlement(row)"
                            @keydown.enter.prevent="openSettlement(row)"
                            @keydown.space.prevent="openSettlement(row)"
                        >
                            <td class="px-4 py-3">
                                <p class="font-medium text-slate-950">{{ row.provider_name }}</p>
                                <p class="text-xs text-slate-500">{{ row.provider_key }} · {{ row.currency }}</p>
                            </td>
                            <td class="px-4 py-3 text-slate-700">{{ row.period_start }} {{ forwardArrow }} {{ row.period_end }}</td>
                            <td class="px-4 py-3">{{ row.expected_cost }}</td>
                            <td class="px-4 py-3">{{ row.supplier_invoice_total }}</td>
                            <td class="px-4 py-3" :class="Number(row.difference) !== 0 ? 'text-amber-700' : 'text-slate-700'">
                                {{ row.difference }}
                            </td>
                            <td class="px-4 py-3">{{ row.review_count }} / {{ row.orders_count }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium uppercase tracking-wide text-slate-700">
                                    {{ row.status }}
                                </span>
                            </td>
                        </tr>
                        <tr v-if="settlements.data.length === 0">
                            <td colspan="7" class="px-4 py-6">
                                <AdminEmptyState
                                    title="No settlements yet."
                                    description="Create a settlement when you are ready to reconcile provider invoices."
                                >
                                    <template v-if="can_manage" #action>
                                        <Link :href="route('admin.settlements.create')">
                                            <AdminButton size="sm">
                                                {{ t('Create period') }}
                                            </AdminButton>
                                        </Link>
                                    </template>
                                </AdminEmptyState>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </AdminLayout>
</template>
