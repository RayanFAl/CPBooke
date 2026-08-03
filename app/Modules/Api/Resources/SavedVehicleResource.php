<?php

namespace App\Modules\Api\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SavedVehicleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'type' => $this->resource->type,
            'label' => $this->resource->label,
            'is_default' => (bool) $this->resource->is_default,
            'beneficiary_name' => $this->resource->beneficiary_name,
            'beneficiary_phone' => $this->resource->beneficiary_phone,
            'email' => $this->resource->email,
            'vehicle_type_id' => $this->resource->vehicle_type_id,
            'vehicle_color_id' => $this->resource->vehicle_color_id,
            'vehicle_licensing_authority_id' => $this->resource->vehicle_licensing_authority_id,
            'vehicle_manufacture_year' => $this->resource->vehicle_manufacture_year,
            'vehicle_chassis_number' => $this->resource->vehicle_chassis_number,
            'vehicle_plate_number' => $this->resource->vehicle_plate_number,
            'payload' => $this->resource->payload !== null ? (float) $this->resource->payload : null,
            'document_type_id' => $this->resource->document_type_id,
            'vehicle_nationality' => $this->resource->vehicle_nationality,
            'address' => $this->resource->address,
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
        ];
    }
}
