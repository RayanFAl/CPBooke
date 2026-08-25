<?php

namespace App\Modules\Api\Orders\Services;

use App\Models\AuditLog;
use App\Models\FinancialTransaction;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\User;
use App\Modules\Api\DTO\CreateOrderDTO;
use App\Modules\Audit\Services\AuditRecorder;
use App\Modules\Orders\Events\OrderCancelled as OrderCancelledEvent;
use App\Modules\Orders\Events\OrderCompleted as OrderCompletedEvent;
use App\Modules\Orders\Events\OrderConfirmed as OrderConfirmedEvent;
use App\Modules\Orders\Events\OrderCreated as OrderCreatedEvent;
use App\Modules\Orders\Events\PaymentFailed as PaymentFailedEvent;
use App\Modules\Orders\Events\PaymentSucceeded as PaymentSucceededEvent;
use App\Modules\Orders\Events\RefundFailed as RefundFailedEvent;
use App\Modules\Orders\Events\RefundInitiated as RefundInitiatedEvent;
use App\Modules\Orders\Events\RefundIssued as RefundIssuedEvent;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderService
{
    private const ORDER_NUMBER_PREFIX = 'CP';

    public function __construct(
        private readonly BookingProviderService $bookingProviderService,
        private readonly AuditRecorder $auditRecorder,
    ) {}

    /**
     * Create a new order for the supplied customer.
     */
    public function createForCustomer(User $customer, CreateOrderDTO $data): Order
    {
        $order = DB::transaction(function () use ($customer, $data): Order {
            $order = Order::query()->create([
                'customer_id' => $customer->id,
                'provider_name' => $data->providerName,
                'booking_reference' => null,
                'status' => Order::STATUS_PENDING_PAYMENT,
                'payment_status' => Order::PAYMENT_STATUS_UNPAID,
                'service_type' => $data->serviceType,
                'details' => $data->details,
                'currency' => $data->currency,
                'total_amount' => $data->totalAmount,
                'internal_notes' => null,
                'request_payload' => $data->requestPayload,
                'response_payload' => null,
                'error_message' => null,
            ]);

            $this->recordFinancialTransactionOnce(
                $order,
                FinancialTransaction::TYPE_PAYMENT,
                FinancialTransaction::SOURCE_ORDER_CREATION,
            );

            $order = $this->assignCpbookeOrderNumber($order);

            $this->dispatchAfterCommit(fn () => event(new OrderCreatedEvent($order->fresh()->load('customer'))));

            return $order;
        });

        return $order->refresh()->load('customer');
    }

    public function assignCpbookeOrderNumber(Order $order): Order
    {
        if (! empty($order->booking_reference)) {
            return $order;
        }

        $orderNumber = $this->generateCpbookeOrderNumber((int) $order->id);

        while (
            Order::query()
                ->where('booking_reference', $orderNumber)
                ->whereKeyNot($order->id)
                ->exists()
        ) {
            $orderNumber = $this->generateCpbookeOrderNumber((int) $order->id + random_int(1, 999));
        }

        $order->forceFill([
            'booking_reference' => $orderNumber,
        ])->save();

        return $order->refresh();
    }

    public function generateCpbookeOrderNumber(int $orderId): string
    {
        $digits = str_pad((string) ($orderId % 10000), 4, '0', STR_PAD_LEFT);
        $suffix = chr(65 + ($orderId % 26)).chr(65 + (intdiv($orderId, 26) % 26));

        return self::ORDER_NUMBER_PREFIX.$digits.$suffix;
    }

    /**
     * Paginate the authenticated customer's orders.
     */
    public function paginateForCustomer(User $customer, int $perPage = 10, ?string $productType = null): LengthAwarePaginator
    {
        return Order::query()
            ->whereBelongsTo($customer, 'customer')
            ->with('hotelReview')
            ->when(
                $productType !== null && $productType !== '',
                fn ($query) => $query->where('service_type', $productType),
            )
            ->latest('id')
            ->paginate($perPage);
    }

    /**
     * Load the supplied order with its customer relation.
     */
    public function get(Order $order): Order
    {
        return $order->loadMissing(['customer', 'hotelReview']);
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
            ->when($filters['search'] ?? null, fn ($query, $search) => $this->applyAdminSearch($query, $search))
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Apply admin order list search filters.
     *
     * @param  Builder<Order>  $query
     * @return Builder<Order>
     */
    private function applyAdminSearch($query, ?string $search)
    {
        if ($search === null || trim($search) === '') {
            return $query;
        }

        $search = trim($search);

        return $query->where(function ($searchQuery) use ($search): void {
            if (ctype_digit($search)) {
                $searchQuery->orWhere('id', (int) $search);
            }

            $searchQuery
                ->orWhere('booking_reference', 'like', $search.'%')
                ->orWhere('external_booking_id', 'like', $search.'%')
                ->orWhere('provider_name', 'like', $search.'%')
                ->orWhere('details->pnr', $search)
                ->orWhere('details->provider_order_number', $search)
                ->orWhereHas('customer', function ($customerQuery) use ($search): void {
                    $customerQuery
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhere('full_name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
        });
    }

    /**
     * Update the order status from the admin area.
     */
    public function updateStatus(Order $order, string $status): Order
    {
        return $this->updateStatusByActor($order, $status, null);
    }

    /**
     * Update the order status from the admin area with actor tracking.
     */
    public function updateStatusByActor(Order $order, string $status, ?User $actor): Order
    {
        $originalStatus = $order->status;

        DB::transaction(function () use ($order, $status, $actor, $originalStatus): void {
            $this->transitionOrder($order, $status, $actor, [
                'error_message' => $status === Order::STATUS_FAILED
                    ? ($order->error_message ?: 'Marked as failed by the operations team.')
                    : null,
            ]);

            if (
                in_array($status, [Order::STATUS_CONFIRMED, Order::STATUS_TICKETED], true)
                && $originalStatus !== $status
            ) {
                $this->dispatchAfterCommit(fn () => event(new OrderConfirmedEvent($order->fresh()->load('customer'))));
            }

            if ($status === Order::STATUS_FAILED && $originalStatus !== Order::STATUS_FAILED) {
                $reason = $order->error_message ?: 'Marked as failed by the operations team.';
                $this->dispatchAfterCommit(fn () => event(new PaymentFailedEvent($order->fresh()->load('customer'), $reason)));
            }

            if ($status === Order::STATUS_CANCELLED && $originalStatus !== Order::STATUS_CANCELLED) {
                $source = $actor ? OrderCancelledEvent::SOURCE_ADMIN : OrderCancelledEvent::SOURCE_CUSTOMER;
                $this->dispatchAfterCommit(fn () => event(new OrderCancelledEvent(
                    $order->fresh()->load('customer'),
                    $source,
                )));
            }

            if ($status === Order::STATUS_COMPLETED && $originalStatus !== Order::STATUS_COMPLETED) {
                $this->dispatchAfterCommit(fn () => event(new OrderCompletedEvent($order->fresh()->load('customer'))));
            }
        });

        if ($originalStatus === $status) {
            return $order->refresh()->load('customer');
        }

        return $order->refresh()->load('customer');
    }

    /**
     * Update the admin-only internal notes for the supplied order.
     */
    public function updateInternalNotes(Order $order, ?string $internalNotes, ?User $actor): Order
    {
        $this->applyTrackedChanges($order, [
            'internal_notes' => $internalNotes,
        ], $actor);

        return $order->refresh()->load('customer');
    }

    /**
     * Update the order payment status from the admin area with actor tracking.
     */
    public function updatePaymentStatusByActor(Order $order, string $paymentStatus, ?User $actor, array $context = []): Order
    {
        if (! $order->canUpdatePaymentStatusTo($paymentStatus)) {
            throw ValidationException::withMessages([
                'payment_status' => 'The selected payment status is invalid.',
            ]);
        }

        $originalPaymentStatus = $order->payment_status;

        DB::transaction(function () use ($order, $paymentStatus, $actor, $originalPaymentStatus, $context): void {
            $transactionType = $this->transactionTypeForPaymentStatusChange(
                $originalPaymentStatus,
                $paymentStatus,
            );

            if ($transactionType !== null) {
                $this->recordFinancialTransaction(
                    $order,
                    $transactionType,
                    (float) ($context['amount'] ?? $order->total_amount),
                    $context['source'] ?? $this->transactionSourceForPaymentStatus($paymentStatus),
                    $actor,
                    [
                        'source_id' => Arr::get($context, 'source_id'),
                        'reason' => Arr::get($context, 'reason'),
                        'metadata' => Arr::get($context, 'metadata', []),
                        'status' => Arr::get($context, 'status', FinancialTransaction::STATUS_EXECUTED),
                    ],
                );
            }

            $this->syncDerivedPaymentStatus($order, $actor, $paymentStatus ?: null, $originalPaymentStatus);
        });

        return $order->refresh()->load('customer');
    }

    public function recordFinancialTransaction(Order $order, string $type, float $amount, string $source, ?User $actor = null, array $context = []): FinancialTransaction
    {
        $attributes = [
            'order_id' => $order->id,
            'type' => $type,
            'status' => $context['status'] ?? FinancialTransaction::STATUS_EXECUTED,
            'amount' => number_format($amount, 2, '.', ''),
            'currency' => $order->currency,
            'performed_by_type' => $actor ? FinancialTransaction::PERFORMED_BY_TYPE_USER : null,
            'performed_by_id' => $actor?->id,
            'source' => $source,
            'source_id' => $context['source_id'] ?? null,
            'reason' => $context['reason'] ?? null,
            'metadata' => $context['metadata'] ?? [],
        ];

        if ($this->financialTransactionLedgerColumnsExist()) {
            $mapping = FinancialTransaction::ledgerMappingForType($type);

            $attributes = [
                ...$attributes,
                'debit_account' => $mapping['debit_account'],
                'credit_account' => $mapping['credit_account'],
                'reference_type' => FinancialTransaction::REFERENCE_TYPE_ORDER,
                'reference_id' => $order->id,
            ];
        }

        $transaction = FinancialTransaction::query()->create($attributes);

        $this->dispatchFinancialTransactionEvent($order, $transaction, $source);

        return $transaction;
    }

    public function syncDerivedPaymentStatus(Order $order, ?User $actor = null, ?string $fallbackStatus = null, ?string $originalPaymentStatus = null): void
    {
        $order->unsetRelation('transactions');
        $order->load('transactions');

        $hasPaymentBase = $order->transactions->contains(fn (FinancialTransaction $transaction): bool => in_array($transaction->type, [
            FinancialTransaction::TYPE_PAYMENT,
            FinancialTransaction::TYPE_REVERSAL,
        ], true));

        $derivedPaymentStatus = (! $hasPaymentBase || $order->transactions->isEmpty())
            ? ($fallbackStatus ?? $order->payment_status)
            : $order->derivePaymentStatus();

        $currentPaymentStatus = $originalPaymentStatus ?? $order->payment_status;

        if ($currentPaymentStatus === $derivedPaymentStatus) {
            return;
        }

        $this->applyTrackedChanges($order, [
            'payment_status' => $derivedPaymentStatus,
        ], $actor);
    }

    public function recordOperationalHistory(Order $order, ?User $actor, string $action, ?string $field, mixed $oldValue, mixed $newValue): void
    {
        OrderHistory::query()->create([
            'order_id' => $order->id,
            'user_id' => $actor?->id,
            'action' => $action,
            'field' => $field,
            'old_value' => $this->normalizeHistoryValue($oldValue),
            'new_value' => $this->normalizeHistoryValue($newValue),
            'created_at' => now(),
        ]);
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
    public function adminStatusOptions(Order $order): array
    {
        return array_map(
            fn (string $status): array => [
                'name' => $status,
                'label' => Str::of($status)->replace('_', ' ')->title()->toString(),
            ],
            $order->availableStatusTransitions(),
        );
    }

    /**
     * Get the admin payment status options.
     *
     * @return array<int, array{name: string, label: string}>
     */
    public function paymentStatusOptions(): array
    {
        return array_map(
            fn (string $status): array => [
                'name' => $status,
                'label' => Str::of($status)->replace('_', ' ')->title()->toString(),
            ],
            Order::paymentStatuses(),
        );
    }

    /**
     * Apply a tracked lifecycle transition.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function transitionOrder(Order $order, string $status, ?User $actor = null, array $attributes = []): void
    {
        if (! $order->canTransitionTo($status)) {
            throw ValidationException::withMessages([
                'status' => 'The selected status transition is invalid for the current order state.',
            ]);
        }

        $this->applyTrackedChanges($order, [
            ...$attributes,
            'status' => $status,
        ], $actor);
    }

    /**
     * Persist tracked order changes and write history entries.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function applyTrackedChanges(Order $order, array $attributes, ?User $actor = null): void
    {
        $trackedFields = ['status', 'payment_status', 'internal_notes'];
        $originalValues = $order->only($trackedFields);

        $order->forceFill($attributes);

        $dirtyTrackedFields = array_values(array_filter(
            $trackedFields,
            fn (string $field): bool => $order->isDirty($field),
        ));

        if (! $order->isDirty()) {
            return;
        }

        $order->save();

        if ($dirtyTrackedFields === []) {
            return;
        }

        $timestamp = now();

        $entries = array_map(function (string $field) use ($order, $originalValues, $actor, $timestamp): array {
            return [
                'order_id' => $order->id,
                'user_id' => $actor?->id,
                'action' => $this->historyActionForField($field),
                'field' => $field,
                'old_value' => $this->normalizeHistoryValue($originalValues[$field] ?? null),
                'new_value' => $this->normalizeHistoryValue($order->getAttribute($field)),
                'created_at' => $timestamp,
            ];
        }, $dirtyTrackedFields);

        OrderHistory::query()->insert($entries);

        foreach ($dirtyTrackedFields as $field) {
            $this->auditRecorder->success(
                AuditLog::MODULE_ORDERS,
                'order.'.$field.'_updated',
                'Order #'.$order->id.' '.$field.' updated',
                AuditLog::ENTITY_ORDER,
                $order->id,
                $actor,
                [$field => $this->normalizeHistoryValue($originalValues[$field] ?? null)],
                [$field => $this->normalizeHistoryValue($order->getAttribute($field))],
            );
        }
    }

    /**
     * Resolve the history action label for the tracked field.
     */
    private function historyActionForField(string $field): string
    {
        return match ($field) {
            'status' => 'status_changed',
            'payment_status' => 'payment_status_changed',
            'internal_notes' => 'internal_notes_updated',
            default => 'order_updated',
        };
    }

    /**
     * Resolve the financial transaction type to record for a payment status change.
     */
    private function transactionTypeForPaymentStatusChange(string $originalPaymentStatus, string $newPaymentStatus): ?string
    {
        if ($originalPaymentStatus === $newPaymentStatus) {
            return null;
        }

        return match ($newPaymentStatus) {
            Order::PAYMENT_STATUS_PAID => FinancialTransaction::TYPE_PAYMENT,
            Order::PAYMENT_STATUS_REFUNDED,
            Order::PAYMENT_STATUS_PARTIALLY_REFUNDED => FinancialTransaction::TYPE_REFUND,
            default => null,
        };
    }

    /**
     * Resolve the transaction source to record for a payment status change.
     */
    private function transactionSourceForPaymentStatus(string $paymentStatus): string
    {
        return match ($paymentStatus) {
            Order::PAYMENT_STATUS_PAID => FinancialTransaction::SOURCE_PAYMENT_STATUS_PAID,
            Order::PAYMENT_STATUS_PARTIALLY_REFUNDED => FinancialTransaction::SOURCE_PAYMENT_STATUS_PARTIALLY_REFUNDED,
            Order::PAYMENT_STATUS_REFUNDED => FinancialTransaction::SOURCE_PAYMENT_STATUS_REFUNDED,
            default => 'payment_status_update',
        };
    }

    /**
     * Record a financial transaction once for the same order event.
     */
    public function recordFinancialTransactionOnce(Order $order, string $type, string $source, mixed $amountOverride = null): void
    {
        $defaults = [
            'amount' => number_format((float) ($amountOverride ?? $order->total_amount), 2, '.', ''),
            'currency' => $order->currency,
        ];

        if ($this->financialTransactionLedgerColumnsExist()) {
            $mapping = FinancialTransaction::ledgerMappingForType($type);

            $defaults = [
                ...$defaults,
                'debit_account' => $mapping['debit_account'],
                'credit_account' => $mapping['credit_account'],
                'reference_type' => FinancialTransaction::REFERENCE_TYPE_ORDER,
                'reference_id' => $order->id,
            ];
        }

        FinancialTransaction::query()->firstOrCreate(
            [
                'order_id' => $order->id,
                'type' => $type,
                'source' => $source,
            ],
            $defaults,
        );
    }

    /**
     * Determine whether the additive ledger columns are available.
     */
    private function financialTransactionLedgerColumnsExist(): bool
    {
        return Schema::hasColumns('financial_transactions', [
            'debit_account',
            'credit_account',
            'reference_type',
            'reference_id',
        ]);
    }

    /**
     * Normalize a history value before persistence.
     */
    private function normalizeHistoryValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return (string) $value;
    }

    private function dispatchFinancialTransactionEvent(Order $order, FinancialTransaction $transaction, string $source): void
    {
        if ($transaction->type === FinancialTransaction::TYPE_REFUND) {
            if (in_array($transaction->status, [FinancialTransaction::STATUS_REQUESTED, FinancialTransaction::STATUS_APPROVED], true)) {
                $this->dispatchAfterCommit(fn () => event(new RefundInitiatedEvent($order->fresh()->load('customer'), $transaction->fresh())));

                return;
            }

            if ($transaction->status === FinancialTransaction::STATUS_FAILED) {
                $this->dispatchAfterCommit(fn () => event(new RefundFailedEvent(
                    $order->fresh()->load('customer'),
                    $transaction->fresh(),
                    $transaction->reason,
                )));

                return;
            }
        }

        if ($transaction->status !== FinancialTransaction::STATUS_EXECUTED) {
            return;
        }

        if ($transaction->type === FinancialTransaction::TYPE_PAYMENT && $source === FinancialTransaction::SOURCE_PAYMENT_STATUS_PAID) {
            $this->dispatchAfterCommit(fn () => event(new PaymentSucceededEvent($order->fresh()->load('customer'), $transaction->fresh())));

            return;
        }

        if ($transaction->type === FinancialTransaction::TYPE_REFUND) {
            $this->dispatchAfterCommit(fn () => event(new RefundIssuedEvent($order->fresh()->load('customer'), $transaction->fresh())));
        }
    }

    private function dispatchAfterCommit(callable $callback): void
    {
        if (DB::transactionLevel() > 0) {
            DB::afterCommit($callback);

            return;
        }

        $callback();
    }
}
