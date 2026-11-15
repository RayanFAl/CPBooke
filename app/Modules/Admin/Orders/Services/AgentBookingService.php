<?php

namespace App\Modules\Admin\Orders\Services;

use App\Models\Order;
use App\Models\User;
use App\Modules\Admin\Home\Services\AdminHotelLookupService;
use App\Modules\Api\DTO\CreateOrderDTO;
use App\Modules\Api\DTO\SyncBooknowOrderDTO;
use App\Modules\Api\Orders\Services\BooknowOrderSyncService;
use App\Modules\Api\Orders\Services\OrderService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AgentBookingService
{
    public function __construct(
        private readonly OrderService $orderService,
        private readonly AdminHotelLookupService $hotelLookupService,
        private readonly BooknowOrderSyncService $booknowOrderSyncService,
    ) {
    }

    /**
     * Create an assisted booking for a customer from the admin panel.
     * Hotel bookings go through the live BookNow select → book APIs, then sync into the ledger.
     *
     * @param  array<string, mixed>  $data
     */
    public function bookForCustomer(User $customer, User $actor, array $data): Order
    {
        if (! $customer->isCustomerAccount()) {
            throw ValidationException::withMessages([
                'customer_id' => 'Only customer accounts can receive assisted bookings.',
            ]);
        }

        $serviceType = (string) $data['service_type'];

        if ($serviceType === Order::SERVICE_TYPE_HOTEL) {
            return $this->bookHotelLive($customer, $actor, $data);
        }

        return $this->createLedgerBooking($customer, $actor, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function bookHotelLive(User $customer, User $actor, array $data): Order
    {
        $searchUuid = trim((string) ($data['search_uuid'] ?? ''));
        $rateKey = trim((string) ($data['rate_key'] ?? $data['offer_id'] ?? ''));

        if ($searchUuid === '' || $rateKey === '') {
            throw ValidationException::withMessages([
                'offer_id' => 'Select a live hotel offer from search before booking. Manual hotel ledger entries are disabled.',
            ]);
        }

        $select = $this->hotelLookupService->selectOffer([
            'search_uuid' => $searchUuid,
            'rate_key' => $rateKey,
            'offer_id' => $data['offer_id'] ?? null,
            'search_code' => $data['search_code'] ?? '',
            'city' => $data['city_name'] ?? $data['city'] ?? null,
            'city_id' => $data['city_id'] ?? null,
            'hotel_id' => $data['hotel_id'] ?? null,
            'check_in' => $data['check_in'] ?? null,
            'check_out' => $data['check_out'] ?? null,
            'adults' => $data['adults'] ?? 2,
            'rooms' => $data['rooms'] ?? 1,
        ]);

        if (! ($select['ok'] ?? false) || empty($select['booking_uuid'])) {
            throw ValidationException::withMessages([
                'offer_id' => $select['error'] ?? 'Provider could not reserve the selected hotel offer.',
            ]);
        }

        $book = $this->hotelLookupService->book([
            'booking_uuid' => $select['booking_uuid'],
            'contact_first_name' => $data['contact_first_name'] ?? '',
            'contact_last_name' => $data['contact_last_name'] ?? '',
            'contact_email' => $data['contact_email'] ?? $customer->email,
            'contact_phone' => $data['contact_phone'] ?? $customer->phone,
            'customer_country' => $data['customer_country'] ?? 'Libya',
            'customer_city' => $data['customer_city'] ?? ($data['city_name'] ?? 'Tripoli'),
            'guest_civility' => $data['guest_civility'] ?? 'Mr',
            'rooms' => $data['rooms'] ?? 1,
            'adults' => $data['adults'] ?? 2,
            'hotel_id' => $data['hotel_id'] ?? null,
            'city_id' => $data['city_id'] ?? null,
            'offer_id' => $data['offer_id'] ?? null,
            'check_in' => $data['check_in'] ?? null,
            'check_out' => $data['check_out'] ?? null,
            'total_amount' => $data['total_amount'] ?? null,
            'currency' => $data['currency'] ?? null,
            'payment_method' => $data['payment_method'] ?? 'wallet',
        ]);

        if (! ($book['ok'] ?? false)) {
            throw ValidationException::withMessages([
                'offer_id' => $book['error'] ?? 'Provider hotel booking failed.',
            ]);
        }

        /** @var array<string, mixed> $bookingResponse */
        $bookingResponse = $book['booking'] ?? [];
        $syncPayload = $this->buildHotelSyncPayload($customer, $actor, $data, $bookingResponse, (string) $select['booking_uuid']);

        try {
            $result = $this->booknowOrderSyncService->upsertForCustomer(
                $customer,
                SyncBooknowOrderDTO::fromArray($syncPayload),
            );
        } catch (\Throwable $exception) {
            Log::error('admin.agent_booking.hotel_sync_failed', [
                'customer_id' => $customer->id,
                'actor_id' => $actor->id,
                'message' => $exception->getMessage(),
                'booking_uuid' => $select['booking_uuid'],
            ]);

            throw ValidationException::withMessages([
                'offer_id' => 'Hotel was booked with the provider, but syncing into Booke failed: '.$exception->getMessage(),
            ]);
        }

        $order = $result['order'];

        $noteLines = array_filter([
            sprintf(
                'Live hotel booking by %s (%s) via BookNow /hotels/select → /hotels/book.',
                $actor->full_name ?: $actor->name ?: ('#'.$actor->id),
                $actor->email ?: 'no-email',
            ),
            'Provider booking uuid: '.$select['booking_uuid'],
            trim((string) ($data['internal_notes'] ?? '')) ?: null,
        ]);

        $this->orderService->updateInternalNotes($order, implode("\n", $noteLines), $actor);
        $this->orderService->recordOperationalHistory(
            $order,
            $actor,
            'agent_live_hotel_booked',
            'source',
            null,
            'admin_agent_booking_live',
        );

        if ((bool) ($data['mark_confirmed'] ?? false)) {
            $order = $this->applyConfirmedStatuses($order, $actor);
        }

        return $order->fresh(['customer']);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $bookingResponse
     * @return array<string, mixed>
     */
    private function buildHotelSyncPayload(
        User $customer,
        User $actor,
        array $data,
        array $bookingResponse,
        string $bookingUuid,
    ): array {
        $bookingId = $this->extractProviderBookingId($bookingResponse, $bookingUuid);
        $orderNumber = $this->firstString($bookingResponse, [
            'order_number', 'number', 'booking_reference', 'bookingReference',
        ]);
        $orderId = $this->firstString($bookingResponse, ['order_id', 'booknow_order_id']);

        $amount = $this->firstNumeric($bookingResponse, ['grand_total', 'total', 'amount', 'price']);
        if ($amount === null) {
            $amount = isset($data['total_amount']) ? (float) $data['total_amount'] : 0.0;
        }

        $currency = strtoupper(trim((string) (
            $this->firstString($bookingResponse, ['currency'])
            ?: ($data['currency'] ?? Order::DEFAULT_CURRENCY)
        )));

        $hotelName = trim((string) ($data['hotel_name'] ?? 'Hotel'));
        $rooms = max(1, (int) ($data['rooms'] ?? 1));
        $adults = max(1, (int) ($data['adults'] ?? 1));
        $checkIn = (string) ($data['check_in'] ?? '');
        $checkOut = (string) ($data['check_out'] ?? '');
        $status = (bool) ($data['mark_confirmed'] ?? false) ? 'confirmed' : 'pending_payment';

        $contact = [
            'first_name' => trim((string) ($data['contact_first_name'] ?? '')) ?: 'Customer',
            'last_name' => trim((string) ($data['contact_last_name'] ?? '')),
            'email' => trim((string) ($data['contact_email'] ?? $customer->email ?? '')),
            'phone' => trim((string) ($data['contact_phone'] ?? $customer->phone ?? '')),
        ];
        $contact['name'] = trim($contact['first_name'].' '.$contact['last_name']);

        $guests = [[
            'civility' => $data['guest_civility'] ?? 'Mr',
            'first_name' => $contact['first_name'],
            'last_name' => $contact['last_name'] !== '' ? $contact['last_name'] : $contact['first_name'],
        ]];

        return [
            'source' => 'admin_agent_booking',
            'product_type' => 'hotel',
            'status' => $status,
            'currency' => $currency !== '' ? $currency : Order::DEFAULT_CURRENCY,
            'grand_total' => number_format($amount, 2, '.', ''),
            'contact' => $contact,
            'guests' => $guests,
            'passengers' => $guests,
            'provider_booking' => array_filter([
                'booking_id' => $bookingId,
                'provider' => 'booknow_hotels',
                'provider_name' => 'BookNow Hotels',
                'order_id' => $orderId !== '' ? $orderId : null,
                'order_number' => $orderNumber !== '' ? $orderNumber : null,
                'booking_reference' => $orderNumber !== '' ? $orderNumber : null,
                'booking_uuid' => $bookingUuid,
            ], static fn ($value) => $value !== null && $value !== ''),
            'items' => [[
                'type' => 'hotel',
                'product_type' => 'hotel',
                'title' => $hotelName,
                'quantity' => $rooms,
                'unit_price' => number_format($amount, 2, '.', ''),
                'currency' => $currency !== '' ? $currency : Order::DEFAULT_CURRENCY,
                'item_details' => array_filter([
                    'hotel_id' => $data['hotel_id'] ?? null,
                    'hotel_name' => $hotelName,
                    'city_id' => $data['city_id'] ?? null,
                    'city_name' => $data['city_name'] ?? null,
                    'country' => $data['customer_country'] ?? null,
                    'source' => $data['hotel_source'] ?? null,
                    'offer_id' => $data['offer_id'] ?? null,
                    'rate_key' => $data['rate_key'] ?? null,
                    'room_name' => $data['offer_label'] ?? null,
                    'board' => $data['board'] ?? null,
                    'check_in' => $checkIn,
                    'check_out' => $checkOut,
                    'rooms' => $rooms,
                    'adults' => $adults,
                    'guests_count' => $adults,
                    'guests' => $guests,
                    'booking_uuid' => $bookingUuid,
                ], static fn ($value) => $value !== null && $value !== ''),
            ]],
            'payment' => [
                'method' => (bool) ($data['mark_confirmed'] ?? false) ? 'agent_confirmed' : 'pending',
                'status' => (bool) ($data['mark_confirmed'] ?? false) ? 'paid' : 'unpaid',
                'amount' => number_format($amount, 2, '.', ''),
                'currency' => $currency !== '' ? $currency : Order::DEFAULT_CURRENCY,
            ],
            'metadata' => [
                'source_screen' => 'admin_agent_booking',
                'booked_by_admin' => true,
                'actor_id' => $actor->id,
                'booking_uuid' => $bookingUuid,
                'booknow_booking_id' => $bookingId,
            ],
            'notes' => trim((string) ($data['booking_notes'] ?? '')) ?: null,
        ];
    }

    /**
     * @param  array<string, mixed>  $bookingResponse
     */
    private function extractProviderBookingId(array $bookingResponse, string $bookingUuid): string
    {
        $nestedBooking = isset($bookingResponse['booking']) && is_array($bookingResponse['booking'])
            ? $bookingResponse['booking']
            : [];
        $responseLayer = [];
        if (isset($nestedBooking['response']) && is_array($nestedBooking['response'])) {
            $responseLayer = $nestedBooking['response'];
        } elseif (isset($bookingResponse['response']) && is_array($bookingResponse['response'])) {
            $responseLayer = $bookingResponse['response'];
        }
        $nestedData = isset($bookingResponse['data']) && is_array($bookingResponse['data'])
            ? $bookingResponse['data']
            : [];

        foreach ([
            $responseLayer['bookingId'] ?? null,
            $responseLayer['booking_id'] ?? null,
            $bookingResponse['bookingId'] ?? null,
            $nestedBooking['bookingId'] ?? null,
            $nestedData['bookingId'] ?? null,
            $bookingResponse['booking_id'] ?? null,
            $nestedBooking['booking_id'] ?? null,
            $nestedData['booking_id'] ?? null,
            $bookingResponse['order_id'] ?? null,
            $nestedBooking['order_id'] ?? null,
            $bookingResponse['booking_reference'] ?? null,
            $bookingResponse['order_number'] ?? null,
            $bookingResponse['booking_uuid'] ?? null,
            $bookingUuid,
        ] as $candidate) {
            $text = trim((string) $candidate);
            if ($text !== '') {
                return $text;
            }
        }

        return $bookingUuid;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $keys
     */
    private function firstString(array $data, array $keys): string
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $data)) {
                continue;
            }
            $value = $data[$key];
            if (is_scalar($value)) {
                $text = trim((string) $value);
                if ($text !== '') {
                    return $text;
                }
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $keys
     */
    private function firstNumeric(array $data, array $keys): ?float
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $data)) {
                continue;
            }
            if (is_numeric($data[$key])) {
                return (float) $data[$key];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createLedgerBooking(User $customer, User $actor, array $data): Order
    {
        $serviceType = (string) $data['service_type'];
        $markConfirmed = (bool) ($data['mark_confirmed'] ?? false);
        $externalReference = trim((string) ($data['external_booking_id'] ?? ''));

        $details = $this->buildDetails($serviceType, $data, $actor);

        return DB::transaction(function () use ($customer, $actor, $data, $serviceType, $markConfirmed, $externalReference, $details): Order {
            $dto = new CreateOrderDTO(
                providerName: trim((string) ($data['provider_name'] ?? 'Booke Agent')) ?: 'Booke Agent',
                currency: strtoupper(trim((string) ($data['currency'] ?? Order::DEFAULT_CURRENCY))),
                totalAmount: number_format((float) $data['total_amount'], 2, '.', ''),
                serviceType: $serviceType,
                details: $details,
                requestPayload: [
                    'source' => 'admin_agent_booking',
                    'actor_id' => $actor->id,
                    'payload' => $data,
                ],
            );

            $order = $this->orderService->createForCustomer($customer, $dto);

            if ($externalReference !== '') {
                $order->forceFill([
                    'external_booking_id' => $externalReference,
                ])->save();
            }

            $noteLines = array_filter([
                sprintf(
                    'Assisted booking created by %s (%s).',
                    $actor->full_name ?: $actor->name ?: ('#'.$actor->id),
                    $actor->email ?: 'no-email',
                ),
                trim((string) ($data['internal_notes'] ?? '')) ?: null,
            ]);

            $this->orderService->updateInternalNotes(
                $order,
                implode("\n", $noteLines),
                $actor,
            );

            $this->orderService->recordOperationalHistory(
                $order,
                $actor,
                'agent_booking_created',
                'source',
                null,
                'admin_agent_booking',
            );

            if ($markConfirmed) {
                $order = $this->applyConfirmedStatuses($order, $actor);
            }

            return $order->fresh(['customer']);
        });
    }

    private function applyConfirmedStatuses(Order $order, User $actor): Order
    {
        $order = $this->orderService->updatePaymentStatusByActor(
            $order->fresh(),
            Order::PAYMENT_STATUS_PAID,
            $actor,
        );

        if ($order->canTransitionTo(Order::STATUS_PAID)) {
            $order = $this->orderService->updateStatusByActor($order, Order::STATUS_PAID, $actor);
        }

        if ($order->canTransitionTo(Order::STATUS_PROCESSING)) {
            $order = $this->orderService->updateStatusByActor($order, Order::STATUS_PROCESSING, $actor);
        }

        if ($order->canTransitionTo(Order::STATUS_CONFIRMED)) {
            $order = $this->orderService->updateStatusByActor($order, Order::STATUS_CONFIRMED, $actor);
        }

        return $order;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function buildDetails(string $serviceType, array $data, User $actor): array
    {
        $contact = [
            'first_name' => trim((string) ($data['contact_first_name'] ?? '')),
            'last_name' => trim((string) ($data['contact_last_name'] ?? '')),
            'email' => trim((string) ($data['contact_email'] ?? '')),
            'phone' => trim((string) ($data['contact_phone'] ?? '')),
        ];

        $base = [
            'source' => 'admin_agent_booking',
            'booked_by_admin' => true,
            'agent' => [
                'id' => $actor->id,
                'name' => $actor->full_name ?: $actor->name,
                'email' => $actor->email,
            ],
            'contact' => $contact,
            'notes' => trim((string) ($data['booking_notes'] ?? '')),
        ];

        return match ($serviceType) {
            Order::SERVICE_TYPE_FLIGHT => array_merge($base, [
                'origin' => $data['origin'] ?? null,
                'destination' => $data['destination'] ?? null,
                'departure_date' => $data['departure_date'] ?? null,
                'return_date' => $data['return_date'] ?? null,
                'airline' => $data['airline'] ?? null,
                'pnr' => $data['pnr'] ?? null,
                'passengers_count' => (int) ($data['passengers_count'] ?? 1),
            ]),
            Order::SERVICE_TYPE_INSURANCE => array_merge($base, [
                'plan_name' => $data['plan_name'] ?? null,
                'zone_name' => $data['zone_name'] ?? null,
                'coverage_start' => $data['coverage_start'] ?? null,
                'coverage_end' => $data['coverage_end'] ?? null,
                'travellers_count' => (int) ($data['travellers_count'] ?? 1),
            ]),
            Order::SERVICE_TYPE_ESIM => array_merge($base, [
                'plan_name' => $data['plan_name'] ?? null,
                'country' => $data['country'] ?? null,
                'data_amount' => $data['data_amount'] ?? null,
                'validity_days' => $data['validity_days'] ?? null,
            ]),
            default => $base,
        };
    }
}
