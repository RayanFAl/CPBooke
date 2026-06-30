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

        $query = DB::table('booknow_airports')
            ->select($this->booknowSelectColumns())
            ->where(function ($builder): void {
                $builder->where(function ($nested): void {
                    $nested->whereNotNull('iata_code')
                        ->where('iata_code', '!=', '');
                })->orWhere(function ($nested): void {
                    $nested->whereNotNull('icao_code')
                        ->where('icao_code', '!=', '');
                });
            })
            ->when($search !== '', fn ($builder) => AirportSearchScope::apply($builder, $search))
            ->orderBy('name_en');

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
            'iata_code',
            'icao_code',
            'name_en',
            'name_ar',
            'name_fr',
            'city_en',
            'city_ar',
            'city_fr',
            'country_iso2',
            'country_name_en',
            'country_name_ar',
            'country_name_fr',
        ];
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
            'featured_order' => $featuredOrder,
        ];
    }
}
