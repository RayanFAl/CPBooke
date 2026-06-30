<?php

namespace App\Modules\Api\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
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
            'provider_name' => $this->resource->provider_name,
            'external_booking_id' => $this->resource->external_booking_id,
            'booking_reference' => $this->resource->booking_reference,
            'status' => $this->resource->status,
            'payment_status' => $this->resource->payment_status,
            'service_type' => $this->resource->service_type,
            'details' => $this->resource->details ?? [],
            'currency' => $this->resource->currency,
            'total_amount' => $this->resource->total_amount,
            'base_amount' => $this->resource->base_amount,
            'tax_amount' => $this->resource->tax_amount,
            'request_payload' => $this->resource->request_payload ?? [],
            'response_payload' => $this->resource->response_payload ?? [],
            'error_message' => $this->resource->error_message,
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
        ];
    }
}