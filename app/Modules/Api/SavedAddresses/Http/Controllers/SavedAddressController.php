<?php

namespace App\Modules\Api\SavedAddresses\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\SavedAddress;
use App\Modules\Api\Resources\SavedAddressResource;
use App\Modules\Api\SavedAddresses\Http\Requests\StoreSavedAddressRequest;
use App\Modules\Api\SavedAddresses\Http\Requests\UpdateSavedAddressRequest;
use App\Modules\Api\SavedAddresses\Services\SavedAddressService;
use App\Modules\Api\Support\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SavedAddressController extends Controller
{
    public function __construct(
        private readonly SavedAddressService $savedAddressService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', SavedAddress::class);

        $addresses = $this->savedAddressService->paginateForUser(
            $request->user(),
            max($request->integer('page', 1), 1),
            (int) min(max($request->integer('per_page', 20), 1), 50),
        );

        return ApiResponse::success(
            [
                'addresses' => SavedAddressResource::collection($addresses->items())->resolve($request),
            ],
            'Saved addresses fetched successfully.',
            $this->savedAddressService->paginationMeta($addresses),
        );
    }

    public function store(StoreSavedAddressRequest $request): JsonResponse
    {
        $this->authorize('create', SavedAddress::class);

        $address = $this->savedAddressService->createForUser(
            $request->user(),
            $request->toDto(),
        );

        return ApiResponse::success(
            SavedAddressResource::make($address)->resolve($request),
            'Address saved successfully.',
            [],
            201,
        );
    }

    public function show(Request $request, SavedAddress $savedAddress): JsonResponse
    {
        $this->authorize('view', $savedAddress);

        return ApiResponse::success(
            SavedAddressResource::make($savedAddress)->resolve($request),
            'Saved address fetched successfully.',
        );
    }

    public function update(UpdateSavedAddressRequest $request, SavedAddress $savedAddress): JsonResponse
    {
        $this->authorize('update', $savedAddress);

        $address = $this->savedAddressService->update(
            $savedAddress,
            $request->toDto(),
        );

        return ApiResponse::success(
            SavedAddressResource::make($address)->resolve($request),
            'Address updated successfully.',
        );
    }

    public function setDefault(Request $request, SavedAddress $savedAddress): JsonResponse
    {
        $this->authorize('update', $savedAddress);

        $address = $this->savedAddressService->setDefault($savedAddress);

        return ApiResponse::success(
            SavedAddressResource::make($address)->resolve($request),
            'Default address updated successfully.',
        );
    }

    public function destroy(SavedAddress $savedAddress): JsonResponse
    {
        $this->authorize('delete', $savedAddress);

        $this->savedAddressService->delete($savedAddress);

        return ApiResponse::success(
            ['deleted' => true],
            'Address deleted successfully.',
        );
    }
}
