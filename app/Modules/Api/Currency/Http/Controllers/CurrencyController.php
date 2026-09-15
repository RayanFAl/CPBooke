<?php

namespace App\Modules\Api\Currency\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ExchangeRate;
use App\Modules\Api\Currency\Http\Requests\ConvertCurrencyRequest;
use App\Modules\Api\Support\Http\Responses\ApiResponse;
use App\Modules\ExchangeRates\Services\ExchangeRateService;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

class CurrencyController extends Controller
{
    public function __construct(
        private readonly ExchangeRateService $exchangeRateService,
    ) {
    }

    public function rates(): JsonResponse
    {
        $payload = $this->exchangeRateService->getRatesPayload();

        return ApiResponse::success([
            'base_currency' => $payload['base_currency'],
            'rates' => $payload['rates'],
            'updated_at' => $payload['updated_at'],
        ], 'Exchange rates fetched successfully.');
    }

    public function convert(ConvertCurrencyRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $result = $this->exchangeRateService->convert(
                $validated['amount'],
                $validated['from'],
                $validated['to'],
                $validated['side'] ?? ExchangeRate::SIDE_MID,
            );
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), [], 'invalid_currency_conversion', 422);
        }

        return ApiResponse::success($result, 'Currency converted successfully.');
    }
}
