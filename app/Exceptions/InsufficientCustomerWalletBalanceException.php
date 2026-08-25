<?php

namespace App\Exceptions;

use App\Models\CustomerWallet;
use RuntimeException;

class InsufficientCustomerWalletBalanceException extends RuntimeException
{
    public function __construct(
        public readonly CustomerWallet $wallet,
        public readonly string $requestedAmount,
        public readonly string $availableBalance,
    ) {
        parent::__construct(sprintf(
            'Insufficient customer wallet balance for wallet #%d (%s). Requested %s, available %s.',
            $wallet->id,
            $wallet->currency,
            $requestedAmount,
            $availableBalance,
        ));
    }
}
