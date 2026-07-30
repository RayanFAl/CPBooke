<?php

namespace App\Exceptions;

use App\Models\ProviderWallet;
use RuntimeException;

class InsufficientWalletBalanceException extends RuntimeException
{
    public function __construct(
        public readonly ProviderWallet $wallet,
        public readonly string $requestedAmount,
        public readonly string $availableBalance,
    ) {
        parent::__construct(sprintf(
            'Insufficient wallet balance for provider #%d (%s %s). Requested %s, available %s.',
            $wallet->provider_id,
            $wallet->currency,
            $wallet->environment,
            $requestedAmount,
            $availableBalance,
        ));
    }
}
