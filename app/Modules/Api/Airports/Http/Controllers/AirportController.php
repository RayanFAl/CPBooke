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
use Illuminate\Support\Facades\Validator;

class AirportController extends Controller
{
    public function __construct(
        private readonly AirportService $airportService,
        private readonly AirportPopularityService $airportPopularityService,
    ) {
    }

    /**
     * Airports near a GPS point. Falls back to featured airports when nothing is inside the radius.
     */
    public function nearby(Request $request): JsonResponse
    {
        $validator = Validator::make($request->query(), [
            'lat' => ['required_without:latitude', 'numeric', 'between:-90,90'],
            'latitude' => ['required_without:lat', 'numeric', 'between:-90,90'],
            'lng' => ['required_without:longitude', 'numeric', 'between:-180,180'],
            'longitude' => ['required_without:lng', 'numeric', 'between:-180,180'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:20'],
            'radius_km' => ['sometimes', 'numeric', 'min:1', 'max:500'],
        ]);

        if ($validator->fails()) {
            return ApiResponse::validation($validator->errors()->toArray());
        }

        $latitude = (float) ($request->input('lat', $request->input('latitude')));
        $longitude = (float) ($request->input('lng', $request->input('longitude')));
        $result = $this->airportService->nearby(
            $latitude,
            $longitude,
            $request->integer('limit', 10),
            (float) $request->input('radius_km', 40),
        );

        $airports = $result['airports']
            ->map(fn (array $airport): array => $this->nearbyPayload($airport, $request))
            ->values()
            ->all();

        return ApiResponse::success(
            [
                'airports' => $airports,
                'source' => $result['source'],
            ],
            $result['source'] === 'nearby'
                ? 'Nearby airports fetched successfully.'
                : 'No airports in range. Featured airports returned.',
        );
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

    /**
     * @param  array<string, mixed>  $airport
     * @return array<string, mixed>
     */
    private function nearbyPayload(array $airport, Request $request): array
    {
        return array_merge(
            AirportResource::make($airport)->resolve($request),
            [
                'iata_code' => $airport['iata_code'] ?? null,
                'icao_code' => $airport['icao_code'] ?? null,
                'name_en' => $airport['name_en'] ?? null,
                'name_ar' => $airport['name_ar'] ?? null,
                'city_en' => $airport['city_en'] ?? null,
                'city_ar' => $airport['city_ar'] ?? null,
                'country_iso2' => $airport['country_iso2'] ?? null,
                'country_name_en' => $airport['country_name_en'] ?? null,
                'latitude_deg' => $airport['latitude_deg'] ?? null,
                'longitude_deg' => $airport['longitude_deg'] ?? null,
                'distance_km' => $airport['distance_km'] ?? null,
            ],
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
