<?php

namespace App\Modules\Wallets\Services;

use App\Exceptions\InsufficientWalletBalanceException;
use App\Models\AuditLog;
use App\Models\Provider;
use App\Models\ProviderWallet;
use App\Models\ProviderWalletTransaction;
use App\Models\User;
use App\Modules\Audit\Services\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WalletService
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
    ) {
    }
    /**
     * Resolve a wallet for provider + currency + environment.
     */
    public function resolveWallet(
        int $providerId,
        string $currency,
        string $environment = ProviderWallet::ENVIRONMENT_PRODUCTION,
        bool $createIfMissing = true,
    ): ProviderWallet {
        $currency = strtoupper($currency);
        $environment = $this->normalizeEnvironment($environment);

        $wallet = ProviderWallet::query()
            ->where('provider_id', $providerId)
            ->where('currency', $currency)
            ->where('environment', $environment)
            ->first();

        if ($wallet) {
            return $wallet;
        }

        if (! $createIfMissing) {
            throw ValidationException::withMessages([
                'wallet' => 'No wallet exists for this provider, currency, and environment.',
            ]);
        }

        Provider::query()->findOrFail($providerId);

        return ProviderWallet::query()->create([
            'provider_id' => $providerId,
            'currency' => $currency,
            'environment' => $environment,
            'balance' => 0,
            'allow_negative' => (bool) config('wallets.default_allow_negative', true),
            'is_active' => true,
        ]);
    }

    /**
     * Generic debit used by any integration (flights, hotels, eSIM, insurance...).
     *
     * @param  array{description?: string|null, order_id?: int|null, metadata?: array<string, mixed>|null, environment?: string, create_wallet_if_missing?: bool, actor?: User|null}  $options
     */
    public function debit(
        int $providerId,
        string|float|int $amount,
        string $currency,
        string $referenceType,
        string|int $referenceId,
        array $options = [],
    ): ProviderWalletTransaction {
        $amount = $this->normalizePositiveAmount($amount, 'amount');
        $currency = strtoupper($currency);
        $referenceType = trim($referenceType);
        $referenceId = (string) $referenceId;
        $environment = $this->normalizeEnvironment($options['environment'] ?? config('wallets.default_environment'));
        $createIfMissing = (bool) ($options['create_wallet_if_missing'] ?? true);
        $actor = $options['actor'] ?? null;

        if ($referenceType === '') {
            throw ValidationException::withMessages([
                'reference_type' => 'Reference type is required.',
            ]);
        }

        return DB::transaction(function () use (
            $providerId,
            $amount,
            $currency,
            $referenceType,
            $referenceId,
            $environment,
            $createIfMissing,
            $options,
            $actor,
        ): ProviderWalletTransaction {
            $wallet = $this->resolveWallet($providerId, $currency, $environment, $createIfMissing);
            $locked = ProviderWallet::query()->whereKey($wallet->id)->lockForUpdate()->firstOrFail();

            $existing = ProviderWalletTransaction::query()
                ->where('provider_wallet_id', $locked->id)
                ->where('reference_type', $referenceType)
                ->where('reference_id', $referenceId)
                ->first();

            if ($existing) {
                return $existing;
            }

            if (! $locked->is_active) {
                throw ValidationException::withMessages([
                    'wallet' => 'This wallet is inactive.',
                ]);
            }

            $available = (float) $locked->balance;
            $balanceAfter = number_format($available - $amount, 2, '.', '');

            if (! $locked->allow_negative && $available < $amount) {
                throw new InsufficientWalletBalanceException(
                    $locked,
                    number_format($amount, 2, '.', ''),
                    number_format($available, 2, '.', ''),
                );
            }

            $locked->forceFill([
                'balance' => $balanceAfter,
            ])->save();

            $transaction = ProviderWalletTransaction::query()->create([
                'provider_wallet_id' => $locked->id,
                'type' => ProviderWalletTransaction::TYPE_DEBIT,
                'amount' => number_format($amount, 2, '.', ''),
                'balance_after' => $balanceAfter,
                'currency' => $locked->currency,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'description' => $options['description'] ?? null,
                'order_id' => $options['order_id'] ?? null,
                'created_by' => $actor?->id,
                'metadata' => array_merge($options['metadata'] ?? [], [
                    'low_balance' => (float) $balanceAfter < 0,
                    'allow_negative' => (bool) $locked->allow_negative,
                ]),
            ]);

            $this->auditRecorder->success(
                AuditLog::MODULE_WALLETS,
                'wallet.debited',
                'Wallet #'.$locked->id.' debited '.$amount.' '.$locked->currency,
                AuditLog::ENTITY_PROVIDER_WALLET,
                $locked->id,
                $actor instanceof User ? $actor : null,
                ['balance' => number_format($available, 2, '.', '')],
                ['balance' => $balanceAfter, 'amount' => number_format($amount, 2, '.', '')],
                [
                    'transaction_id' => $transaction->id,
                    'order_id' => $options['order_id'] ?? null,
                    'reference_type' => $referenceType,
                    'reference_id' => $referenceId,
                ],
            );

            return $transaction;
        });
    }

    /**
     * Manual deposit (admin).
     *
     * @param  array{description?: string|null, metadata?: array<string, mixed>|null}  $options
     */
    public function deposit(
        ProviderWallet $wallet,
        string|float|int $amount,
        ?User $actor = null,
        array $options = [],
    ): ProviderWalletTransaction {
        $amount = $this->normalizePositiveAmount($amount, 'amount');

        return DB::transaction(function () use ($wallet, $amount, $actor, $options): ProviderWalletTransaction {
            $locked = ProviderWallet::query()->whereKey($wallet->id)->lockForUpdate()->firstOrFail();
            $previousBalance = number_format((float) $locked->balance, 2, '.', '');
            $balanceAfter = number_format((float) $locked->balance + $amount, 2, '.', '');

            $locked->forceFill([
                'balance' => $balanceAfter,
            ])->save();

            $transaction = ProviderWalletTransaction::query()->create([
                'provider_wallet_id' => $locked->id,
                'type' => ProviderWalletTransaction::TYPE_DEPOSIT,
                'amount' => number_format($amount, 2, '.', ''),
                'balance_after' => $balanceAfter,
                'currency' => $locked->currency,
                'reference_type' => ProviderWalletTransaction::REFERENCE_MANUAL,
                'reference_id' => (string) Str::ulid(),
                'description' => $options['description'] ?? null,
                'created_by' => $actor?->id,
                'metadata' => $options['metadata'] ?? ['source' => 'admin_deposit'],
            ]);

            $this->auditRecorder->success(
                AuditLog::MODULE_WALLETS,
                'wallet.deposited',
                'Wallet #'.$locked->id.' deposit '.$amount.' '.$locked->currency,
                AuditLog::ENTITY_PROVIDER_WALLET,
                $locked->id,
                $actor,
                ['balance' => $previousBalance],
                ['balance' => $balanceAfter, 'amount' => number_format($amount, 2, '.', '')],
                ['transaction_id' => $transaction->id],
            );

            return $transaction;
        });
    }

    /**
     * Manual signed adjustment (admin).
     *
     * @param  array{description?: string|null, metadata?: array<string, mixed>|null}  $options
     */
    public function adjust(
        ProviderWallet $wallet,
        string|float|int $amount,
        ?User $actor = null,
        array $options = [],
    ): ProviderWalletTransaction {
        $amount = $this->normalizeSignedAmount($amount, 'amount');

        if ($amount == 0.0) {
            throw ValidationException::withMessages([
                'amount' => 'Adjustment amount cannot be zero.',
            ]);
        }

        return DB::transaction(function () use ($wallet, $amount, $actor, $options): ProviderWalletTransaction {
            $locked = ProviderWallet::query()->whereKey($wallet->id)->lockForUpdate()->firstOrFail();
            $available = (float) $locked->balance;
            $previousBalance = number_format($available, 2, '.', '');
            $balanceAfterValue = $available + $amount;

            if (! $locked->allow_negative && $balanceAfterValue < 0) {
                throw new InsufficientWalletBalanceException(
                    $locked,
                    number_format(abs($amount), 2, '.', ''),
                    number_format($available, 2, '.', ''),
                );
            }

            $balanceAfter = number_format($balanceAfterValue, 2, '.', '');

            $locked->forceFill([
                'balance' => $balanceAfter,
            ])->save();

            $transaction = ProviderWalletTransaction::query()->create([
                'provider_wallet_id' => $locked->id,
                'type' => ProviderWalletTransaction::TYPE_ADJUSTMENT,
                'amount' => number_format($amount, 2, '.', ''),
                'balance_after' => $balanceAfter,
                'currency' => $locked->currency,
                'reference_type' => ProviderWalletTransaction::REFERENCE_MANUAL,
                'reference_id' => (string) Str::ulid(),
                'description' => $options['description'] ?? null,
                'created_by' => $actor?->id,
                'metadata' => $options['metadata'] ?? ['source' => 'admin_adjustment'],
            ]);

            $this->auditRecorder->success(
                AuditLog::MODULE_WALLETS,
                'wallet.adjusted',
                'Wallet #'.$locked->id.' adjusted by '.$amount.' '.$locked->currency,
                AuditLog::ENTITY_PROVIDER_WALLET,
                $locked->id,
                $actor,
                ['balance' => $previousBalance],
                ['balance' => $balanceAfter, 'amount' => number_format($amount, 2, '.', '')],
                ['transaction_id' => $transaction->id],
            );

            return $transaction;
        });
    }

    /**
     * @param  array{provider_id: int, currency: string, environment?: string, low_balance_threshold?: string|float|null, allow_negative?: bool}  $data
     */
    public function createWallet(array $data): ProviderWallet
    {
        $provider = Provider::query()->findOrFail($data['provider_id']);
        $currency = strtoupper($data['currency']);
        $environment = $this->normalizeEnvironment($data['environment'] ?? config('wallets.default_environment'));

        $exists = ProviderWallet::query()
            ->where('provider_id', $provider->id)
            ->where('currency', $currency)
            ->where('environment', $environment)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'currency' => 'A wallet already exists for this provider, currency, and environment.',
            ]);
        }

        return ProviderWallet::query()->create([
            'provider_id' => $provider->id,
            'currency' => $currency,
            'environment' => $environment,
            'balance' => 0,
            'low_balance_threshold' => isset($data['low_balance_threshold']) && $data['low_balance_threshold'] !== '' && $data['low_balance_threshold'] !== null
                ? number_format((float) $data['low_balance_threshold'], 2, '.', '')
                : null,
            'allow_negative' => array_key_exists('allow_negative', $data)
                ? (bool) $data['allow_negative']
                : (bool) config('wallets.default_allow_negative', true),
            'is_active' => true,
        ]);
    }

    /**
     * @param  array{name: string, key: string, status?: string}  $data
     */
    public function createProvider(array $data): Provider
    {
        $key = $this->normalizeProviderKey($data['key']);

        if (Provider::query()->where('key', $key)->exists()) {
            throw ValidationException::withMessages([
                'key' => 'A provider with this key already exists.',
            ]);
        }

        return Provider::query()->create([
            'name' => $data['name'],
            'key' => $key,
            'status' => $data['status'] ?? Provider::STATUS_ACTIVE,
        ]);
    }

    public function findOrCreateProviderByKey(string $key, string $name): Provider
    {
        $key = $this->normalizeProviderKey($key);

        return Provider::query()->firstOrCreate(
            ['key' => $key],
            [
                'name' => $name,
                'status' => Provider::STATUS_ACTIVE,
            ],
        );
    }

    private function normalizeEnvironment(string $environment): string
    {
        $environment = strtolower(trim($environment));
        $allowed = config('wallets.environments', [
            ProviderWallet::ENVIRONMENT_PRODUCTION,
            ProviderWallet::ENVIRONMENT_SANDBOX,
        ]);

        if (! in_array($environment, $allowed, true)) {
            throw ValidationException::withMessages([
                'environment' => 'Unsupported wallet environment.',
            ]);
        }

        return $environment;
    }

    private function normalizeProviderKey(string $providerKey): string
    {
        $normalized = Str::of($providerKey)
            ->lower()
            ->replaceMatches('/[^a-z0-9_-]+/', '-')
            ->trim('-')
            ->toString();

        if ($normalized === '') {
            throw ValidationException::withMessages([
                'key' => 'Provider key is required.',
            ]);
        }

        return $normalized;
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
}
