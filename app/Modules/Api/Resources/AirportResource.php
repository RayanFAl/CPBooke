<?php

namespace App\Modules\Api\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AirportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $airport */
        $airport = is_array($this->resource) ? $this->resource : (array) $this->resource;

        return [
            'code' => $airport['iata_code'] ?? $airport['icao_code'] ?? null,
            'name' => [
                'en' => $airport['name_en'] ?? null,
                'ar' => $airport['name_ar'] ?? null,
                'fr' => $airport['name_fr'] ?? null,
            ],
            'city' => [
                'en' => $airport['city_en'] ?? null,
                'ar' => $airport['city_ar'] ?? null,
                'fr' => $airport['city_fr'] ?? null,
            ],
            'country' => [
                'code' => $airport['country_iso2'] ?? null,
                'name' => [
                    'en' => $airport['country_name_en'] ?? null,
                    'ar' => $airport['country_name_ar'] ?? null,
                    'fr' => $airport['country_name_fr'] ?? null,
                ],
            ],
        ];
    }
}
