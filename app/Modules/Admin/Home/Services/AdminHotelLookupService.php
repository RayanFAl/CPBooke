<?php

namespace App\Modules\Admin\Home\Services;

use App\Models\Provider;
use App\Models\ProviderApiConfig;
use App\Modules\Providers\Services\ProviderApiConfigFromEnvService;
use App\Modules\Providers\Services\ProviderApiHttpClient;
use Illuminate\Support\Facades\Log;

class AdminHotelLookupService
{
    public function __construct(
        private readonly ProviderApiHttpClient $httpClient,
        private readonly ProviderApiConfigFromEnvService $configFromEnv,
    ) {}

    /**
     * Agency autocomplete is Latin-biased; Arabic queries are mapped first.
     * Country-style queries like «تونس» expand to a Tunisian city list.
     *
     * @return array{ok: bool, cities: list<array{city_id: string, city: string, city_ar: string|null, country: string|null}>, error: string|null}
     */
    public function autocomplete(string $query): array
    {
        $config = $this->resolveApiConfig();

        if ($config === null) {
            return $this->configMissing('cities');
        }

        $raw = trim($query);
        if ($raw === '') {
            return ['ok' => true, 'cities' => [], 'error' => null];
        }

        try {
            $apiQuery = $this->resolveAutocompleteQuery($raw);
            $cities = $this->fetchCities($config, $apiQuery);

            if ($this->isTunisiaQuery($raw, $apiQuery)) {
                $cities = $this->mergeCities(
                    $cities,
                    $this->expandTunisiaCities($config),
                );
            }

            $cities = $this->attachArabicLabels($cities);

            return [
                'ok' => true,
                'cities' => array_slice($cities, 0, 40),
                'error' => null,
            ];
        } catch (\Throwable $exception) {
            Log::warning('admin.hotel_lookup.autocomplete_failed', [
                'message' => $exception->getMessage(),
            ]);

            return [
                'ok' => false,
                'cities' => [],
                'error' => 'Unable to reach the hotel provider for city search.',
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{ok: bool, hotels: list<array<string, mixed>>, error: string|null}
     */
    public function search(array $input): array
    {
        $config = $this->resolveApiConfig();

        if ($config === null) {
            return $this->configMissing('hotels');
        }

        $city = trim((string) ($input['city'] ?? ''));
        $cityId = trim((string) ($input['city_id'] ?? ''));
        $checkIn = $this->dateOnly($input['check_in'] ?? null);
        $checkOut = $this->dateOnly($input['check_out'] ?? null);

        if ($city === '' || $cityId === '' || $checkIn === null || $checkOut === null) {
            return [
                'ok' => false,
                'hotels' => [],
                'error' => 'City, city id, check-in and check-out are required.',
            ];
        }

        $adults = max(1, (int) ($input['adults'] ?? 2));
        $payload = [
            'city' => $city,
            'city_id' => ctype_digit($cityId) ? (int) $cityId : $cityId,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'rooms' => [
                [
                    'adult' => $adults,
                    'children' => [],
                ],
            ],
            'language' => 'fr-FR',
            'page' => 1,
        ];

        try {
            $response = $this->httpClient->post($config, '/hotels/search', $payload);
            $body = $response->json();

            if (! $response->successful() || ! is_array($body)) {
                return [
                    'ok' => false,
                    'hotels' => [],
                    'error' => 'Hotel search failed (HTTP '.$response->status().').',
                ];
            }

            $searchUuid = $this->firstString($body, ['search_uuid', 'searchUuid']);
            if ($searchUuid === '' && isset($body['data']) && is_array($body['data'])) {
                $searchUuid = $this->firstString($body['data'], ['search_uuid', 'searchUuid']);
            }

            return [
                'ok' => true,
                'search_uuid' => $searchUuid !== '' ? $searchUuid : null,
                'hotels' => $this->normalizeHotels($body, $city, $cityId, $searchUuid !== '' ? $searchUuid : null),
                'error' => null,
            ];
        } catch (\Throwable $exception) {
            Log::warning('admin.hotel_lookup.search_failed', [
                'message' => $exception->getMessage(),
            ]);

            return [
                'ok' => false,
                'hotels' => [],
                'error' => 'Unable to reach the hotel provider for search.',
            ];
        }
    }

    /**
     * Reserve an offer session with the provider (`POST /hotels/select`).
     *
     * @param  array<string, mixed>  $input
     * @return array{ok: bool, booking_uuid: string|null, payload: array<string, mixed>, error: string|null}
     */
    public function selectOffer(array $input): array
    {
        $config = $this->resolveApiConfig();

        if ($config === null) {
            return [
                'ok' => false,
                'booking_uuid' => null,
                'payload' => [],
                'error' => 'Hotel provider is not configured.',
            ];
        }

        $searchUuid = trim((string) ($input['search_uuid'] ?? ''));
        $rateKey = trim((string) ($input['rate_key'] ?? ''));
        $offerId = trim((string) ($input['offer_id'] ?? ''));
        if ($rateKey === '') {
            $rateKey = $offerId;
        }
        $searchCode = trim((string) ($input['search_code'] ?? ''));
        $city = trim((string) ($input['city'] ?? $input['city_name'] ?? ''));
        $cityId = trim((string) ($input['city_id'] ?? ''));
        $hotelId = trim((string) ($input['hotel_id'] ?? ''));
        $checkIn = $this->dateOnly($input['check_in'] ?? null);
        $checkOut = $this->dateOnly($input['check_out'] ?? null);
        $adults = max(1, (int) ($input['adults'] ?? 2));
        $rooms = max(1, (int) ($input['rooms'] ?? 1));

        if ($searchUuid === '' || $rateKey === '') {
            return [
                'ok' => false,
                'booking_uuid' => null,
                'payload' => [],
                'error' => 'search_uuid and rate_key are required to select a hotel offer.',
            ];
        }

        // Mobile contract + Postman collection fields for /hotels/select.
        $payload = array_filter([
            'search_uuid' => $searchUuid,
            'rate_key' => $rateKey,
            'search_code' => $searchCode,
            'language' => 'fr-FR',
            'city' => $city !== '' ? $city : null,
            'city_id' => $cityId !== ''
                ? (ctype_digit($cityId) ? (int) $cityId : $cityId)
                : null,
            'hotel_id' => $hotelId !== '' ? $hotelId : null,
            'offer_id' => $offerId !== '' ? $offerId : null,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'adults' => $adults,
            'rooms' => $rooms,
        ], static fn ($value) => $value !== null && $value !== '');

        try {
            $response = $this->httpClient->post($config, '/hotels/select', $payload);
            $body = $response->json();

            if (! $response->successful() || ! is_array($body)) {
                return [
                    'ok' => false,
                    'booking_uuid' => null,
                    'payload' => is_array($body) ? $body : [],
                    'error' => $this->providerErrorMessage($body, 'Hotel offer select failed (HTTP '.$response->status().').'),
                ];
            }

            $data = isset($body['data']) && is_array($body['data']) ? $body['data'] : $body;
            $bookingUuid = $this->firstString($data, ['booking_uuid', 'bookingUuid', 'uuid']);
            if ($bookingUuid === '') {
                $bookingUuid = $this->firstString($body, ['booking_uuid', 'bookingUuid', 'uuid']);
            }

            if ($bookingUuid === '') {
                return [
                    'ok' => false,
                    'booking_uuid' => null,
                    'payload' => $body,
                    'error' => 'Provider select did not return a booking_uuid.',
                ];
            }

            return [
                'ok' => true,
                'booking_uuid' => $bookingUuid,
                'payload' => $body,
                'error' => null,
            ];
        } catch (\Throwable $exception) {
            Log::warning('admin.hotel_lookup.select_failed', [
                'message' => $exception->getMessage(),
            ]);

            return [
                'ok' => false,
                'booking_uuid' => null,
                'payload' => [],
                'error' => 'Unable to reach the hotel provider to select the offer.',
            ];
        }
    }

    /**
     * Confirm a hotel booking with the provider (`POST /hotels/book`).
     *
     * @param  array<string, mixed>  $input
     * @return array{ok: bool, booking: array<string, mixed>, error: string|null}
     */
    public function book(array $input): array
    {
        $config = $this->resolveApiConfig();

        if ($config === null) {
            return [
                'ok' => false,
                'booking' => [],
                'error' => 'Hotel provider is not configured.',
            ];
        }

        $bookingUuid = trim((string) ($input['booking_uuid'] ?? ''));
        if ($bookingUuid === '') {
            return [
                'ok' => false,
                'booking' => [],
                'error' => 'booking_uuid is required to book a hotel.',
            ];
        }

        $firstName = trim((string) ($input['contact_first_name'] ?? $input['first_name'] ?? 'Guest'));
        $lastName = trim((string) ($input['contact_last_name'] ?? $input['last_name'] ?? $firstName));
        $email = trim((string) ($input['contact_email'] ?? $input['email'] ?? ''));
        $phone = trim((string) ($input['contact_phone'] ?? $input['phone'] ?? ''));
        $country = trim((string) ($input['customer_country'] ?? $input['country'] ?? 'Libya'));
        $city = trim((string) ($input['customer_city'] ?? $input['city_name'] ?? $input['city'] ?? 'Tripoli'));
        $civility = $this->normalizeCivility($input['guest_civility'] ?? $input['civility'] ?? 'Mr');
        $roomsCount = max(1, (int) ($input['rooms'] ?? 1));
        $adults = max(1, (int) ($input['adults'] ?? $roomsCount));
        $hotelId = trim((string) ($input['hotel_id'] ?? ''));
        $cityId = trim((string) ($input['city_id'] ?? ''));
        $offerId = trim((string) ($input['offer_id'] ?? ''));
        $checkIn = $this->dateOnly($input['check_in'] ?? null);
        $checkOut = $this->dateOnly($input['check_out'] ?? null);
        $amount = isset($input['total_amount']) && is_numeric($input['total_amount'])
            ? (float) $input['total_amount']
            : null;
        $currency = strtoupper(trim((string) ($input['currency'] ?? 'LYD'))) ?: 'LYD';

        $guest = [
            'civility' => $civility,
            'first_name' => $firstName !== '' ? $firstName : 'Guest',
            'last_name' => $lastName !== '' ? $lastName : 'Guest',
        ];

        $roomsPayload = [];
        for ($i = 0; $i < $roomsCount; $i++) {
            $roomsPayload[] = ['guests' => [$guest]];
        }

        // Mobile contract (booking_uuid + rooms[].guests + customer) plus
        // Postman collection fields used by agency docs.
        $payload = array_filter([
            'booking_uuid' => $bookingUuid,
            'rooms' => $roomsPayload,
            'customer' => array_filter([
                'first_name' => $guest['first_name'],
                'last_name' => $guest['last_name'],
                'email' => $email,
                'phone' => $phone,
                'country' => $country !== '' ? $country : null,
                'city' => $city !== '' ? $city : null,
            ], static fn ($value) => $value !== null && $value !== ''),
            'hotel_id' => $hotelId !== ''
                ? (ctype_digit($hotelId) ? (int) $hotelId : $hotelId)
                : null,
            'city_id' => $cityId !== ''
                ? (ctype_digit($cityId) ? (int) $cityId : $cityId)
                : null,
            'offer_id' => $offerId !== '' ? $offerId : null,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'adults' => $adults,
            'payment_method' => $input['payment_method'] ?? 'wallet',
            'amount' => $amount,
            'currency' => $currency,
            'guests' => [[
                'title' => $civility === 'Mme' ? 'Mrs.' : 'Mr.',
                'first_name' => $guest['first_name'],
                'last_name' => $guest['last_name'],
                'email' => $email,
                'phone' => $phone,
            ]],
        ], static fn ($value) => $value !== null && $value !== '');

        try {
            $response = $this->httpClient->post($config, '/hotels/book', $payload);
            $body = $response->json();

            if (! $response->successful() || ! is_array($body)) {
                return [
                    'ok' => false,
                    'booking' => is_array($body) ? $body : [],
                    'error' => $this->providerErrorMessage($body, 'Hotel book failed (HTTP '.$response->status().').'),
                ];
            }

            $data = isset($body['data']) && is_array($body['data']) ? $body['data'] : $body;

            return [
                'ok' => true,
                'booking' => is_array($data) ? $data : $body,
                'error' => null,
            ];
        } catch (\Throwable $exception) {
            Log::warning('admin.hotel_lookup.book_failed', [
                'message' => $exception->getMessage(),
            ]);

            return [
                'ok' => false,
                'booking' => [],
                'error' => 'Unable to reach the hotel provider to confirm the booking.',
            ];
        }
    }

    /**
     * @return array{ok: bool, offers: list<array<string, mixed>>, error: string|null}
     */
    public function offers(
        string $hotelId,
        string $cityId,
        ?string $source = null,
        ?string $checkIn = null,
        ?string $checkOut = null,
        ?string $city = null,
    ): array {
        $config = $this->resolveApiConfig();

        if ($config === null) {
            return $this->configMissing('offers');
        }

        $hotelId = trim($hotelId);
        $cityId = trim($cityId);

        if ($hotelId === '' || $cityId === '') {
            return [
                'ok' => false,
                'offers' => [],
                'error' => 'Hotel id and city id are required.',
            ];
        }

        try {
            $offers = $this->offersFromDetails($config, $hotelId, $cityId, $source);

            if ($offers === [] && $checkIn && $checkOut) {
                $offers = $this->offersFromSearch(
                    $config,
                    $hotelId,
                    $cityId,
                    $city ?: '',
                    $checkIn,
                    $checkOut,
                );
            }

            return [
                'ok' => true,
                'offers' => $offers,
                'error' => null,
            ];
        } catch (\Throwable $exception) {
            Log::warning('admin.hotel_lookup.offers_failed', [
                'message' => $exception->getMessage(),
            ]);

            return [
                'ok' => false,
                'offers' => [],
                'error' => 'Unable to load room offers from the hotel provider.',
            ];
        }
    }

    public function isConfigured(): bool
    {
        return $this->resolveApiConfig() !== null;
    }

    /**
     * @return array{ok: bool, cities?: list<mixed>, hotels?: list<mixed>, offers?: list<mixed>, error: string}
     */
    private function configMissing(string $key): array
    {
        return [
            'ok' => false,
            $key => [],
            'error' => 'Hotel provider API is not configured. Set PROVIDER_API_BASE_URL and PROVIDER_API_TOKEN (agency hotels base, e.g. https://agency.atom.ly/agency/median/api/v1).',
        ];
    }

    /**
     * @return list<array{city_id: string, city: string, city_ar: string|null, country: string|null}>
     */
    private function fetchCities(ProviderApiConfig $config, string $query): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $response = $this->httpClient->get($config, '/hotels/autocomplete', [
            'q' => $query,
        ]);
        $payload = $response->json();

        if (! $response->successful() || ! is_array($payload)) {
            return [];
        }

        return $this->normalizeCities($payload);
    }

    /**
     * Keep in sync with mobile hotel destination Arabic → English aliases.
     */
    private function resolveAutocompleteQuery(string $raw): string
    {
        $map = $this->arabicCityAliases();
        if (isset($map[$raw])) {
            return $map[$raw];
        }

        if (preg_match('/[\x{0600}-\x{06FF}]/u', $raw) === 1) {
            foreach ($map as $ar => $en) {
                if (str_contains($raw, $ar)) {
                    return $en;
                }
            }
        }

        return $raw;
    }

    /**
     * @return array<string, string>
     */
    private function arabicCityAliases(): array
    {
        return [
            'طرابلس' => 'Tripoli',
            'بنغازي' => 'Benghazi',
            'مصراتة' => 'Misrata',
            'مصراته' => 'Misrata',
            'سبها' => 'Sebha',
            'الزاوية' => 'Zawiya',
            'زليتن' => 'Zliten',
            'تونس' => 'Tunis',
            'تونس العاصمة' => 'Tunis',
            'صفاقس' => 'Sfax',
            'سوسة' => 'Sousse',
            'جربة' => 'Djerba',
            'جربة جزيرة' => 'Djerba',
            'نابل' => 'Nabeul',
            'الحمامات' => 'Hammamet',
            'المنستير' => 'Monastir',
            'المهدية' => 'Mahdia',
            'قابس' => 'Gabes',
            'قفصة' => 'Gafsa',
            'بنزرت' => 'Bizerte',
            'القيروان' => 'Kairouan',
            'طبرقة' => 'Tabarka',
            'القاهرة' => 'Cairo',
            'الإسكندرية' => 'Alexandria',
            'الاسكندرية' => 'Alexandria',
            'الدار البيضاء' => 'Casablanca',
            'الرباط' => 'Rabat',
            'مراكش' => 'Marrakech',
        ];
    }

    /**
     * @return array<string, string> English city => Arabic label
     */
    private function arabicLabelsByEnglishCity(): array
    {
        $flip = [];
        foreach ($this->arabicCityAliases() as $ar => $en) {
            $flip[$en] ??= $ar;
        }

        return $flip;
    }

    private function isTunisiaQuery(string $raw, string $apiQuery): bool
    {
        $needle = mb_strtolower(trim($raw.' '.$apiQuery));

        return str_contains($needle, 'تونس')
            || str_contains($needle, 'tunis')
            || str_contains($needle, 'tunisia')
            || str_contains($needle, 'tunisie');
    }

    /**
     * @return list<array{city_id: string, city: string, city_ar: string|null, country: string|null}>
     */
    private function expandTunisiaCities(ProviderApiConfig $config): array
    {
        $names = [
            'Tunis',
            'Sousse',
            'Hammamet',
            'Djerba',
            'Sfax',
            'Monastir',
            'Nabeul',
            'Mahdia',
            'Bizerte',
            'Gabes',
            'Kairouan',
            'Tabarka',
            'Tozeur',
            'Houmt Souk',
        ];

        $out = [];
        foreach ($names as $name) {
            foreach ($this->fetchCities($config, $name) as $city) {
                $country = mb_strtolower((string) ($city['country'] ?? ''));
                if ($country !== '' && ! str_contains($country, 'tunisia') && ! str_contains($country, 'tunisie')) {
                    // Keep exact name matches even if country missing; skip clear mismatches.
                    if (mb_strtolower($city['city']) !== mb_strtolower($name)) {
                        continue;
                    }
                }
                $out[] = $city;
            }
        }

        return $out;
    }

    /**
     * @param  list<array{city_id: string, city: string, city_ar: string|null, country: string|null}>  $primary
     * @param  list<array{city_id: string, city: string, city_ar: string|null, country: string|null}>  $extra
     * @return list<array{city_id: string, city: string, city_ar: string|null, country: string|null}>
     */
    private function mergeCities(array $primary, array $extra): array
    {
        $seen = [];
        $out = [];

        foreach (array_merge($primary, $extra) as $city) {
            $id = $city['city_id'] ?? '';
            if ($id === '' || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $out[] = $city;
        }

        return $out;
    }

    /**
     * @param  list<array{city_id: string, city: string, city_ar?: string|null, country: string|null}>  $cities
     * @return list<array{city_id: string, city: string, city_ar: string|null, country: string|null}>
     */
    private function attachArabicLabels(array $cities): array
    {
        $labels = $this->arabicLabelsByEnglishCity();

        return array_map(static function (array $city) use ($labels): array {
            $en = $city['city'] ?? '';
            $city['city_ar'] = $city['city_ar'] ?? ($labels[$en] ?? null);

            return $city;
        }, $cities);
    }

    /**
     * @return list<array{city_id: string, city: string, city_ar: string|null, country: string|null}>
     */
    private function normalizeCities(array $payload): array
    {
        $rows = $this->extractList($payload, [
            'destinations',
            'cities',
            'suggestions',
            'autocomplete',
            'results',
            'items',
            'data',
        ]);

        $out = [];
        $seen = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            // Country wrappers: { country, cities: [...] }
            if (isset($row['cities']) && is_array($row['cities'])) {
                foreach ($row['cities'] as $cityRow) {
                    if (! is_array($cityRow)) {
                        continue;
                    }
                    $normalized = $this->normalizeCityRow(
                        $cityRow,
                        isset($row['country']) ? (string) $row['country'] : null,
                    );
                    if ($normalized === null || isset($seen[$normalized['city_id']])) {
                        continue;
                    }
                    $seen[$normalized['city_id']] = true;
                    $out[] = $normalized;
                }

                continue;
            }

            $normalized = $this->normalizeCityRow($row);
            if ($normalized === null || isset($seen[$normalized['city_id']])) {
                continue;
            }
            $seen[$normalized['city_id']] = true;
            $out[] = $normalized;
        }

        return array_slice($out, 0, 40);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{city_id: string, city: string, city_ar: string|null, country: string|null}|null
     */
    private function normalizeCityRow(array $row, ?string $countryHint = null): ?array
    {
        $cityId = $this->firstString($row, ['city_id', 'cityId', 'id', 'destination_id', 'destinationId']);
        $city = $this->firstString($row, ['city', 'city_name', 'cityName', 'name', 'label', 'destination']);

        if ($cityId === '' || $city === '') {
            return null;
        }

        $country = $this->firstString($row, ['country', 'country_name', 'countryName']) ?: $countryHint;

        return [
            'city_id' => $cityId,
            'city' => $city,
            'city_ar' => null,
            'country' => $country !== '' ? $country : null,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function normalizeHotels(array $payload, string $city, string $cityId, ?string $searchUuid = null): array
    {
        $rows = $this->extractList($payload, ['hotels', 'results', 'items', 'data']);
        $out = [];
        $rootSearchUuid = $searchUuid
            ?: $this->firstString($payload, ['search_uuid', 'searchUuid'])
            ?: null;

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            // Agency shape: { hotel: {...}, rooms: [[...]], source }
            $nested = (isset($row['hotel']) && is_array($row['hotel'])) ? $row['hotel'] : [];
            $merged = $nested === [] ? $row : array_merge($row, $nested);

            $hotelId = $this->firstString($merged, ['hotel_id', 'hotelId', 'id']);
            $name = $this->firstString($merged, ['hotel_name', 'hotelName', 'name', 'title']);

            if ($hotelId === '' || $name === '') {
                continue;
            }

            $hotelSearchUuid = $this->firstString($merged, ['search_uuid', 'searchUuid'])
                ?: $rootSearchUuid;

            $offers = $this->normalizeOffers(
                $row['rooms'] ?? $merged['rooms'] ?? $row['offers'] ?? $row['rates'] ?? [],
                $hotelSearchUuid,
            );

            $out[] = [
                'hotel_id' => $hotelId,
                'hotel_name' => $name,
                'city' => $this->firstString($merged, ['city', 'city_name', 'cityName']) ?: $city,
                'city_id' => $this->firstString($merged, ['city_id', 'cityId']) ?: $cityId,
                'country' => $this->firstString($merged, ['country', 'country_name', 'countryName']) ?: null,
                'source' => $this->firstString($merged, ['source', 'supplierSourceId']) ?: null,
                'search_uuid' => $hotelSearchUuid,
                'search_code' => $this->firstString($merged, ['search_code', 'searchCode']) ?: '',
                'image_url' => $this->normalizeImageUrl($this->firstString($merged, [
                    'image_url', 'imageUrl', 'image', 'thumbImage', 'thumb_image', 'thumbnail',
                ])),
                'offers' => $offers,
            ];
        }

        return array_slice($out, 0, 40);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function normalizeOffers(mixed $raw, ?string $searchUuid = null): array
    {
        if (! is_array($raw)) {
            return [];
        }

        // Envelope
        if (isset($raw['rooms']) || isset($raw['offers']) || isset($raw['rates'])) {
            $raw = $raw['rooms'] ?? $raw['offers'] ?? $raw['rates'];
        }

        if (! is_array($raw)) {
            return [];
        }

        // Agency often nests rooms as [[{offer...}, ...], ...]
        $flat = [];
        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }
            if ($row !== [] && array_is_list($row) && isset($row[0]) && is_array($row[0])) {
                foreach ($row as $inner) {
                    if (is_array($inner)) {
                        $flat[] = $inner;
                    }
                }
                continue;
            }
            $flat[] = $row;
        }

        $out = [];
        $seen = [];

        foreach ($flat as $row) {
            $rateKey = $this->firstString($row, ['rate_key', 'rateKey', 'key']);
            $offerId = $this->firstString($row, [
                'offer_id', 'offerId', 'associationId', 'rate_key', 'rateKey', 'id', 'code',
            ]);

            if ($offerId === '' || isset($seen[$offerId])) {
                continue;
            }

            $seen[$offerId] = true;
            $roomName = $this->firstString($row, ['name', 'type', 'room_type', 'roomType', 'room_name']);
            $board = $this->firstString($row, ['boardName', 'board_name', 'board']);
            $label = trim($roomName.($board !== '' ? ' · '.$board : ''));
            $price = $row['price'] ?? $row['amount'] ?? $row['total'] ?? null;
            $priceFloat = is_numeric($price) ? (float) $price : null;
            $rowSearchUuid = $this->firstString($row, ['search_uuid', 'searchUuid']) ?: $searchUuid;

            $out[] = [
                'offer_id' => $offerId,
                'rate_key' => $rateKey !== '' ? $rateKey : $offerId,
                'search_uuid' => $rowSearchUuid,
                'search_code' => $this->firstString($row, ['search_code', 'searchCode']) ?: '',
                'label' => $label !== '' ? $label : $offerId,
                'room_name' => $roomName !== '' ? $roomName : null,
                'board' => $board !== '' ? $board : null,
                'price' => $priceFloat,
                'currency' => $this->firstString($row, ['currency']) ?: null,
            ];
        }

        return array_slice($out, 0, 60);
    }

    private function normalizeCivility(mixed $raw): string
    {
        $value = strtolower(trim((string) $raw));
        $value = str_replace('.', '', $value);

        return match ($value) {
            'mrs', 'ms', 'miss', 'mme', 'madame', 'mademoiselle' => 'Mme',
            default => 'Mr',
        };
    }

    /**
     * @param  mixed  $body
     */
    private function providerErrorMessage(mixed $body, string $fallback): string
    {
        if (! is_array($body)) {
            return $fallback;
        }

        foreach (['message', 'error', 'detail'] as $key) {
            if (! empty($body[$key]) && is_scalar($body[$key])) {
                return (string) $body[$key];
            }
        }

        if (isset($body['errors']) && is_array($body['errors'])) {
            $first = reset($body['errors']);
            if (is_string($first) && $first !== '') {
                return $first;
            }
            if (is_array($first) && isset($first[0]) && is_string($first[0])) {
                return $first[0];
            }
        }

        return $fallback;
    }

    /**
     * @return list<array{offer_id: string, label: string, price: float|null, currency: string|null}>
     */
    private function offersFromDetails(
        ProviderApiConfig $config,
        string $hotelId,
        string $cityId,
        ?string $source,
    ): array {
        $query = [
            'hotel_id' => $hotelId,
            'city_id' => ctype_digit($cityId) ? (int) $cityId : $cityId,
        ];

        if (is_string($source) && trim($source) !== '') {
            $query['source'] = trim($source);
        }

        $response = $this->httpClient->get($config, '/hotels/details', $query);
        $body = $response->json();

        if (! $response->successful() || ! is_array($body)) {
            return [];
        }

        $data = $body['data'] ?? $body;

        if (! is_array($data)) {
            return [];
        }

        return $this->normalizeOffers(
            $data['rooms'] ?? $data['offers'] ?? $data['rates'] ?? $data,
        );
    }

    /**
     * @return list<array{offer_id: string, label: string, price: float|null, currency: string|null}>
     */
    private function offersFromSearch(
        ProviderApiConfig $config,
        string $hotelId,
        string $cityId,
        string $city,
        string $checkIn,
        string $checkOut,
    ): array {
        $search = $this->search([
            'city' => $city !== '' ? $city : 'City',
            'city_id' => $cityId,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'adults' => 2,
        ]);

        if (! ($search['ok'] ?? false)) {
            return [];
        }

        foreach ($search['hotels'] as $hotel) {
            if (($hotel['hotel_id'] ?? '') === $hotelId) {
                return is_array($hotel['offers'] ?? null) ? $hotel['offers'] : [];
            }
        }

        return [];
    }

    /**
     * @param  list<string>  $keys
     * @return list<mixed>
     */
    private function extractList(array $payload, array $keys): array
    {
        if (isset($payload['data']) && is_array($payload['data']) && array_is_list($payload['data'])) {
            return $payload['data'];
        }

        foreach ($keys as $key) {
            $value = $payload[$key] ?? null;
            if (is_array($value) && array_is_list($value)) {
                return $value;
            }
            if (is_array($value) && isset($value['data']) && is_array($value['data']) && array_is_list($value['data'])) {
                return $value['data'];
            }
        }

        if (isset($payload['data']) && is_array($payload['data'])) {
            return $this->extractList($payload['data'], $keys);
        }

        return array_is_list($payload) ? $payload : [];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $keys
     */
    private function firstString(array $row, array $keys): string
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $row) || $row[$key] === null) {
                continue;
            }
            $value = trim((string) $row[$key]);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function normalizeImageUrl(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        // Encode spaces / unicode paths from supplier CDNs.
        $parts = parse_url($url);
        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return $url;
        }

        $path = $parts['path'] ?? '';
        $encodedPath = implode('/', array_map(
            static fn (string $segment): string => rawurlencode(rawurldecode($segment)),
            explode('/', $path),
        ));

        $query = isset($parts['query']) ? '?'.$parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#'.$parts['fragment'] : '';
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return $parts['scheme'].'://'.$parts['host'].$port.$encodedPath.$query.$fragment;
    }

    private function dateOnly(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $raw, $matches) === 1) {
            return $matches[0];
        }

        try {
            return \Carbon\Carbon::parse($raw)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolveApiConfig(): ?ProviderApiConfig
    {
        $environment = strtolower((string) config('provider_api.environment', ProviderApiConfig::ENVIRONMENT_PRODUCTION));

        $provider = Provider::query()->where('key', Provider::KEY_BOOKNOW_HOTELS)->first()
            ?? Provider::query()->where('key', Provider::KEY_BOOKNOW)->first()
            ?? Provider::query()->where('status', Provider::STATUS_ACTIVE)->orderBy('id')->first();

        if ($provider === null) {
            return null;
        }

        $config = ProviderApiConfig::query()
            ->where('provider_id', $provider->id)
            ->where('environment', $environment)
            ->where('status', ProviderApiConfig::STATUS_ACTIVE)
            ->whereNotNull('base_url')
            ->first();

        if ($config !== null && filled($config->base_url)) {
            return $config;
        }

        return $this->configFromEnv->syncForProvider($provider);
    }
}
