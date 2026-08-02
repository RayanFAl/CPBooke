<?php

namespace App\Modules\Partners\Http\Middleware;

use App\Models\Partner;
use App\Models\PartnerApiKey;
use App\Modules\Api\Support\Http\Responses\ApiResponse;
use App\Modules\Partners\Services\PartnerApiKeyService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticatePartnerApiKey
{
    public function __construct(
        private readonly PartnerApiKeyService $apiKeyService,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $plainText = $this->extractKey($request);
        $apiKey = $this->apiKeyService->findActiveByPlainText($plainText);

        if ($apiKey === null) {
            return ApiResponse::error('Invalid or revoked partner API key.', [], 'partner_unauthenticated', 401);
        }

        $this->apiKeyService->touchLastUsed($apiKey);

        $request->attributes->set('partner', $apiKey->partner);
        $request->attributes->set('partner_api_key', $apiKey);

        return $next($request);
    }

    private function extractKey(Request $request): ?string
    {
        $headerKey = $request->header('X-Partner-Key');
        if (is_string($headerKey) && $headerKey !== '') {
            return trim($headerKey);
        }

        $bearer = $request->bearerToken();
        if (is_string($bearer) && str_starts_with($bearer, 'pk_')) {
            return $bearer;
        }

        return null;
    }
}
