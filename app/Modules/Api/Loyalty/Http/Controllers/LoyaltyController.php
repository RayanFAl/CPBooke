<?php

namespace App\Modules\Api\Loyalty\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Modules\Api\Support\Http\Responses\ApiResponse;
use App\Modules\Loyalty\Services\LoyaltyMobileRatesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LoyaltyController extends Controller
{
    public function __construct(
        private readonly LoyaltyMobileRatesService $loyaltyMobileRatesService,
    ) {}

    /**
     * GET /api/v1/loyalty
     */
    public function show(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->loyaltyMobileRatesService->profile($request->user()),
            'Loyalty profile loaded.',
        );
    }

    /**
     * GET /api/v1/loyalty/rates
     */
    public function rates(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->loyaltyMobileRatesService->ratesForUser($request->user()),
            'Loyalty discount rates loaded.',
        );
    }

    /**
     * GET /api/v1/loyalty/rates/resolve
     *
     * Query:
     * - service_type=flight|hotel|esim|insurance
     * - airline_code=NB (required for flight)
     */
    public function resolve(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'service_type' => ['required', 'string', Rule::in(Order::serviceTypes())],
            'airline_code' => ['nullable', 'string', 'max:16'],
        ]);

        $serviceType = strtolower((string) $validated['service_type']);

        if ($serviceType === Order::SERVICE_TYPE_FLIGHT && blank($validated['airline_code'] ?? null)) {
            return ApiResponse::validation(
                ['airline_code' => ['The airline_code field is required when service_type is flight.']],
                'airline_code is required for flight discounts.',
            );
        }

        return ApiResponse::success(
            $this->loyaltyMobileRatesService->resolve(
                $request->user(),
                $serviceType,
                isset($validated['airline_code']) ? (string) $validated['airline_code'] : null,
            ),
            'Loyalty discount resolved.',
        );
    }
}
