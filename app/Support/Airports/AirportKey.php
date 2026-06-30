<?php

namespace App\Support\Airports;

use Illuminate\Database\Query\Builder;

final class AirportKey
{
    public static function fromCodes(?string $iataCode, ?string $icaoCode): ?string
    {
        $iata = self::normalizeCode($iataCode);
        $icao = self::normalizeCode($icaoCode);

        if ($iata !== null) {
            return "IATA:{$iata}";
        }

        if ($icao !== null) {
            return "ICAO:{$icao}";
        }

        return null;
    }

    /**
     * @return array{type: string, value: string}|null
     */
    public static function parse(string $key): ?array
    {
        $parts = explode(':', $key, 2);

        if (count($parts) !== 2) {
            return null;
        }

        [$type, $value] = $parts;

        if (! in_array($type, ['IATA', 'ICAO'], true) || $value === '') {
            return null;
        }

        return [
            'type' => $type,
            'value' => $value,
        ];
    }

    /**
     * @param  list<string>  $keys
     */
    public static function applyKeyFilter(Builder $query, array $keys, string $table = 'booknow_airports'): void
    {
        $keys = array_values(array_filter($keys));

        if ($keys === []) {
            $query->whereRaw('0 = 1');

            return;
        }

        $query->where(function (Builder $builder) use ($keys, $table): void {
            foreach ($keys as $key) {
                $parsed = self::parse($key);

                if ($parsed === null) {
                    continue;
                }

                $column = $parsed['type'] === 'IATA' ? 'iata_code' : 'icao_code';

                $builder->orWhere("{$table}.{$column}", $parsed['value']);
            }
        });
    }

    /**
     * @param  list<string>  $keys
     */
    public static function applyKeyExclusion(Builder $query, array $keys, string $table = 'booknow_airports'): void
    {
        foreach ($keys as $key) {
            $parsed = self::parse($key);

            if ($parsed === null) {
                continue;
            }

            $column = $parsed['type'] === 'IATA' ? 'iata_code' : 'icao_code';

            $query->where(function (Builder $builder) use ($table, $column, $parsed): void {
                $builder->whereNull("{$table}.{$column}")
                    ->orWhere("{$table}.{$column}", '!=', $parsed['value']);
            });
        }
    }

    public static function sqlExpression(string $table = 'booknow_airports'): string
    {
        $driver = \Illuminate\Support\Facades\DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            return "CASE WHEN NULLIF({$table}.iata_code, '') IS NOT NULL THEN ('IATA:' || {$table}.iata_code) WHEN NULLIF({$table}.icao_code, '') IS NOT NULL THEN ('ICAO:' || {$table}.icao_code) ELSE NULL END";
        }

        return "CASE WHEN NULLIF({$table}.iata_code, '') IS NOT NULL THEN CONCAT('IATA:', {$table}.iata_code) WHEN NULLIF({$table}.icao_code, '') IS NOT NULL THEN CONCAT('ICAO:', {$table}.icao_code) ELSE NULL END";
    }

    private static function normalizeCode(?string $code): ?string
    {
        if ($code === null) {
            return null;
        }

        $trimmed = trim($code);

        return $trimmed === '' ? null : strtoupper($trimmed);
    }
}
