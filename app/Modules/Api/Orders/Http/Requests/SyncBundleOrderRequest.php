<?php

namespace App\Modules\Api\Orders\Http\Requests;

use App\Modules\Api\DTO\SyncBooknowOrderDTO;
use App\Modules\Api\Support\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SyncBundleOrderRequest extends ApiFormRequest
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

        $metadata = is_array($this->input('metadata')) ? $this->input('metadata') : [];
        $metadata['bundle'] = true;

        $this->merge([
            'product_type' => 'flight',
            'metadata' => $metadata,
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

                $type = strtolower((string) ($item['type'] ?? ''));
                $item['type'] = $type !== '' ? $type : 'flight';
                $item['product_type'] = $item['product_type'] ?? match ($item['type']) {
                    'esim' => 'esim',
                    'insurance' => 'insurance',
                    default => 'ticket',
                };

                if (isset($item['item_details']) && is_array($item['item_details'])) {
                    foreach (['item_id', 'order_id', 'booking_uuid'] as $idKey) {
                        if (array_key_exists($idKey, $item['item_details']) && $item['item_details'][$idKey] !== null) {
                            $item['item_details'][$idKey] = (string) $item['item_details'][$idKey];
                        }
                    }
                }

                if (! isset($item['total']) && isset($item['unit_price'])) {
                    $qty = max(1, (int) ($item['quantity'] ?? 1));
                    $item['total'] = round((float) $item['unit_price'] * $qty, 2);
                }

                if (! isset($item['currency']) && $this->filled('currency')) {
                    $item['currency'] = strtoupper((string) $this->input('currency'));
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
            'product_type' => ['required', 'string', Rule::in(['flight'])],
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
            'provider_booking.pnr' => ['nullable', 'string', 'max:40'],
            'provider_booking.provider' => ['nullable', 'string', 'max:120'],
            'provider_booking.provider_key' => ['nullable', 'string', 'max:120'],
            'provider_booking.provider_name' => ['nullable', 'string', 'max:120'],
            'provider_booking.provider_id' => ['nullable', 'integer'],
            'provider_booking.search_uuid' => ['nullable', 'string', 'max:120'],
            'contact' => ['required', 'array'],
            'contact.name' => ['nullable', 'string', 'max:190'],
            'contact.first_name' => ['required', 'string', 'max:120'],
            'contact.last_name' => ['nullable', 'string', 'max:120'],
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
            'items.*.type' => ['required', 'string', Rule::in(['flight', 'esim', 'insurance', 'seat', 'ancillary'])],
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
            'items.*.item_details.order_id' => ['nullable', 'string', 'max:120'],
            'items.*.item_details.booking_uuid' => ['nullable', 'string', 'max:120'],
            'items.*.item_details.pnr' => ['nullable', 'string', 'max:40'],
            'items.*.item_details.seats' => ['nullable', 'array'],
            'items.*.item_details.iccid' => ['nullable', 'string', 'max:120'],
            'items.*.item_details.activation_code' => ['nullable', 'string', 'max:500'],
            'items.*.item_details.qr' => ['nullable', 'string'],
            'items.*.item_details.ticket_number' => ['nullable', 'string', 'max:120'],
            'items.*.item_details.report_reference' => ['nullable', 'string', 'max:120'],
            'items.*.item_details.zone_id' => ['nullable', 'integer'],
            'items.*.item_details.zone_name' => ['nullable', 'string', 'max:120'],
            'items.*.item_details.duration_id' => ['nullable', 'integer'],
            'items.*.item_details.duration_label' => ['nullable', 'string', 'max:120'],
            'items.*.item_details.policy_date_from' => ['nullable', 'date'],
            'items.*.item_details.policy_date_to' => ['nullable', 'date'],
            'items.*.item_details.provider' => ['nullable', 'string', 'max:120'],
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
            'metadata.bundle' => ['required', 'boolean'],
            'metadata.booknow_flight_order_id' => ['nullable', 'string', 'max:120'],
            'metadata.booknow_insurance_order_id' => ['nullable', 'string', 'max:120'],
            'metadata.booknow_esim_booking_ids' => ['nullable', 'array'],
            'metadata.booknow_esim_booking_ids.*' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:500'],
            'agency_id' => ['nullable', 'string', 'max:60'],
            'commission_amount' => ['nullable', 'numeric', 'min:0'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'base_amount' => ['nullable', 'numeric', 'min:0'],
            'issued_at' => ['nullable', 'date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $items = $this->input('items');

            if (! is_array($items) || $items === []) {
                return;
            }

            $hasFlight = false;

            foreach ($items as $index => $item) {
                if (! is_array($item)) {
                    continue;
                }

                $type = strtolower((string) ($item['type'] ?? ''));

                if ($type === 'flight') {
                    $hasFlight = true;
                }

                $details = is_array($item['item_details'] ?? null) ? $item['item_details'] : [];

                if ($type === 'esim') {
                    $hasIdentity = ! empty($details['item_id']) || ! empty($details['booking_uuid']);
                    $hasCredential = ! empty($details['qr'])
                        || ! empty($details['activation_code'])
                        || ! empty($details['iccid']);

                    if (! $hasIdentity) {
                        $validator->errors()->add(
                            "items.{$index}.item_details.item_id",
                            'eSIM items require item_id or booking_uuid.',
                        );
                    }

                    if (! $hasCredential) {
                        $validator->errors()->add(
                            "items.{$index}.item_details.qr",
                            'eSIM items require qr, activation_code, or iccid.',
                        );
                    }
                }

                if ($type === 'insurance' && empty($details['order_id'])) {
                    $validator->errors()->add(
                        "items.{$index}.item_details.order_id",
                        'Insurance items require order_id for policy PDF.',
                    );
                }
            }

            if (! $hasFlight) {
                $validator->errors()->add('items', 'A bundle order must include at least one flight item.');
            }
        });
    }

    public function toDto(): SyncBooknowOrderDTO
    {
        return SyncBooknowOrderDTO::fromArray($this->validated());
    }
}
