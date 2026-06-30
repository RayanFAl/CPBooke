<?php

namespace App\Modules\Admin\Airports\Services;

use App\Models\FeaturedAirport;
use App\Support\Airports\AirportKey;
use App\Support\Airports\AirportSearchScope;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class FeaturedAirportService
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function listWithDetails(): Collection
    {
        $featured = FeaturedAirport::query()
            ->orderBy('sort_order')
            ->get();

        if ($featured->isEmpty()) {
            return collect();
        }

        $records = $this->resolveAirportsByKeys($featured->pluck('airport_key'));

        return $featured
            ->map(function (FeaturedAirport $item) use ($records): ?array {
                $airport = $records->get($item->airport_key);

                if ($airport === null) {
                    return null;
                }

                return array_merge($airport, [
                    'sort_order' => $item->sort_order,
                ]);
            })
            ->filter()
            ->values();
    }

    /**
     * @param  list<string>  $airportKeys
     * @return Collection<int, array<string, mixed>>
     */
    public function sync(array $airportKeys): Collection
    {
        $airportKeys = array_values(array_unique(array_map('strval', $airportKeys)));

        if (count($airportKeys) > FeaturedAirport::MAX_COUNT) {
            throw ValidationException::withMessages([
                'airports' => 'You can feature at most '.FeaturedAirport::MAX_COUNT.' airports.',
            ]);
        }

        foreach ($airportKeys as $airportKey) {
            if (AirportKey::parse($airportKey) === null) {
                throw ValidationException::withMessages([
                    'airports' => "Invalid airport key: {$airportKey}.",
                ]);
            }

            if (! $this->airportExists($airportKey)) {
                throw ValidationException::withMessages([
                    'airports' => "Airport not found: {$airportKey}.",
                ]);
            }
        }

        DB::transaction(function () use ($airportKeys): void {
            FeaturedAirport::query()->delete();

            foreach ($airportKeys as $index => $airportKey) {
                FeaturedAirport::query()->create([
                    'airport_key' => $airportKey,
                    'sort_order' => $index + 1,
                ]);
            }
        });

        return $this->listWithDetails();
    }

    public function isFeatured(string $airportKey): bool
    {
        return FeaturedAirport::query()
            ->where('airport_key', $airportKey)
            ->exists();
    }

    public function featuredOrder(string $airportKey): ?int
    {
        return FeaturedAirport::query()
            ->where('airport_key', $airportKey)
            ->value('sort_order');
    }

    /**
     * @return Collection<int, string>
     */
    public function featuredKeys(): Collection
    {
        return FeaturedAirport::query()
            ->orderBy('sort_order')
            ->pluck('airport_key');
    }

    /**
     * Add or remove an airport from the featured list.
     */
    public function toggle(string $airportKey): bool
    {
        if (AirportKey::parse($airportKey) === null) {
            throw ValidationException::withMessages([
                'featured' => "Invalid airport key: {$airportKey}.",
            ]);
        }

        if (! $this->airportExists($airportKey)) {
            throw ValidationException::withMessages([
                'featured' => "Airport not found: {$airportKey}.",
            ]);
        }

        $existing = FeaturedAirport::query()
            ->where('airport_key', $airportKey)
            ->first();

        if ($existing !== null) {
            $this->removeAndReorder($airportKey);

            return false;
        }

        if (FeaturedAirport::query()->count() >= FeaturedAirport::MAX_COUNT) {
            throw ValidationException::withMessages([
                'featured' => 'You can feature at most '.FeaturedAirport::MAX_COUNT.' airports.',
            ]);
        }

        FeaturedAirport::query()->create([
            'airport_key' => $airportKey,
            'sort_order' => FeaturedAirport::query()->count() + 1,
        ]);

        return true;
    }

    public function remove(string $airportKey): void
    {
        if (! FeaturedAirport::query()->where('airport_key', $airportKey)->exists()) {
            return;
        }

        $this->removeAndReorder($airportKey);
    }

    private function removeAndReorder(string $airportKey): void
    {
        DB::transaction(function () use ($airportKey): void {
            FeaturedAirport::query()
                ->where('airport_key', $airportKey)
                ->delete();

            $remainingKeys = FeaturedAirport::query()
                ->orderBy('sort_order')
                ->pluck('airport_key');

            foreach ($remainingKeys as $index => $key) {
                FeaturedAirport::query()
                    ->where('airport_key', $key)
                    ->update(['sort_order' => $index + 1]);
            }
        });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function searchCandidates(?string $search, int $limit = 10): Collection
    {
        if (! Schema::hasTable('booknow_airports')) {
            return collect();
        }

        $search = trim((string) $search);

        if ($search === '') {
            return collect();
        }

        $query = DB::table('booknow_airports')
            ->select([
                'iata_code',
                'icao_code',
                'name_en',
                'city_en',
                'country_name_en',
                'country_iso2',
            ])
            ->where(function ($builder) use ($search): void {
                AirportSearchScope::apply($builder, $search);
            })
            ->where(function ($builder): void {
                $builder->whereNotNull('iata_code')
                    ->where('iata_code', '!=', '')
                    ->orWhere(function ($nested): void {
                        $nested->whereNotNull('icao_code')
                            ->where('icao_code', '!=', '');
                    });
            })
            ->orderBy('name_en')
            ->limit($limit);

        return collect($query->get())
            ->map(fn (object $record): array => $this->formatBooknowRecord($record))
            ->filter(fn (array $airport): bool => $airport['airport_key'] !== null)
            ->values();
    }

    /**
     * @return Collection<string, array<string, mixed>>
     */
    public function resolveAirportsByKeys(Collection $airportKeys): Collection
    {
        if ($airportKeys->isEmpty() || ! Schema::hasTable('booknow_airports')) {
            return collect();
        }

        $records = DB::table('booknow_airports')
            ->select([
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
                'type',
                'latitude_deg',
                'longitude_deg',
            ])
            ->where(function ($query) use ($airportKeys): void {
                AirportKey::applyKeyFilter($query, $airportKeys->all());
            })
            ->get()
            ->mapWithKeys(function (object $record): array {
                $airport = $this->formatBooknowRecord($record);

                return $airport['airport_key'] === null
                    ? []
                    : [$airport['airport_key'] => $airport];
            });

        return $records;
    }

    private function airportExists(string $airportKey): bool
    {
        if (! Schema::hasTable('booknow_airports')) {
            return false;
        }

        $parsed = AirportKey::parse($airportKey);

        if ($parsed === null) {
            return false;
        }

        $column = $parsed['type'] === 'IATA' ? 'iata_code' : 'icao_code';

        return DB::table('booknow_airports')
            ->where($column, $parsed['value'])
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    private function formatBooknowRecord(object $record): array
    {
        return [
            'airport_key' => AirportKey::fromCodes($record->iata_code ?? null, $record->icao_code ?? null),
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
            'type' => $record->type ?? null,
            'latitude_deg' => isset($record->latitude_deg) ? (float) $record->latitude_deg : null,
            'longitude_deg' => isset($record->longitude_deg) ? (float) $record->longitude_deg : null,
        ];
    }
}
