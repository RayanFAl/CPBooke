<?php

namespace App\Support\Airports;

use Illuminate\Database\Query\Builder;

final class AirportSearchScope
{
    public static function apply(Builder $query, string $search, string $table = 'booknow_airports'): void
    {
        $query->where(function (Builder $builder) use ($search, $table): void {
            $builder->where("{$table}.name_en", 'like', "%{$search}%")
                ->orWhere("{$table}.name_ar", 'like', "%{$search}%")
                ->orWhere("{$table}.name_fr", 'like', "%{$search}%")
                ->orWhere("{$table}.iata_code", 'like', "%{$search}%")
                ->orWhere("{$table}.icao_code", 'like', "%{$search}%")
                ->orWhere("{$table}.city_en", 'like', "%{$search}%")
                ->orWhere("{$table}.city_ar", 'like', "%{$search}%")
                ->orWhere("{$table}.city_fr", 'like', "%{$search}%")
                ->orWhere("{$table}.country_name_en", 'like', "%{$search}%")
                ->orWhere("{$table}.country_name_ar", 'like', "%{$search}%")
                ->orWhere("{$table}.country_name_fr", 'like', "%{$search}%")
                ->orWhere("{$table}.country_iso2", 'like', "%{$search}%");
        });
    }
}
