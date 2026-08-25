<script setup>
import { computed, ref } from 'vue';
import OrderStatusBadge from './OrderStatusBadge.vue';
import PaymentStatusBadge from './PaymentStatusBadge.vue';
import { useAdminLocale } from '../../composables/useAdminLocale';
import { usePlatformCurrency } from '../../composables/usePlatformCurrency';

const props = defineProps({
    ticket: {
        type: Object,
        required: true,
    },
    currency: {
        type: String,
        default: null,
    },
    bookedByClickable: {
        type: Boolean,
        default: false,
    },
    showBookingActions: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits(['booked-by-click', 'action-click']);

const { locale, t, forwardArrow } = useAdminLocale();
const { defaultCurrency } = usePlatformCurrency();
const logoFailed = ref(false);
const hotelImageFailed = ref(false);

const labelClass = 'text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-500';
const cardClass = 'overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm';

const isHotel = computed(() => (
    props.ticket.service_type === 'hotel'
    || props.ticket.order_context?.service_type === 'hotel'
    || Boolean(props.ticket.hotel?.hotel_name || props.ticket.hotel?.check_in)
));

const hotel = computed(() => props.ticket.hotel ?? null);

const hotelLocation = computed(() => {
    const parts = [hotel.value?.city_name, hotel.value?.country].filter(Boolean);

    return parts.length > 0 ? parts.join(', ') : null;
});

const hotelStayFacts = computed(() => {
    const facts = [];

    if (hotel.value?.room_name) {
        facts.push({ label: t('Room'), value: hotel.value.room_name });
    } else if (hotel.value?.room_type) {
        facts.push({ label: t('Room'), value: formatLabel(hotel.value.room_type) });
    }

    if (hotel.value?.board) {
        facts.push({ label: t('Board'), value: String(hotel.value.board).toUpperCase() });
    }

    if (hotel.value?.nights) {
        facts.push({
            label: t('Nights'),
            value: `${hotel.value.nights} ${Number(hotel.value.nights) === 1 ? t('night') : t('nights')}`,
        });
    }

    if (hotel.value?.rooms) {
        facts.push({
            label: t('Rooms'),
            value: String(hotel.value.rooms),
        });
    }

    const adults = hotel.value?.adults;
    const children = hotel.value?.children;
    const guestsCount = hotel.value?.guests_count;

    if (adults !== null && adults !== undefined && adults !== '') {
        const parts = [`${adults} ${Number(adults) === 1 ? t('adult') : t('adults')}`];

        if (children !== null && children !== undefined && Number(children) > 0) {
            parts.push(`${children} ${Number(children) === 1 ? t('child') : t('children')}`);
        }

        facts.push({ label: t('Occupancy'), value: parts.join(' · ') });
    } else if (guestsCount) {
        facts.push({
            label: t('Guests'),
            value: String(guestsCount),
        });
    }

    return facts;
});

const hotelGuests = computed(() => {
    const list = props.ticket.guests?.length
        ? props.ticket.guests
        : (props.ticket.passengers ?? []);

    return list.filter((guest) => guest && (guest.first_name || guest.last_name || guest.name));
});

const hotelStars = computed(() => {
    const stars = Number(hotel.value?.stars ?? 0);

    return Number.isFinite(stars) && stars > 0 ? Math.min(5, Math.round(stars)) : 0;
});

const hotelBookingRef = computed(() => (
    hotel.value?.booking_reference
    || hotel.value?.booking_id
    || props.ticket.provider_order_number
    || null
));

const airlineCode = computed(() => {
    if (isHotel.value) {
        return '';
    }

    const flightNumber = props.ticket.segments?.[0]?.flight_number || props.ticket.flight_number;
    if (flightNumber) {
        const code = flightNumber.split(' ')[0];
        return code?.toUpperCase() || '';
    }
    return props.ticket.airline_code?.toUpperCase() || '';
});

const airlineLogoUrl = computed(() => (
    airlineCode.value && !logoFailed.value
        ? `https://images.kiwi.com/airlines/64/${airlineCode.value}.png`
        : null
));

const airlineName = computed(() => props.ticket.airline || props.ticket.airline_name || airlineCode.value || '');

const airlineInitial = computed(() => {
    if (airlineCode.value) {
        return airlineCode.value.slice(0, 2);
    }
    return airlineName.value ? airlineName.value.charAt(0).toUpperCase() : '✈';
});

const ticketActions = computed(() => [
    {
        id: 'cancel',
        label: t('Cancel'),
        title: t('Cancel booking'),
        tone: 'border-rose-200 text-rose-600 hover:border-rose-300 hover:bg-rose-50 hover:text-rose-700 focus-visible:ring-rose-500/30',
    },
    {
        id: 'reschedule',
        label: t('Reschedule'),
        title: t('Reschedule booking'),
        tone: 'border-slate-200 text-slate-600 hover:border-slate-300 hover:bg-slate-50 hover:text-slate-900 focus-visible:ring-slate-500/30',
    },
]);

const handleAction = (actionId) => {
    emit('action-click', { action: actionId, ticket: props.ticket });
};

const extraEntries = computed(() => Object.entries(props.ticket.extra ?? {})
    .filter(([, value]) => value !== null && value !== '' && value !== undefined)
    .map(([key, value]) => ({
        key,
        label: formatLabel(key),
        value: formatValue(value),
    })));

const bookedByName = computed(() => {
    const contact = props.ticket.contact ?? {};
    const contactName = `${contact.first_name ?? ''} ${contact.last_name ?? ''}`.trim();

    return contactName || null;
});

const providerStatusKey = computed(() => {
    const status = props.ticket.provider_status;

    if (! status) {
        return null;
    }

    return String(status).trim().toLowerCase().replace(/[\s-]+/g, '_');
});

const displaySegments = computed(() => {
    const segments = props.ticket.segments ?? [];

    if (segments.length > 0) {
        return segments;
    }

    if (props.ticket.origin || props.ticket.destination) {
        return [{
            departure_airport: props.ticket.origin,
            arrival_airport: props.ticket.destination,
            departure_time: props.ticket.departure_time,
            arrival_time: null,
            flight_number: null,
        }];
    }

    return [];
});

const totalAmount = computed(() => {
    const context = props.ticket.order_context ?? {};
    const amount = context.total_amount;

    if (amount === null || amount === undefined || amount === '') {
        return null;
    }

    return formatMoney(amount, context.currency);
});

const baseAmount = computed(() => {
    const context = props.ticket.order_context ?? {};
    const amount = context.base_amount;

    if (amount === null || amount === undefined || amount === '') {
        return null;
    }

    return formatMoney(amount, context.currency);
});

const taxAmount = computed(() => {
    const context = props.ticket.order_context ?? {};
    const amount = context.tax_amount;

    if (amount === null || amount === undefined || amount === '') {
        return null;
    }

    return formatMoney(amount, context.currency);
});

const contactPhone = computed(() => (
    props.ticket.contact?.phone ?? props.ticket.order_context?.customer_phone ?? null
));

const contactEmail = computed(() => props.ticket.contact?.email ?? null);

const paymentStatus = computed(() => {
    const context = props.ticket.order_context ?? {};
    const providerStatus = props.ticket.payment?.status ?? null;

    return context.payment_status || providerStatus || null;
});

const paymentDetails = computed(() => {
    const payment = props.ticket.payment ?? {};
    const fields = [];

    if (payment.method) {
        fields.push({ label: t('Payment method'), value: formatLabel(payment.method) });
    }

    if (payment.transaction_id) {
        fields.push({ label: t('Transaction id'), value: payment.transaction_id, mono: true });
    }

    if (payment.paid_at) {
        fields.push({ label: t('Paid at'), value: formatDateTime(payment.paid_at) });
    }

    return fields;
});

const metadataFields = computed(() => Object.entries(props.ticket.metadata ?? {})
    .map(([key, value]) => ({
        label: formatLabel(key),
        value: formatValue(value),
    })));

const metaFields = computed(() => {
    const context = props.ticket.order_context ?? {};
    const fields = [];

    if (context.created_at) {
        fields.push({ label: t('Created at'), value: formatShortDate(context.created_at) });
    }

    if (context.updated_at) {
        fields.push({ label: t('Updated at'), value: formatShortDate(context.updated_at) });
    }

    return fields;
});

const showAdditionalDetails = computed(() => (
    ! isHotel.value
    && (metaFields.value.length > 0 || extraEntries.value.length > 0 || metadataFields.value.length > 0)
));

const showContactsCard = computed(() => Boolean(
    bookedByName.value || contactPhone.value || contactEmail.value,
));

const showPricingCard = computed(() => Boolean(
    totalAmount.value || baseAmount.value || taxAmount.value || paymentStatus.value || paymentDetails.value.length > 0,
));

const couponRows = computed(() => {
    if (isHotel.value) {
        return [];
    }

    if (Array.isArray(props.ticket.segment_coupons) && props.ticket.segment_coupons.length > 0) {
        return props.ticket.segment_coupons;
    }

    const segments = displaySegments.value;
    const passengers = props.ticket.passengers?.length ? props.ticket.passengers : [{}];

    return segments.flatMap((segment, segmentIndex) => passengers.map((passenger) => ({
        segment_index: segmentIndex,
        flight_number: segment.flight_number ?? null,
        departure_airport: segment.departure_airport ?? null,
        arrival_airport: segment.arrival_airport ?? null,
        departure_time: segment.departure_time ?? null,
        arrival_time: segment.arrival_time ?? null,
        etkt: segment.etkt ?? segment.e_ticket ?? segment.ticket_number ?? passenger.etkt ?? null,
        status_code: segment.status ?? segment.segment_status ?? segment.action_code ?? null,
        cabin_type: segment.cabin_type ?? null,
        booking_class: segment.class ?? segment.booking_class ?? null,
        coupon: segment.coupon ?? segment.coupon_number ?? String(segmentIndex + 1).padStart(2, '0'),
        passenger_name: passengerName(passenger) !== t('Not available') ? passengerName(passenger).toUpperCase() : null,
        price: segment.price ?? segment.fare ?? (segmentIndex === 0 ? props.ticket.order_context?.total_amount : null),
        currency: segment.currency ?? props.ticket.order_context?.currency ?? props.currency,
    })));
});

const hasAnyTicketData = computed(() => (
    isHotel.value
    || props.ticket.pnr
    || couponRows.value.length > 0
    || bookedByName.value
    || contactPhone.value
    || contactEmail.value
    || totalAmount.value
    || paymentDetails.value.length > 0
    || extraEntries.value.length > 0
    || metadataFields.value.length > 0
    || metaFields.value.length > 0
));

const copyHotelRef = async () => {
    if (! hotelBookingRef.value || ! navigator.clipboard) {
        return;
    }

    await navigator.clipboard.writeText(hotelBookingRef.value);
};

const copyPnr = async () => {
    if (! props.ticket.pnr || ! navigator.clipboard) {
        return;
    }

    await navigator.clipboard.writeText(props.ticket.pnr);
};

const formatDateTime = (value) => {
    if (! value) {
        return t('Not available');
    }

    const parsed = new Date(value);

    if (Number.isNaN(parsed.getTime())) {
        return value;
    }

    return new Intl.DateTimeFormat(locale.value, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(parsed);
};

const formatShortDate = (value) => {
    if (! value) {
        return t('Not available');
    }

    return new Intl.DateTimeFormat(locale.value, {
        month: 'short',
        day: 'numeric',
    }).format(new Date(value));
};

const formatTime = (value) => {
    if (! value) {
        return '—';
    }

    const parsed = new Date(value);

    if (Number.isNaN(parsed.getTime())) {
        return value;
    }

    return new Intl.DateTimeFormat(locale.value, {
        hour: '2-digit',
        minute: '2-digit',
    }).format(parsed);
};

const formatDateOnly = (value) => {
    if (! value) {
        return '—';
    }

    const parsed = new Date(value);

    if (Number.isNaN(parsed.getTime())) {
        return value;
    }

    return new Intl.DateTimeFormat(locale.value, {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).format(parsed);
};

const formatLabel = (value) => {
    if (! value) {
        return t('Not available');
    }

    const normalizedValue = String(value).replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());

    return t(normalizedValue);
};

const formatValue = (value) => {
    if (value === null || value === undefined || value === '') {
        return t('Not available');
    }

    if (typeof value === 'object') {
        return JSON.stringify(value, null, 2);
    }

    return String(value);
};

const formatMoney = (amount, itemCurrency = props.currency || defaultCurrency.value) => {
    if (amount === null || amount === undefined || amount === '') {
        return t('Not available');
    }

    return new Intl.NumberFormat(locale.value, {
        style: 'currency',
        currency: itemCurrency || props.currency || defaultCurrency.value,
    }).format(Number(amount));
};

const passengerName = (passenger) => {
    const title = passenger.title ? `${passenger.title} ` : '';

    return `${title}${passenger.first_name ?? ''} ${passenger.last_name ?? ''}`.trim() || t('Not available');
};

const routeLabel = (row) => {
    if (row.departure_airport && row.arrival_airport) {
        return `${row.departure_airport} ${forwardArrow.value} ${row.arrival_airport}`;
    }

    return row.departure_airport || row.arrival_airport || t('Not available');
};

const cabinClassLabel = (row) => {
    const code = row.cabin_type;

    if (! code) {
        return null;
    }

    const normalized = String(code).toUpperCase();

    if (['Y', 'M', 'W', 'E'].includes(normalized)) {
        return t('Economy');
    }

    if (['C', 'J', 'D'].includes(normalized)) {
        return t('Business');
    }

    if (normalized === 'F') {
        return t('First');
    }

    return normalized;
};

const couponMetaParts = (row) => {
    const parts = [];

    if (row.etkt) {
        parts.push(`${t('ETKT')}: ${row.etkt}`);
    }

    if (row.status_code) {
        parts.push(String(row.status_code).toUpperCase());
    }

    const cabinLabel = cabinClassLabel(row);

    if (cabinLabel) {
        parts.push(cabinLabel);
    } else if (row.cabin_type) {
        parts.push(String(row.cabin_type).toUpperCase());
    }

    if (row.booking_class) {
        parts.push(String(row.booking_class).toUpperCase());
    }

    if (row.passenger_name) {
        parts.push(row.passenger_name);
    }

    return parts;
};

</script>

<template>
    <div v-if="!hasAnyTicketData" :class="cardClass">
        <div class="px-5 py-8 text-center">
            <p class="text-sm font-medium text-slate-900">{{ t('Order') }}</p>
            <p class="mt-1 text-sm text-slate-500">
                {{ t('No stored order or booking data is available for this order yet.') }}
            </p>
        </div>
    </div>

    <div v-else class="space-y-4">
        <article v-if="isHotel && hotel" :class="cardClass">
            <div class="border-b border-slate-100 px-4 py-3">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="flex min-w-0 flex-1 items-start gap-3">
                        <div class="h-14 w-14 shrink-0 overflow-hidden rounded-lg border border-slate-200 bg-slate-100">
                            <img
                                v-if="hotel.image_url && !hotelImageFailed"
                                :src="hotel.image_url"
                                :alt="hotel.hotel_name || t('Hotel')"
                                class="h-full w-full object-cover"
                                @error="hotelImageFailed = true"
                            >
                            <div
                                v-else
                                class="flex h-full w-full items-center justify-center text-slate-400"
                                aria-hidden="true"
                            >
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" class="h-6 w-6">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M5 21V8.5L12 4l7 4.5V21M9 21v-5h6v5M9 10h.01M15 10h.01" />
                                </svg>
                            </div>
                        </div>

                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-slate-950">
                                {{ hotel.hotel_name || t('Hotel stay') }}
                            </p>
                            <div
                                v-if="hotelStars > 0"
                                class="mt-1 flex items-center gap-0.5 text-amber-500"
                                :aria-label="`${hotelStars} ${t('stars')}`"
                            >
                                <svg
                                    v-for="star in hotelStars"
                                    :key="star"
                                    viewBox="0 0 20 20"
                                    fill="currentColor"
                                    class="h-3.5 w-3.5"
                                    aria-hidden="true"
                                >
                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                </svg>
                            </div>
                            <p v-if="hotelLocation" class="mt-1 text-xs text-slate-500">
                                {{ hotelLocation }}
                            </p>
                            <p v-else-if="hotel.address" class="mt-1 text-xs text-slate-500">
                                {{ hotel.address }}
                            </p>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <div
                            v-if="hotelBookingRef"
                            class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1.5 shadow-sm"
                        >
                            <span class="rounded-md bg-slate-800 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-[0.18em] text-white">
                                {{ t('Ref') }}
                            </span>
                            <span class="font-mono text-sm font-bold tracking-wide text-slate-950">
                                {{ hotelBookingRef }}
                            </span>
                            <button
                                type="button"
                                class="rounded-md border border-slate-200 bg-white p-1 text-slate-500 transition hover:border-slate-300 hover:bg-slate-100 hover:text-slate-800"
                                :title="t('Copy')"
                                @click="copyHotelRef"
                            >
                                <svg viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5" aria-hidden="true">
                                    <path d="M7 3.5A1.5 1.5 0 018.5 2h3.879a1.5 1.5 0 011.06.44l3.122 3.12A1.5 1.5 0 0117 6.622V12.5a1.5 1.5 0 01-1.5 1.5h-1v-3.379a3 3 0 00-.879-2.121L10.5 5.379A3 3 0 008.379 4.5H7v-1z" />
                                    <path d="M4.5 6A1.5 1.5 0 003 7.5v9A1.5 1.5 0 004.5 18h7a1.5 1.5 0 001.5-1.5v-5a1.5 1.5 0 00-1.5-1.5H5v-1z" />
                                </svg>
                            </button>
                        </div>

                        <div v-if="providerStatusKey" class="flex items-center gap-2 text-sm text-slate-600">
                            <OrderStatusBadge :status="providerStatusKey" />
                        </div>

                        <div
                            v-if="showBookingActions"
                            class="flex items-center gap-2"
                            role="group"
                            :aria-label="t('Booking actions')"
                        >
                            <button
                                v-for="action in ticketActions"
                                :key="action.id"
                                type="button"
                                class="inline-flex h-9 w-9 items-center justify-center rounded-lg border bg-white shadow-sm transition focus-visible:outline-none focus-visible:ring-2"
                                :class="action.tone"
                                :title="action.title"
                                :aria-label="action.label"
                                @click="handleAction(action.id)"
                            >
                                <svg
                                    v-if="action.id === 'cancel'"
                                    viewBox="0 0 20 20"
                                    fill="currentColor"
                                    class="h-4 w-4"
                                    aria-hidden="true"
                                >
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" />
                                </svg>
                                <svg
                                    v-else
                                    viewBox="0 0 20 20"
                                    fill="currentColor"
                                    class="h-4 w-4"
                                    aria-hidden="true"
                                >
                                    <path fill-rule="evenodd" d="M5.75 2a.75.75 0 01.75.75V4h7V2.75a.75.75 0 011.5 0V4h.25A2.75 2.75 0 0118 6.75v8.5A2.75 2.75 0 0115.25 18H4.75A2.75 2.75 0 012 15.25v-8.5A2.75 2.75 0 014.75 4H5V2.75A.75.75 0 015.75 2h-.5zm-1 5.5c-.69 0-1.25.56-1.25 1.25v6.5c0 .69.56 1.25 1.25 1.25h10.5c.69 0 1.25-.56 1.25-1.25v-6.5c0-.69-.56-1.25-1.25-1.25H4.75z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid divide-y divide-slate-100 sm:grid-cols-2 sm:divide-x sm:divide-y-0">
                <div class="px-4 py-3">
                    <p :class="labelClass">{{ t('Check-in') }}</p>
                    <p class="mt-1.5 text-base font-semibold text-slate-950">{{ formatDateOnly(hotel.check_in) }}</p>
                </div>
                <div class="px-4 py-3">
                    <p :class="labelClass">{{ t('Check-out') }}</p>
                    <p class="mt-1.5 text-base font-semibold text-slate-950">{{ formatDateOnly(hotel.check_out) }}</p>
                </div>
            </div>

            <div v-if="hotelStayFacts.length > 0" class="border-t border-slate-100 px-4 py-3">
                <dl class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div v-for="fact in hotelStayFacts" :key="fact.label">
                        <dt :class="labelClass">{{ fact.label }}</dt>
                        <dd class="mt-0.5 text-sm font-medium text-slate-900">{{ fact.value }}</dd>
                    </div>
                </dl>
            </div>

            <div v-if="hotel.address && hotelLocation" class="border-t border-slate-100 px-4 py-3">
                <p :class="labelClass">{{ t('Address') }}</p>
                <p class="mt-0.5 text-sm text-slate-700">{{ hotel.address }}</p>
            </div>
        </article>

        <div v-if="isHotel && hotelGuests.length > 0" :class="cardClass">
            <div class="border-b border-slate-100 px-5 py-3">
                <p class="text-sm font-semibold text-slate-950">{{ t('Guests') }}</p>
            </div>
            <ul class="divide-y divide-slate-100">
                <li
                    v-for="(guest, index) in hotelGuests"
                    :key="index"
                    class="flex items-center justify-between gap-3 px-5 py-3"
                >
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-slate-900">{{ passengerName(guest) }}</p>
                        <p v-if="guest.email || guest.phone" class="mt-0.5 truncate text-xs text-slate-500">
                            <span v-if="guest.email">{{ guest.email }}</span>
                            <span v-if="guest.email && guest.phone"> · </span>
                            <span v-if="guest.phone" dir="ltr">{{ guest.phone }}</span>
                        </p>
                    </div>
                </li>
            </ul>
        </div>

        <article
            v-for="(row, index) in couponRows"
            :key="`${row.flight_number}-${row.segment_index}-${index}`"
            :class="cardClass"
        >
            <div class="border-b border-slate-100 px-4 py-3">
                <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1.5">
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5">
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center overflow-hidden rounded-full border border-slate-200 bg-white">
                            <img
                                v-if="airlineLogoUrl"
                                :src="airlineLogoUrl"
                                :alt="airlineName"
                                class="h-full w-full object-contain p-0.5"
                                @error="logoFailed = true"
                            >
                            <span v-else class="text-[11px] font-bold uppercase text-slate-600">{{ airlineInitial }}</span>
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-slate-900">
                                {{ airlineName || routeLabel(row) }}
                                <span v-if="row.flight_number" class="text-slate-500">| {{ row.flight_number }}</span>
                            </p>
                        </div>
                    </div>

                    <div
                        v-if="showBookingActions"
                        class="flex items-center gap-2"
                        role="group"
                        :aria-label="t('Booking actions')"
                    >
                        <button
                            v-for="action in ticketActions"
                            :key="action.id"
                            type="button"
                            class="inline-flex h-9 w-9 items-center justify-center rounded-lg border bg-white shadow-sm transition focus-visible:outline-none focus-visible:ring-2"
                            :class="action.tone"
                            :title="action.title"
                            :aria-label="action.label"
                            @click="handleAction(action.id)"
                        >
                            <svg
                                v-if="action.id === 'cancel'"
                                viewBox="0 0 20 20"
                                fill="currentColor"
                                class="h-4 w-4"
                                aria-hidden="true"
                            >
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" />
                            </svg>
                            <svg
                                v-else
                                viewBox="0 0 20 20"
                                fill="currentColor"
                                class="h-4 w-4"
                                aria-hidden="true"
                            >
                                <path fill-rule="evenodd" d="M5.75 2a.75.75 0 01.75.75V4h7V2.75a.75.75 0 011.5 0V4h.25A2.75 2.75 0 0118 6.75v8.5A2.75 2.75 0 0115.25 18H4.75A2.75 2.75 0 012 15.25v-8.5A2.75 2.75 0 014.75 4H5V2.75A.75.75 0 015.75 2h-.5zm-1 5.5c-.69 0-1.25.56-1.25 1.25v6.5c0 .69.56 1.25 1.25 1.25h10.5c.69 0 1.25-.56 1.25-1.25v-6.5c0-.69-.56-1.25-1.25-1.25H4.75z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </div>

                    <template v-if="index === 0 && (ticket.pnr || providerStatusKey)">
                        <span v-if="routeLabel(row) !== t('Not available')" class="text-slate-300 sm:hidden">•</span>

                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5 sm:ms-auto">
                            <div
                                v-if="ticket.pnr"
                                class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1.5 shadow-sm"
                            >
                                <span class="rounded-md bg-slate-800 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-[0.18em] text-white">
                                    {{ t('PNR') }}
                                </span>
                                <span class="font-mono text-base font-bold tracking-[0.2em] text-slate-950">
                                    {{ ticket.pnr }}
                                </span>
                                <button
                                    type="button"
                                    class="rounded-md border border-slate-200 bg-white p-1 text-slate-500 transition hover:border-slate-300 hover:bg-slate-100 hover:text-slate-800"
                                    :title="t('Copy PNR')"
                                    @click="copyPnr"
                                >
                                    <svg viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5" aria-hidden="true">
                                        <path d="M7 3.5A1.5 1.5 0 018.5 2h3.879a1.5 1.5 0 011.06.44l3.122 3.12A1.5 1.5 0 0117 6.622V12.5a1.5 1.5 0 01-1.5 1.5h-1v-3.379a3 3 0 00-.879-2.121L10.5 5.379A3 3 0 008.379 4.5H7v-1z" />
                                        <path d="M4.5 6A1.5 1.5 0 003 7.5v9A1.5 1.5 0 004.5 18h7a1.5 1.5 0 001.5-1.5v-5a1.5 1.5 0 00-1.5-1.5H5v-1z" />
                                    </svg>
                                </button>
                            </div>

                            <span v-if="ticket.pnr && providerStatusKey" class="text-slate-300">•</span>

                            <div v-if="providerStatusKey" class="flex items-center gap-2 text-sm text-slate-600">
                                <span class="font-medium">{{ t('Status') }}:</span>
                                <OrderStatusBadge :status="providerStatusKey" />
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <div
                class="grid divide-y divide-slate-100 sm:grid-cols-2 sm:divide-x sm:divide-y-0"
                :class="couponMetaParts(row).length > 0 ? 'lg:grid-cols-3' : 'lg:grid-cols-2'"
            >
                <div class="px-4 py-3">
                    <p :class="labelClass">{{ t('Departure') }}</p>
                    <p class="mt-1.5 font-mono text-base font-semibold text-slate-950">{{ row.departure_airport || '—' }}</p>
                    <p class="mt-0.5 text-xs text-slate-600">{{ formatDateOnly(row.departure_time) }}</p>
                    <p class="text-xs font-medium text-slate-900">{{ formatTime(row.departure_time) }}</p>
                </div>

                <div class="px-4 py-3">
                    <p :class="labelClass">{{ t('Arrival') }}</p>
                    <p class="mt-1.5 font-mono text-base font-semibold text-slate-950">{{ row.arrival_airport || '—' }}</p>
                    <p class="mt-0.5 text-xs text-slate-600">{{ formatDateOnly(row.arrival_time) }}</p>
                    <p class="text-xs font-medium text-slate-900">{{ formatTime(row.arrival_time) }}</p>
                </div>

                <div v-if="couponMetaParts(row).length > 0" class="px-4 py-3">
                    <p :class="labelClass">{{ t('Ticket details') }}</p>
                    <p class="mt-1.5 flex flex-wrap items-center gap-x-1.5 gap-y-1 text-sm leading-6 text-slate-900">
                        <template v-for="(part, partIndex) in couponMetaParts(row)" :key="`${part}-${partIndex}`">
                            <span v-if="partIndex > 0" class="shrink-0 text-slate-300">•</span>
                            <span class="shrink-0">{{ part }}</span>
                        </template>
                    </p>
                </div>
            </div>
        </article>

        <div
            v-if="showPricingCard || showContactsCard"
            class="grid gap-4 lg:grid-cols-2"
        >
            <div
                v-if="showPricingCard"
                :class="cardClass"
            >
                <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-3">
                    <p class="text-sm font-semibold text-slate-950">{{ t('Payment') }}</p>
                    <PaymentStatusBadge v-if="paymentStatus" :status="paymentStatus" />
                </div>
                <dl class="divide-y divide-slate-100">
                    <div v-if="baseAmount" class="flex items-center justify-between gap-4 px-5 py-3">
                        <dt class="text-sm text-slate-600">{{ t('Base fare') }}</dt>
                        <dd class="text-sm font-medium text-slate-900">{{ baseAmount }}</dd>
                    </div>
                    <div v-if="taxAmount" class="flex items-center justify-between gap-4 px-5 py-3">
                        <dt class="text-sm text-slate-600">{{ t('Tax') }}</dt>
                        <dd class="text-sm font-medium text-slate-900">{{ taxAmount }}</dd>
                    </div>
                    <div v-if="totalAmount" class="px-5 py-4">
                        <dt class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ t('Total amount') }}</dt>
                        <dd class="mt-1.5 text-2xl font-semibold text-slate-950">{{ totalAmount }}</dd>
                    </div>
                    <div
                        v-for="field in paymentDetails"
                        :key="field.label"
                        class="flex items-center justify-between gap-4 px-5 py-3"
                    >
                        <dt class="text-sm text-slate-600">{{ field.label }}</dt>
                        <dd class="text-sm font-medium text-slate-900" :class="field.mono ? 'font-mono' : ''">{{ field.value }}</dd>
                    </div>
                </dl>
            </div>

            <div
                v-if="showContactsCard"
                :class="cardClass"
            >
                <div class="border-b border-slate-100 px-5 py-3">
                    <p class="text-sm font-semibold text-slate-950">{{ t('Contact') }}</p>
                </div>
                <div class="space-y-4 px-5 py-4">
                    <div v-if="bookedByName" class="flex items-center gap-2.5">
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-slate-100 text-slate-500">
                            <svg viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4" aria-hidden="true">
                                <path d="M10 9a3 3 0 100-6 3 3 0 000 6zM6 8a2 2 0 11-4 0 2 2 0 014 0zM1.49 15.326a.78.78 0 01-.358-.442 3 3 0 014.308-3.516 6.484 6.484 0 00-1.905 3.959c-.023.222-.014.442.025.654a4.97 4.97 0 00-2.07-.655zM16.44 15.98a4.97 4.97 0 002.07-.654.78.78 0 00.357-.442 3 3 0 00-4.306-3.517 6.484 6.484 0 011.907 3.96 2.32 2.32 0 01-.028.654zM18 8a2 2 0 11-4 0 2 2 0 014 0zM5.304 16.19a.844.844 0 01-.277-.71 5 5 0 019.947 0 .843.843 0 01-.277.71A6.975 6.975 0 0110 18a6.974 6.974 0 01-4.696-1.81z" />
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ t('Booked by') }}</p>
                            <button
                                v-if="bookedByClickable"
                                type="button"
                                class="mt-0.5 text-start text-sm font-medium text-slate-900 transition hover:text-slate-600 hover:underline"
                                @click="emit('booked-by-click')"
                            >
                                {{ bookedByName }}
                            </button>
                            <p v-else class="mt-0.5 text-sm font-medium text-slate-900">{{ bookedByName }}</p>
                        </div>
                    </div>
                    <div v-if="contactPhone" class="flex items-center gap-2.5">
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-slate-100 text-slate-500">
                            <svg viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4" aria-hidden="true">
                                <path fill-rule="evenodd" d="M2 3.5A1.5 1.5 0 013.5 2h2.879a1.5 1.5 0 011.06.44l2.122 2.12a1.5 1.5 0 01.326 1.636l-.794 1.588a11.04 11.04 0 005.516 5.516l1.588-.794a1.5 1.5 0 011.636.326l2.12 2.122a1.5 1.5 0 01.44 1.06V16.5a1.5 1.5 0 01-1.5 1.5A12.5 12.5 0 012 5a1.5 1.5 0 011.5-1.5z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <a
                            :href="`tel:${contactPhone}`"
                            class="text-sm font-medium text-slate-900 transition hover:text-slate-600"
                            dir="ltr"
                        >{{ contactPhone }}</a>
                    </div>
                    <div v-if="contactEmail" class="flex items-center gap-2.5">
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-slate-100 text-slate-500">
                            <svg viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4" aria-hidden="true">
                                <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z" />
                                <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z" />
                            </svg>
                        </div>
                        <a
                            :href="`mailto:${contactEmail}`"
                            class="text-sm font-medium text-slate-900 transition hover:text-slate-600"
                        >{{ contactEmail }}</a>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="ticket.items.length > 0 && couponRows.length === 0 && !isHotel" :class="cardClass">
            <div class="border-b border-slate-100 px-5 py-3">
                <p class="text-sm font-semibold text-slate-950">{{ t('Booking items') }}</p>
            </div>
            <div class="divide-y divide-slate-100">
                <article
                    v-for="(item, index) in ticket.items"
                    :key="index"
                    class="flex items-center justify-between gap-3 px-5 py-3"
                >
                    <div class="min-w-0 text-sm text-slate-900">
                        <span class="font-medium">{{ formatLabel(item.product_type || item.type) }}</span>
                        <span v-if="item.product_subtype" class="text-slate-500"> · {{ formatLabel(item.product_subtype) }}</span>
                    </div>
                    <span
                        v-if="item.total !== null && item.total !== undefined && item.total !== ''"
                        class="shrink-0 text-sm font-semibold text-slate-950"
                    >
                        {{ formatMoney(item.total, item.currency) }}
                    </span>
                </article>
            </div>
        </div>

        <div
            v-if="showAdditionalDetails"
            :class="cardClass"
        >
            <div class="border-b border-slate-100 px-5 py-3">
                <p class="text-sm font-semibold text-slate-950">{{ t('Additional details') }}</p>
            </div>
            <dl class="grid gap-4 px-5 py-4 sm:grid-cols-2 lg:grid-cols-3">
                <div v-for="field in metaFields" :key="field.label">
                    <dt :class="labelClass">{{ field.label }}</dt>
                    <dd class="mt-0.5 text-sm text-slate-900">{{ field.value }}</dd>
                </div>
                <div v-for="entry in extraEntries" :key="entry.key">
                    <dt :class="labelClass">{{ entry.label }}</dt>
                    <dd class="mt-0.5 text-sm text-slate-900">{{ entry.value }}</dd>
                </div>
                <div v-for="field in metadataFields" :key="field.label">
                    <dt :class="labelClass">{{ field.label }}</dt>
                    <dd class="mt-0.5 text-sm text-slate-900">{{ field.value }}</dd>
                </div>
            </dl>
        </div>
    </div>
</template>
