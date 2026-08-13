<?php

namespace App\Modules\Api\Resources;

use App\Models\HotelReview;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HotelReviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var HotelReview $review */
        $review = $this->resource;

        return [
            'id' => (string) $review->id,
            'booking_reference' => $review->booking_reference,
            'hotel_id' => $review->hotel_id,
            'overall_rating' => $review->overall_rating,
            'categories' => $review->categories ?? new \stdClass(),
            'comment' => $review->comment,
            'created_at' => $review->created_at?->toIso8601String(),
            'updated_at' => $review->updated_at?->toIso8601String(),
        ];
    }
}
