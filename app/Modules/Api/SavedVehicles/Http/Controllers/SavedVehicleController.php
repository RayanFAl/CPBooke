<?php

namespace App\Modules\Api\SavedVehicles\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\SavedVehicle;
use App\Modules\Api\Resources\SavedVehicleResource;
use App\Modules\Api\SavedVehicles\Http\Requests\StoreSavedVehicleRequest;
use App\Modules\Api\SavedVehicles\Http\Requests\UpdateSavedVehicleRequest;
use App\Modules\Api\SavedVehicles\Services\SavedVehicleService;
use App\Modules\Api\Support\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SavedVehicleController extends Controller
{
    public function __construct(
        private readonly SavedVehicleService $savedVehicleService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', SavedVehicle::class);

        $search = $request->query('query', $request->query('q'));
        $search = is_string($search) && trim($search) !== '' ? trim($search) : null;
        $type = $request->query('type');
        $type = is_string($type) && trim($type) !== '' ? trim($type) : null;

        $vehicles = $this->savedVehicleService->paginateForUser(
            $request->user(),
            $search,
            max($request->integer('page', 1), 1),
            (int) min(max($request->integer('per_page', 20), 1), 50),
            $type,
        );

        return ApiResponse::success(
            [
                'vehicles' => SavedVehicleResource::collection($vehicles->items())->resolve($request),
            ],
            'Saved vehicles fetched successfully.',
            $this->savedVehicleService->paginationMeta($vehicles),
        );
    }

    public function store(StoreSavedVehicleRequest $request): JsonResponse
    {
        $this->authorize('create', SavedVehicle::class);

        $vehicle = $this->savedVehicleService->createForUser(
            $request->user(),
            $request->toDto(),
        );

        return ApiResponse::success(
            SavedVehicleResource::make($vehicle)->resolve($request),
            'Vehicle saved successfully.',
            [],
            201,
        );
    }

    public function show(Request $request, SavedVehicle $savedVehicle): JsonResponse
    {
        $this->authorize('view', $savedVehicle);

        return ApiResponse::success(
            SavedVehicleResource::make($savedVehicle)->resolve($request),
            'Saved vehicle fetched successfully.',
        );
    }

    public function update(UpdateSavedVehicleRequest $request, SavedVehicle $savedVehicle): JsonResponse
    {
        $this->authorize('update', $savedVehicle);

        $vehicle = $this->savedVehicleService->update(
            $savedVehicle,
            $request->toDto(),
        );

        return ApiResponse::success(
            SavedVehicleResource::make($vehicle)->resolve($request),
            'Vehicle updated successfully.',
        );
    }

    public function destroy(SavedVehicle $savedVehicle): JsonResponse
    {
        $this->authorize('delete', $savedVehicle);

        $this->savedVehicleService->delete($savedVehicle);

        return ApiResponse::success(
            [],
            'Vehicle deleted successfully.',
        );
    }
}
