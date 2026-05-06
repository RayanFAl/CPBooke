<?php

namespace App\Modules\Api\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'name' => $this->resource->full_name ?: $this->resource->name,
            'email' => $this->resource->email,
            'phone' => $this->resource->phone,
            'country' => $this->resource->country,
            'is_active' => (bool) $this->resource->is_active,
            'last_login_at' => $this->resource->last_login_at?->toIso8601String(),
            'created_at' => $this->resource->created_at?->toIso8601String(),
        ];
    }
}