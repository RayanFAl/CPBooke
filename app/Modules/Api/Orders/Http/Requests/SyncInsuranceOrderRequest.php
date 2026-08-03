<?php

namespace App\Modules\Api\Orders\Http\Requests;

use App\Modules\Api\DTO\SyncBooknowOrderDTO;
use App\Modules\Api\Support\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class SyncInsuranceOrderRequest extends ApiFormRequest
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

        $this->merge([
            'product_type' => 'insurance',
            'passengers' => $this->input('passengers', []),
        ]);

        $contact = $this->input('contact');

        if (is_array($contact)) {
            if (! empty($contact['name']) && empty($contact['first_name']) && empty($contact['last_name'])) {
                $parts = preg_split('/\s+/', trim((string) $contact['name']), 2) ?: [];
                $contact['first_name'] = $parts[0] ?? 'Customer';
                $contact['last_name'] = $parts[1] ?? '';
            }

            $contact['first_name'] = $contact['first_name'] ?? 'Customer';
            $contact['last_name'] = $contact['last_name'] ?? '';
            $this->merge(['contact' => $contact]);
        }

        $providerBooking = $this->input('provider_booking');

        if (is_array($providerBooking)) {
            if (array_key_exists('booking_id', $providerBooking) && $providerBooking['booking_id'] !== null) {
                $providerBooking['booking_id'] = (string) $providerBooking['booking_id'];
            }

            if (empty($providerBooking['order_number']) && ! empty($providerBooking['order_id'])) {
                $providerBooking['order_number'] = (string) $providerBooking['order_id'];
            }

            if (empty($providerBooking['provider_name']) && ! empty($providerBooking['provider'])) {
                $providerBooking['provider_name'] = (string) $providerBooking['provider'];
            }

            $this->merge(['provider_booking' => $providerBooking]);
        }

        $items = $this->input('items');

        if (is_array($items)) {
            $normalized = [];

            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $item['type'] = $item['type'] ?? 'insurance';
                $item['product_type'] = $item['product_type'] ?? 'insurance';

                if (isset($item['item_details']) && is_array($item['item_details'])) {
                    if (array_key_exists('item_id', $item['item_details']) && $item['item_details']['item_id'] !== null) {
                        $item['item_details']['item_id'] = (string) $item['item_details']['item_id'];
                    }
                }

                if (! isset($item['total']) && isset($item['unit_price'])) {
                    $qty = max(1, (int) ($item['quantity'] ?? 1));
                    $item['total'] = round((float) $item['unit_price'] * $qty, 2);
                }

                $normalized[] = $item;
            }

            $this->merge(['items' => $normalized]);
        }

        $payment = $this->input('payment');
        $status = strtolower((string) $this->input('status', ''));

        if (
            is_array($payment)
            && empty($payment['status'])
            && in_array($status, ['confirmed', 'paid', 'ticketed', 'completed'], true)
        ) {
            $payment['status'] = 'paid';
            $this->merge(['payment' => $payment]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'source' => ['required', 'string', 'max:60'],
            'product_type' => ['required', 'string', Rule::in(['insurance'])],
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
            'provider_booking.order_id' => ['nullable', 'string', 'max:120'],
            'provider_booking.provider' => ['nullable', 'string', 'max:120'],
            'provider_booking.provider_key' => ['nullable', 'string', 'max:120'],
            'provider_booking.provider_name' => ['nullable', 'string', 'max:120'],
            'provider_booking.provider_id' => ['nullable', 'integer'],
            'contact' => ['required', 'array'],
            'contact.name' => ['nullable', 'string', 'max:190'],
            'contact.first_name' => ['required', 'string', 'max:120'],
            'contact.last_name' => ['nullable', 'string', 'max:120'],
            'contact.email' => ['nullable', 'email', 'max:190'],
            'contact.phone' => ['nullable', 'string', 'max:40'],
            'passengers' => ['nullable', 'array'],
            'passengers.*.type' => ['nullable', 'string', 'max:20'],
            'passengers.*.first_name' => ['nullable', 'string', 'max:120'],
            'passengers.*.last_name' => ['nullable', 'string', 'max:120'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.type' => ['required', 'string', 'max:30'],
            'items.*.product_type' => ['required', 'string', 'max:30'],
            'items.*.product_subtype' => ['nullable', 'string', 'max:60'],
            'items.*.title' => ['nullable', 'string', 'max:190'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.total' => ['nullable', 'numeric', 'min:0'],
            'items.*.currency' => ['nullable', 'string', 'size:3'],
            'items.*.provider_reference' => ['nullable', 'string', 'max:120'],
            'items.*.item_details' => ['required', 'array'],
            'items.*.item_details.item_id' => ['required', 'string', 'max:120'],
            'items.*.item_details.provider' => ['nullable', 'string', 'max:120'],
            'items.*.item_details.ticket_number' => ['nullable', 'string', 'max:120'],
            'items.*.item_details.report_reference' => ['nullable', 'string', 'max:120'],
            'items.*.item_details.zone_id' => ['nullable', 'integer'],
            'items.*.item_details.zone_name' => ['nullable', 'string', 'max:120'],
            'items.*.item_details.duration_id' => ['nullable', 'integer'],
            'items.*.item_details.duration_label' => ['nullable', 'string', 'max:120'],
            'items.*.item_details.policy_date_from' => ['nullable', 'date'],
            'items.*.item_details.policy_date_to' => ['nullable', 'date'],
            'customer_id' => ['prohibited'],
            'payment' => ['nullable', 'array'],
            'payment.status' => ['nullable', 'string', 'max:30'],
            'payment.method' => ['nullable', 'string', 'max:60'],
            'payment.amount' => ['nullable', 'numeric', 'min:0'],
            'payment.currency' => ['nullable', 'string', 'size:3'],
            'payment.transaction_id' => ['nullable', 'string', 'max:120'],
            'payment.paid_at' => ['nullable', 'date'],
            'metadata' => ['nullable', 'array'],
            'metadata.source_screen' => ['nullable', 'string', 'max:120'],
            'metadata.booknow_insurance_item_id' => ['nullable', 'string', 'max:120'],
            'metadata.related_flight_order_id' => ['nullable', 'integer'],
            'metadata.related_flight_booking_id' => ['nullable', 'string', 'max:120'],
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
