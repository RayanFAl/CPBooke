<script setup>
import AdminLayout from '../../layouts/AdminLayout.vue';
import AdminButton from '../../components/AdminButton.vue';
import AdminEmptyState from '../../components/AdminEmptyState.vue';
import AdminInput from '../../components/AdminInput.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useAdminLocale } from '../../composables/useAdminLocale';

const props = defineProps({
    result: { type: Object, required: true },
    q: { type: String, default: '' },
});

const { t } = useAdminLocale();
const query = ref(props.q || '');

const groupLabels = {
    orders: 'Orders',
    customers: 'Customers',
    support_tickets: 'Support Tickets',
    wallet_transactions: 'Wallet Transactions',
    settlements: 'Settlements',
    passengers: 'Passengers',
};

const search = () => {
    router.get(route('admin.search.index'), {
        ...(query.value.trim() ? { q: query.value.trim() } : {}),
    }, { preserveState: true, replace: true });
};
</script>

<template>
    <AdminLayout>
        <Head :title="t('Global Search')" />

        <section class="space-y-6">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.25em] text-cyan-700">{{ t('Operations') }}</p>
                <h2 class="mt-3 text-2xl font-semibold text-slate-950">{{ t('Global Search') }}</h2>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                    {{ t('Search booking references, PNRs, orders, customers, tickets, wallets, and settlements in one place.') }}
                </p>

                <form class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-end" @submit.prevent="search">
                    <AdminInput
                        v-model="query"
                        type="search"
                        :placeholder="t('Booking ref, PNR, order id, phone, email, passport, ticket…')"
                    />
                    <AdminButton type="submit" size="lg">
                        {{ t('Search') }}
                    </AdminButton>
                </form>
            </div>

            <AdminEmptyState
                v-if="!q"
                title="Enter at least 2 characters to search across operational records."
                description="Search booking references, PNRs, orders, customers, tickets, wallets, and settlements in one place."
                icon="search"
            />

            <AdminEmptyState
                v-else-if="result.total === 0"
                :title="`${t('No results for')} “${q}”`"
                description="Try a different booking reference, customer email, phone number, or ticket identifier."
                icon="search"
            />

            <div v-else class="space-y-4">
                <p class="text-sm text-slate-600">{{ result.total }} {{ t('results') }}</p>

                <div
                    v-for="(items, group) in result.groups"
                    :key="group"
                    class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm"
                >
                    <h3 class="text-sm font-semibold text-slate-950">{{ t(groupLabels[group] || group) }}</h3>
                    <ul class="mt-4 divide-y divide-slate-100">
                        <li v-for="item in items" :key="`${item.type}-${item.id}`" class="py-3">
                            <Link
                                v-if="item.url"
                                :href="item.url"
                                class="block rounded-xl px-2 py-1 hover:bg-slate-50"
                            >
                                <div class="font-medium text-slate-900">{{ item.title }}</div>
                                <div class="mt-1 text-sm text-slate-600">{{ item.subtitle || '—' }}</div>
                            </Link>
                            <div v-else class="px-2 py-1">
                                <div class="font-medium text-slate-900">{{ item.title }}</div>
                                <div class="mt-1 text-sm text-slate-600">{{ item.subtitle || '—' }}</div>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </section>
    </AdminLayout>
</template>
