<?php

namespace App\Modules\Api\Currency\Http\Controllers;

use App\Http\Controllers\Controller;
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

        // Cast rate strings to numeric values for JSON (6.5 not "6.50000000").
        $rates = [];
        foreach ($payload['rates'] as $code => $rate) {
            $rates[$code] = (float) $rate;
        }

        return ApiResponse::success([
            'base_currency' => $payload['base_currency'],
            'rates' => $rates,
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
            );
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), [], 'invalid_currency_conversion', 422);
        }

        return ApiResponse::success($result, 'Currency converted successfully.');
    }
}
