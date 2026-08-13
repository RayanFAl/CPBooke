<?php

namespace App\Modules\Api\HotelReviews\Http\Requests;

use App\Models\HotelReview;
use App\Modules\Api\Support\Http\Requests\ApiFormRequest;

class StoreHotelReviewRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $categoryRules = [];

        foreach (HotelReview::CATEGORY_KEYS as $key) {
            $categoryRules["categories.{$key}"] = ['nullable', 'integer', 'min:1', 'max:5'];
        }

        return array_merge([
            'booking_reference' => ['nullable', 'string', 'max:80'],
            'booking_id' => ['nullable', 'string', 'max:80'],
            'hotel_id' => ['nullable', 'string', 'max:80'],
            'overall_rating' => ['required', 'integer', 'min:1', 'max:5'],
            'categories' => ['nullable', 'array'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ], $categoryRules);
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $hasBooking = filled($this->input('booking_reference'))
                || filled($this->input('booking_id'))
                || filled($this->route('bookingId'));

            if (! $hasBooking) {
                $validator->errors()->add('booking_reference', 'A booking_reference or booking_id is required.');
            }
        });
    }
}
