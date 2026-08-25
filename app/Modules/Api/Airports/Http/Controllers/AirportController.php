<?php

namespace App\Modules\Api\Airports\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Api\Airports\Services\AirportService;
use App\Modules\Api\Resources\AirportResource;
use App\Modules\Api\Resources\FeaturedAirportResource;
use App\Modules\Api\Support\Http\Responses\ApiResponse;
use App\Support\Airports\AirportPopularityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AirportController extends Controller
{
    public function __construct(
        private readonly AirportService $airportService,
        private readonly AirportPopularityService $airportPopularityService,
    ) {
    }

    /**
     * Best locations configured by admin (max 10).
     */
    public function featured(Request $request): JsonResponse
    {
        $featured = $this->airportService->featured();

        return ApiResponse::success(
            [
                'featured' => FeaturedAirportResource::collection($featured)->resolve($request),
            ],
            'Featured airports fetched successfully.',
        );
    }

    /**
     * Search and paginate airports. Without `q`/`search`, returns featured airports then popular app routes.
     */
    public function index(Request $request): JsonResponse
    {
        $search = $this->resolveSearchQuery($request);

        if ($search === '') {
            $airports = $this->airportService->browse();

            return ApiResponse::success(
                [
                    'airports' => FeaturedAirportResource::collection($airports)->resolve($request),
                ],
                'Featured airports fetched successfully.',
            );
        }

        $this->airportPopularityService->recordExactAirportQuery($search);

        $page = max($request->integer('page', 1), 1);
        $perPage = (int) min(max($request->integer('per_page', 20), 1), 50);

        $result = $this->airportService->paginate($search, $page, $perPage);

        return ApiResponse::success(
            [
                'airports' => AirportResource::collection($result['items'])->resolve($request),
            ],
            'Airports fetched successfully.',
            $this->airportService->paginationMeta($page, $perPage, $result['total']),
        );
    }

    private function resolveSearchQuery(Request $request): string
    {
        foreach (['q', 'search'] as $parameter) {
            $value = trim($request->string($parameter)->toString());

            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }
}
