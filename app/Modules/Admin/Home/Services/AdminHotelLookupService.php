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
            'language' => 'en',
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

            return [
                'ok' => true,
                'hotels' => $this->normalizeHotels($body, $city, $cityId),
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
    private function normalizeHotels(array $payload, string $city, string $cityId): array
    {
        $rows = $this->extractList($payload, ['hotels', 'results', 'items', 'data']);
        $out = [];

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

            $offers = $this->normalizeOffers(
                $row['rooms'] ?? $merged['rooms'] ?? $row['offers'] ?? $row['rates'] ?? [],
            );

            $out[] = [
                'hotel_id' => $hotelId,
                'hotel_name' => $name,
                'city' => $this->firstString($merged, ['city', 'city_name', 'cityName']) ?: $city,
                'city_id' => $this->firstString($merged, ['city_id', 'cityId']) ?: $cityId,
                'source' => $this->firstString($merged, ['source', 'supplierSourceId']) ?: null,
                'image_url' => $this->normalizeImageUrl($this->firstString($merged, [
                    'image_url', 'imageUrl', 'image', 'thumbImage', 'thumb_image', 'thumbnail',
                ])),
                'offers' => $offers,
            ];
        }

        return array_slice($out, 0, 40);
    }

    /**
     * @return list<array{offer_id: string, label: string, price: float|null, currency: string|null}>
     */
    private function normalizeOffers(mixed $raw): array
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
            $offerId = $this->firstString($row, [
                'offer_id', 'offerId', 'associationId', 'rate_key', 'rateKey', 'id', 'code',
            ]);

            if ($offerId === '' || isset($seen[$offerId])) {
                continue;
            }

            $seen[$offerId] = true;
            $roomName = $this->firstString($row, ['name', 'type', 'room_type', 'roomType']);
            $board = $this->firstString($row, ['boardName', 'board_name']);
            $label = trim($roomName.($board !== '' ? ' · '.$board : ''));
            $price = $row['price'] ?? $row['amount'] ?? $row['total'] ?? null;
            $priceFloat = is_numeric($price) ? (float) $price : null;

            $out[] = [
                'offer_id' => $offerId,
                'label' => $label !== '' ? $label : $offerId,
                'price' => $priceFloat,
                'currency' => $this->firstString($row, ['currency']) ?: null,
            ];
        }

        return array_slice($out, 0, 60);
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
