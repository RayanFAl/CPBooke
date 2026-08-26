<?php

namespace App\Modules\Api\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LinkedAccountRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $fromUser = $this->resource->relationLoaded('fromUser')
            ? $this->resource->fromUser
            : null;

        return [
            'id' => $this->resource->id,
            'from_user_id' => (string) $this->resource->from_user_id,
            'to_user_id' => (string) $this->resource->to_user_id,
            'relationship_type' => $this->resource->relationship_type,
            'nickname' => $this->resource->nickname,
            'message' => $this->resource->message,
            'status' => $this->resource->status,
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'from_user_name' => $fromUser
                ? ($fromUser->full_name ?: $fromUser->name)
                : null,
            'from_user_phone' => $fromUser?->phone,
            'from_user_avatar' => $fromUser?->avatarUrl(),
        ];
    }
}
