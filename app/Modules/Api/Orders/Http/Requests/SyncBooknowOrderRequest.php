<?php

namespace App\Modules\Api\Orders\Http\Requests;

use App\Modules\Api\DTO\SyncBooknowOrderDTO;
use App\Modules\Api\Support\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class SyncBooknowOrderRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('currency') && is_string($this->input('currency'))) {
            $this->merge([
                'currency' => strtoupper($this->input('currency')),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'source' => ['required', 'string', 'max:60'],
            'product_type' => ['required', 'string', Rule::in(['flight', 'hotel', 'insurance'])],
            'status' => ['required', 'string', Rule::in([
                'draft',
                'pending',
                'pending_payment',
                'awaiting_payment',
                'payment_failed',
                'paid',
                'processing',
                'confirmed',
                'ticketed',
                'completed',
                'cancelled',
                'canceled',
                'voided',
                'failed',
                'expired',
                'refunded',
                'refund',
            ])],
            'currency' => ['required', 'string', 'size:3'],
            'grand_total' => ['required', 'numeric', 'min:0'],
            'provider_booking' => ['required', 'array'],
            'provider_booking.booking_id' => ['required', 'string', 'max:120'],
            'provider_booking.order_number' => ['nullable', 'string', 'max:120'],
            'provider_booking.pnr' => ['nullable', 'string', 'max:40'],
            'provider_booking.provider_id' => ['nullable', 'integer'],
            'provider_booking.provider_name' => ['nullable', 'string', 'max:120'],
            'provider_booking.search_uuid' => ['nullable', 'string', 'max:120'],
            'contact' => ['required', 'array'],
            'contact.first_name' => ['required', 'string', 'max:120'],
            'contact.last_name' => ['required', 'string', 'max:120'],
            'contact.email' => ['nullable', 'email', 'max:190'],
            'contact.phone' => ['nullable', 'string', 'max:40'],
            'passengers' => ['required', 'array', 'min:1'],
            'passengers.*.type' => ['required', 'string', 'max:20'],
            'passengers.*.first_name' => ['required', 'string', 'max:120'],
            'passengers.*.last_name' => ['required', 'string', 'max:120'],
            'passengers.*.title' => ['nullable', 'string', 'max:20'],
            'passengers.*.dob' => ['nullable', 'date'],
            'passengers.*.gender' => ['nullable', 'string', 'max:10'],
            'passengers.*.nationality' => ['nullable', 'string', 'max:2'],
            'passengers.*.passport_number' => ['nullable', 'string', 'max:40'],
            'passengers.*.passport_expiry' => ['nullable', 'date'],
            'passengers.*.passport_issue_country' => ['nullable', 'string', 'max:2'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.type' => ['required', 'string', 'max:30'],
            'items.*.product_type' => ['required', 'string', 'max:30'],
            'items.*.product_subtype' => ['nullable', 'string', 'max:30'],
            'items.*.provider_reference' => ['nullable', 'string', 'max:40'],
            'items.*.total' => ['nullable', 'numeric', 'min:0'],
            'items.*.currency' => ['nullable', 'string', 'size:3'],
            'items.*.item_details' => ['nullable', 'array'],
            'customer_id' => ['prohibited'],
            'payment' => ['nullable', 'array'],
            'payment.status' => ['nullable', 'string', 'max:30'],
            'payment.method' => ['nullable', 'string', 'max:60'],
            'payment.method_code' => ['nullable', 'integer'],
            'payment.amount' => ['nullable', 'numeric', 'min:0'],
            'payment.currency' => ['nullable', 'string', 'size:3'],
            'payment.transaction_id' => ['nullable', 'string', 'max:120'],
            'payment.paid_at' => ['nullable', 'date'],
            'booking_flight_data' => ['nullable', 'array'],
            'metadata' => ['nullable', 'array'],
            'notes' => ['nullable', 'string', 'max:500'],
            'agency_id' => ['nullable', 'string', 'max:60'],
            'commission_amount' => ['nullable', 'numeric', 'min:0'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'base_amount' => ['nullable', 'numeric', 'min:0'],
            'issued_at' => ['nullable', 'date'],
        ];
    }

    public function toDto(): SyncBooknowOrderDTO
    {
        return SyncBooknowOrderDTO::fromArray($this->validated());
    }
}
