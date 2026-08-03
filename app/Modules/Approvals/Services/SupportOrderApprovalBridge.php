<?php

namespace App\Modules\Approvals\Services;

use App\Models\Approval;
use App\Models\Order;
use App\Models\ProviderWallet;
use App\Models\SupportTicket;
use App\Models\User;

class SupportOrderApprovalBridge
{
    public function __construct(
        private readonly ApprovalService $approvalService,
        private readonly ApprovalRuleService $approvalRuleService,
    ) {
    }

    /**
     * @return array{executed: bool, approval: Approval|null, result: array<string, mixed>|null}
     */
    public function submitCancel(SupportTicket $ticket, User $requester, string $reason): array
    {
        $order = $this->requireOrder($ticket);

        return $this->approvalService->submit(
            type: Approval::TYPE_CANCEL,
            entityType: Approval::ENTITY_ORDER,
            entityId: $order->id,
            requester: $requester,
            payload: [
                'support_ticket_id' => $ticket->id,
                'snapshot' => $this->approvalRuleService->orderFinancialSnapshot($order),
            ],
            reason: $reason,
            ruleContext: [
                'order_status' => $order->status,
            ],
        );
    }

    /**
     * @return array{executed: bool, approval: Approval|null, result: array<string, mixed>|null}
     */
    public function submitFullRefund(SupportTicket $ticket, User $requester, string $reason): array
    {
        $order = $this->requireOrder($ticket);
        $order->loadMissing('transactions');
        $amount = $order->getNetPaidAmount();

        return $this->approvalService->submit(
            type: Approval::TYPE_REFUND,
            entityType: Approval::ENTITY_ORDER,
            entityId: $order->id,
            requester: $requester,
            payload: [
                'support_ticket_id' => $ticket->id,
                'mode' => 'full',
                'amount' => number_format($amount, 2, '.', ''),
                'snapshot' => $this->approvalRuleService->orderFinancialSnapshot($order),
            ],
            reason: $reason,
            ruleContext: [
                'amount' => $amount,
                'mode' => 'full',
                'order_created_at' => $order->created_at?->toIso8601String(),
            ],
        );
    }

    /**
     * @return array{executed: bool, approval: Approval|null, result: array<string, mixed>|null}
     */
    public function submitPartialRefund(SupportTicket $ticket, User $requester, float $amount, string $reason): array
    {
        $order = $this->requireOrder($ticket);

        return $this->approvalService->submit(
            type: Approval::TYPE_REFUND,
            entityType: Approval::ENTITY_ORDER,
            entityId: $order->id,
            requester: $requester,
            payload: [
                'support_ticket_id' => $ticket->id,
                'mode' => 'partial',
                'amount' => number_format($amount, 2, '.', ''),
                'snapshot' => $this->approvalRuleService->orderFinancialSnapshot($order),
            ],
            reason: $reason,
            ruleContext: [
                'amount' => $amount,
                'mode' => 'partial',
                'order_created_at' => $order->created_at?->toIso8601String(),
            ],
        );
    }

    /**
     * @return array{executed: bool, approval: Approval|null, result: array<string, mixed>|null}
     */
    public function submitWalletDeposit(ProviderWallet $wallet, User $requester, array $data): array
    {
        return $this->approvalService->submit(
            type: Approval::TYPE_WALLET_DEPOSIT,
            entityType: Approval::ENTITY_WALLET,
            entityId: $wallet->id,
            requester: $requester,
            payload: [
                'wallet_id' => $wallet->id,
                'amount' => $data['amount'],
                'note' => $data['note'] ?? null,
                'snapshot' => $this->approvalRuleService->walletSnapshot($wallet->loadMissing('provider')),
            ],
            reason: (string) ($data['note'] ?? 'Wallet deposit'),
            ruleContext: [],
        );
    }

    /**
     * @return array{executed: bool, approval: Approval|null, result: array<string, mixed>|null}
     */
    public function submitWalletAdjustment(ProviderWallet $wallet, User $requester, array $data): array
    {
        return $this->approvalService->submit(
            type: Approval::TYPE_WALLET_ADJUSTMENT,
            entityType: Approval::ENTITY_WALLET,
            entityId: $wallet->id,
            requester: $requester,
            payload: [
                'wallet_id' => $wallet->id,
                'amount' => $data['amount'],
                'note' => $data['note'] ?? null,
                'snapshot' => $this->approvalRuleService->walletSnapshot($wallet->loadMissing('provider')),
            ],
            reason: (string) ($data['note'] ?? 'Wallet adjustment'),
            ruleContext: [],
        );
    }

    private function requireOrder(SupportTicket $ticket): Order
    {
        $ticket->loadMissing('order');

        if ($ticket->order === null) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'order' => 'This support ticket is not linked to an order.',
            ]);
        }

        return $ticket->order;
    }
}
