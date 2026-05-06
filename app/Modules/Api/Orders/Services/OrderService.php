<?php

namespace App\Modules\Api\Orders\Services;

use App\Models\Order;
use App\Models\User;
use App\Modules\Api\DTO\CreateOrderDTO;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderService
{
    public function __construct(
        private readonly BookingProviderService $bookingProviderService,
    ) {
    }

    /**
     * Create a new order for the supplied customer.
     */
    public function createForCustomer(User $customer, CreateOrderDTO $data): Order
    {
        $order = DB::transaction(function () use ($customer, $data): Order {
            return Order::query()->create([
                'customer_id' => $customer->id,
                'provider_name' => $data->providerName,
                'booking_reference' => $this->generateBookingReference(),
                'status' => Order::STATUS_PENDING,
                'currency' => $data->currency,
                'total_amount' => $data->totalAmount,
                'request_payload' => $data->requestPayload,
                'response_payload' => null,
                'error_message' => null,
            ]);
        });

        try {
            $responsePayload = $this->bookingProviderService->createBooking($order);

            $order->forceFill([
                'external_booking_id' => $responsePayload['external_booking_id'],
                'booking_reference' => $responsePayload['booking_reference'] ?? $order->booking_reference,
                'status' => Order::STATUS_CONFIRMED,
                'response_payload' => $responsePayload,
                'error_message' => null,
            ])->save();
        } catch (\Throwable $exception) {
            report($exception);

            $order->forceFill([
                'status' => Order::STATUS_FAILED,
                'response_payload' => [
                    'provider' => $order->provider_name,
                    'failed_at' => now()->toIso8601String(),
                ],
                'error_message' => $exception->getMessage(),
            ])->save();
        }

        return $order->refresh()->load('customer');
    }

    /**
     * Paginate the authenticated customer's orders.
     */
    public function paginateForCustomer(User $customer, int $perPage = 10): LengthAwarePaginator
    {
        return Order::query()
            ->whereBelongsTo($customer, 'customer')
            ->latest('id')
            ->paginate($perPage);
    }

    /**
     * Load the supplied order with its customer relation.
     */
    public function get(Order $order): Order
    {
        return $order->loadMissing('customer');
    }

    /**
     * Paginate all orders for the admin area.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginateForAdmin(array $filters, int $perPage = 12): LengthAwarePaginator
    {
        return Order::query()
            ->with('customer')
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Update the order status from the admin area.
     */
    public function updateStatus(Order $order, string $status): Order
    {
        $order->forceFill([
            'status' => $status,
            'error_message' => $status === Order::STATUS_FAILED
                ? ($order->error_message ?: 'Marked as failed by the operations team.')
                : null,
        ])->save();

        return $order->refresh()->load('customer');
    }

    /**
     * Build pagination metadata for API responses.
     *
     * @return array<string, mixed>
     */
    public function paginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }

    /**
     * Get the admin filter options for order statuses.
     *
     * @return array<int, array{name: string, label: string}>
     */
    public function statusOptions(): array
    {
        return array_map(
            fn (string $status): array => [
                'name' => $status,
                'label' => Str::of($status)->replace('_', ' ')->title()->toString(),
            ],
            Order::statuses(),
        );
    }

    /**
     * Get the admin status options allowed for manual updates.
     *
     * @return array<int, array{name: string, label: string}>
     */
    public function adminStatusOptions(): array
    {
        return array_map(
            fn (string $status): array => [
                'name' => $status,
                'label' => Str::of($status)->replace('_', ' ')->title()->toString(),
            ],
            Order::adminUpdatableStatuses(),
        );
    }

    /**
     * Generate an internal booking reference.
     */
    private function generateBookingReference(): string
    {
        return 'BK-'.now()->format('Ymd').'-'.Str::upper(Str::random(8));
    }
}