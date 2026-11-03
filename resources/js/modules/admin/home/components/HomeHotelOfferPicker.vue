<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useAdminLocale } from '../../composables/useAdminLocale';

const props = defineProps({
    modelValue: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue', 'hotel-image']);

const { t, isArabic } = useAdminLocale();

const configured = ref(null);
const busy = ref('');
const error = ref('');
const step = ref(1);

const cityQuery = ref('');
const cities = ref([]);
const hotels = ref([]);
const offers = ref([]);
const selectedOfferLabel = ref('');
const selectedHotelImage = ref('');

const selected = reactive({
    city: '',
    city_ar: '',
    city_id: '',
    hotel_id: '',
    hotel_name: '',
    source: '',
    offer_id: '',
    check_in: '',
    check_out: '',
});

const safeImageUrl = (url) => {
    const raw = String(url || '').trim();
    if (!raw) return '';
    try {
        return encodeURI(decodeURI(raw));
    } catch {
        return raw.replace(/ /g, '%20');
    }
};

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

const defaultDates = () => {
    const inDate = new Date();
    inDate.setDate(inDate.getDate() + 1);
    const outDate = new Date(inDate);
    outDate.setDate(outDate.getDate() + 2);
    const fmt = (d) => d.toISOString().slice(0, 10);
    return { check_in: fmt(inDate), check_out: fmt(outDate) };
};

const cityDisplay = (city) => {
    if (!city) return '';
    if (isArabic.value) {
        return city.city_ar || city.city;
    }
    return city.city_ar ? `${city.city} (${city.city_ar})` : city.city;
};

const selectedCityLabel = computed(() => {
    if (!selected.city) return '';
    if (selected.city_ar) return `${selected.city} · ${selected.city_ar}`;
    return selected.city;
});

const isReady = computed(() => Boolean(selected.hotel_id && selected.city_id));

const parsePayload = (raw) => {
    if (!raw || !String(raw).trim()) return {};
    try {
        const parsed = JSON.parse(raw);
        return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : {};
    } catch {
        return {};
    }
};

const hydrateFromModel = () => {
    const parsed = parsePayload(props.modelValue);
    selected.city = String(parsed.city ?? '');
    selected.city_ar = String(parsed.city_ar ?? '');
    selected.city_id = String(parsed.city_id ?? parsed.cityId ?? '');
    selected.hotel_id = String(parsed.hotel_id ?? parsed.hotelId ?? '');
    selected.hotel_name = String(parsed.hotel_name ?? parsed.hotelName ?? '');
    selected.source = String(parsed.source ?? '');
    selected.offer_id = String(parsed.offer_id ?? parsed.offerId ?? parsed.rate_key ?? '');
    selected.check_in = String(parsed.check_in ?? parsed.checkIn ?? '').slice(0, 10);
    selected.check_out = String(parsed.check_out ?? parsed.checkOut ?? '').slice(0, 10);

    if (!selected.check_in || !selected.check_out) {
        const defaults = defaultDates();
        if (!selected.check_in) selected.check_in = defaults.check_in;
        if (!selected.check_out) selected.check_out = defaults.check_out;
    }

    if (selected.city) {
        cityQuery.value = selectedCityLabel.value;
    }

    if (isReady.value) {
        step.value = selected.offer_id ? 3 : 2;
    } else if (selected.city_id) {
        step.value = 1;
    }
};

const emitPayload = () => {
    const payload = {};
    if (selected.hotel_id) payload.hotel_id = selected.hotel_id;
    if (selected.hotel_name) payload.hotel_name = selected.hotel_name;
    if (selected.city) payload.city = selected.city;
    if (selected.city_id) payload.city_id = selected.city_id;
    if (selected.check_in) payload.check_in = selected.check_in;
    if (selected.check_out) payload.check_out = selected.check_out;
    if (selected.offer_id) payload.offer_id = selected.offer_id;
    if (selected.source) payload.source = selected.source;

    const serialized = Object.keys(payload).length ? JSON.stringify(payload) : '';
    if (serialized !== (props.modelValue ?? '')) {
        emit('update:modelValue', serialized);
    }
};

const apiFetch = async (url, options = {}) => {
    const response = await fetch(url, {
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
            ...(options.headers || {}),
        },
        ...options,
    });
    const body = await response.json().catch(() => ({}));
    return { response, body };
};

const loadStatus = async () => {
    try {
        const { body } = await apiFetch(route('admin.home.hotel-lookup.status'));
        configured.value = Boolean(body?.data?.configured);
    } catch {
        configured.value = false;
    }
};

let cityTimer = null;
const searchCities = () => {
    clearTimeout(cityTimer);
    cityTimer = setTimeout(async () => {
        const q = cityQuery.value.trim();
        if (q.length < 2) {
            cities.value = [];
            return;
        }
        busy.value = 'cities';
        error.value = '';
        try {
            const { response, body } = await apiFetch(
                `${route('admin.home.hotel-lookup.cities')}?q=${encodeURIComponent(q)}`,
            );
            if (!response.ok || body.success === false) {
                error.value = body.message || t('Could not find cities. Try another spelling.');
                cities.value = [];
                return;
            }
            cities.value = body.data?.cities ?? [];
        } catch {
            error.value = t('Could not find cities. Try another spelling.');
            cities.value = [];
        } finally {
            busy.value = '';
        }
    }, 300);
};

const pickCity = (city) => {
    selected.city = city.city;
    selected.city_ar = city.city_ar || '';
    selected.city_id = String(city.city_id);
    selected.hotel_id = '';
    selected.hotel_name = '';
    selected.source = '';
    selected.offer_id = '';
    selectedOfferLabel.value = '';
    selectedHotelImage.value = '';
    hotels.value = [];
    offers.value = [];
    cities.value = [];
    cityQuery.value = cityDisplay(city);
    step.value = 1;
    emitPayload();
};

const searchHotels = async () => {
    if (!selected.city_id || !selected.city || !selected.check_in || !selected.check_out) {
        error.value = t('Choose a city and the stay dates first.');
        return;
    }
    busy.value = 'hotels';
    error.value = '';
    hotels.value = [];
    offers.value = [];
    selected.hotel_id = '';
    selected.hotel_name = '';
    selected.offer_id = '';
    selectedOfferLabel.value = '';
    try {
        const { response, body } = await apiFetch(route('admin.home.hotel-lookup.hotels'), {
            method: 'POST',
            body: JSON.stringify({
                city: selected.city,
                city_id: selected.city_id,
                check_in: selected.check_in,
                check_out: selected.check_out,
                adults: 2,
            }),
        });
        if (!response.ok || body.success === false) {
            error.value = body.message || t('No hotels found. Change dates and try again.');
            return;
        }
        hotels.value = body.data?.hotels ?? [];
        if (hotels.value.length === 0) {
            error.value = t('No hotels found. Change dates and try again.');
            return;
        }
        step.value = 2;
    } catch {
        error.value = t('No hotels found. Change dates and try again.');
    } finally {
        busy.value = '';
    }
};

const pickHotel = async (hotel) => {
    selected.hotel_id = String(hotel.hotel_id);
    selected.hotel_name = hotel.hotel_name || '';
    selected.source = hotel.source || '';
    selected.offer_id = '';
    selectedOfferLabel.value = '';
    selectedHotelImage.value = safeImageUrl(hotel.image_url);
    if (hotel.city_id) selected.city_id = String(hotel.city_id);
    if (hotel.city) selected.city = hotel.city;
    if (selectedHotelImage.value) {
        emit('hotel-image', selectedHotelImage.value);
    }
    emitPayload();
    step.value = 3;

    if (Array.isArray(hotel.offers) && hotel.offers.length > 0) {
        offers.value = hotel.offers;
        return;
    }

    busy.value = 'offers';
    try {
        const params = new URLSearchParams({
            hotel_id: selected.hotel_id,
            city_id: selected.city_id,
            city: selected.city,
            check_in: selected.check_in,
            check_out: selected.check_out,
        });
        if (selected.source) params.set('source', selected.source);
        const { response, body } = await apiFetch(
            `${route('admin.home.hotel-lookup.offers')}?${params.toString()}`,
        );
        offers.value = (!response.ok || body.success === false) ? [] : (body.data?.offers ?? []);
    } catch {
        offers.value = [];
    } finally {
        busy.value = '';
    }
};

const pickOffer = (offer) => {
    selected.offer_id = String(offer.offer_id);
    selectedOfferLabel.value = offer.label || '';
    emitPayload();
};

const skipOffer = () => {
    selected.offer_id = '';
    selectedOfferLabel.value = '';
    emitPayload();
};

const goStep = (n) => {
    step.value = n;
    error.value = '';
};

const resetAll = () => {
    selected.city = '';
    selected.city_ar = '';
    selected.city_id = '';
    selected.hotel_id = '';
    selected.hotel_name = '';
    selected.source = '';
    selected.offer_id = '';
    selectedOfferLabel.value = '';
    selectedHotelImage.value = '';
    cityQuery.value = '';
    cities.value = [];
    hotels.value = [];
    offers.value = [];
    const defaults = defaultDates();
    selected.check_in = defaults.check_in;
    selected.check_out = defaults.check_out;
    step.value = 1;
    emit('update:modelValue', '');
};

watch(() => props.modelValue, hydrateFromModel, { immediate: true });
watch(() => [selected.check_in, selected.check_out], () => emitPayload());
onMounted(loadStatus);
</script>

<template>
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 bg-slate-50 px-4 py-3">
            <p class="text-base font-semibold text-slate-950">{{ t('Link this promo to a hotel') }}</p>
            <p class="mt-1 text-sm text-slate-600">
                {{ t('3 simple steps: city → hotel → room (optional). Guest taps the promo and opens that hotel.') }}
            </p>
        </div>

        <div v-if="configured === false" class="m-4 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900">
            {{ t('Hotel search is not connected yet. Ask tech to set the hotel provider, then reload.') }}
        </div>

        <!-- Ready summary -->
        <div v-if="isReady" class="m-4 overflow-hidden rounded-xl border border-emerald-200 bg-emerald-50">
            <div class="flex gap-3 p-3">
                <img
                    v-if="selectedHotelImage"
                    :src="selectedHotelImage"
                    alt=""
                    class="h-20 w-24 shrink-0 rounded-lg object-cover"
                    referrerpolicy="no-referrer"
                    @error="selectedHotelImage = ''"
                >
                <div class="min-w-0 flex-1 py-0.5">
                    <p class="text-sm font-semibold text-emerald-900">{{ t('Ready — this promo will open:') }}</p>
                    <p class="mt-1 text-base font-medium text-slate-950">{{ selected.hotel_name }}</p>
                    <p class="mt-1 text-sm text-slate-700">
                        {{ selectedCityLabel }}
                        · {{ selected.check_in }} → {{ selected.check_out }}
                    </p>
                    <p class="mt-1 text-sm text-slate-600">
                        {{ selected.offer_id
                            ? `${t('Room')}: ${selectedOfferLabel || selected.offer_id}`
                            : t('Any available room') }}
                    </p>
                </div>
            </div>
            <div class="border-t border-emerald-200 px-3 py-2">
                <button type="button" class="text-sm font-medium text-emerald-800 underline" @click="resetAll">
                    {{ t('Change selection') }}
                </button>
            </div>
        </div>

        <!-- Steps -->
        <div class="flex gap-2 border-b border-slate-100 px-4 py-3">
            <button
                v-for="n in 3"
                :key="n"
                type="button"
                class="flex-1 rounded-xl px-2 py-2 text-sm font-medium transition"
                :class="step === n
                    ? 'bg-slate-900 text-white'
                    : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                @click="goStep(n)"
            >
                <span class="opacity-70">{{ n }}.</span>
                {{ n === 1 ? t('City & dates') : n === 2 ? t('Hotel') : t('Room') }}
            </button>
        </div>

        <div class="space-y-4 p-4">
            <!-- Step 1 -->
            <div v-show="step === 1" class="space-y-4">
                <div>
                    <label class="mb-1.5 block text-sm font-semibold text-slate-900">
                        1) {{ t('Where is the hotel?') }}
                    </label>
                    <input
                        v-model="cityQuery"
                        type="search"
                        class="w-full rounded-xl border border-slate-300 px-3 py-3 text-base"
                        :placeholder="t('Enter city name')"
                        :disabled="configured === false"
                        @input="searchCities"
                    >
                    <p class="mt-1.5 text-xs text-slate-500">
                        {{ t('You can search in Arabic or English, for example Tunis or Sousse.') }}
                    </p>
                    <div v-if="busy === 'cities'" class="mt-2 text-sm text-slate-500">{{ t('Searching…') }}</div>
                    <div v-if="cities.length" class="mt-2 max-h-52 overflow-auto rounded-xl border border-slate-200">
                        <button
                            v-for="city in cities"
                            :key="city.city_id"
                            type="button"
                            class="flex w-full items-center justify-between gap-3 border-b border-slate-100 px-4 py-3 text-left last:border-b-0 hover:bg-slate-50"
                            @click="pickCity(city)"
                        >
                            <span class="text-base font-medium text-slate-900">
                                {{ city.city_ar || city.city }}
                                <span v-if="city.city_ar && city.city !== city.city_ar" class="text-sm font-normal text-slate-500">
                                    ({{ city.city }})
                                </span>
                            </span>
                            <span v-if="city.country" class="text-xs text-slate-400">{{ city.country }}</span>
                        </button>
                    </div>
                    <p v-if="selected.city_id" class="mt-2 rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-700">
                        {{ t('Selected city') }}: <strong>{{ selectedCityLabel }}</strong>
                    </p>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-slate-900">{{ t('Check-in') }}</label>
                        <input v-model="selected.check_in" type="date" class="w-full rounded-xl border border-slate-300 px-3 py-3 text-base">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-slate-900">{{ t('Check-out') }}</label>
                        <input v-model="selected.check_out" type="date" class="w-full rounded-xl border border-slate-300 px-3 py-3 text-base">
                    </div>
                </div>

                <button
                    type="button"
                    class="w-full rounded-xl bg-slate-900 px-4 py-3 text-base font-semibold text-white disabled:opacity-40"
                    :disabled="configured === false || busy === 'hotels' || !selected.city_id"
                    @click="searchHotels"
                >
                    {{ busy === 'hotels' ? t('Searching hotels…') : t('Next: show hotels') }}
                </button>
            </div>

            <!-- Step 2 -->
            <div v-show="step === 2" class="space-y-3">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-sm font-semibold text-slate-900">2) {{ t('Choose the hotel') }}</p>
                    <button type="button" class="text-sm text-cyan-700 hover:underline" @click="goStep(1)">
                        {{ t('Back') }}
                    </button>
                </div>
                <p class="text-sm text-slate-600">
                    {{ selectedCityLabel }} · {{ selected.check_in }} → {{ selected.check_out }}
                </p>

                <div v-if="!hotels.length" class="rounded-xl border border-dashed border-slate-200 px-4 py-8 text-center text-sm text-slate-500">
                    {{ t('No hotel list yet. Go back and tap “Next: show hotels”.') }}
                </div>

                <div v-else class="max-h-72 overflow-auto rounded-xl border border-slate-200">
                    <button
                        v-for="hotel in hotels"
                        :key="hotel.hotel_id"
                        type="button"
                        class="flex w-full items-center gap-3 border-b border-slate-100 px-4 py-3.5 text-left last:border-b-0 hover:bg-slate-50"
                        :class="selected.hotel_id === String(hotel.hotel_id) ? 'bg-emerald-50' : ''"
                        @click="pickHotel(hotel)"
                    >
                        <img
                            v-if="safeImageUrl(hotel.image_url)"
                            :src="safeImageUrl(hotel.image_url)"
                            alt=""
                            class="h-16 w-16 shrink-0 rounded-lg bg-slate-100 object-cover"
                            referrerpolicy="no-referrer"
                            loading="lazy"
                            @error="$event.target.style.display = 'none'"
                        >
                        <div
                            v-else
                            class="flex h-16 w-16 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-xs text-slate-400"
                        >
                            {{ t('No photo') }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-base font-semibold text-slate-950">{{ hotel.hotel_name }}</p>
                            <p class="text-sm text-slate-500">{{ t('Tap to select') }}</p>
                        </div>
                    </button>
                </div>
            </div>

            <!-- Step 3 -->
            <div v-show="step === 3" class="space-y-3">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-sm font-semibold text-slate-900">3) {{ t('Room offer (optional)') }}</p>
                    <button type="button" class="text-sm text-cyan-700 hover:underline" @click="goStep(2)">
                        {{ t('Back') }}
                    </button>
                </div>
                <p class="text-sm text-slate-600">
                    {{ selected.hotel_name || t('Select a hotel first') }}
                </p>
                <p class="rounded-xl bg-slate-50 px-3 py-2 text-sm text-slate-600">
                    {{ t('You can skip this. The app will open the hotel and pick the first available room.') }}
                </p>

                <div v-if="busy === 'offers'" class="text-sm text-slate-500">{{ t('Loading rooms…') }}</div>

                <div v-if="offers.length" class="max-h-56 overflow-auto rounded-xl border border-slate-200">
                    <button
                        v-for="offer in offers"
                        :key="offer.offer_id"
                        type="button"
                        class="flex w-full items-center justify-between gap-3 border-b border-slate-100 px-4 py-3 text-left last:border-b-0 hover:bg-slate-50"
                        :class="selected.offer_id === String(offer.offer_id) ? 'bg-emerald-50' : ''"
                        @click="pickOffer(offer)"
                    >
                        <span class="text-sm font-medium text-slate-900">{{ offer.label }}</span>
                        <span v-if="offer.price != null" class="shrink-0 text-sm text-slate-600">
                            {{ offer.price }}{{ offer.currency ? ` ${offer.currency}` : '' }}
                        </span>
                    </button>
                </div>

                <button
                    type="button"
                    class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-base font-medium text-slate-800"
                    @click="skipOffer"
                >
                    {{ t('Skip — any available room') }}
                </button>
            </div>

            <p v-if="error" class="rounded-xl bg-rose-50 px-3 py-2 text-sm text-rose-700">{{ error }}</p>
        </div>
    </div>
</template>
