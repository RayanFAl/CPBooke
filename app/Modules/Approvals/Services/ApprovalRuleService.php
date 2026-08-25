<?php

namespace App\Modules\Approvals\Services;

use App\Models\Order;
use App\Models\ProviderWallet;
use App\Models\User;
use App\Support\Rbac\RbacRegistry;
use Carbon\Carbon;

class ApprovalRuleService
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function requiresApproval(string $type, User $requester, array $context = []): bool
    {
        if ($requester->hasRole(RbacRegistry::ROLE_SUPER_ADMIN)) {
            return false;
        }

        return match ($type) {
            'refund' => $this->requiresRefundApproval($requester, $context),
            'cancel' => $this->requiresCancelApproval($context),
            'wallet_deposit', 'wallet_adjustment' => (bool) config('approvals.wallet_always_requires_approval', true),
            'settlement_adjustment' => (bool) config('approvals.settlement_adjustment_always_requires_approval', true),
            default => true,
        };
    }

    public function canApprove(User $user): bool
    {
        if ($user->hasRole(RbacRegistry::ROLE_SUPER_ADMIN)) {
            return true;
        }

        return $user->hasPermissionTo('approvals.approve');
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function requiresRefundApproval(User $requester, array $context): bool
    {
        $amount = (float) ($context['amount'] ?? 0);
        $mode = (string) ($context['mode'] ?? 'partial');
        $threshold = (float) config('approvals.refund_direct_threshold', 100);
        $directRoles = config('approvals.refund_direct_roles', []);

        if ($amount > $threshold) {
            return true;
        }

        if ($mode === 'full' && $this->orderExceedsRefundAgeLimit($context)) {
            return true;
        }

        if ($this->userHasAnyRole($requester, $directRoles) && $amount <= $threshold) {
            return false;
        }

        return $amount > $threshold;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function requiresCancelApproval(array $context): bool
    {
        $status = (string) ($context['order_status'] ?? '');

        return in_array($status, config('approvals.cancel_issued_statuses', []), true);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function orderExceedsRefundAgeLimit(array $context): bool
    {
        $hours = (int) config('approvals.refund_full_after_hours', 24);
        $createdAt = $context['order_created_at'] ?? null;

        if (! $createdAt) {
            return false;
        }

        return Carbon::parse($createdAt)->diffInHours(now()) >= $hours;
    }

    /**
     * @param  array<int, string>  $roles
     */
    private function userHasAnyRole(User $user, array $roles): bool
    {
        return $user->hasRole($roles);
    }

    /**
     * Build financial snapshot for approval payload.
     *
     * @return array<string, mixed>
     */
    public function orderFinancialSnapshot(Order $order): array
    {
        $walletBalance = null;

        if ($order->provider_id) {
            $wallet = ProviderWallet::query()
                ->where('provider_id', $order->provider_id)
                ->where('currency', $order->currency)
                ->where('environment', config('wallets.default_environment', 'production'))
                ->first();

            $walletBalance = $wallet?->balance;
        }

        return [
            'order_id' => $order->id,
            'booking_reference' => $order->booking_reference,
            'currency' => $order->currency,
            'selling_price' => $order->selling_price ?? $order->total_amount,
            'supplier_cost' => $order->supplier_cost,
            'commission_amount' => $order->commission_amount,
            'profit_amount' => $order->profit_amount,
            'margin_percent' => $order->margin_percent,
            'payment_status' => $order->payment_status,
            'order_status' => $order->status,
            'wallet_balance_before' => $walletBalance,
            'provider_id' => $order->provider_id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function walletSnapshot(ProviderWallet $wallet): array
    {
        return [
            'wallet_id' => $wallet->id,
            'provider_id' => $wallet->provider_id,
            'provider_name' => $wallet->provider?->name,
            'currency' => $wallet->currency,
            'environment' => $wallet->environment,
            'balance_before' => $wallet->balance,
        ];
    }
}
