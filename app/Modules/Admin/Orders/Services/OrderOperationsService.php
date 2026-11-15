<?php

namespace App\Modules\Admin\Orders\Services;

use App\Models\Order;
use App\Models\SupportTicket;
use App\Models\User;
use App\Modules\Api\Orders\Services\OrderActionService;
use App\Modules\Approvals\Services\SupportOrderApprovalBridge;
use App\Modules\Support\Services\SupportService;
use Illuminate\Validation\ValidationException;

class OrderOperationsService
{
    public function __construct(
        private readonly SupportService $supportService,
        private readonly SupportOrderApprovalBridge $supportOrderApprovalBridge,
        private readonly OrderActionService $orderActionService,
    ) {
    }

    /**
     * @return array{executed: bool, approval: \App\Models\Approval|null, result: array<string, mixed>|null, ticket: SupportTicket}
     */
    public function cancel(Order $order, User $actor, string $reason): array
    {
        $ticket = $this->ensureTicket(
            $order,
            $actor,
            category: 'booking_change',
            subject: $this->subject($order, 'Cancellation request'),
            firstMessage: sprintf(
                'Staff cancellation requested for order %s. Reason: %s',
                $this->orderLabel($order),
                trim($reason),
            ),
        );

        $result = $this->supportOrderApprovalBridge->submitCancel($ticket, $actor, $reason);

        return [...$result, 'ticket' => $ticket];
    }

    /**
     * @return array{executed: bool, approval: \App\Models\Approval|null, result: array<string, mixed>|null, ticket: SupportTicket}
     */
    public function fullRefund(Order $order, User $actor, string $reason): array
    {
        $ticket = $this->ensureTicket(
            $order,
            $actor,
            category: 'refund_request',
            subject: $this->subject($order, 'Full refund request'),
            firstMessage: sprintf(
                'Staff full refund requested for order %s. Reason: %s',
                $this->orderLabel($order),
                trim($reason),
            ),
        );

        $result = $this->supportOrderApprovalBridge->submitFullRefund($ticket, $actor, $reason);

        return [...$result, 'ticket' => $ticket];
    }

    /**
     * @return array{executed: bool, approval: \App\Models\Approval|null, result: array<string, mixed>|null, ticket: SupportTicket}
     */
    public function partialRefund(Order $order, User $actor, float $amount, string $reason): array
    {
        $ticket = $this->ensureTicket(
            $order,
            $actor,
            category: 'refund_request',
            subject: $this->subject($order, 'Partial refund request'),
            firstMessage: sprintf(
                'Staff partial refund of %s requested for order %s. Reason: %s',
                number_format($amount, 2, '.', ''),
                $this->orderLabel($order),
                trim($reason),
            ),
        );

        $result = $this->supportOrderApprovalBridge->submitPartialRefund($ticket, $actor, $amount, $reason);

        return [...$result, 'ticket' => $ticket];
    }

    /**
     * @return array{order: Order, ticket: SupportTicket}
     */
    public function reverseRefund(Order $order, User $actor, string $reason, string $internalNote): array
    {
        $ticket = $this->ensureTicket(
            $order,
            $actor,
            category: 'refund_request',
            subject: $this->subject($order, 'Refund reversal'),
            firstMessage: sprintf(
                'Staff refund reversal requested for order %s. Reason: %s',
                $this->orderLabel($order),
                trim($reason),
            ),
        );

        $updatedOrder = $this->orderActionService->reverseRefundFromSupport(
            $ticket,
            $actor,
            $reason,
            $internalNote,
        );

        return [
            'order' => $updatedOrder,
            'ticket' => $ticket,
        ];
    }

    /**
     * @return array{order: Order, ticket: SupportTicket}
     */
    public function compensation(
        Order $order,
        User $actor,
        float $amount,
        string $reason,
        string $compensationType,
    ): array {
        $ticket = $this->ensureTicket(
            $order,
            $actor,
            category: 'refund_request',
            subject: $this->subject($order, 'Compensation'),
            firstMessage: sprintf(
                'Staff compensation of %s (%s) requested for order %s. Reason: %s',
                number_format($amount, 2, '.', ''),
                $compensationType,
                $this->orderLabel($order),
                trim($reason),
            ),
        );

        $updatedOrder = $this->orderActionService->compensationFromSupport(
            $ticket,
            $actor,
            $amount,
            $reason,
            $compensationType,
        );

        return [
            'order' => $updatedOrder,
            'ticket' => $ticket,
        ];
    }

    private function ensureTicket(
        Order $order,
        User $actor,
        string $category,
        string $subject,
        string $firstMessage,
    ): SupportTicket {
        $order->loadMissing('customer');

        if ($order->customer === null) {
            throw ValidationException::withMessages([
                'order' => 'This order has no linked customer account.',
            ]);
        }

        $existing = $this->supportService->findOpenTicketForOrder((int) $order->id);

        if ($existing !== null) {
            $this->supportService->addInternalMessage($existing, $actor->id, $firstMessage);

            return $existing->fresh(['order', 'user']) ?? $existing;
        }

        return $this->supportService->createTicket(
            [
                'user_id' => $order->customer_id,
                'order_id' => $order->id,
                'category' => $category,
                'priority' => 'high',
                'subject' => $subject,
                'assigned_to' => $actor->id,
                'status' => 'open',
            ],
            $firstMessage,
            $actor->id,
        );
    }

    private function orderLabel(Order $order): string
    {
        return $order->booking_reference ?: ('#'.$order->id);
    }

    private function subject(Order $order, string $action): string
    {
        return sprintf('%s · %s', $action, $this->orderLabel($order));
    }
}
