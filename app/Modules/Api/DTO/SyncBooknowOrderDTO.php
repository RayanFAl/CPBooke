<?php

namespace App\Modules\Api\DTO;

final readonly class SyncBooknowOrderDTO
{
    /**
     * @param  array<string, mixed>  $providerBooking
     * @param  array<string, mixed>  $contact
     * @param  array<int, array<string, mixed>>  $passengers
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<string, mixed>|null  $payment
     * @param  array<string, mixed>|null  $bookingFlightData
     * @param  array<string, mixed>|null  $metadata
     * @param  array<string, mixed>  $rawPayload
     */
    public function __construct(
        public string $source,
        public string $productType,
        public string $status,
        public string $currency,
        public string $grandTotal,
        public array $providerBooking,
        public array $contact,
        public array $passengers,
        public array $items,
        public ?int $customerId,
        public ?array $payment,
        public ?array $bookingFlightData,
        public ?array $metadata,
        public ?string $notes,
        public ?string $agencyId,
        public ?string $baseAmount,
        public ?string $taxAmount,
        public ?string $commissionAmount,
        public ?string $issuedAt,
        public array $rawPayload,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            source: (string) $data['source'],
            productType: (string) $data['product_type'],
            status: (string) $data['status'],
            currency: strtoupper((string) $data['currency']),
            grandTotal: number_format((float) $data['grand_total'], 2, '.', ''),
            providerBooking: $data['provider_booking'],
            contact: $data['contact'],
            passengers: $data['passengers'],
            items: $data['items'],
            customerId: null,
            payment: $data['payment'] ?? null,
            bookingFlightData: $data['booking_flight_data'] ?? null,
            metadata: $data['metadata'] ?? null,
            notes: $data['notes'] ?? null,
            agencyId: isset($data['agency_id']) ? (string) $data['agency_id'] : null,
            baseAmount: isset($data['base_amount']) ? number_format((float) $data['base_amount'], 2, '.', '') : null,
            taxAmount: isset($data['tax_amount']) ? number_format((float) $data['tax_amount'], 2, '.', '') : null,
            commissionAmount: isset($data['commission_amount']) ? number_format((float) $data['commission_amount'], 2, '.', '') : null,
            issuedAt: $data['issued_at'] ?? null,
            rawPayload: $data,
        );
    }

    public function externalBookingId(): string
    {
        return (string) ($this->providerBooking['booking_id'] ?? '');
    }

    public function orderNumber(): ?string
    {
        $number = $this->providerBooking['order_number'] ?? null;

        return is_string($number) && $number !== '' ? $number : null;
    }

    public function pnr(): ?string
    {
        $pnr = $this->providerBooking['pnr'] ?? null;

        return is_string($pnr) && $pnr !== '' ? $pnr : null;
    }

    public function providerName(): string
    {
        return (string) ($this->providerBooking['provider_name'] ?? 'booknow');
    }
}
