<?php

namespace App\Modules\Api\Orders\Services;

use App\Models\FinancialTransaction;
use App\Models\Order;
use App\Models\SupportTicket;
use App\Models\User;
use App\Modules\Support\Services\SupportService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class OrderActionService
{
    private const ACTION_WORKFLOW_STATUSES = [
        FinancialTransaction::STATUS_REQUESTED,
        FinancialTransaction::STATUS_APPROVED,
        FinancialTransaction::STATUS_EXECUTED,
        FinancialTransaction::STATUS_FAILED,
        FinancialTransaction::STATUS_REVERSED,
    ];

    private const COMPENSATION_TYPES = [
        'wallet_credit',
        'manual_adjustment',
        'future_discount',
    ];

    public function __construct(
        private readonly OrderService $orderService,
        private readonly SupportService $supportService,
    ) {
    }

    /**
     * @return array<int, array{name: string, label: string, variant: string, requires_amount: bool}>
     */
    public function canViewSupportActions(?Order $order, ?User $actor): bool
    {
        if ($order === null || $actor === null) {
            return false;
        }

        return Gate::forUser($actor)->allows('support.view-order-actions');
    }

    public function availableSupportActions(?Order $order, ?User $actor = null): array
    {
        if (! $this->canViewSupportActions($order, $actor)) {
            return [];
        }

        $order->loadMissing('transactions');

        $actions = [];
        $netPaidAmount = $order->getNetPaidAmount();
        $refundedAmount = $order->getRefundedAmount();
        $paymentStatus = $order->derivePaymentStatus();

        if ($order->canTransitionTo(Order::STATUS_CANCELLED) && $this->canRunAction($actor, 'cancel')) {
            $actions[] = [
                'name' => 'cancel',
                'label' => 'Cancel Order',
                'variant' => 'danger',
                'requires_amount' => false,
                'permission' => 'support.cancel-order',
            ];
        }

        if ($netPaidAmount > 0 && $this->canRunAction($actor, 'full_refund')) {
            $actions[] = [
                'name' => 'full_refund',
                'label' => 'Full Refund',
                'variant' => 'danger',
                'requires_amount' => false,
                'permission' => 'support.full-refund',
                'available_amount' => number_format($netPaidAmount, 2, '.', ''),
            ];
        }

        // Partial refund is intentionally not offered in the support UI (V1 simplification).
        // Full refund + compensation cover the operational cases for now.

        if (in_array($paymentStatus, [Order::PAYMENT_STATUS_REFUNDED, Order::PAYMENT_STATUS_PARTIALLY_REFUNDED], true)
            && $refundedAmount > 0
            && $this->canRunAction($actor, 'reverse_refund')) {
            $actions[] = [
                'name' => 'reverse_refund',
                'label' => 'Reverse Refund',
                'variant' => 'secondary',
                'requires_amount' => false,
                'requires_internal_note' => true,
                'permission' => 'finance.reverse-refund',
                'available_amount' => number_format($refundedAmount, 2, '.', ''),
            ];
        }

        if ($this->canRunAction($actor, 'compensation')) {
            $actions[] = [
                'name' => 'compensation',
                'label' => 'Compensation',
                'variant' => 'secondary',
                'requires_amount' => true,
                'requires_compensation_type' => true,
                'permission' => 'support.partial-refund',
                'compensation_types' => self::COMPENSATION_TYPES,
            ];
        }

        return $actions;
    }

    public function cancelFromSupport(SupportTicket $ticket, User $actor, string $reason): Order
    {
        $order = $this->linkedOrder($ticket);
        $this->ensureActionAllowed($order, $actor, 'cancel');

        return DB::transaction(function () use ($ticket, $actor, $reason): Order {
            $order = $this->linkedOrder($ticket);
            $originalStatus = $order->status;

            $updatedOrder = $this->orderService->updateStatusByActor($order, Order::STATUS_CANCELLED, $actor);

            $this->supportService->recordOperationalHistory(
                $ticket,
                $actor->id,
                'order_cancelled',
                'order_status',
                $originalStatus,
                Order::STATUS_CANCELLED,
            );

            $this->supportService->addInternalMessage(
                $ticket,
                $actor->id,
                sprintf(
                    'Order %s was cancelled manually. Reason: %s',
                    $updatedOrder->booking_reference ?: ('#'.$updatedOrder->id),
                    trim($reason),
                ),
            );

            $this->orderService->recordOperationalHistory(
                $updatedOrder,
                $actor,
                'support_order_cancelled',
                'status',
                $originalStatus,
                Order::STATUS_CANCELLED,
            );

            return $updatedOrder;
        });
    }

    public function fullRefundFromSupport(SupportTicket $ticket, User $actor, string $reason): Order
    {
        $order = $this->linkedOrder($ticket);
        $this->ensureActionAllowed($order, $actor, 'full_refund');

        return DB::transaction(function () use ($ticket, $actor, $reason): Order {
            $order = $this->linkedOrder($ticket);
            $order->loadMissing('transactions');

            $amount = $order->getNetPaidAmount();

            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'order' => 'There is no refundable balance remaining on this order.',
                ]);
            }

            $originalPaymentStatus = $order->derivePaymentStatus();

            $this->orderService->recordFinancialTransaction(
                $order,
                FinancialTransaction::TYPE_REFUND,
                $amount,
                FinancialTransaction::SOURCE_SUPPORT_TICKET,
                $actor,
                [
                    'source_id' => $ticket->id,
                    'reason' => trim($reason),
                    'metadata' => $this->workflowMetadata('full_refund', [
                        'ticket_id' => $ticket->id,
                        'mode' => 'full',
                    ]),
                ],
            );

            $this->orderService->syncDerivedPaymentStatus($order, $actor, Order::PAYMENT_STATUS_REFUNDED, $originalPaymentStatus);

            $updatedOrder = $order->refresh()->load(['customer', 'transactions']);

            $this->supportService->recordOperationalHistory(
                $ticket,
                $actor->id,
                'order_refunded',
                'payment_status',
                $originalPaymentStatus,
                Order::PAYMENT_STATUS_REFUNDED,
            );

            $this->supportService->addInternalMessage(
                $ticket,
                $actor->id,
                sprintf(
                    'Full refund of %s %s was applied manually. Reason: %s',
                    number_format($amount, 2, '.', ''),
                    $updatedOrder->currency,
                    trim($reason),
                ),
            );

            $this->orderService->recordOperationalHistory(
                $updatedOrder,
                $actor,
                'support_full_refund_applied',
                'refund_amount',
                null,
                number_format($amount, 2, '.', ''),
            );

            return $updatedOrder;
        });
    }

    public function partialRefundFromSupport(SupportTicket $ticket, User $actor, float $amount, string $reason): Order
    {
        $order = $this->linkedOrder($ticket);
        $this->ensureActionAllowed($order, $actor, 'partial_refund');

        return DB::transaction(function () use ($ticket, $actor, $amount, $reason): Order {
            $order = $this->linkedOrder($ticket);
            $order->loadMissing('transactions');

            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'The refund amount must be greater than zero.',
                ]);
            }

            $availableRefundAmount = $order->getNetPaidAmount();

            if ($amount > $availableRefundAmount) {
                throw ValidationException::withMessages([
                    'amount' => 'The partial refund amount exceeds the refundable balance.',
                ]);
            }

            if ($amount >= $availableRefundAmount) {
                throw ValidationException::withMessages([
                    'amount' => 'Use the full refund action when refunding the entire remaining balance.',
                ]);
            }

            $originalPaymentStatus = $order->derivePaymentStatus();

            $this->orderService->recordFinancialTransaction(
                $order,
                FinancialTransaction::TYPE_REFUND,
                $amount,
                FinancialTransaction::SOURCE_SUPPORT_TICKET,
                $actor,
                [
                    'source_id' => $ticket->id,
                    'reason' => trim($reason),
                    'metadata' => $this->workflowMetadata('partial_refund', [
                        'ticket_id' => $ticket->id,
                        'mode' => 'partial',
                    ]),
                ],
            );

            $this->orderService->syncDerivedPaymentStatus($order, $actor, Order::PAYMENT_STATUS_PARTIALLY_REFUNDED, $originalPaymentStatus);

            $updatedOrder = $order->refresh()->load(['customer', 'transactions']);

            $formattedAmount = number_format($amount, 2, '.', '');

            $this->supportService->recordOperationalHistory(
                $ticket,
                $actor->id,
                'order_partially_refunded',
                'payment_status',
                $originalPaymentStatus,
                Order::PAYMENT_STATUS_PARTIALLY_REFUNDED,
            );

            $this->supportService->recordOperationalHistory(
                $ticket,
                $actor->id,
                'refund_amount_recorded',
                'refund_amount',
                null,
                $formattedAmount,
            );

            $this->supportService->addInternalMessage(
                $ticket,
                $actor->id,
                sprintf(
                    'Partial refund of %s %s was applied manually. Reason: %s',
                    $formattedAmount,
                    $updatedOrder->currency,
                    trim($reason),
                ),
            );

            $this->orderService->recordOperationalHistory(
                $updatedOrder,
                $actor,
                'support_partial_refund_applied',
                'refund_amount',
                null,
                $formattedAmount,
            );

            return $updatedOrder;
        });
    }

    public function reverseRefundFromSupport(SupportTicket $ticket, User $actor, string $reason, string $internalNote): Order
    {
        $order = $this->linkedOrder($ticket);
        $this->ensureActionAllowed($order, $actor, 'reverse_refund');

        return DB::transaction(function () use ($ticket, $actor, $reason, $internalNote): Order {
            $order = $this->linkedOrder($ticket);
            $order->loadMissing('transactions');

            $reversibleAmount = $order->getRefundedAmount();

            if ($reversibleAmount <= 0) {
                throw ValidationException::withMessages([
                    'order' => 'This order has no refunded balance to reverse.',
                ]);
            }

            $originalPaymentStatus = $order->derivePaymentStatus();

            $this->orderService->recordFinancialTransaction(
                $order,
                FinancialTransaction::TYPE_REVERSAL,
                $reversibleAmount,
                FinancialTransaction::SOURCE_SUPPORT_TICKET,
                $actor,
                [
                    'source_id' => $ticket->id,
                    'reason' => trim($reason),
                    'metadata' => $this->workflowMetadata('reverse_refund', [
                        'ticket_id' => $ticket->id,
                        'internal_note' => trim($internalNote),
                    ]),
                ],
            );

            $this->orderService->syncDerivedPaymentStatus($order, $actor, Order::PAYMENT_STATUS_PAID, $originalPaymentStatus);

            $updatedOrder = $order->refresh()->load(['customer', 'transactions']);

            $formattedAmount = number_format($reversibleAmount, 2, '.', '');

            $this->supportService->recordOperationalHistory(
                $ticket,
                $actor->id,
                'refund_reversed',
                'payment_status',
                $originalPaymentStatus,
                $updatedOrder->payment_status,
            );

            $this->supportService->recordOperationalHistory(
                $ticket,
                $actor->id,
                'refund_reversal_amount_recorded',
                'refund_reversal_amount',
                null,
                $formattedAmount,
            );

            $this->supportService->addInternalMessage(
                $ticket,
                $actor->id,
                sprintf(
                    'Refund reversal of %s %s was executed. Reason: %s. Internal note: %s',
                    $formattedAmount,
                    $updatedOrder->currency,
                    trim($reason),
                    trim($internalNote),
                ),
            );

            $this->orderService->recordOperationalHistory(
                $updatedOrder,
                $actor,
                'support_refund_reversed',
                'payment_status',
                $originalPaymentStatus,
                $updatedOrder->payment_status,
            );

            return $updatedOrder;
        });
    }

    public function compensationFromSupport(SupportTicket $ticket, User $actor, float $amount, string $reason, string $compensationType): Order
    {
        $order = $this->linkedOrder($ticket);
        $this->ensureActionAllowed($order, $actor, 'compensation');

        return DB::transaction(function () use ($ticket, $actor, $amount, $reason, $compensationType): Order {
            $order = $this->linkedOrder($ticket);

            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'The compensation amount must be greater than zero.',
                ]);
            }

            if (! in_array($compensationType, self::COMPENSATION_TYPES, true)) {
                throw ValidationException::withMessages([
                    'compensation_type' => 'The selected compensation type is invalid.',
                ]);
            }

            $this->orderService->recordFinancialTransaction(
                $order,
                FinancialTransaction::TYPE_COMPENSATION,
                $amount,
                FinancialTransaction::SOURCE_SUPPORT_TICKET,
                $actor,
                [
                    'source_id' => $ticket->id,
                    'reason' => trim($reason),
                    'metadata' => $this->workflowMetadata('compensation', [
                        'ticket_id' => $ticket->id,
                        'compensation_type' => $compensationType,
                    ]),
                ],
            );

            $updatedOrder = $order->refresh()->load(['customer', 'transactions']);
            $formattedAmount = number_format($amount, 2, '.', '');

            $this->supportService->recordOperationalHistory(
                $ticket,
                $actor->id,
                'compensation_added',
                'compensation_amount',
                null,
                $formattedAmount,
            );

            $this->supportService->recordOperationalHistory(
                $ticket,
                $actor->id,
                'compensation_type_selected',
                'compensation_type',
                null,
                $compensationType,
            );

            $this->supportService->addInternalMessage(
                $ticket,
                $actor->id,
                sprintf(
                    'Compensation of %s %s was added as %s. Reason: %s',
                    $formattedAmount,
                    $updatedOrder->currency,
                    str_replace('_', ' ', $compensationType),
                    trim($reason),
                ),
            );

            $this->orderService->recordOperationalHistory(
                $updatedOrder,
                $actor,
                'support_compensation_added',
                'compensation_amount',
                null,
                $formattedAmount,
            );

            return $updatedOrder;
        });
    }

    public static function compensationTypes(): array
    {
        return self::COMPENSATION_TYPES;
    }

    private function ensureActionAllowed(Order $order, User $actor, string $action): void
    {
        if (! $this->canViewSupportActions($order, $actor) || ! $this->isActionAvailable($order, $actor, $action)) {
            throw new AuthorizationException('You are not authorized to run this support order action.');
        }
    }

    private function isActionAvailable(Order $order, User $actor, string $action): bool
    {
        return collect($this->availableSupportActions($order, $actor))
            ->contains(fn (array $candidate): bool => $candidate['name'] === $action);
    }

    private function canRunAction(User $actor, string $action): bool
    {
        return match ($action) {
            'cancel' => Gate::forUser($actor)->allows('support.cancel-order'),
            'full_refund' => Gate::forUser($actor)->allows('support.full-refund'),
            'partial_refund', 'compensation' => Gate::forUser($actor)->allows('support.partial-refund'),
            'reverse_refund' => Gate::forUser($actor)->allows('finance.reverse-refund'),
            default => false,
        };
    }

    private function workflowMetadata(string $action, array $extra = []): array
    {
        return [
            'action' => $action,
            'workflow_status' => FinancialTransaction::STATUS_EXECUTED,
            'available_workflow_statuses' => self::ACTION_WORKFLOW_STATUSES,
            ...$extra,
        ];
    }

    private function linkedOrder(SupportTicket $ticket): Order
    {
        $ticket->loadMissing('order');

        if ($ticket->order === null) {
            throw ValidationException::withMessages([
                'order' => 'This support ticket is not linked to an order.',
            ]);
        }

        return $ticket->order;
    }
}