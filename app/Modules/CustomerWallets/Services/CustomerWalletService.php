<?php

namespace App\Modules\CustomerWallets\Services;

use App\Exceptions\InsufficientCustomerWalletBalanceException;
use App\Models\AuditLog;
use App\Models\CustomerWallet;
use App\Models\CustomerWalletTransaction;
use App\Models\FinancialTransaction;
use App\Models\Order;
use App\Models\User;
use App\Modules\Api\Orders\Services\OrderService;
use App\Modules\Audit\Services\AuditRecorder;
use App\Modules\Orders\Events\PaymentSucceeded as PaymentSucceededEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CustomerWalletService
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
        private readonly OrderService $orderService,
    ) {
    }

    public function resolveWallet(
        User $user,
        string $currency = null,
        bool $createIfMissing = true,
    ): CustomerWallet {
        $currency = strtoupper($currency ?? (string) config('customer_wallets.default_currency', 'LYD'));

        $wallet = CustomerWallet::query()
            ->where('user_id', $user->id)
            ->where('currency', $currency)
            ->first();

        if ($wallet) {
            return $wallet;
        }

        if (! $createIfMissing) {
            throw ValidationException::withMessages([
                'wallet' => 'No wallet exists for this customer and currency.',
            ]);
        }

        return CustomerWallet::query()->create([
            'user_id' => $user->id,
            'wallet_number' => $this->generateWalletNumber($user),
            'currency' => $currency,
            'balance' => 0,
            'status' => CustomerWallet::STATUS_ACTIVE,
        ]);
    }

    /**
     * @param  array{description?: string|null, metadata?: array<string, mixed>|null}  $options
     */
    public function adminCredit(
        CustomerWallet $wallet,
        string|float|int $amount,
        ?User $actor = null,
        array $options = [],
    ): CustomerWalletTransaction {
        $amount = $this->normalizePositiveAmount($amount, 'amount');

        return $this->credit(
            $wallet,
            $amount,
            CustomerWalletTransaction::TYPE_ADMIN_CREDIT,
            CustomerWalletTransaction::REFERENCE_MANUAL,
            (string) Str::ulid(),
            array_merge($options, [
                'actor' => $actor,
                'metadata' => array_merge($options['metadata'] ?? [], ['source' => 'admin_credit']),
            ]),
        );
    }

    /**
     * @param  array{description?: string|null, metadata?: array<string, mixed>|null}  $options
     */
    public function adminDebit(
        CustomerWallet $wallet,
        string|float|int $amount,
        ?User $actor = null,
        array $options = [],
    ): CustomerWalletTransaction {
        $amount = $this->normalizePositiveAmount($amount, 'amount');

        return $this->debit(
            $wallet,
            $amount,
            CustomerWalletTransaction::TYPE_ADMIN_DEBIT,
            CustomerWalletTransaction::REFERENCE_MANUAL,
            (string) Str::ulid(),
            array_merge($options, [
                'actor' => $actor,
                'metadata' => array_merge($options['metadata'] ?? [], ['source' => 'admin_debit']),
            ]),
        );
    }

    /**
     * @param  array{description?: string|null, metadata?: array<string, mixed>|null}  $options
     */
    public function adjust(
        CustomerWallet $wallet,
        string|float|int $amount,
        ?User $actor = null,
        array $options = [],
    ): CustomerWalletTransaction {
        $amount = $this->normalizeSignedAmount($amount, 'amount');

        if ($amount === 0.0) {
            throw ValidationException::withMessages([
                'amount' => 'Adjustment amount cannot be zero.',
            ]);
        }

        if ($amount > 0) {
            return $this->credit(
                $wallet,
                $amount,
                CustomerWalletTransaction::TYPE_ADJUSTMENT,
                CustomerWalletTransaction::REFERENCE_MANUAL,
                (string) Str::ulid(),
                array_merge($options, [
                    'actor' => $actor,
                    'metadata' => array_merge($options['metadata'] ?? [], ['source' => 'admin_adjustment']),
                ]),
            );
        }

        return $this->debit(
            $wallet,
            abs($amount),
            CustomerWalletTransaction::TYPE_ADJUSTMENT,
            CustomerWalletTransaction::REFERENCE_MANUAL,
            (string) Str::ulid(),
            array_merge($options, [
                'actor' => $actor,
                'metadata' => array_merge($options['metadata'] ?? [], ['source' => 'admin_adjustment']),
            ]),
        );
    }

    public function testTopUp(User $user, string|float|int $amount): CustomerWalletTransaction
    {
        if (! config('customer_wallets.test_mode')) {
            throw ValidationException::withMessages([
                'wallet' => 'Test wallet top-up is disabled.',
            ]);
        }

        $amount = $this->normalizePositiveAmount($amount, 'amount');
        $max = (float) config('customer_wallets.test_top_up_max', 1000);
        $min = (float) config('customer_wallets.test_top_up_min', 1);

        if ($amount < $min) {
            throw ValidationException::withMessages([
                'amount' => 'Amount must be at least '.$min.'.',
            ]);
        }

        if ($amount > $max) {
            throw ValidationException::withMessages([
                'amount' => 'Test top-up cannot exceed '.$max.' '.config('customer_wallets.default_currency', 'LYD').'.',
            ]);
        }

        $wallet = $this->resolveWallet($user);

        return $this->credit(
            $wallet,
            $amount,
            CustomerWalletTransaction::TYPE_CREDIT,
            CustomerWalletTransaction::REFERENCE_TEST_TOP_UP,
            (string) Str::ulid(),
            [
                'actor' => $user,
                'description' => 'Test wallet top-up',
                'metadata' => ['source' => 'test_top_up'],
            ],
        );
    }

    public function payForOrder(Order $order, User $user): CustomerWalletTransaction
    {
        if ($order->customer_id !== $user->id) {
            throw ValidationException::withMessages([
                'order' => 'This order does not belong to the authenticated customer.',
            ]);
        }

        if ($order->payment_status === Order::PAYMENT_STATUS_PAID) {
            $existing = CustomerWalletTransaction::query()
                ->where('order_id', $order->id)
                ->where('type', CustomerWalletTransaction::TYPE_BOOKING)
                ->first();

            if ($existing) {
                return $existing;
            }

            throw ValidationException::withMessages([
                'order' => 'This order has already been paid.',
            ]);
        }

        $amount = $this->orderChargeAmount($order);

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'order' => 'This order has no collectible amount.',
            ]);
        }

        $wallet = $this->resolveWallet($user, $order->currency);

        if ($wallet->currency !== strtoupper($order->currency)) {
            throw ValidationException::withMessages([
                'wallet' => 'Wallet currency does not match the order currency.',
            ]);
        }

        return DB::transaction(function () use ($wallet, $order, $user, $amount): CustomerWalletTransaction {
            $transaction = $this->debit(
                $wallet,
                $amount,
                CustomerWalletTransaction::TYPE_BOOKING,
                CustomerWalletTransaction::REFERENCE_ORDER,
                (string) $order->id,
                [
                    'actor' => $user,
                    'order_id' => $order->id,
                    'description' => 'Wallet payment for order #'.$order->id,
                    'metadata' => ['source' => 'wallet_payment'],
                ],
            );

            $originalPaymentStatus = $order->payment_status;

            $financialTransaction = $this->orderService->recordFinancialTransaction(
                $order,
                FinancialTransaction::TYPE_PAYMENT,
                $amount,
                FinancialTransaction::SOURCE_CUSTOMER_WALLET,
                $user,
                [
                    'source_id' => $transaction->id,
                    'reason' => 'Customer wallet payment',
                    'metadata' => [
                        'wallet_id' => $wallet->id,
                        'wallet_transaction_id' => $transaction->id,
                    ],
                ],
            );

            $order->forceFill([
                'payment_method' => Order::PAYMENT_METHOD_WALLET,
                'payment_status' => Order::PAYMENT_STATUS_PAID,
                'status' => $order->status === Order::STATUS_PENDING_PAYMENT
                    ? Order::STATUS_PAID
                    : $order->status,
            ])->save();

            $this->orderService->syncDerivedPaymentStatus(
                $order,
                $user,
                Order::PAYMENT_STATUS_PAID,
                $originalPaymentStatus,
            );

            $this->dispatchAfterCommit(fn () => event(new PaymentSucceededEvent(
                $order->fresh()->load('customer'),
                $financialTransaction,
            )));

            return $transaction;
        });
    }

    /**
     * @param  array{description?: string|null, metadata?: array<string, mixed>|null, actor?: User|null}  $options
     */
    public function refundForOrder(
        Order $order,
        string|float|int $amount,
        ?User $actor = null,
        array $options = [],
    ): CustomerWalletTransaction {
        if ($order->payment_method !== Order::PAYMENT_METHOD_WALLET) {
            throw ValidationException::withMessages([
                'order' => 'This order was not paid with the customer wallet.',
            ]);
        }

        $amount = $this->normalizePositiveAmount($amount, 'amount');
        $customer = $order->customer;

        if (! $customer) {
            throw ValidationException::withMessages([
                'order' => 'Order customer is missing.',
            ]);
        }

        $wallet = $this->resolveWallet($customer, $order->currency);
        $this->ensureRefundAmountWithinPaidWalletAmount($wallet, $order, $amount);
        $referenceId = $options['reference_id'] ?? (string) Str::ulid();

        return $this->credit(
            $wallet,
            $amount,
            CustomerWalletTransaction::TYPE_REFUND,
            CustomerWalletTransaction::REFERENCE_ORDER,
            $referenceId,
            array_merge($options, [
                'actor' => $actor,
                'order_id' => $order->id,
                'description' => $options['description'] ?? 'Wallet refund for order #'.$order->id,
                'metadata' => array_merge($options['metadata'] ?? [], ['source' => 'wallet_refund']),
            ]),
        );
    }

    public function freeze(CustomerWallet $wallet, ?User $actor = null): CustomerWallet
    {
        return DB::transaction(function () use ($wallet, $actor): CustomerWallet {
            $locked = CustomerWallet::query()->whereKey($wallet->id)->lockForUpdate()->firstOrFail();

            if ($locked->isFrozen()) {
                return $locked;
            }

            $locked->forceFill(['status' => CustomerWallet::STATUS_FROZEN])->save();

            $this->auditRecorder->success(
                AuditLog::MODULE_WALLETS,
                'customer_wallet.frozen',
                'Customer wallet #'.$locked->id.' frozen',
                AuditLog::ENTITY_CUSTOMER_WALLET,
                $locked->id,
                $actor,
                ['status' => CustomerWallet::STATUS_ACTIVE],
                ['status' => CustomerWallet::STATUS_FROZEN],
            );

            return $locked;
        });
    }

    public function unfreeze(CustomerWallet $wallet, ?User $actor = null): CustomerWallet
    {
        return DB::transaction(function () use ($wallet, $actor): CustomerWallet {
            $locked = CustomerWallet::query()->whereKey($wallet->id)->lockForUpdate()->firstOrFail();

            if ($locked->isActive()) {
                return $locked;
            }

            $locked->forceFill(['status' => CustomerWallet::STATUS_ACTIVE])->save();

            $this->auditRecorder->success(
                AuditLog::MODULE_WALLETS,
                'customer_wallet.unfrozen',
                'Customer wallet #'.$locked->id.' unfrozen',
                AuditLog::ENTITY_CUSTOMER_WALLET,
                $locked->id,
                $actor,
                ['status' => CustomerWallet::STATUS_FROZEN],
                ['status' => CustomerWallet::STATUS_ACTIVE],
            );

            return $locked;
        });
    }

    /**
     * @param  array{description?: string|null, order_id?: int|null, metadata?: array<string, mixed>|null, actor?: User|null}  $options
     */
    private function credit(
        CustomerWallet $wallet,
        float $amount,
        string $type,
        string $referenceType,
        string $referenceId,
        array $options = [],
    ): CustomerWalletTransaction {
        $actor = $options['actor'] ?? null;

        return DB::transaction(function () use ($wallet, $amount, $type, $referenceType, $referenceId, $options, $actor): CustomerWalletTransaction {
            $locked = CustomerWallet::query()->whereKey($wallet->id)->lockForUpdate()->firstOrFail();

            $existing = CustomerWalletTransaction::query()
                ->where('customer_wallet_id', $locked->id)
                ->where('reference_type', $referenceType)
                ->where('reference_id', $referenceId)
                ->first();

            if ($existing) {
                return $existing;
            }

            if ($locked->isFrozen() && $type !== CustomerWalletTransaction::TYPE_REFUND) {
                throw ValidationException::withMessages([
                    'wallet' => 'This wallet is frozen.',
                ]);
            }

            $balanceBefore = number_format((float) $locked->balance, 2, '.', '');
            $balanceAfter = number_format((float) $locked->balance + $amount, 2, '.', '');

            $locked->forceFill(['balance' => $balanceAfter])->save();

            $transaction = CustomerWalletTransaction::query()->create([
                'customer_wallet_id' => $locked->id,
                'type' => $type,
                'amount' => number_format($amount, 2, '.', ''),
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'currency' => $locked->currency,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'description' => $options['description'] ?? null,
                'order_id' => $options['order_id'] ?? null,
                'created_by' => $actor?->id,
                'metadata' => $options['metadata'] ?? null,
            ]);

            $this->auditRecorder->success(
                AuditLog::MODULE_WALLETS,
                'customer_wallet.credited',
                'Customer wallet #'.$locked->id.' credited '.$amount.' '.$locked->currency,
                AuditLog::ENTITY_CUSTOMER_WALLET,
                $locked->id,
                $actor instanceof User ? $actor : null,
                ['balance' => $balanceBefore],
                ['balance' => $balanceAfter, 'amount' => number_format($amount, 2, '.', '')],
                [
                    'transaction_id' => $transaction->id,
                    'type' => $type,
                    'reference_type' => $referenceType,
                    'reference_id' => $referenceId,
                ],
            );

            return $transaction;
        });
    }

    /**
     * @param  array{description?: string|null, order_id?: int|null, metadata?: array<string, mixed>|null, actor?: User|null}  $options
     */
    private function debit(
        CustomerWallet $wallet,
        float $amount,
        string $type,
        string $referenceType,
        string $referenceId,
        array $options = [],
    ): CustomerWalletTransaction {
        $actor = $options['actor'] ?? null;

        return DB::transaction(function () use ($wallet, $amount, $type, $referenceType, $referenceId, $options, $actor): CustomerWalletTransaction {
            $locked = CustomerWallet::query()->whereKey($wallet->id)->lockForUpdate()->firstOrFail();

            $existing = CustomerWalletTransaction::query()
                ->where('customer_wallet_id', $locked->id)
                ->where('reference_type', $referenceType)
                ->where('reference_id', $referenceId)
                ->first();

            if ($existing) {
                return $existing;
            }

            if ($locked->isFrozen()) {
                throw ValidationException::withMessages([
                    'wallet' => 'This wallet is frozen.',
                ]);
            }

            $available = (float) $locked->balance;
            $balanceBefore = number_format($available, 2, '.', '');
            $balanceAfter = number_format($available - $amount, 2, '.', '');

            if ($available < $amount) {
                throw new InsufficientCustomerWalletBalanceException(
                    $locked,
                    number_format($amount, 2, '.', ''),
                    $balanceBefore,
                );
            }

            $locked->forceFill(['balance' => $balanceAfter])->save();

            $storedAmount = $type === CustomerWalletTransaction::TYPE_ADJUSTMENT
                ? number_format(-$amount, 2, '.', '')
                : number_format($amount, 2, '.', '');

            $transaction = CustomerWalletTransaction::query()->create([
                'customer_wallet_id' => $locked->id,
                'type' => $type,
                'amount' => $storedAmount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'currency' => $locked->currency,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'description' => $options['description'] ?? null,
                'order_id' => $options['order_id'] ?? null,
                'created_by' => $actor?->id,
                'metadata' => $options['metadata'] ?? null,
            ]);

            $this->auditRecorder->success(
                AuditLog::MODULE_WALLETS,
                'customer_wallet.debited',
                'Customer wallet #'.$locked->id.' debited '.$amount.' '.$locked->currency,
                AuditLog::ENTITY_CUSTOMER_WALLET,
                $locked->id,
                $actor instanceof User ? $actor : null,
                ['balance' => $balanceBefore],
                ['balance' => $balanceAfter, 'amount' => number_format($amount, 2, '.', '')],
                [
                    'transaction_id' => $transaction->id,
                    'type' => $type,
                    'reference_type' => $referenceType,
                    'reference_id' => $referenceId,
                ],
            );

            return $transaction;
        });
    }

    private function orderChargeAmount(Order $order): float
    {
        $remaining = $order->getRemainingCollectibleAmount();

        if ($remaining > 0) {
            return $remaining;
        }

        if ($order->final_amount !== null && (float) $order->final_amount > 0) {
            return (float) $order->final_amount;
        }

        return (float) $order->total_amount;
    }

    private function ensureRefundAmountWithinPaidWalletAmount(
        CustomerWallet $wallet,
        Order $order,
        float $refundAmount,
    ): void {
        $paidWithWallet = (float) CustomerWalletTransaction::query()
            ->where('customer_wallet_id', $wallet->id)
            ->where('order_id', $order->id)
            ->where('type', CustomerWalletTransaction::TYPE_BOOKING)
            ->sum('amount');

        $alreadyRefunded = (float) CustomerWalletTransaction::query()
            ->where('customer_wallet_id', $wallet->id)
            ->where('order_id', $order->id)
            ->where('type', CustomerWalletTransaction::TYPE_REFUND)
            ->sum('amount');

        $remaining = round($paidWithWallet - $alreadyRefunded, 2);

        if ($remaining <= 0) {
            throw ValidationException::withMessages([
                'order' => 'No refundable wallet amount remains for this order.',
            ]);
        }

        if ($refundAmount > $remaining) {
            throw ValidationException::withMessages([
                'amount' => 'Refund amount exceeds remaining refundable wallet amount.',
            ]);
        }
    }

    private function generateWalletNumber(User $user): string
    {
        do {
            $number = 'WLT-'.str_pad((string) $user->id, 6, '0', STR_PAD_LEFT).'-'.strtoupper(Str::random(6));
        } while (CustomerWallet::query()->where('wallet_number', $number)->exists());

        return $number;
    }

    private function normalizePositiveAmount(mixed $amount, string $field): float
    {
        if (! is_numeric($amount) || (float) $amount <= 0) {
            throw ValidationException::withMessages([
                $field => 'Amount must be a number greater than zero.',
            ]);
        }

        return round((float) $amount, 2);
    }

    private function normalizeSignedAmount(mixed $amount, string $field): float
    {
        if (! is_numeric($amount)) {
            throw ValidationException::withMessages([
                $field => 'Amount must be a valid number.',
            ]);
        }

        return round((float) $amount, 2);
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
