<?php

namespace App\Modules\Api\Orders\Services;

use App\Models\FinancialTransaction;
use App\Models\Order;
use App\Models\User;
use App\Modules\Api\DTO\SyncBooknowOrderDTO;
use App\Modules\Orders\Events\OrderConfirmed as OrderConfirmedEvent;
use App\Modules\Orders\Events\OrderCreated as OrderCreatedEvent;
use App\Support\Orders\BooknowOrderStatusMapper;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BooknowOrderSyncService
{
    public function __construct(
        private readonly OrderService $orderService,
    ) {
    }

    /**
     * Idempotent upsert keyed by provider booking id. Customer is always taken from the auth token.
     *
     * @return array{order: Order, created: bool}
     */
    public function upsert(User $authenticatedUser, SyncBooknowOrderDTO $data): array
    {
        $customer = $this->resolveCustomerFromToken($authenticatedUser);
        $bookingId = $data->externalBookingId();

        if ($bookingId === '') {
            throw ValidationException::withMessages([
                'provider_booking.booking_id' => 'The provider booking id is required for sync.',
            ]);
        }

        return DB::transaction(function () use ($customer, $data, $bookingId): array {
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
            default => Order::SERVICE_TYPE_FLIGHT,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDetailsSummary(SyncBooknowOrderDTO $data): array
    {
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
    private function buildResponsePayload(SyncBooknowOrderDTO $data): array
    {
        return [
            'id' => $data->externalBookingId(),
            'number' => null,
            'provider_order_number' => $data->orderNumber(),
            'status' => $data->status,
            'internal_status' => BooknowOrderStatusMapper::toInternal($data->status),
            'grand_total' => $data->grandTotal,
            'currency' => $data->currency,
            'contact' => $data->contact,
            'passengers' => $data->passengers,
            'items' => $data->items,
            'payment' => $data->payment,
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
