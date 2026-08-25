<script setup>
import { computed, ref } from 'vue';
import { useAdminLocale } from '../../composables/useAdminLocale';

const props = defineProps({
    flight: {
        type: Object,
        default: null,
    },
    hotel: {
        type: Object,
        default: null,
    },
    providerName: {
        type: String,
        default: '',
    },
});

const { locale, t, forwardArrow } = useAdminLocale();
const logoFailed = ref(false);

const isHotel = computed(() => Boolean(
    props.hotel?.hotel_name || props.hotel?.check_in || props.hotel?.booking_reference,
));

const airlineCode = computed(() => props.flight?.airline_code?.toUpperCase() ?? '');

const airlineLogoUrl = computed(() => (
    airlineCode.value && !logoFailed.value
        ? `https://images.kiwi.com/airlines/64/${airlineCode.value}.png`
        : null
));

const airlineInitial = computed(() => {
    if (airlineCode.value) {
        return airlineCode.value.slice(0, 2);
    }

    const name = props.flight?.airline ?? props.providerName ?? '';

    return name ? name.charAt(0).toUpperCase() : '✈';
});

const hotelInitial = computed(() => {
    const name = props.hotel?.hotel_name ?? props.providerName ?? '';

    return name ? name.charAt(0).toUpperCase() : 'H';
});

const routeLabel = computed(() => {
    const origin = props.flight?.origin;
    const destination = props.flight?.destination;

    if (origin && destination) {
        return `${origin} - ${destination}`;
    }

    return origin || destination || null;
});

const departureLabel = computed(() => {
    const value = props.flight?.departure_time;

    if (!value) {
        return null;
    }

    const date = new Date(value);
    const dayMonth = new Intl.DateTimeFormat(locale.value, {
        day: '2-digit',
        month: 'short',
    }).format(date);
    const time = new Intl.DateTimeFormat(locale.value, {
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    }).format(date);

    return `${dayMonth} ${time}`;
});

const metaLabel = computed(() => {
    const parts = [routeLabel.value, departureLabel.value].filter(Boolean);

    return parts.length > 0 ? parts.join(' | ') : null;
});

const hotelMetaLabel = computed(() => {
    const parts = [];

    if (props.hotel?.city_name) {
        parts.push(props.hotel.city_name);
    }

    if (props.hotel?.check_in && props.hotel?.check_out) {
        const format = (value) => new Intl.DateTimeFormat(locale.value, {
            day: '2-digit',
            month: 'short',
        }).format(new Date(value));

        parts.push(`${format(props.hotel.check_in)} ${forwardArrow.value} ${format(props.hotel.check_out)}`);
    } else if (props.hotel?.check_in) {
        parts.push(new Intl.DateTimeFormat(locale.value, {
            day: '2-digit',
            month: 'short',
        }).format(new Date(props.hotel.check_in)));
    }

    return parts.length > 0 ? parts.join(' | ') : null;
});

const fallbackLabel = computed(() => (
    props.hotel?.provider_name
    || props.flight?.provider_name
    || props.providerName
    || t('Not available')
));
</script>

<template>
    <div v-if="isHotel" class="flex min-w-[10rem] items-start gap-2">
        <div class="flex h-6 w-6 shrink-0 items-center justify-center overflow-hidden rounded-full border border-slate-200 bg-slate-100 text-[10px] font-bold uppercase text-slate-600">
            {{ hotelInitial }}
        </div>

        <div class="min-w-0">
            <p class="truncate text-sm font-semibold leading-5 text-slate-950">
                {{ hotel?.hotel_name || hotel?.booking_reference || fallbackLabel }}
            </p>
            <p v-if="hotelMetaLabel" class="truncate text-xs leading-4 text-slate-500">
                {{ hotelMetaLabel }}
            </p>
        </div>
    </div>

    <div v-else-if="flight?.pnr || routeLabel || airlineCode || flight?.airline" class="flex min-w-[10rem] items-start gap-2">
        <div class="flex h-6 w-6 shrink-0 items-center justify-center overflow-hidden rounded-full border border-slate-200 bg-white">
            <img
                v-if="airlineLogoUrl"
                :src="airlineLogoUrl"
                :alt="flight?.airline || providerName"
                class="h-full w-full object-contain p-0.5"
                @error="logoFailed = true"
            >
            <span v-else class="text-[10px] font-bold uppercase text-slate-600">{{ airlineInitial }}</span>
        </div>

        <div class="min-w-0">
            <p v-if="flight?.pnr" class="truncate text-sm font-semibold leading-5 text-slate-950">
                {{ flight.pnr }}
            </p>
            <p v-else class="truncate text-sm font-medium leading-5 text-slate-900">
                {{ fallbackLabel }}
            </p>
            <p v-if="metaLabel" class="truncate text-xs leading-4 text-slate-500">
                {{ metaLabel }}
            </p>
        </div>
    </div>

    <span v-else class="text-sm text-slate-700">{{ fallbackLabel }}</span>
</template>
