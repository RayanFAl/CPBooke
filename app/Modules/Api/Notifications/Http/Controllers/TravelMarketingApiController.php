<?php

namespace App\Modules\Api\Notifications\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\PriceAlert;
use App\Modules\Api\Notifications\Http\Requests\StorePriceAlertRequest;
use App\Modules\Api\Notifications\Http\Requests\UpsertTravelSearchIntentRequest;
use App\Modules\Api\Support\Http\Responses\ApiResponse;
use App\Modules\Notifications\Services\TravelMarketingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TravelMarketingApiController extends Controller
{
    public function __construct(
        private readonly TravelMarketingService $travelMarketingService,
    ) {}

    public function storeSearchIntent(UpsertTravelSearchIntentRequest $request): JsonResponse
    {
        $intent = $this->travelMarketingService->recordSearch(
            $request->user(),
            $request->validated(),
        );

        return ApiResponse::success(
            [
                'id' => (string) $intent->id,
                'origin' => $intent->origin,
                'destination' => $intent->destination,
                'departure_date' => $intent->departure_date?->toDateString(),
                'return_date' => $intent->return_date?->toDateString(),
                'last_seen_price' => $intent->last_seen_price !== null ? (float) $intent->last_seen_price : null,
                'currency' => $intent->currency,
                'converted' => $intent->converted_at !== null,
                'deep_link' => $intent->deepLink(),
            ],
            'Search intent saved.',
        );
    }

    public function priceAlerts(Request $request): JsonResponse
    {
        $alerts = PriceAlert::query()
            ->where('user_id', $request->user()->id)
            ->where('is_active', true)
            ->latest('id')
            ->get()
            ->map(fn (PriceAlert $alert): array => $alert->toPassengerArray())
            ->values()
            ->all();

        return ApiResponse::success($alerts, 'Price alerts fetched successfully.');
    }

    public function storePriceAlert(StorePriceAlertRequest $request): JsonResponse
    {
        $alert = $this->travelMarketingService->upsertPriceAlert(
            $request->user(),
            $request->validated(),
        );

        return ApiResponse::success($alert->toPassengerArray(), 'Price alert saved.');
    }

    public function destroyPriceAlert(Request $request, PriceAlert $priceAlert): JsonResponse
    {
        abort_unless((int) $priceAlert->user_id === (int) $request->user()->id, 404);

        $priceAlert->forceFill(['is_active' => false])->save();

        return ApiResponse::success(
            ['id' => (string) $priceAlert->id, 'is_active' => false],
            'Price alert disabled.',
        );
    }
}
