<?php

namespace App\Modules\Admin\ProviderWallets\Services;

use App\Models\Order;
use App\Models\Provider;
use App\Models\ProviderWallet;
use App\Models\ProviderWalletTransaction;
use App\Models\User;
use App\Modules\Orders\Services\OrderCostService;
use App\Modules\Wallets\Services\WalletService;

/**
 * Admin-facing helpers around the shared WalletService.
 */
class ProviderWalletService
{
    public function __construct(
        private readonly WalletService $walletService,
        private readonly OrderCostService $orderCostService,
    ) {
    }

    /**
     * @param  array{provider_id: int, currency: string, environment?: string, low_balance_threshold?: string|float|null, allow_negative?: bool}  $data
     */
    public function createWallet(array $data): ProviderWallet
    {
        return $this->walletService->createWallet($data);
    }

    /**
     * @param  array{name: string, key: string, status?: string}  $data
     */
    public function createProvider(array $data): Provider
    {
        return $this->walletService->createProvider($data);
    }

    /**
     * @param  array{amount: string|float|int, note?: string|null}  $data
     */
    public function deposit(ProviderWallet $wallet, array $data, User $actor): ProviderWalletTransaction
    {
        return $this->walletService->deposit($wallet, $data['amount'], $actor, [
            'description' => $data['note'] ?? null,
        ]);
    }

    /**
     * @param  array{amount: string|float|int, note?: string|null}  $data
     */
    public function adjust(ProviderWallet $wallet, array $data, User $actor): ProviderWalletTransaction
    {
        return $this->walletService->adjust($wallet, $data['amount'], $actor, [
            'description' => $data['note'] ?? null,
        ]);
    }

    /**
     * Debit a paid order via the shared wallet service (used by BookNow sync and future integrations).
     */
    public function debitPaidOrder(Order $order, Provider $provider, ?string $environment = null): ?ProviderWalletTransaction
    {
        if ($order->payment_status !== Order::PAYMENT_STATUS_PAID) {
            return null;
        }

        $amount = $this->orderCostService->debitAmount($order);

        if ($amount <= 0) {
            return null;
        }

        $referenceType = match ($order->service_type) {
            Order::SERVICE_TYPE_HOTEL => ProviderWalletTransaction::REFERENCE_HOTEL_BOOKING,
            Order::SERVICE_TYPE_INSURANCE => ProviderWalletTransaction::REFERENCE_INSURANCE_ORDER,
            default => ProviderWalletTransaction::REFERENCE_FLIGHT_BOOKING,
        };

        return $this->walletService->debit(
            providerId: $provider->id,
            amount: $amount,
            currency: (string) ($order->currency ?: \App\Support\Platform\PlatformSettings::defaultCurrency()),
            referenceType: $referenceType,
            referenceId: $order->id,
            options: [
                'description' => 'Auto debit for paid booking '.($order->booking_reference ?: $order->external_booking_id),
                'order_id' => $order->id,
                'environment' => $environment ?? config('wallets.default_environment'),
                'create_wallet_if_missing' => true,
                'metadata' => [
                    'source' => 'order_integration',
                    'external_booking_id' => $order->external_booking_id,
                    'provider_name_on_order' => $order->provider_name,
                    'debit_basis' => $order->supplier_cost !== null ? 'supplier_cost' : 'total_amount',
                    'selling_price' => $order->selling_price,
                    'supplier_cost' => $order->supplier_cost,
                    'profit_amount' => $order->profit_amount,
                ],
            ],
        );
    }
}
