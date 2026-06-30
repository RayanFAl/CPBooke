<?php

namespace App\Modules\Api\SavedPassengers\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\SavedPassenger;
use App\Modules\Api\Resources\SavedPassengerResource;
use App\Modules\Api\SavedPassengers\Http\Requests\StoreSavedPassengerRequest;
use App\Modules\Api\SavedPassengers\Http\Requests\UpdateSavedPassengerRequest;
use App\Modules\Api\SavedPassengers\Services\SavedPassengerService;
use App\Modules\Api\Support\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SavedPassengerController extends Controller
{
    public function __construct(
        private readonly SavedPassengerService $savedPassengerService,
    ) {
    }

    /**
     * List saved passengers for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', SavedPassenger::class);

        $passengers = $this->savedPassengerService->paginateForUser(
            $request->user(),
            $request->string('q')->toString() ?: null,
            max($request->integer('page', 1), 1),
            (int) min(max($request->integer('per_page', 20), 1), 50),
        );

        return ApiResponse::success(
            [
                'passengers' => SavedPassengerResource::collection($passengers->items())->resolve($request),
            ],
            'Saved passengers fetched successfully.',
            $this->savedPassengerService->paginationMeta($passengers),
        );
    }

    /**
     * Store a newly created saved passenger.
     */
    public function store(StoreSavedPassengerRequest $request): JsonResponse
    {
        $this->authorize('create', SavedPassenger::class);

        $passenger = $this->savedPassengerService->createForUser(
            $request->user(),
            $request->toDto(),
        );

        return ApiResponse::success(
            SavedPassengerResource::make($passenger)->resolve($request),
            'Passenger saved successfully',
            [],
            201,
        );
    }

    /**
     * Display the specified saved passenger.
     */
    public function show(Request $request, SavedPassenger $savedPassenger): JsonResponse
    {
        $this->authorize('view', $savedPassenger);

        return ApiResponse::success(
            SavedPassengerResource::make($savedPassenger)->resolve($request),
            'Saved passenger fetched successfully.',
        );
    }

    /**
     * Update the specified saved passenger.
     */
    public function update(UpdateSavedPassengerRequest $request, SavedPassenger $savedPassenger): JsonResponse
    {
        $this->authorize('update', $savedPassenger);

        $passenger = $this->savedPassengerService->update(
            $savedPassenger,
            $request->toDto(),
        );

        return ApiResponse::success(
            SavedPassengerResource::make($passenger)->resolve($request),
            'Passenger updated successfully.',
        );
    }

    /**
     * Soft delete the specified saved passenger.
     */
    public function destroy(SavedPassenger $savedPassenger): JsonResponse
    {
        $this->authorize('delete', $savedPassenger);

        $this->savedPassengerService->delete($savedPassenger);

        return ApiResponse::success(
            [],
            'Passenger deleted successfully.',
        );
    }
}
