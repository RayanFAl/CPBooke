<?php

namespace App\Modules\Api\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SavedPassengerResource extends JsonResource
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
            'title' => $this->resource->title,
            'first_name' => $this->resource->first_name,
            'last_name' => $this->resource->last_name,
            'date_of_birth' => $this->resource->date_of_birth?->format('Y-m-d'),
            'gender' => $this->resource->gender,
            'nationality' => $this->resource->nationality,
            'country_of_residence' => $this->resource->country_of_residence,
            'document_type' => $this->resource->document_type,
            'passport_number' => $this->resource->passport_number,
            'passport_issue_country' => $this->resource->passport_issue_country,
            'passport_issue_date' => $this->resource->passport_issue_date?->format('Y-m-d'),
            'passport_expiry' => $this->resource->passport_expiry?->format('Y-m-d'),
            'email' => $this->resource->email,
            'phone' => $this->resource->phone,
            'seat_preference' => $this->resource->seat_preference,
            'meal_preference' => $this->resource->meal_preference,
            'is_default' => $this->resource->is_default,
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
        ];
    }
}
