<?php

namespace App\Modules\Api\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LinkedAccountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $linkedUser = $this->resource->linkedUser;

        return [
            'id' => $this->resource->id,
            'user_id' => (string) $this->resource->user_id,
            'linked_user_id' => (string) $this->resource->linked_user_id,
            'relationship_type' => $this->resource->relationship_type,
            'nickname' => $this->resource->nickname,
            'can_request_payment' => (bool) $this->resource->can_request_payment,
            'can_receive_payment_requests' => (bool) $this->resource->can_receive_payment_requests,
            'auto_approve' => (bool) $this->resource->auto_approve,
            'is_active' => (bool) $this->resource->is_active,
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'linked_user_name' => $linkedUser
                ? ($linkedUser->full_name ?: $linkedUser->name)
                : null,
            'linked_user_phone' => $linkedUser?->phone,
            'linked_user_email' => $linkedUser?->email,
            'linked_user_avatar' => $linkedUser?->avatarUrl(),
        ];
    }
}
