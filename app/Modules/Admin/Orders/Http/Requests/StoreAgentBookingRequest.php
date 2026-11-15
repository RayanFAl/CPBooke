<?php

namespace App\Modules\Admin\Orders\Http\Requests;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAgentBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('orders.agent-book') ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('total_amount') === '' || $this->input('total_amount') === null) {
            $this->merge(['total_amount' => null]);
        }
    }

    public function rules(): array
    {
        $serviceType = (string) $this->input('service_type');

        $rules = [
            'customer_id' => ['required', 'integer', 'exists:users,id'],
            'service_type' => ['required', 'string', Rule::in(Order::serviceTypes())],
            'provider_name' => ['nullable', 'string', 'max:120'],
            'currency' => ['required', 'string', 'size:3'],
            'total_amount' => ['required', 'numeric', 'gt:0'],
            'external_booking_id' => ['nullable', 'string', 'max:120'],
            'mark_confirmed' => ['sometimes', 'boolean'],
            'internal_notes' => ['nullable', 'string', 'max:2000'],
            'booking_notes' => ['nullable', 'string', 'max:2000'],
            'contact_first_name' => ['required', 'string', 'max:80'],
            'contact_last_name' => ['nullable', 'string', 'max:80'],
            'contact_email' => ['nullable', 'email', 'max:160'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'customer_country' => ['nullable', 'string', 'max:80'],
            'customer_city' => ['nullable', 'string', 'max:80'],
            'guest_civility' => ['nullable', 'string', 'max:10'],
        ];

        return array_merge($rules, match ($serviceType) {
            Order::SERVICE_TYPE_HOTEL => [
                'hotel_name' => ['required', 'string', 'max:180'],
                'hotel_id' => ['required', 'string', 'max:80'],
                'city_name' => ['required', 'string', 'max:120'],
                'city_id' => ['required', 'string', 'max:80'],
                'check_in' => ['required', 'date'],
                'check_out' => ['required', 'date', 'after:check_in'],
                'rooms' => ['nullable', 'integer', 'min:1', 'max:20'],
                'adults' => ['nullable', 'integer', 'min:1', 'max:20'],
                'offer_id' => ['required', 'string', 'max:500'],
                'offer_label' => ['nullable', 'string', 'max:180'],
                'rate_key' => ['required', 'string', 'max:500'],
                'search_uuid' => ['required', 'string', 'max:120'],
                'search_code' => ['nullable', 'string', 'max:120'],
                'hotel_source' => ['nullable', 'string', 'max:80'],
                'board' => ['nullable', 'string', 'max:80'],
            ],
            Order::SERVICE_TYPE_FLIGHT => [
                'origin' => ['required', 'string', 'max:10'],
                'destination' => ['required', 'string', 'max:10'],
                'departure_date' => ['required', 'date'],
                'return_date' => ['nullable', 'date', 'after_or_equal:departure_date'],
                'airline' => ['nullable', 'string', 'max:80'],
                'pnr' => ['nullable', 'string', 'max:40'],
                'passengers_count' => ['nullable', 'integer', 'min:1', 'max:20'],
            ],
            Order::SERVICE_TYPE_INSURANCE => [
                'plan_name' => ['required', 'string', 'max:160'],
                'zone_name' => ['nullable', 'string', 'max:120'],
                'coverage_start' => ['required', 'date'],
                'coverage_end' => ['required', 'date', 'after_or_equal:coverage_start'],
                'travellers_count' => ['nullable', 'integer', 'min:1', 'max:20'],
            ],
            Order::SERVICE_TYPE_ESIM => [
                'plan_name' => ['required', 'string', 'max:160'],
                'country' => ['required', 'string', 'max:120'],
                'data_amount' => ['nullable', 'string', 'max:80'],
                'validity_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            ],
            default => [],
        });
    }
}
