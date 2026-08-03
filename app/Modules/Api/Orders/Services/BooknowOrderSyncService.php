<?php

namespace App\Modules\Api\Orders\Services;

use App\Models\FinancialTransaction;
use App\Models\Order;
use App\Models\Provider;
use App\Models\User;
use App\Modules\Admin\ProviderWallets\Services\ProviderWalletService;
use App\Modules\Api\DTO\SyncBooknowOrderDTO;
use App\Modules\Orders\Events\OrderConfirmed as OrderConfirmedEvent;
use App\Modules\Orders\Events\OrderCreated as OrderCreatedEvent;
use App\Modules\Orders\Services\OrderCostService;
use App\Modules\ProviderHealth\Services\ProviderApiEventRecorder;
use App\Modules\Wallets\Services\WalletService;
use App\Support\Orders\BooknowOrderStatusMapper;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class BooknowOrderSyncService
{
    public function __construct(
        private readonly OrderService $orderService,
        private readonly ProviderWalletService $providerWalletService,
        private readonly WalletService $walletService,
        private readonly OrderCostService $orderCostService,
        private readonly ProviderApiEventRecorder $apiEventRecorder,
    ) {
    }

    /**
     * Idempotent upsert keyed by provider booking id. Customer is always taken from the auth token.
     *
     * @return array{order: Order, created: bool}
     */
    public function upsert(User $authenticatedUser, SyncBooknowOrderDTO $data): array
    {
        $started = microtime(true);
        $provider = $this->resolveProvider($data);

        try {
            $result = $this->upsertOrder($authenticatedUser, $data, $provider);
            $latencyMs = (int) max(0, round((microtime(true) - $started) * 1000));
            $this->apiEventRecorder->recordSyncSuccess($provider, $latencyMs, $result['order']->id);

            return $result;
        } catch (Throwable $exception) {
            $latencyMs = (int) max(0, round((microtime(true) - $started) * 1000));
            $this->apiEventRecorder->recordSyncFailure($provider, $latencyMs, $exception);

            throw $exception;
        }
    }

    private function resolveProvider(SyncBooknowOrderDTO $data): Provider
    {
        $key = $data->providerKey();

        $label = match ($key) {
            Provider::KEY_BOOKNOW_ESIM, 'booknow_esim' => 'BookNow eSIM',
            Provider::KEY_BOOKNOW_INSURANCE, 'booknow_insurance' => 'BookNow Insurance',
            Provider::KEY_BOOKNOW_HOTELS, 'booknow_hotels' => 'BookNow Hotels',
            Provider::KEY_BOOKNOW, 'booknow' => 'BookNow',
            default => $data->providerName(),
        };

        return $this->walletService->findOrCreateProviderByKey($key, $label);
    }

    /**
     * @return array{order: Order, created: bool}
     */
    private function upsertOrder(User $authenticatedUser, SyncBooknowOrderDTO $data, Provider $provider): array
    {
        $customer = $this->resolveCustomerFromToken($authenticatedUser);
        $bookingId = $data->externalBookingId();

        if ($bookingId === '') {
            throw ValidationException::withMessages([
                'provider_booking.booking_id' => 'The provider booking id is required for sync.',
            ]);
        }

        return DB::transaction(function () use ($customer, $data, $bookingId, $provider): array {
            $order = Order::query()->firstOrNew([
                'external_booking_id' => $bookingId,
            ]);

            $created = ! $order->exists;
            $previousStatus = $order->exists ? $order->status : null;

            if ($order->exists) {
                $this->assertSameCustomer($order, $customer);
            }

            $mapped = $this->mapOrderAttributes($data);

            if ($order->exists) {
                unset($mapped['booking_reference']);
            } else {
                $mapped['booking_reference'] = null;
            }

            $order->fill([
                'customer_id' => $customer->id,
                ...$mapped,
            ]);

            $order->save();

            if ($created) {
                $order = $this->orderService->assignCpbookeOrderNumber($order);
            }

            $order = $this->refreshResponsePayload($order, $data);

            $order = $this->orderCostService->apply($order, $provider, [
                'commission_amount' => $data->commissionAmount,
                'base_amount' => $data->baseAmount,
            ]);

            if ($created) {
                if ($order->payment_status === Order::PAYMENT_STATUS_PAID) {
                    $this->orderService->recordFinancialTransactionOnce(
                        $order,
                        FinancialTransaction::TYPE_PAYMENT,
                        FinancialTransaction::SOURCE_ORDER_CREATION,
                    );
                }

                $this->dispatchAfterCommit(fn () => event(new OrderCreatedEvent($order->fresh()->load('customer'))));
            }

            if ($order->payment_status === Order::PAYMENT_STATUS_PAID) {
                $this->providerWalletService->debitPaidOrder($order, $provider);
            }

            if (
                in_array($order->status, [Order::STATUS_CONFIRMED, Order::STATUS_TICKETED], true)
                && ! in_array($previousStatus, [Order::STATUS_CONFIRMED, Order::STATUS_TICKETED, Order::STATUS_COMPLETED], true)
            ) {
                $this->dispatchAfterCommit(fn () => event(new OrderConfirmedEvent($order->fresh()->load('customer'))));
            }

            return [
                'order' => $order->refresh()->load('customer'),
                'created' => $created,
            ];
        });
    }

    private function resolveCustomerFromToken(User $authenticatedUser): User
    {
        if (! $authenticatedUser->isCustomerAccount()) {
            throw new AuthorizationException('Only customer accounts can sync booking orders.');
        }

        return $authenticatedUser;
    }

    private function assertSameCustomer(Order $existing, User $customer): void
    {
        if ((int) $existing->customer_id !== (int) $customer->id) {
            throw ValidationException::withMessages([
                'provider_booking.booking_id' => 'This booking is already linked to another customer.',
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function mapOrderAttributes(SyncBooknowOrderDTO $data): array
    {
        [$status, $paymentStatus] = $this->mapStatuses($data);

        return [
            'source' => $data->source,
            'provider_name' => $data->providerName(),
            'external_booking_id' => $data->externalBookingId(),
            'status' => $status,
            'payment_status' => $paymentStatus,
            'service_type' => $this->mapServiceType($data->productType),
            'currency' => $data->currency,
            'total_amount' => $data->grandTotal,
            'base_amount' => $data->baseAmount,
            'tax_amount' => $data->taxAmount,
            'details' => $this->buildDetailsSummary($data),
            'request_payload' => $data->rawPayload,
            'response_payload' => $this->buildResponsePayload($data),
            'error_message' => in_array($status, [Order::STATUS_FAILED], true) ? 'Booking or payment failed.' : null,
            'internal_notes' => $data->notes,
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function mapStatuses(SyncBooknowOrderDTO $data): array
    {
        $paymentStatus = match ($data->payment['status'] ?? null) {
            'paid' => Order::PAYMENT_STATUS_PAID,
            'refunded' => Order::PAYMENT_STATUS_REFUNDED,
            'partially_refunded' => Order::PAYMENT_STATUS_PARTIALLY_REFUNDED,
            default => Order::PAYMENT_STATUS_UNPAID,
        };

        $status = BooknowOrderStatusMapper::toInternal($data->status);

        if ($paymentStatus === Order::PAYMENT_STATUS_PAID && $status === Order::STATUS_PENDING_PAYMENT) {
            $status = Order::STATUS_CONFIRMED;
        }

        return [$status, $paymentStatus];
    }

    private function mapServiceType(string $productType): string
    {
        return match ($productType) {
            'hotel' => Order::SERVICE_TYPE_HOTEL,
            'insurance' => Order::SERVICE_TYPE_INSURANCE,
            'esim' => Order::SERVICE_TYPE_ESIM,
            default => Order::SERVICE_TYPE_FLIGHT,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDetailsSummary(SyncBooknowOrderDTO $data): array
    {
        if ($data->productType === 'esim') {
            return $this->buildEsimDetailsSummary($data);
        }

        if ($data->productType === 'insurance') {
            return $this->buildInsuranceDetailsSummary($data);
        }

        if ($data->productType === 'hotel') {
            return $this->buildHotelDetailsSummary($data);
        }

        if ($data->isBundle()) {
            return $this->buildBundleDetailsSummary($data);
        }

        $firstItem = $data->items[0] ?? [];
        $itemDetails = is_array($firstItem['item_details'] ?? null) ? $firstItem['item_details'] : [];
        $firstSegment = is_array($itemDetails['segments'][0] ?? null) ? $itemDetails['segments'][0] : [];
        $flightData = is_array($data->bookingFlightData) ? $data->bookingFlightData : [];
        $flightSegments = is_array($flightData['segments'] ?? null) ? $flightData['segments'] : [];
        $firstFlightSegment = is_array($flightSegments[0] ?? null) ? $flightSegments[0] : [];
        $firstPassenger = $data->passengers[0] ?? [];

        return [
            'provider_status' => $data->status,
            'pnr' => $data->pnr() ?? ($itemDetails['pnr'] ?? null),
            'airline' => $itemDetails['airline_name'] ?? $data->providerName(),
            'airline_code' => $itemDetails['airline_code'] ?? null,
            'passenger_name' => trim(($firstPassenger['first_name'] ?? '').' '.($firstPassenger['last_name'] ?? '')),
            'origin' => $firstSegment['departure_airport']
                ?? $firstFlightSegment['departure_airport']
                ?? ($flightData['departure_airport'] ?? null),
            'destination' => $firstSegment['arrival_airport']
                ?? $firstFlightSegment['arrival_airport']
                ?? ($flightData['arrival_airport'] ?? null),
            'departure_time' => $firstSegment['departure_time']
                ?? $firstFlightSegment['departure_time']
                ?? ($flightData['departure_time'] ?? null),
            'product_subtype' => $firstItem['product_subtype'] ?? null,
            'contact' => $data->contact,
            'payment' => $data->payment,
            'metadata' => $data->metadata,
            'provider_order_number' => $data->orderNumber(),
            'booking_flight_data' => $data->bookingFlightData,
            'segments' => $itemDetails['segments'] ?? $flightSegments,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildBundleDetailsSummary(SyncBooknowOrderDTO $data): array
    {
        $flightItem = $this->firstItemOfType($data->items, 'flight') ?? ($data->items[0] ?? []);
        $flightDetails = is_array($flightItem['item_details'] ?? null) ? $flightItem['item_details'] : [];
        $firstSegment = is_array($flightDetails['segments'][0] ?? null) ? $flightDetails['segments'][0] : [];
        $flightData = is_array($data->bookingFlightData) ? $data->bookingFlightData : [];
        $flightSegments = is_array($flightData['segments'] ?? null) ? $flightData['segments'] : [];
        $firstFlightSegment = is_array($flightSegments[0] ?? null) ? $flightSegments[0] : [];
        $firstPassenger = $data->passengers[0] ?? [];

        $esimItems = $this->itemsOfType($data->items, 'esim');
        $insuranceItems = $this->itemsOfType($data->items, 'insurance');

        return [
            'provider_status' => $data->status,
            'bundle' => true,
            'pnr' => $data->pnr() ?? ($flightDetails['pnr'] ?? null),
            'airline' => $flightDetails['airline_name'] ?? $data->providerName(),
            'airline_code' => $flightDetails['airline_code'] ?? null,
            'passenger_name' => trim(($firstPassenger['first_name'] ?? '').' '.($firstPassenger['last_name'] ?? '')),
            'origin' => $firstSegment['departure_airport']
                ?? $firstFlightSegment['departure_airport']
                ?? ($flightData['departure_airport'] ?? null),
            'destination' => $firstSegment['arrival_airport']
                ?? $firstFlightSegment['arrival_airport']
                ?? ($flightData['arrival_airport'] ?? null),
            'departure_time' => $firstSegment['departure_time']
                ?? $firstFlightSegment['departure_time']
                ?? ($flightData['departure_time'] ?? null),
            'product_subtype' => $flightItem['product_subtype'] ?? null,
            'flight_item_id' => isset($flightDetails['item_id']) ? (string) $flightDetails['item_id'] : null,
            'seats' => $flightDetails['seats'] ?? null,
            'has_esim' => $esimItems !== [],
            'has_insurance' => $insuranceItems !== [],
            'esims' => array_map(fn (array $item): array => $this->summarizeEsimItem($item), $esimItems),
            'insurances' => array_map(fn (array $item): array => $this->summarizeInsuranceItem($item), $insuranceItems),
            'contact' => $data->contact,
            'payment' => $data->payment,
            'metadata' => $data->metadata,
            'provider_order_number' => $data->orderNumber(),
            'booking_flight_data' => $data->bookingFlightData,
            'segments' => $flightDetails['segments'] ?? $flightSegments,
            'items' => $data->items,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function itemsOfType(array $items, string $type): array
    {
        return array_values(array_filter(
            $items,
            static fn ($item): bool => is_array($item) && strtolower((string) ($item['type'] ?? '')) === $type,
        ));
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, mixed>|null
     */
    private function firstItemOfType(array $items, string $type): ?array
    {
        foreach ($items as $item) {
            if (is_array($item) && strtolower((string) ($item['type'] ?? '')) === $type) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function summarizeEsimItem(array $item): array
    {
        $details = is_array($item['item_details'] ?? null) ? $item['item_details'] : [];

        return [
            'title' => $item['title'] ?? null,
            'quantity' => $item['quantity'] ?? 1,
            'unit_price' => $item['unit_price'] ?? null,
            'total' => $item['total'] ?? null,
            'currency' => $item['currency'] ?? null,
            'item_id' => isset($details['item_id']) ? (string) $details['item_id'] : null,
            'booking_uuid' => isset($details['booking_uuid']) ? (string) $details['booking_uuid'] : null,
            'country' => $details['country'] ?? null,
            'data' => $details['data'] ?? null,
            'validity_days' => $details['validity_days'] ?? null,
            'iccid' => $details['iccid'] ?? null,
            'activation_code' => $details['activation_code'] ?? null,
            'qr' => $details['qr'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function summarizeInsuranceItem(array $item): array
    {
        $details = is_array($item['item_details'] ?? null) ? $item['item_details'] : [];

        return [
            'title' => $item['title'] ?? null,
            'product_subtype' => $item['product_subtype'] ?? null,
            'quantity' => $item['quantity'] ?? 1,
            'unit_price' => $item['unit_price'] ?? null,
            'total' => $item['total'] ?? null,
            'currency' => $item['currency'] ?? null,
            'item_id' => isset($details['item_id']) ? (string) $details['item_id'] : null,
            'order_id' => isset($details['order_id']) ? (string) $details['order_id'] : null,
            'provider' => $details['provider'] ?? null,
            'ticket_number' => $details['ticket_number'] ?? null,
            'report_reference' => $details['report_reference'] ?? null,
            'zone_id' => $details['zone_id'] ?? null,
            'zone_name' => $details['zone_name'] ?? null,
            'duration_id' => $details['duration_id'] ?? null,
            'duration_label' => $details['duration_label'] ?? null,
            'policy_date_from' => $details['policy_date_from'] ?? null,
            'policy_date_to' => $details['policy_date_to'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildEsimDetailsSummary(SyncBooknowOrderDTO $data): array
    {
        $firstItem = $data->items[0] ?? [];
        $itemDetails = is_array($firstItem['item_details'] ?? null) ? $firstItem['item_details'] : [];

        return [
            'provider_status' => $data->status,
            'product_type' => 'esim',
            'title' => $firstItem['title'] ?? null,
            'quantity' => $firstItem['quantity'] ?? 1,
            'country' => $itemDetails['country'] ?? null,
            'data' => $itemDetails['data'] ?? null,
            'validity_days' => $itemDetails['validity_days'] ?? null,
            'iccid' => $itemDetails['iccid'] ?? null,
            'activation_code' => $itemDetails['activation_code'] ?? null,
            'qr' => $itemDetails['qr'] ?? null,
            'contact' => $data->contact,
            'payment' => $data->payment,
            'metadata' => $data->metadata,
            'provider_order_number' => $data->orderNumber(),
            'items' => $data->items,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildInsuranceDetailsSummary(SyncBooknowOrderDTO $data): array
    {
        $firstItem = $data->items[0] ?? [];
        $itemDetails = is_array($firstItem['item_details'] ?? null) ? $firstItem['item_details'] : [];

        return [
            'provider_status' => $data->status,
            'product_type' => 'insurance',
            'product_subtype' => $firstItem['product_subtype'] ?? null,
            'title' => $firstItem['title'] ?? null,
            'quantity' => $firstItem['quantity'] ?? 1,
            'item_id' => isset($itemDetails['item_id']) ? (string) $itemDetails['item_id'] : null,
            'provider' => $itemDetails['provider'] ?? null,
            'ticket_number' => $itemDetails['ticket_number'] ?? null,
            'report_reference' => $itemDetails['report_reference'] ?? null,
            'zone_id' => $itemDetails['zone_id'] ?? null,
            'zone_name' => $itemDetails['zone_name'] ?? null,
            'duration_id' => $itemDetails['duration_id'] ?? null,
            'duration_label' => $itemDetails['duration_label'] ?? null,
            'policy_date_from' => $itemDetails['policy_date_from'] ?? null,
            'policy_date_to' => $itemDetails['policy_date_to'] ?? null,
            'contact' => $data->contact,
            'payment' => $data->payment,
            'metadata' => $data->metadata,
            'provider_order_number' => $data->orderNumber(),
            'items' => $data->items,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildHotelDetailsSummary(SyncBooknowOrderDTO $data): array
    {
        $firstItem = $data->items[0] ?? [];
        $itemDetails = is_array($firstItem['item_details'] ?? null) ? $firstItem['item_details'] : [];
        $guests = $data->rawPayload['guests'] ?? $data->passengers;

        return [
            'provider_status' => $data->status,
            'product_type' => 'hotel',
            'title' => $firstItem['title'] ?? ($itemDetails['hotel_name'] ?? null),
            'quantity' => $firstItem['quantity'] ?? 1,
            'hotel_id' => isset($itemDetails['hotel_id']) ? (string) $itemDetails['hotel_id'] : null,
            'hotel_name' => $itemDetails['hotel_name'] ?? null,
            'city_id' => isset($itemDetails['city_id']) ? (string) $itemDetails['city_id'] : null,
            'city_name' => $itemDetails['city_name'] ?? null,
            'country' => $itemDetails['country'] ?? null,
            'source' => $itemDetails['source'] ?? null,
            'offer_id' => isset($itemDetails['offer_id']) ? (string) $itemDetails['offer_id'] : null,
            'room_name' => $itemDetails['room_name'] ?? null,
            'room_type' => $itemDetails['room_type'] ?? null,
            'board' => $itemDetails['board'] ?? null,
            'check_in' => $itemDetails['check_in'] ?? null,
            'check_out' => $itemDetails['check_out'] ?? null,
            'nights' => $itemDetails['nights'] ?? null,
            'rooms' => $itemDetails['rooms'] ?? null,
            'adults' => $itemDetails['adults'] ?? null,
            'children' => $itemDetails['children'] ?? null,
            'guests_count' => $itemDetails['guests_count'] ?? null,
            'stars' => $itemDetails['stars'] ?? null,
            'address' => $itemDetails['address'] ?? null,
            'image_url' => $itemDetails['image_url'] ?? null,
            'guests' => is_array($guests) ? $guests : [],
            'contact' => $data->contact,
            'payment' => $data->payment,
            'metadata' => $data->metadata,
            'provider_order_number' => $data->orderNumber(),
            'booking_reference' => $data->providerBooking['booking_reference'] ?? $data->orderNumber(),
            'items' => $data->items,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildResponsePayload(SyncBooknowOrderDTO $data): array
    {
        $guests = $data->rawPayload['guests'] ?? $data->passengers;

        return [
            'id' => $data->externalBookingId(),
            'number' => null,
            'provider_order_number' => $data->orderNumber(),
            'status' => $data->status,
            'internal_status' => BooknowOrderStatusMapper::toInternal($data->status),
            'grand_total' => $data->grandTotal,
            'currency' => $data->currency,
            'contact' => $data->contact,
            'guests' => is_array($guests) ? $guests : [],
            'passengers' => $data->passengers,
            'items' => $data->items,
            'payment' => $data->payment,
            'provider_booking' => [
                'booking_id' => $data->externalBookingId(),
                'provider' => $data->providerName(),
                'order_id' => isset($data->providerBooking['order_id'])
                    ? (string) $data->providerBooking['order_id']
                    : null,
                'order_number' => $data->orderNumber(),
                'booking_reference' => $data->providerBooking['booking_reference'] ?? $data->orderNumber(),
            ],
            'booking_flight_data' => $data->bookingFlightData,
            'metadata' => $data->metadata,
        ];
    }

    private function refreshResponsePayload(Order $order, SyncBooknowOrderDTO $data): Order
    {
        $responsePayload = $this->buildResponsePayload($data);
        $responsePayload['number'] = $order->booking_reference;
        $responsePayload['internal_status'] = $order->status;

        $order->forceFill([
            'response_payload' => $responsePayload,
        ])->save();

        return $order->refresh();
    }

    private function dispatchAfterCommit(callable $callback): void
    {
        DB::afterCommit($callback);
    }
}
