<?php

namespace App\Modules\Api\Orders\Http\Requests;

use App\Modules\Api\DTO\SyncBooknowOrderDTO;
use App\Modules\Api\Support\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class SyncHotelOrderRequest extends ApiFormRequest
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

        $guests = $this->input('guests', []);
        $passengers = $this->input('passengers', $guests);

        $this->merge([
            'product_type' => 'hotel',
            'passengers' => is_array($passengers) ? $passengers : [],
            'guests' => is_array($guests) ? $guests : (is_array($passengers) ? $passengers : []),
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

            if (empty($contact['name'])) {
                $contact['name'] = trim(($contact['first_name'] ?? '').' '.($contact['last_name'] ?? ''));
            }

            $this->merge(['contact' => $contact]);
        }

        $providerBooking = $this->input('provider_booking');

        if (is_array($providerBooking)) {
            if (array_key_exists('booking_id', $providerBooking) && $providerBooking['booking_id'] !== null) {
                $providerBooking['booking_id'] = (string) $providerBooking['booking_id'];
            }

            if (empty($providerBooking['order_number']) && ! empty($providerBooking['booking_reference'])) {
                $providerBooking['order_number'] = (string) $providerBooking['booking_reference'];
            }

            if (empty($providerBooking['order_number']) && ! empty($providerBooking['order_id'])) {
                $providerBooking['order_number'] = (string) $providerBooking['order_id'];
            }

            if (empty($providerBooking['provider'])) {
                $providerBooking['provider'] = 'booknow_hotels';
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

                $item['type'] = $item['type'] ?? 'hotel';
                $item['product_type'] = $item['product_type'] ?? 'hotel';

                if (isset($item['item_details']) && is_array($item['item_details'])) {
                    if (array_key_exists('hotel_id', $item['item_details']) && $item['item_details']['hotel_id'] !== null) {
                        $item['item_details']['hotel_id'] = (string) $item['item_details']['hotel_id'];
                    }

                    if (empty($item['item_details']['hotel_name']) && ! empty($item['title'])) {
                        $item['item_details']['hotel_name'] = (string) $item['title'];
                    }

                    if (empty($item['title']) && ! empty($item['item_details']['hotel_name'])) {
                        $item['title'] = (string) $item['item_details']['hotel_name'];
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
            'product_type' => ['required', 'string', Rule::in(['hotel'])],
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
            'provider_booking.booking_reference' => ['nullable', 'string', 'max:120'],
            'provider_booking.provider' => ['required', 'string', Rule::in(['booknow_hotels'])],
            'provider_booking.provider_key' => ['nullable', 'string', 'max:120'],
            'provider_booking.provider_name' => ['nullable', 'string', 'max:120'],
            'provider_booking.provider_id' => ['nullable', 'integer'],
            'contact' => ['required', 'array'],
            'contact.name' => ['nullable', 'string', 'max:190'],
            'contact.first_name' => ['required', 'string', 'max:120'],
            'contact.last_name' => ['nullable', 'string', 'max:120'],
            'contact.email' => ['nullable', 'email', 'max:190'],
            'contact.phone' => ['nullable', 'string', 'max:40'],
            'guests' => ['nullable', 'array'],
            'guests.*.title' => ['nullable', 'string', 'max:20'],
            'guests.*.first_name' => ['nullable', 'string', 'max:120'],
            'guests.*.last_name' => ['nullable', 'string', 'max:120'],
            'guests.*.email' => ['nullable', 'email', 'max:190'],
            'guests.*.phone' => ['nullable', 'string', 'max:40'],
            'passengers' => ['nullable', 'array'],
            'passengers.*.title' => ['nullable', 'string', 'max:20'],
            'passengers.*.type' => ['nullable', 'string', 'max:20'],
            'passengers.*.first_name' => ['nullable', 'string', 'max:120'],
            'passengers.*.last_name' => ['nullable', 'string', 'max:120'],
            'passengers.*.email' => ['nullable', 'email', 'max:190'],
            'passengers.*.phone' => ['nullable', 'string', 'max:40'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.type' => ['required', 'string', 'max:30'],
            'items.*.product_type' => ['required', 'string', Rule::in(['hotel'])],
            'items.*.product_subtype' => ['nullable', 'string', 'max:60'],
            'items.*.title' => ['nullable', 'string', 'max:190'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.total' => ['nullable', 'numeric', 'min:0'],
            'items.*.currency' => ['nullable', 'string', 'size:3'],
            'items.*.provider_reference' => ['nullable', 'string', 'max:120'],
            'items.*.item_details' => ['required', 'array'],
            'items.*.item_details.hotel_id' => ['required', 'string', 'max:120'],
            'items.*.item_details.hotel_name' => ['required', 'string', 'max:190'],
            'items.*.item_details.check_in' => ['required', 'date'],
            'items.*.item_details.check_out' => ['required', 'date', 'after_or_equal:items.*.item_details.check_in'],
            'items.*.item_details.city_id' => ['nullable', 'string', 'max:120'],
            'items.*.item_details.city_name' => ['nullable', 'string', 'max:120'],
            'items.*.item_details.country' => ['nullable', 'string', 'max:10'],
            'items.*.item_details.source' => ['nullable', 'string', 'max:120'],
            'items.*.item_details.offer_id' => ['nullable', 'string', 'max:120'],
            'items.*.item_details.room_name' => ['nullable', 'string', 'max:190'],
            'items.*.item_details.room_type' => ['nullable', 'string', 'max:120'],
            'items.*.item_details.board' => ['nullable', 'string', 'max:60'],
            'items.*.item_details.nights' => ['nullable', 'integer', 'min:1'],
            'items.*.item_details.rooms' => ['nullable', 'integer', 'min:1'],
            'items.*.item_details.adults' => ['nullable', 'integer', 'min:0'],
            'items.*.item_details.children' => ['nullable', 'integer', 'min:0'],
            'items.*.item_details.guests_count' => ['nullable', 'integer', 'min:0'],
            'items.*.item_details.stars' => ['nullable', 'integer', 'min:0', 'max:5'],
            'items.*.item_details.address' => ['nullable', 'string', 'max:500'],
            'items.*.item_details.image_url' => ['nullable', 'string', 'max:500'],
            'items.*.item_details.guests' => ['nullable', 'array'],
            'customer_id' => ['prohibited'],
            'payment' => ['required', 'array'],
            'payment.status' => ['required', 'string', 'max:30'],
            'payment.method' => ['nullable', 'string', 'max:60'],
            'payment.method_code' => ['nullable', 'integer'],
            'payment.amount' => ['required', 'numeric', 'min:0'],
            'payment.currency' => ['required', 'string', 'size:3'],
            'payment.transaction_id' => ['nullable', 'string', 'max:120'],
            'payment.paid_at' => ['nullable', 'date'],
            'metadata' => ['nullable', 'array'],
            'metadata.source_screen' => ['nullable', 'string', 'max:120'],
            'metadata.booknow_booking_id' => ['nullable', 'string', 'max:120'],
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
        $validated = $this->validated();

        if (! empty($validated['guests']) && empty($validated['passengers'])) {
            $validated['passengers'] = $validated['guests'];
        }

        return SyncBooknowOrderDTO::fromArray($validated);
    }
}
