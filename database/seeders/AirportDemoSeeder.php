<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AirportDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('booknow_airports')) {
            $this->command?->error('The booknow_airports table does not exist. Run migrations first.');

            return;
        }

        $airports = require database_path('seeders/data/airports-demo.php');
        $imported = 0;
        $updated = 0;

        foreach ($airports as $airport) {
            $payload = $this->payload($airport);
            $match = $this->matchAttributes($airport);

            $existing = DB::table('booknow_airports')->where($match)->exists();

            DB::table('booknow_airports')->updateOrInsert($match, $payload);

            if ($existing) {
                $updated++;
            } else {
                $imported++;
            }
        }

        $this->command?->info("Airport demo data ready. Inserted: {$imported}, updated: {$updated}.");
    }

    /**
     * @param  array<string, mixed>  $airport
     * @return array<string, mixed>
     */
    private function matchAttributes(array $airport): array
    {
        if (! empty($airport['iata_code'])) {
            return ['iata_code' => $airport['iata_code']];
        }

        return ['icao_code' => $airport['icao_code']];
    }

    /**
     * @param  array<string, mixed>  $airport
     * @return array<string, mixed>
     */
    private function payload(array $airport): array
    {
        return [
            'iata_code' => $airport['iata_code'] ?? null,
            'icao_code' => $airport['icao_code'] ?? null,
            'name_en' => $airport['name_en'],
            'name_ar' => $airport['name_ar'] ?? null,
            'name_fr' => $airport['name_fr'] ?? null,
            'city_en' => $airport['city_en'] ?? null,
            'city_ar' => $airport['city_ar'] ?? null,
            'city_fr' => $airport['city_fr'] ?? null,
            'country_iso2' => $airport['country_iso2'] ?? null,
            'country_name_en' => $airport['country_name_en'] ?? null,
            'country_name_ar' => $airport['country_name_ar'] ?? null,
            'country_name_fr' => $airport['country_name_fr'] ?? null,
            'type' => $airport['type'] ?? null,
            'scheduled_service' => $airport['scheduled_service'] ?? null,
            'translation_status' => 'complete',
        ];
    }
}
