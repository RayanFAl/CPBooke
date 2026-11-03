<?php

namespace App\Modules\Api\Airports\Services;

use App\Models\FeaturedAirport;
use App\Support\Airports\AirportKey;
use App\Support\Airports\AirportSearchScope;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AirportService
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function featured(): Collection
    {
        if (! Schema::hasTable('booknow_airports')) {
            return collect();
        }

        $featuredRows = FeaturedAirport::query()
            ->orderBy('sort_order')
            ->get(['airport_key', 'sort_order']);

        if ($featuredRows->isEmpty()) {
            return collect();
        }

        $records = DB::table('booknow_airports')
            ->select($this->booknowSelectColumns())
            ->where(function ($builder) use ($featuredRows): void {
                AirportKey::applyKeyFilter($builder, $featuredRows->pluck('airport_key')->all());
            })
            ->get()
            ->mapWithKeys(function (object $record): array {
                $airportKey = AirportKey::fromCodes($record->iata_code ?? null, $record->icao_code ?? null);

                return $airportKey === null ? [] : [$airportKey => $record];
            });

        return $featuredRows
            ->map(function (FeaturedAirport $featured) use ($records): ?array {
                $record = $records->get($featured->airport_key);

                if ($record === null) {
                    return null;
                }

                return $this->formatBooknowRecord($record, $featured->sort_order);
            })
            ->filter()
            ->values();
    }

    /**
     * Featured airports first, then the most traveled / searched airports from the app.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function browse(int $limit = 20): Collection
    {
        $featured = $this->featured();
        $remaining = max(0, $limit - $featured->count());

        if ($remaining === 0) {
            return $featured;
        }

        $exclude = $featured
            ->map(fn (array $airport): ?string => AirportKey::fromCodes($airport['iata_code'] ?? null, $airport['icao_code'] ?? null))
            ->filter()
            ->values()
            ->all();

        return $featured->concat($this->popular($remaining, $exclude))->values();
    }

    /**
     * @param  list<string>  $excludeKeys
     * @return Collection<int, array<string, mixed>>
     */
    public function popular(int $limit, array $excludeKeys = []): Collection
    {
        if ($limit < 1 || ! Schema::hasTable('booknow_airports')) {
            return collect();
        }

        $query = $this->baseAirportQuery();

        if ($excludeKeys !== []) {
            AirportKey::applyKeyExclusion($query, $excludeKeys);
        }

        $items = collect($query->limit($limit)->get())
            ->map(fn (object $record): array => $this->formatBooknowRecord($record))
            ->values();

        return $items;
    }

    /**
     * @return array{items: Collection<int, array<string, mixed>>, total: int}
     */
    public function paginate(?string $search, int $page, int $perPage): array
    {
        $perPage = min(max($perPage, 1), 50);
        $page = max($page, 1);
        $search = trim((string) ($search ?? ''));

        if (! Schema::hasTable('booknow_airports')) {
            return $this->paginateLegacyAirports($search, $page, $perPage);
        }

        $query = $this->baseAirportQuery()
            ->when($search !== '', fn ($builder) => AirportSearchScope::apply($builder, $search));

        $total = (clone $query)->count();
        $items = collect($query->offset(($page - 1) * $perPage)->limit($perPage)->get())
            ->map(fn (object $record): array => $this->formatBooknowRecord($record))
            ->values();

        return [
            'items' => $items,
            'total' => $total,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function paginationMeta(int $page, int $perPage, int $total): array
    {
        $lastPage = (int) max(1, (int) ceil($total / max($perPage, 1)));

        return [
            'current_page' => $page,
            'last_page' => $lastPage,
            'per_page' => $perPage,
            'total' => $total,
        ];
    }

    /**
     * @return array{items: Collection<int, array<string, mixed>>, total: int}
     */
    private function paginateLegacyAirports(string $search, int $page, int $perPage): array
    {
        $query = DB::table('airports')
            ->select(['id', 'name', 'code', 'city', 'country'])
            ->when($search !== '', function ($builder) use ($search): void {
                $builder->where(function ($nested) use ($search): void {
                    $nested->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('city', 'like', "%{$search}%")
                        ->orWhere('country', 'like', "%{$search}%");
                });
            })
            ->orderBy('name');

        $total = (clone $query)->count();
        $items = collect($query
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get())
            ->map(fn (object $record): array => [
                'iata_code' => $record->code,
                'icao_code' => null,
                'name_en' => $record->name,
                'name_ar' => null,
                'name_fr' => null,
                'city_en' => $record->city,
                'city_ar' => null,
                'city_fr' => null,
                'country_iso2' => null,
                'country_name_en' => $record->country,
                'country_name_ar' => null,
                'country_name_fr' => null,
            ])
            ->values();

        return [
            'items' => $items,
            'total' => $total,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function booknowSelectColumns(): array
    {
        return [
            'booknow_airports.iata_code',
            'booknow_airports.icao_code',
            'booknow_airports.name_en',
            'booknow_airports.name_ar',
            'booknow_airports.name_fr',
            'booknow_airports.city_en',
            'booknow_airports.city_ar',
            'booknow_airports.city_fr',
            'booknow_airports.country_iso2',
            'booknow_airports.country_name_en',
            'booknow_airports.country_name_ar',
            'booknow_airports.country_name_fr',
            'booknow_airports.latitude_deg',
            'booknow_airports.longitude_deg',
        ];
    }

    private function baseAirportQuery()
    {
        $query = DB::table('booknow_airports')
            ->select($this->booknowSelectColumns())
            ->where(function ($builder): void {
                $builder->where(function ($nested): void {
                    $nested->whereNotNull('booknow_airports.iata_code')
                        ->where('booknow_airports.iata_code', '!=', '');
                })->orWhere(function ($nested): void {
                    $nested->whereNotNull('booknow_airports.icao_code')
                        ->where('booknow_airports.icao_code', '!=', '');
                });
            });

        if (Schema::hasTable('airport_stats')) {
            $query->leftJoin('airport_stats', function ($join): void {
                $join->whereRaw('airport_stats.airport_key = ('.AirportKey::sqlExpression('booknow_airports').')');
            })
                ->orderByRaw('COALESCE(airport_stats.travel_count, 0) DESC')
                ->orderByRaw('COALESCE(airport_stats.search_count, 0) DESC');
        }

        return $query->orderBy('booknow_airports.name_en');
    }

    /**
     * @return array<string, mixed>
     */
    private function formatBooknowRecord(object $record, ?int $featuredOrder = null): array
    {
        return [
            'iata_code' => $record->iata_code,
            'icao_code' => $record->icao_code,
            'name_en' => $record->name_en,
            'name_ar' => $record->name_ar ?? null,
            'name_fr' => $record->name_fr ?? null,
            'city_en' => $record->city_en ?? null,
            'city_ar' => $record->city_ar ?? null,
            'city_fr' => $record->city_fr ?? null,
            'country_iso2' => $record->country_iso2 ?? null,
            'country_name_en' => $record->country_name_en ?? null,
            'country_name_ar' => $record->country_name_ar ?? null,
            'country_name_fr' => $record->country_name_fr ?? null,
            'latitude_deg' => isset($record->latitude_deg) ? (float) $record->latitude_deg : null,
            'longitude_deg' => isset($record->longitude_deg) ? (float) $record->longitude_deg : null,
            'featured_order' => $featuredOrder,
        ];
    }

    /**
     * Airports within $radiusKm of a GPS point. When none match, returns featured airports with distance.
     *
     * @return array{airports: Collection<int, array<string, mixed>>, source: string}
     */
    public function nearby(float $latitude, float $longitude, int $limit = 10, float $radiusKm = 40): array
    {
        $limit = min(max($limit, 1), 20);
        $radiusKm = min(max($radiusKm, 1), 500);

        $matches = $this->airportsNear($latitude, $longitude, $radiusKm)
            ->sortBy('distance_km')
            ->take($limit)
            ->values();

        if ($matches->isNotEmpty()) {
            return [
                'airports' => $matches,
                'source' => 'nearby',
            ];
        }

        $featured = $this->featured()
            ->map(function (array $airport) use ($latitude, $longitude): array {
                $airportLat = $airport['latitude_deg'] ?? null;
                $airportLng = $airport['longitude_deg'] ?? null;
                if (is_numeric($airportLat) && is_numeric($airportLng)) {
                    $airport['distance_km'] = round($this->haversineKm(
                        $latitude,
                        $longitude,
                        (float) $airportLat,
                        (float) $airportLng,
                    ), 1);
                }

                return $airport;
            })
            ->sortBy(fn (array $airport): float => (float) ($airport['distance_km'] ?? INF))
            ->take($limit)
            ->values();

        return [
            'airports' => $featured,
            'source' => 'featured',
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function airportsNear(float $latitude, float $longitude, float $radiusKm): Collection
    {
        if (! Schema::hasTable('booknow_airports')) {
            return collect();
        }

        $latDelta = $radiusKm / 111.0;
        $lngScale = max(abs(cos(deg2rad($latitude))), 0.2);
        $lngDelta = $radiusKm / (111.0 * $lngScale);

        $rows = DB::table('booknow_airports')
            ->select($this->booknowSelectColumns())
            ->whereNotNull('latitude_deg')
            ->whereNotNull('longitude_deg')
            ->whereBetween('latitude_deg', [$latitude - $latDelta, $latitude + $latDelta])
            ->whereBetween('longitude_deg', [$longitude - $lngDelta, $longitude + $lngDelta])
            ->limit(200)
            ->get();

        return collect($rows)
            ->map(function (object $record) use ($latitude, $longitude, $radiusKm): ?array {
                $distance = $this->haversineKm(
                    $latitude,
                    $longitude,
                    (float) $record->latitude_deg,
                    (float) $record->longitude_deg,
                );

                if ($distance > $radiusKm) {
                    return null;
                }

                $airport = $this->formatBooknowRecord($record);
                $airport['distance_km'] = round($distance, 1);

                return $airport;
            })
            ->filter()
            ->values();
    }

    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earth = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $earth * (2 * atan2(sqrt($a), sqrt(1 - $a)));
    }
}
