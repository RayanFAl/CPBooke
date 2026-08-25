<script setup>
import AdminLayout from '../../layouts/AdminLayout.vue';
import AdminButton from '../../components/AdminButton.vue';
import AdminInput from '../../components/AdminInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useAdminLocale } from '../../composables/useAdminLocale';

const props = defineProps({
    customers: { type: Array, required: true },
    selected_customer_id: { type: Number, default: null },
    service_types: { type: Array, required: true },
    default_currency: { type: String, required: true },
});

const { t, backArrow } = useAdminLocale();

const form = useForm({
    customer_id: props.selected_customer_id || props.customers[0]?.id || '',
    service_type: 'flight',
    booking_reference: '',
    provider_name: 'BookNow',
    currency: props.default_currency || 'LYD',
    total_amount: '',
    payment_status: 'paid',
    payment_method: 'cash',
    passenger_name: '',
    origin: '',
    destination: '',
    departure_date: '',
    return_date: '',
    hotel_name: '',
    check_in: '',
    check_out: '',
    insurance_type: '',
    internal_notes: '',
});

const isFlight = computed(() => form.service_type === 'flight');
const isHotel = computed(() => form.service_type === 'hotel');
const isInsurance = computed(() => form.service_type === 'insurance');
const isPaid = computed(() => form.payment_status === 'paid');

const serviceLabel = (value) => t(String(value).replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase()));

const submit = () => {
    form.post(route('admin.orders.store'), { preserveScroll: true });
};
</script>

<template>
    <Head :title="t('Record booking')" />

    <AdminLayout
        title="Record booking"
        description="Log a booking made by phone or at the desk. This does not ticket through the provider."
    >
        <section class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.25em] text-cyan-700">
                    {{ t('Manual booking') }}
                </p>
                <h2 class="mt-2 text-2xl font-semibold text-slate-950">{{ t('Book for a customer') }}</h2>
                <p class="mt-2 text-sm leading-6 text-slate-600">
                    {{ t('Use this after you already issued a PNR or voucher. The Control Panel stores the booking; it does not call BookNow to buy a ticket.') }}
                </p>

                <form class="mt-8 space-y-5" @submit.prevent="submit">
                    <label class="block space-y-2 text-sm font-medium text-slate-700">
                        <span>{{ t('Customer') }}</span>
                        <select
                            v-model="form.customer_id"
                            required
                            class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-cyan-600"
                        >
                            <option value="" disabled>{{ t('Select a customer') }}</option>
                            <option v-for="customer in customers" :key="customer.id" :value="customer.id">
                                {{ customer.name }} — {{ customer.phone || customer.email }}
                            </option>
                        </select>
                        <p v-if="form.errors.customer_id" class="text-sm text-rose-600">{{ form.errors.customer_id }}</p>
                    </label>

                    <div class="grid gap-5 md:grid-cols-2">
                        <label class="block space-y-2 text-sm font-medium text-slate-700">
                            <span>{{ t('Service') }}</span>
                            <select v-model="form.service_type" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-cyan-600">
                                <option v-for="type in service_types" :key="type" :value="type">{{ serviceLabel(type) }}</option>
                            </select>
                        </label>
                        <AdminInput v-model="form.booking_reference" :label="t('PNR / booking reference')" required :error="form.errors.booking_reference" />
                    </div>

                    <AdminInput v-model="form.passenger_name" :label="t('Passenger / guest name')" required :error="form.errors.passenger_name" />

                    <div v-if="isFlight" class="grid gap-5 md:grid-cols-2">
                        <AdminInput v-model="form.origin" :label="t('Origin')" placeholder="MJI" :error="form.errors.origin" />
                        <AdminInput v-model="form.destination" :label="t('Destination')" placeholder="IST" :error="form.errors.destination" />
                        <AdminInput v-model="form.departure_date" type="date" :label="t('Departure date')" :error="form.errors.departure_date" />
                        <AdminInput v-model="form.return_date" type="date" :label="t('Return date')" :error="form.errors.return_date" />
                    </div>

                    <div v-if="isHotel" class="grid gap-5 md:grid-cols-2">
                        <AdminInput v-model="form.hotel_name" :label="t('Hotel name')" :error="form.errors.hotel_name" />
                        <span />
                        <AdminInput v-model="form.check_in" type="date" :label="t('Check-in')" :error="form.errors.check_in" />
                        <AdminInput v-model="form.check_out" type="date" :label="t('Check-out')" :error="form.errors.check_out" />
                    </div>

                    <AdminInput v-if="isInsurance" v-model="form.insurance_type" :label="t('Insurance type')" :error="form.errors.insurance_type" />

                    <div class="grid gap-5 md:grid-cols-2">
                        <AdminInput v-model="form.total_amount" type="number" step="0.01" min="0.01" :label="t('Amount')" required :error="form.errors.total_amount" />
                        <AdminInput v-model="form.currency" :label="t('Currency')" required :error="form.errors.currency" />
                    </div>

                    <div class="grid gap-5 md:grid-cols-2">
                        <label class="block space-y-2 text-sm font-medium text-slate-700">
                            <span>{{ t('Payment') }}</span>
                            <select v-model="form.payment_status" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-cyan-600">
                                <option value="paid">{{ t('Paid') }}</option>
                                <option value="unpaid">{{ t('Unpaid') }}</option>
                            </select>
                        </label>
                        <label v-if="isPaid" class="block space-y-2 text-sm font-medium text-slate-700">
                            <span>{{ t('Payment method') }}</span>
                            <select v-model="form.payment_method" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-cyan-600">
                                <option value="cash">{{ t('Cash') }}</option>
                                <option value="card">{{ t('Card') }}</option>
                                <option value="bank">{{ t('Bank transfer') }}</option>
                                <option value="wallet">{{ t('Customer wallet') }}</option>
                            </select>
                        </label>
                    </div>

                    <label class="block space-y-2 text-sm font-medium text-slate-700">
                        <span>{{ t('Internal notes') }}</span>
                        <textarea
                            v-model="form.internal_notes"
                            rows="3"
                            class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-cyan-600"
                        />
                    </label>

                    <div class="flex flex-wrap gap-3">
                        <AdminButton type="submit" :processing="form.processing">{{ t('Save booking') }}</AdminButton>
                        <Link :href="route('admin.orders.index')" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                            {{ backArrow }} {{ t('Cancel') }}
                        </Link>
                    </div>
                </form>
            </div>

            <aside class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-slate-950">{{ t('Before you save') }}</h3>
                <ul class="mt-4 space-y-3 text-sm leading-6 text-slate-600">
                    <li>{{ t('The PNR must already exist with the airline or hotel.') }}</li>
                    <li>{{ t('Wallet payment deducts the customer wallet immediately.') }}</li>
                    <li>{{ t('This is for phone and desk bookings, not a replacement for the mobile app.') }}</li>
                </ul>
            </aside>
        </section>
    </AdminLayout>
</template>
