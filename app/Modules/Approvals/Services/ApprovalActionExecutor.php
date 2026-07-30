<?php

namespace App\Modules\Approvals\Services;

use App\Models\Approval;
use App\Models\Order;
use App\Models\ProviderWallet;
use App\Models\SupportTicket;
use App\Models\User;
use App\Modules\Api\Orders\Services\OrderActionService;
use App\Modules\Admin\ProviderWallets\Services\ProviderWalletService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class ApprovalActionExecutor
{
    public function __construct(
        private readonly OrderActionService $orderActionService,
        private readonly ProviderWalletService $providerWalletService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function execute(Approval $approval): array
    {
        $approval->loadMissing('requester');
        $payload = $approval->payload ?? [];

        return match ($approval->type) {
            Approval::TYPE_REFUND => $this->executeRefund($approval, $payload),
            Approval::TYPE_CANCEL => $this->executeCancel($approval, $payload),
            Approval::TYPE_WALLET_DEPOSIT => $this->executeWalletDeposit($approval, $payload),
            Approval::TYPE_WALLET_ADJUSTMENT => $this->executeWalletAdjustment($approval, $payload),
            default => throw ValidationException::withMessages([
                'type' => 'Unsupported approval type.',
            ]),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function executeRefund(Approval $approval, array $payload): array
    {
        $ticket = $this->supportTicket($payload);
        $requester = $approval->requester;
        $reason = (string) ($approval->reason ?? 'Approved refund');

        $order = match ($payload['mode'] ?? 'full') {
            'partial' => $this->orderActionService->partialRefundFromSupport(
                $ticket,
                $requester,
                (float) ($payload['amount'] ?? 0),
                $reason,
            ),
            default => $this->orderActionService->fullRefundFromSupport($ticket, $requester, $reason),
        };

        return [
            'order_id' => $order->id,
            'payment_status' => $order->payment_status,
            'mode' => $payload['mode'] ?? 'full',
            'amount' => $payload['amount'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function executeCancel(Approval $approval, array $payload): array
    {
        $ticket = $this->supportTicket($payload);
        $order = $this->orderActionService->cancelFromSupport(
            $ticket,
            $approval->requester,
            (string) ($approval->reason ?? 'Approved cancellation'),
        );

        return [
            'order_id' => $order->id,
            'status' => $order->status,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function executeWalletDeposit(Approval $approval, array $payload): array
    {
        $wallet = ProviderWallet::query()->findOrFail($payload['wallet_id'] ?? 0);
        $transaction = $this->providerWalletService->deposit($wallet, [
            'amount' => $payload['amount'] ?? 0,
            'note' => $payload['note'] ?? $approval->reason,
        ], $approval->requester);

        return [
            'wallet_id' => $wallet->id,
            'transaction_id' => $transaction->id,
            'balance_after' => $transaction->balance_after,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function executeWalletAdjustment(Approval $approval, array $payload): array
    {
        $wallet = ProviderWallet::query()->findOrFail($payload['wallet_id'] ?? 0);
        $transaction = $this->providerWalletService->adjust($wallet, [
            'amount' => $payload['amount'] ?? 0,
            'note' => $payload['note'] ?? $approval->reason,
        ], $approval->requester);

        return [
            'wallet_id' => $wallet->id,
            'transaction_id' => $transaction->id,
            'balance_after' => $transaction->balance_after,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function supportTicket(array $payload): SupportTicket
    {
        $ticketId = $payload['support_ticket_id'] ?? null;

        if (! $ticketId) {
            throw ValidationException::withMessages([
                'support_ticket_id' => 'Support ticket is required for this approval.',
            ]);
        }

        return SupportTicket::query()->findOrFail($ticketId);
    }
}
