<script setup>
import AdminLayout from '../../layouts/AdminLayout.vue';
import AdminButton from '../../components/AdminButton.vue';
import AdminInput from '../../components/AdminInput.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';
import { useAdminLocale } from '../../composables/useAdminLocale';

const props = defineProps({
    preselected_customer: {
        type: Object,
        default: null,
    },
    customers: {
        type: Array,
        default: () => [],
    },
    service_types: {
        type: Array,
        required: true,
    },
    default_currency: {
        type: String,
        default: 'LYD',
    },
    hotel_lookup_configured: {
        type: Boolean,
        default: false,
    },
});

const { t, isArabic } = useAdminLocale();

const step = ref(props.preselected_customer ? 2 : 1);
const customerQuery = ref('');
const customerResults = ref([...props.customers]);
const customerBusy = ref(false);
const selectedCustomer = ref(props.preselected_customer);
const hotelBusy = ref('');
const hotelError = ref('');
const cities = ref([]);
const hotels = ref([]);
const offers = ref([]);
const searchUuid = ref('');
let customerSearchTimer = null;
let citySearchTimer = null;

const form = useForm({
    customer_id: props.preselected_customer?.id ?? '',
    service_type: 'hotel',
    provider_name: 'BookNow Hotels',
    currency: props.default_currency || 'LYD',
    total_amount: '',
    external_booking_id: '',
    mark_confirmed: false,
    internal_notes: '',
    booking_notes: '',
    contact_first_name: props.preselected_customer?.name?.split(' ')?.[0] ?? '',
    contact_last_name: props.preselected_customer?.name?.split(' ')?.slice(1).join(' ') ?? '',
    contact_email: props.preselected_customer?.email ?? '',
    contact_phone: props.preselected_customer?.phone ?? '',
    customer_country: 'Libya',
    customer_city: '',
    guest_civility: 'Mr',
    hotel_name: '',
    hotel_id: '',
    city_name: '',
    city_id: '',
    check_in: '',
    check_out: '',
    rooms: 1,
    adults: 2,
    offer_id: '',
    offer_label: '',
    rate_key: '',
    search_uuid: '',
    search_code: '',
    hotel_source: '',
    board: '',
    origin: '',
    destination: '',
    departure_date: '',
    return_date: '',
    airline: '',
    pnr: '',
    passengers_count: 1,
    plan_name: '',
    zone_name: '',
    coverage_start: '',
    coverage_end: '',
    travellers_count: 1,
    country: '',
    data_amount: '',
    validity_days: '',
});

const breadcrumbs = [
    { label: 'Dashboard', href: route('admin.dashboard') },
    { label: 'Orders', href: route('admin.orders.index') },
    { label: 'Book for customer', current: true },
];

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

const steps = computed(() => [
    { id: 1, label: t('Customer') },
    { id: 2, label: t('Booking') },
    { id: 3, label: t('Confirm') },
]);

const hotelReady = computed(() => Boolean(
    form.hotel_name
    && form.hotel_id
    && form.city_name
    && form.city_id
    && form.check_in
    && form.check_out
    && form.rate_key
    && form.search_uuid
    && form.offer_id
    && form.total_amount,
));

const canGoNext = computed(() => {
    if (step.value === 1) {
        return Boolean(selectedCustomer.value?.id);
    }

    if (step.value === 2) {
        if (form.service_type === 'hotel') {
            return hotelReady.value;
        }

        if (!form.total_amount || !form.contact_first_name) {
            return false;
        }

        if (form.service_type === 'flight') {
            return Boolean(form.origin && form.destination && form.departure_date);
        }

        if (form.service_type === 'insurance') {
            return Boolean(form.plan_name && form.coverage_start && form.coverage_end);
        }

        if (form.service_type === 'esim') {
            return Boolean(form.plan_name && form.country);
        }
    }

    return true;
});

const selectCustomer = (customer) => {
    selectedCustomer.value = customer;
    form.customer_id = customer.id;
    form.contact_first_name = customer.name?.split(' ')?.[0] || '';
    form.contact_last_name = customer.name?.split(' ')?.slice(1).join(' ') || '';
    form.contact_email = customer.email || '';
    form.contact_phone = customer.phone || '';
    customerQuery.value = '';
    step.value = 2;
};

const clearCustomer = () => {
    selectedCustomer.value = null;
    form.customer_id = '';
    customerQuery.value = '';
    step.value = 1;
    loadCustomers();
};

const loadCustomers = async (query = '') => {
    customerBusy.value = true;

    try {
        const params = query.trim() !== '' ? `?q=${encodeURIComponent(query.trim())}` : '';
        const response = await fetch(`${route('admin.orders.book.customers')}${params}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        const payload = await response.json();
        customerResults.value = payload?.data?.customers ?? [];
    } catch {
        customerResults.value = query.trim() ? [] : [...props.customers];
    } finally {
        customerBusy.value = false;
    }
};

watch(customerQuery, () => {
    clearTimeout(customerSearchTimer);
    customerSearchTimer = setTimeout(() => loadCustomers(customerQuery.value), 250);
});

onMounted(() => {
    if (!selectedCustomer.value) {
        loadCustomers();
    }
});

const goNext = () => {
    if (!canGoNext.value || step.value >= 3) {
        return;
    }
    step.value += 1;
};

const goBack = () => {
    if (step.value <= 1) {
        return;
    }
    step.value -= 1;
};

const cityDisplay = (city) => {
    if (!city) {
        return '';
    }

    if (isArabic.value) {
        return city.city_ar || city.city;
    }

    return city.city_ar ? `${city.city} (${city.city_ar})` : city.city;
};

const searchCities = async () => {
    const query = form.city_name.trim();

    if (query.length < 2 || !props.hotel_lookup_configured) {
        cities.value = [];
        return;
    }

    hotelBusy.value = 'cities';
    hotelError.value = '';

    try {
        const response = await fetch(`${route('admin.orders.book.hotel-lookup.cities')}?q=${encodeURIComponent(query)}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        const payload = await response.json();
        cities.value = payload?.data?.cities ?? [];
        if (!payload?.success) {
            hotelError.value = payload?.message || t('City search failed.');
        }
    } catch {
        hotelError.value = t('City search failed.');
        cities.value = [];
    } finally {
        hotelBusy.value = '';
    }
};

watch(() => form.city_name, () => {
    if (form.service_type !== 'hotel' || form.city_id) {
        return;
    }

    clearTimeout(citySearchTimer);
    citySearchTimer = setTimeout(searchCities, 300);
});

const clearHotelSelection = () => {
    form.hotel_id = '';
    form.hotel_name = '';
    form.offer_id = '';
    form.offer_label = '';
    form.rate_key = '';
    form.search_uuid = '';
    form.search_code = '';
    form.hotel_source = '';
    form.board = '';
    form.total_amount = '';
    hotels.value = [];
    offers.value = [];
    searchUuid.value = '';
};

const pickCity = (city) => {
    form.city_name = city.city;
    form.city_id = city.city_id;
    form.customer_city = city.city;
    cities.value = [];
    clearHotelSelection();
};

const searchHotels = async () => {
    if (!form.city_id || !form.check_in || !form.check_out) {
        hotelError.value = t('Select a city and dates first.');
        return;
    }

    hotelBusy.value = 'hotels';
    hotelError.value = '';
    offers.value = [];
    form.offer_id = '';
    form.offer_label = '';
    form.rate_key = '';
    form.hotel_id = '';
    form.hotel_name = '';
    form.total_amount = '';

    try {
        const response = await fetch(route('admin.orders.book.hotel-lookup.hotels'), {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                city: form.city_name,
                city_id: form.city_id,
                check_in: form.check_in,
                check_out: form.check_out,
                adults: form.adults || 2,
            }),
        });
        const payload = await response.json();
        hotels.value = payload?.data?.hotels ?? [];
        searchUuid.value = payload?.data?.search_uuid || '';
        form.search_uuid = searchUuid.value;

        if (!payload?.success) {
            hotelError.value = payload?.message || t('Hotel search failed.');
        } else if (!hotels.value.length) {
            hotelError.value = t('No hotels found for these dates.');
        }
    } catch {
        hotelError.value = t('Hotel search failed.');
        hotels.value = [];
    } finally {
        hotelBusy.value = '';
    }
};

const pickHotel = async (hotel) => {
    form.hotel_id = hotel.hotel_id || hotel.id || '';
    form.hotel_name = hotel.hotel_name || hotel.name || '';
    form.hotel_source = hotel.source || '';
    form.search_uuid = hotel.search_uuid || searchUuid.value || form.search_uuid || '';
    form.search_code = hotel.search_code || form.search_code || '';
    form.offer_id = '';
    form.offer_label = '';
    form.rate_key = '';
    form.board = '';
    form.total_amount = '';
    hotels.value = [];

    const searchOffers = Array.isArray(hotel.offers) ? hotel.offers : [];
    if (searchOffers.length) {
        offers.value = searchOffers;
        return;
    }

    if (!form.hotel_id || !form.city_id) {
        return;
    }

    hotelBusy.value = 'offers';
    hotelError.value = '';

    try {
        const params = new URLSearchParams({
            hotel_id: form.hotel_id,
            city_id: form.city_id,
            city: form.city_name || '',
            check_in: form.check_in || '',
            check_out: form.check_out || '',
            source: hotel.source || '',
        });
        const response = await fetch(`${route('admin.orders.book.hotel-lookup.offers')}?${params}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        const payload = await response.json();
        offers.value = (payload?.data?.offers ?? []).map((offer) => ({
            ...offer,
            search_uuid: offer.search_uuid || form.search_uuid,
        }));
        if (!payload?.success) {
            hotelError.value = payload?.message || t('Offer search failed.');
        }
    } catch {
        hotelError.value = t('Offer search failed.');
        offers.value = [];
    } finally {
        hotelBusy.value = '';
    }
};

const pickOffer = (offer) => {
    form.offer_id = offer.offer_id || offer.id || '';
    form.rate_key = offer.rate_key || offer.offer_id || offer.id || '';
    form.search_uuid = offer.search_uuid || form.search_uuid || searchUuid.value || '';
    form.search_code = offer.search_code || form.search_code || '';
    form.offer_label = offer.label || offer.name || offer.room_name || form.offer_id;
    form.board = offer.board || '';
    if (offer.currency) {
        form.currency = offer.currency;
    }
    if (offer.price || offer.total || offer.amount) {
        form.total_amount = String(offer.price || offer.total || offer.amount);
    }
    offers.value = [];
    step.value = 3;
};

const submit = () => {
    form.post(route('admin.orders.book.store'), {
        preserveScroll: true,
    });
};

const serviceLabel = (type) => t(String(type).charAt(0).toUpperCase() + String(type).slice(1));

const changeService = (name) => {
    form.service_type = name;
    clearHotelSelection();
    cities.value = [];
};
</script>

<template>
    <Head :title="t('Book for customer')" />

    <AdminLayout
        title="Book for customer"
        description="Choose a customer, pick a hotel, and book."
        :breadcrumbs="breadcrumbs"
    >
        <section class="mx-auto max-w-3xl space-y-5">
            <div class="flex gap-2">
                <div
                    v-for="item in steps"
                    :key="item.id"
                    class="flex-1 rounded-xl px-3 py-2.5 text-center text-sm font-medium"
                    :class="step === item.id
                        ? 'bg-[#2f6db8] text-white'
                        : step > item.id
                            ? 'bg-[#2f6db8]/10 text-[#2f6db8]'
                            : 'bg-slate-100 text-slate-400'"
                >
                    {{ item.id }}. {{ item.label }}
                </div>
            </div>

            <!-- Step 1: Customer -->
            <div v-if="step === 1" class="admin-panel space-y-4 p-5 sm:p-6">
                <div>
                    <h2 class="text-xl font-semibold tracking-tight text-slate-950">{{ t('Select customer') }}</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ t('Tap a customer to continue.') }}</p>
                </div>

                <AdminInput
                    v-model="customerQuery"
                    :label="t('Search')"
                    :placeholder="t('Name, email, or phone')"
                />

                <p v-if="customerBusy" class="text-sm text-slate-500">{{ t('Loading…') }}</p>

                <div
                    v-else-if="customerResults.length"
                    class="max-h-96 divide-y divide-slate-100 overflow-y-auto rounded-xl border border-slate-200"
                >
                    <button
                        v-for="customer in customerResults"
                        :key="customer.id"
                        type="button"
                        class="admin-row-hover block w-full px-4 py-3.5 text-start"
                        @click="selectCustomer(customer)"
                    >
                        <p class="font-medium text-slate-950">{{ customer.name }}</p>
                        <p class="mt-0.5 text-sm text-slate-500">{{ customer.email }} · {{ customer.phone || t('No phone') }}</p>
                    </button>
                </div>

                <p v-else class="rounded-xl border border-dashed border-slate-200 px-4 py-8 text-center text-sm text-slate-500">
                    {{ customerQuery.trim() ? t('No customers match your search.') : t('No customers found.') }}
                </p>
            </div>

            <!-- Step 2: Booking -->
            <div v-else-if="step === 2" class="admin-panel space-y-5 p-5 sm:p-6">
                <div class="flex items-start justify-between gap-3 rounded-xl bg-slate-50 px-4 py-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">{{ t('Customer') }}</p>
                        <p class="mt-1 font-semibold text-slate-950">{{ selectedCustomer?.name }}</p>
                        <p class="text-sm text-slate-500">{{ selectedCustomer?.email }}</p>
                    </div>
                    <AdminButton variant="secondary" size="sm" @click="clearCustomer">
                        {{ t('Change') }}
                    </AdminButton>
                </div>

                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="service in service_types"
                        :key="service.name"
                        type="button"
                        class="rounded-lg px-3 py-1.5 text-sm font-medium transition"
                        :class="form.service_type === service.name
                            ? 'bg-[#2f6db8] text-white'
                            : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                        @click="changeService(service.name)"
                    >
                        {{ serviceLabel(service.name) }}
                    </button>
                </div>

                <template v-if="form.service_type === 'hotel'">
                    <div class="space-y-4">
                        <AdminInput
                            v-model="form.city_name"
                            :label="t('City')"
                            :placeholder="t('Type a city name')"
                            @update:model-value="form.city_id = ''"
                        />

                        <div v-if="cities.length" class="divide-y divide-slate-100 overflow-hidden rounded-xl border border-slate-200">
                            <button
                                v-for="city in cities"
                                :key="`${city.city_id}-${city.city}`"
                                type="button"
                                class="admin-row-hover block w-full px-3 py-2.5 text-start text-sm"
                                @click="pickCity(city)"
                            >
                                {{ cityDisplay(city) }}
                                <span v-if="city.country" class="text-slate-500"> · {{ city.country }}</span>
                            </button>
                        </div>

                        <div v-if="form.city_id" class="rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                            {{ form.city_name }}
                        </div>

                        <div class="grid gap-3 sm:grid-cols-3">
                            <AdminInput v-model="form.check_in" type="date" :label="t('Check-in')" />
                            <AdminInput v-model="form.check_out" type="date" :label="t('Check-out')" />
                            <AdminInput v-model="form.adults" type="number" min="1" :label="t('Adults')" />
                        </div>

                        <AdminButton
                            :disabled="!hotel_lookup_configured || !form.city_id || !form.check_in || !form.check_out || hotelBusy === 'hotels'"
                            @click="searchHotels"
                        >
                            {{ hotelBusy === 'hotels' ? t('Searching…') : t('Search hotels') }}
                        </AdminButton>

                        <p v-if="hotelError" class="text-sm text-rose-600">{{ hotelError }}</p>

                        <div v-if="hotels.length" class="space-y-2">
                            <p class="text-sm font-semibold text-slate-900">{{ t('Choose hotel') }}</p>
                            <div class="max-h-64 space-y-2 overflow-y-auto">
                                <button
                                    v-for="hotel in hotels"
                                    :key="hotel.hotel_id || hotel.id"
                                    type="button"
                                    class="admin-row-hover block w-full rounded-xl border border-slate-200 px-3 py-3 text-start"
                                    @click="pickHotel(hotel)"
                                >
                                    <p class="font-medium text-slate-950">{{ hotel.hotel_name || hotel.name }}</p>
                                </button>
                            </div>
                        </div>

                        <div v-if="offers.length" class="space-y-2">
                            <p class="text-sm font-semibold text-slate-900">{{ t('Choose room') }}</p>
                            <div class="max-h-64 space-y-2 overflow-y-auto">
                                <button
                                    v-for="offer in offers"
                                    :key="offer.offer_id || offer.rate_key || offer.id"
                                    type="button"
                                    class="admin-row-hover block w-full rounded-xl border border-slate-200 px-3 py-3 text-start"
                                    @click="pickOffer(offer)"
                                >
                                    <p class="font-medium text-slate-950">{{ offer.label || offer.name || offer.room_name }}</p>
                                    <p v-if="offer.price || offer.total || offer.amount" class="mt-1 text-sm text-slate-600">
                                        {{ offer.price || offer.total || offer.amount }} {{ offer.currency || form.currency }}
                                    </p>
                                </button>
                            </div>
                        </div>

                        <div
                            v-if="form.hotel_name && form.rate_key"
                            class="rounded-xl border border-emerald-200 bg-emerald-50/60 px-4 py-3"
                        >
                            <p class="font-semibold text-slate-950">{{ form.hotel_name }}</p>
                            <p class="mt-1 text-sm text-slate-600">{{ form.offer_label }}</p>
                            <p class="mt-1 text-sm font-medium text-slate-900">{{ form.total_amount }} {{ form.currency }}</p>
                        </div>
                    </div>
                </template>

                <template v-else-if="form.service_type === 'flight'">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <AdminInput v-model="form.origin" :label="t('Origin')" required />
                        <AdminInput v-model="form.destination" :label="t('Destination')" required />
                        <AdminInput v-model="form.departure_date" type="date" :label="t('Departure date')" required />
                        <AdminInput v-model="form.return_date" type="date" :label="t('Return date')" />
                        <AdminInput v-model="form.total_amount" type="number" step="0.01" min="0.01" :label="t('Total amount')" required />
                        <AdminInput v-model="form.currency" :label="t('Currency')" />
                    </div>
                </template>

                <template v-else-if="form.service_type === 'insurance'">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <AdminInput v-model="form.plan_name" :label="t('Plan name')" required />
                        <AdminInput v-model="form.zone_name" :label="t('Zone')" />
                        <AdminInput v-model="form.coverage_start" type="date" :label="t('Coverage start')" required />
                        <AdminInput v-model="form.coverage_end" type="date" :label="t('Coverage end')" required />
                        <AdminInput v-model="form.total_amount" type="number" step="0.01" min="0.01" :label="t('Total amount')" required />
                        <AdminInput v-model="form.currency" :label="t('Currency')" />
                    </div>
                </template>

                <template v-else-if="form.service_type === 'esim'">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <AdminInput v-model="form.plan_name" :label="t('Plan name')" required />
                        <AdminInput v-model="form.country" :label="t('Country')" required />
                        <AdminInput v-model="form.total_amount" type="number" step="0.01" min="0.01" :label="t('Total amount')" required />
                        <AdminInput v-model="form.currency" :label="t('Currency')" />
                    </div>
                </template>
            </div>

            <!-- Step 3: Confirm -->
            <div v-else class="admin-panel space-y-5 p-5 sm:p-6">
                <div>
                    <h2 class="text-xl font-semibold tracking-tight text-slate-950">{{ t('Confirm booking') }}</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ t('Review and book.') }}</p>
                </div>

                <div class="space-y-3 rounded-xl bg-slate-50 p-4 text-sm">
                    <div class="flex justify-between gap-3">
                        <span class="text-slate-500">{{ t('Customer') }}</span>
                        <span class="font-medium text-slate-950">{{ selectedCustomer?.name }}</span>
                    </div>
                    <div class="flex justify-between gap-3">
                        <span class="text-slate-500">{{ t('Service') }}</span>
                        <span class="font-medium text-slate-950">{{ serviceLabel(form.service_type) }}</span>
                    </div>
                    <template v-if="form.service_type === 'hotel'">
                        <div class="flex justify-between gap-3">
                            <span class="text-slate-500">{{ t('Hotel') }}</span>
                            <span class="text-end font-medium text-slate-950">{{ form.hotel_name }}</span>
                        </div>
                        <div class="flex justify-between gap-3">
                            <span class="text-slate-500">{{ t('Room') }}</span>
                            <span class="text-end font-medium text-slate-950">{{ form.offer_label }}</span>
                        </div>
                        <div class="flex justify-between gap-3">
                            <span class="text-slate-500">{{ t('Dates') }}</span>
                            <span class="font-medium text-slate-950">{{ form.check_in }} → {{ form.check_out }}</span>
                        </div>
                    </template>
                    <div class="flex justify-between gap-3 border-t border-slate-200 pt-3">
                        <span class="text-slate-500">{{ t('Total') }}</span>
                        <span class="text-base font-semibold text-slate-950">{{ form.total_amount }} {{ form.currency }}</span>
                    </div>
                </div>

                <p v-if="form.errors.offer_id || form.errors.rate_key || form.errors.search_uuid || form.errors.total_amount || form.errors.customer_id" class="text-sm text-rose-600">
                    {{ form.errors.offer_id || form.errors.rate_key || form.errors.search_uuid || form.errors.total_amount || form.errors.customer_id }}
                </p>
            </div>

            <div class="flex items-center justify-between gap-3">
                <AdminButton
                    variant="secondary"
                    :disabled="step === 1 || form.processing"
                    @click="goBack"
                >
                    {{ t('Back') }}
                </AdminButton>

                <AdminButton
                    v-if="step < 3"
                    :disabled="!canGoNext"
                    @click="goNext"
                >
                    {{ t('Continue') }}
                </AdminButton>
                <AdminButton
                    v-else
                    :processing="form.processing"
                    @click="submit"
                >
                    {{ t('Book now') }}
                </AdminButton>
            </div>
        </section>
    </AdminLayout>
</template>
