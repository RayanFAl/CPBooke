<?php

namespace App\Modules\Api\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FavoriteResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'type' => $this->resource->type,
            'item_key' => $this->resource->item_key,
            'status' => $this->resource->status,
            'snapshot' => $this->resource->snapshot ?? [],
            'search_context' => $this->resource->search_context,
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'expires_at' => $this->resource->expires_at?->toIso8601String(),
        ];
    }
}
