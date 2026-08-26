<?php

namespace App\Modules\Api\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LinkedAccountSearchUserResource extends JsonResource
{
    /**
     * Minimal public profile for link search.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->resource->id,
            'name' => $this->resource->full_name ?: $this->resource->name,
            'phone' => $this->resource->phone,
            'email' => $this->resource->email,
            'avatar' => $this->resource->avatarUrl(),
        ];
    }
}
