<?php

namespace App\Modules\Api\Pricing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Api\Pricing\Http\Requests\PricingPreviewRequest;
use App\Modules\Pricing\Services\PricingPreviewService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;

class PricingController extends Controller
{
    public function __construct(
        private readonly PricingPreviewService $pricingPreviewService,
    ) {
    }

    /**
     * Return a read-only pricing preview with no persistence or side effects.
     *
     * @throws AuthorizationException
     */
    public function preview(PricingPreviewRequest $request): JsonResponse
    {
        $requestedUserId = $request->integer('user_id');
        $authenticatedUserId = $request->user()?->id;

        if ($requestedUserId !== 0 && $requestedUserId !== null && $requestedUserId !== $authenticatedUserId) {
            throw new AuthorizationException('You are not authorized to preview pricing for another user.');
        }

        return response()->json([
            'data' => $this->pricingPreviewService->preview($request->validated(), $request->user()),
        ]);
    }
}