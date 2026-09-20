<script setup>
import AdminLayout from '../../layouts/AdminLayout.vue';
import { useAdminLocale } from '../../composables/useAdminLocale';
import { Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    report: { type: Object, required: true },
    filters: { type: Object, required: true },
    print_url: { type: String, required: true },
    index_url: { type: String, required: true },
});

const { t, locale } = useAdminLocale();
const selectedDate = ref(props.filters.date ?? '');

const buyCards = computed(() => (props.report.buy_rates ?? []).map((rate) => ({
    currency: rate.currency_code,
    value: rate.buy_rate_to_lyd ?? '—',
})));

const salesCards = computed(() => props.report.sales_by_currency ?? []);

const formatDateTime = (value) => {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat(locale.value, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
};

const applyDate = () => {
    router.get(route('admin.exchange-rates.daily-report'), {
        date: selectedDate.value || undefined,
    }, {
        preserveScroll: true,
    });
};
</script>

<template>
    <AdminLayout
        :title="t('Daily FX buy + sales report')"
        :description="t('Buy rates for USD and EUR with paid booking sales for the selected day.')"
    >
        <section class="space-y-6">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.25em] text-cyan-700">{{ t('Reports') }}</p>
                        <h2 class="mt-3 text-2xl font-semibold text-slate-950">{{ t('Daily FX buy + sales report') }}</h2>
                        <p class="mt-3 max-w-3xl text-base leading-7 text-slate-700">
                            {{ t('Report day') }}: <span class="font-semibold text-slate-950">{{ report.report_date }}</span>
                            · {{ report.timezone }}
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Link
                            :href="index_url"
                            class="rounded-2xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                        >
                            {{ t('Exchange Rates') }}
                        </Link>
                        <a
                            :href="print_url"
                            target="_blank"
                            class="rounded-2xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800"
                        >
                            {{ t('Print / Save PDF') }}
                        </a>
                    </div>
                </div>

                <form class="mt-6 flex flex-wrap items-end gap-3" @submit.prevent="applyDate">
                    <label class="grid gap-1 text-sm text-slate-700">
                        <span>{{ t('Report day') }}</span>
                        <input
                            v-model="selectedDate"
                            type="date"
                            class="rounded-2xl border border-slate-200 px-3 py-2 text-slate-950"
                        >
                    </label>
                    <button
                        type="submit"
                        class="rounded-2xl bg-cyan-700 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-800"
                    >
                        {{ t('Apply') }}
                    </button>
                </form>
            </div>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div
                    v-for="card in buyCards"
                    :key="card.currency"
                    class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm"
                >
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{{ t('Buy rate') }} · {{ card.currency }}</p>
                    <p class="mt-3 text-3xl font-semibold text-slate-950">{{ card.value }}</p>
                    <p class="mt-1 text-sm text-slate-500">LYD</p>
                </div>
                <div
                    v-for="card in salesCards"
                    :key="`sales-${card.currency}`"
                    class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm"
                >
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{{ t('Sales') }} · {{ card.currency }}</p>
                    <p class="mt-3 text-3xl font-semibold text-slate-950">{{ card.total_amount }}</p>
                    <p class="mt-1 text-sm text-slate-500">{{ card.orders_count }} {{ t('orders') }}</p>
                </div>
                <div
                    v-if="salesCards.length === 0"
                    class="rounded-3xl border border-dashed border-slate-200 bg-slate-50 p-5"
                >
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{{ t('Sales') }}</p>
                    <p class="mt-3 text-lg font-semibold text-slate-700">{{ t('No paid orders for this day.') }}</p>
                </div>
            </div>

            <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-6 py-4">
                    <h3 class="text-lg font-semibold text-slate-950">{{ t('Paid orders') }}</h3>
                    <p class="mt-1 text-sm text-slate-600">{{ report.totals.orders_count }} {{ t('orders') }}</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-3">{{ t('ID') }}</th>
                                <th class="px-4 py-3">{{ t('Reference') }}</th>
                                <th class="px-4 py-3">{{ t('Customer') }}</th>
                                <th class="px-4 py-3">{{ t('Service') }}</th>
                                <th class="px-4 py-3">{{ t('Amount') }}</th>
                                <th class="px-4 py-3">{{ t('Method') }}</th>
                                <th class="px-4 py-3">{{ t('Updated') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="order in report.orders"
                                :key="order.id"
                                class="border-t border-slate-100 text-slate-700"
                            >
                                <td class="px-4 py-3 font-medium text-slate-950">{{ order.id }}</td>
                                <td class="px-4 py-3">{{ order.booking_reference || '—' }}</td>
                                <td class="px-4 py-3">{{ order.customer_name }}</td>
                                <td class="px-4 py-3">{{ order.service_type }}</td>
                                <td class="px-4 py-3">{{ order.amount }} {{ order.currency }}</td>
                                <td class="px-4 py-3">{{ order.payment_method || '—' }}</td>
                                <td class="px-4 py-3">{{ formatDateTime(order.updated_at) }}</td>
                            </tr>
                            <tr v-if="report.orders.length === 0">
                                <td colspan="7" class="px-4 py-8 text-center text-slate-500">
                                    {{ t('No paid orders for this day.') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </AdminLayout>
</template>
