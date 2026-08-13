<?php

namespace App\Services\AI;

use RuntimeException;
use Throwable;

class GeminiException extends RuntimeException
{
    public function __construct(
        public readonly string $reason,
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message !== '' ? $message : $reason, $code, $previous);
    }

    public function isFallbackWorthy(): bool
    {
        return in_array($this->reason, [
            GeminiClient::REASON_DISABLED,
            GeminiClient::REASON_MISSING_KEY,
            GeminiClient::REASON_TIMEOUT,
            GeminiClient::REASON_RATE_LIMIT,
            GeminiClient::REASON_QUOTA,
            GeminiClient::REASON_UNAVAILABLE,
            GeminiClient::REASON_NETWORK,
            GeminiClient::REASON_INVALID_RESPONSE,
        ], true);
    }
}
