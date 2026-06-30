<?php

namespace App\Services;

use App\Support\Imports\XlsxStreamReader;
use Illuminate\Support\Facades\DB;

class BooknowAirportsImportService
{
    public int $imported = 0;

    public int $updated = 0;

    public int $skipped = 0;

    private const CHUNK_SIZE = 500;

    /**
     * @return array{imported: int, updated: int, skipped: int}
     */
    public function import(string $file, ?int $limit = null, ?callable $onChunk = null): array
    {
        @ini_set('memory_limit', '512M');

        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        if ($extension === 'csv') {
            $this->importCsv($file, $limit, $onChunk);
        } else {
            $this->importSpreadsheet($file, $limit, $onChunk);
        }

        return [
            'imported' => $this->imported,
            'updated' => $this->updated,
            'skipped' => $this->skipped,
        ];
    }

    private function importCsv(string $file, ?int $limit, ?callable $onChunk): void
    {
        $handle = fopen($file, 'rb');

        if ($handle === false) {
            throw new \RuntimeException("Unable to open CSV file: {$file}");
        }

        $headers = null;
        $processedInChunk = 0;

        while (($line = fgets($handle)) !== false) {
            $line = $this->normalizeCsvLine($line);
            $values = str_getcsv($line);

            if ($headers === null) {
                $headers = array_map(fn ($header) => $this->normalizeHeader((string) $header), $values);

                continue;
            }

            if ($limit !== null && $this->processedTotal() >= $limit) {
                break;
            }

            $row = $this->combineRow($headers, $values);
            $this->processRow($row);

            $processedInChunk++;

            if ($processedInChunk >= self::CHUNK_SIZE) {
                if ($onChunk !== null) {
                    $onChunk($this->processedTotal());
                }

                $processedInChunk = 0;
            }
        }

        fclose($handle);

        if ($onChunk !== null) {
            $onChunk($this->processedTotal());
        }
    }

    private function importSpreadsheet(string $file, ?int $limit, ?callable $onChunk): void
    {
        $streamer = new XlsxStreamReader($file);
        $headers = null;
        $processedInChunk = 0;

        foreach ($streamer->rows() as $values) {
            if ($headers === null) {
                $headers = array_map(fn ($header) => $this->normalizeHeader((string) $header), $values);

                continue;
            }

            if ($limit !== null && $this->processedTotal() >= $limit) {
                break;
            }

            if ($this->rowIsEmpty($values)) {
                continue;
            }

            $this->processRow($this->combineRow($headers, $values));

            $processedInChunk++;

            if ($processedInChunk >= self::CHUNK_SIZE) {
                if ($onChunk !== null) {
                    $onChunk($this->processedTotal());
                }

                $processedInChunk = 0;
                gc_collect_cycles();
            }
        }

        if ($onChunk !== null) {
            $onChunk($this->processedTotal());
        }
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, mixed>  $values
     * @return array<string, mixed>
     */
    private function combineRow(array $headers, array $values): array
    {
        $row = [];

        foreach ($headers as $index => $header) {
            if ($header === '') {
                continue;
            }

            $row[$header] = $values[$index] ?? null;
        }

        return $row;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function processRow(array $row): void
    {
        $payload = $this->mapRow($row);

        if ($payload === null) {
            $this->skipped++;

            return;
        }

        if ($this->persistRow($payload)) {
            $this->updated++;
        } else {
            $this->imported++;
        }
    }

    private function processedTotal(): int
    {
        return $this->imported + $this->updated + $this->skipped;
    }

    private function normalizeHeader(string $header): string
    {
        return strtolower(trim($header));
    }

    private function normalizeCsvLine(string $line): string
    {
        return ltrim($line, "\xEF\xBB\xBF");
    }

    /**
     * @param  array<int, mixed>  $values
     */
    private function rowIsEmpty(array $values): bool
    {
        foreach ($values as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null
     */
    private function mapRow(array $row): ?array
    {
        $nameEn = $this->stringValue($row['name_en'] ?? null);

        if ($nameEn === null) {
            return null;
        }

        return [
            'iata_code' => $this->stringValue($row['iata_code'] ?? null),
            'icao_code' => $this->stringValue($row['icao_code'] ?? null),
            'name_en' => $nameEn,
            'name_ar' => $this->stringValue($row['name_ar'] ?? null),
            'name_fr' => $this->stringValue($row['name_fr'] ?? null),
            'city_en' => $this->stringValue($row['city_en'] ?? null),
            'city_ar' => $this->stringValue($row['city_ar'] ?? null),
            'city_fr' => $this->stringValue($row['city_fr'] ?? null),
            'country_iso2' => $this->upperStringValue($row['country_iso2'] ?? null, 2),
            'country_name_en' => $this->stringValue($row['country_name_en'] ?? null),
            'country_name_ar' => $this->stringValue($row['country_name_ar'] ?? null),
            'country_name_fr' => $this->stringValue($row['country_name_fr'] ?? null),
            'type' => $this->stringValue($row['type'] ?? null),
            'scheduled_service' => $this->scheduledServiceValue($row['scheduled_service'] ?? null),
            'latitude_deg' => $this->decimalValue($row['latitude_deg'] ?? null),
            'longitude_deg' => $this->decimalValue($row['longitude_deg'] ?? null),
            'translation_status' => $this->stringValue($row['translation_status'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function persistRow(array $payload): bool
    {
        if ($payload['iata_code'] !== null) {
            $existing = DB::table('booknow_airports')
                ->where('iata_code', $payload['iata_code'])
                ->exists();

            DB::table('booknow_airports')->updateOrInsert(
                ['iata_code' => $payload['iata_code']],
                $payload,
            );

            return $existing;
        }

        if ($payload['icao_code'] !== null) {
            $existing = DB::table('booknow_airports')
                ->where('icao_code', $payload['icao_code'])
                ->exists();

            DB::table('booknow_airports')->updateOrInsert(
                ['icao_code' => $payload['icao_code']],
                $payload,
            );

            return $existing;
        }

        $existing = DB::table('booknow_airports')
            ->where('name_en', $payload['name_en'])
            ->where('city_en', $payload['city_en'])
            ->where('country_iso2', $payload['country_iso2'])
            ->whereNull('iata_code')
            ->whereNull('icao_code')
            ->exists();

        if ($existing) {
            DB::table('booknow_airports')
                ->where('name_en', $payload['name_en'])
                ->where('city_en', $payload['city_en'])
                ->where('country_iso2', $payload['country_iso2'])
                ->whereNull('iata_code')
                ->whereNull('icao_code')
                ->update($payload);

            return true;
        }

        DB::table('booknow_airports')->insert($payload);

        return false;
    }

    private function stringValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }

    private function upperStringValue(mixed $value, int $maxLength): ?string
    {
        $string = $this->stringValue($value);

        if ($string === null) {
            return null;
        }

        return strtoupper(substr($string, 0, $maxLength));
    }

    private function scheduledServiceValue(mixed $value): ?string
    {
        $string = strtolower((string) ($this->stringValue($value) ?? ''));

        return in_array($string, ['yes', 'no'], true) ? $string : null;
    }

    private function decimalValue(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }
}
