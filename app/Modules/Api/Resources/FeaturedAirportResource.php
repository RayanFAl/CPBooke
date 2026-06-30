<?php

namespace App\Modules\Api\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FeaturedAirportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $airport */
        $airport = is_array($this->resource) ? $this->resource : (array) $this->resource;

        return array_merge(
            AirportResource::make($airport)->resolve($request),
            [
                'order' => $airport['featured_order'] ?? null,
            ],
        );
    }
}
