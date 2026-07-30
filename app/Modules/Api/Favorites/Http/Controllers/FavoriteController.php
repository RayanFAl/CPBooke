<?php

namespace App\Modules\Api\Favorites\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Favorite;
use App\Modules\Api\Favorites\Http\Requests\CheckFavoriteRequest;
use App\Modules\Api\Favorites\Http\Requests\DestroyFavoriteByKeyRequest;
use App\Modules\Api\Favorites\Http\Requests\IndexFavoriteRequest;
use App\Modules\Api\Favorites\Http\Requests\StoreFavoriteRequest;
use App\Modules\Api\Favorites\Services\FavoriteService;
use App\Modules\Api\Resources\FavoriteResource;
use App\Modules\Api\Support\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class FavoriteController extends Controller
{
    public function __construct(
        private readonly FavoriteService $favoriteService,
    ) {
    }

    /**
     * List favorites for the authenticated user.
     */
    public function index(IndexFavoriteRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Favorite::class);

        $result = $this->favoriteService->paginateForUser(
            $request->user(),
            $request->string('type', 'all')->toString() ?: 'all',
            $request->string('status', 'all')->toString() ?: 'all',
            max($request->integer('page', 1), 1),
            (int) min(max($request->integer('per_page', 20), 1), 50),
        );

        /** @var \Illuminate\Contracts\Pagination\LengthAwarePaginator $paginator */
        $paginator = $result['paginator'];

        return ApiResponse::success(
            FavoriteResource::collection($paginator->items())->resolve($request),
            'Favorites fetched successfully.',
            $this->favoriteService->paginationMeta(
                $paginator,
                $result['active_count'],
                $result['expired_count'],
            ),
        );
    }

    /**
     * Store a newly created favorite (idempotent).
     */
    public function store(StoreFavoriteRequest $request): JsonResponse
    {
        $this->authorize('create', Favorite::class);

        $result = $this->favoriteService->createForUser(
            $request->user(),
            $request->toDto(),
        );

        $created = $result['created'];

        return ApiResponse::success(
            FavoriteResource::make($result['favorite'])->resolve($request),
            $created ? 'Favorite saved successfully.' : 'Favorite already exists.',
            [],
            $created ? 201 : 200,
        );
    }

    /**
     * Quickly check whether an item is favorited.
     */
    public function check(CheckFavoriteRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Favorite::class);

        $result = $this->favoriteService->check(
            $request->user(),
            $request->string('type')->toString(),
            $request->string('item_key')->toString(),
        );

        return ApiResponse::success(
            $result,
            'Favorite check completed.',
        );
    }

    /**
     * Hard delete the specified favorite by id.
     */
    public function destroy(Favorite $favorite): JsonResponse
    {
        $this->authorize('delete', $favorite);

        $this->favoriteService->delete($favorite);

        return ApiResponse::success(
            [],
            'Favorite removed successfully.',
        );
    }

    /**
     * Hard delete a favorite by type + item_key query params.
     */
    public function destroyByKey(DestroyFavoriteByKeyRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Favorite::class);

        $deleted = $this->favoriteService->deleteByKey(
            $request->user(),
            $request->string('type')->toString(),
            $request->string('item_key')->toString(),
        );

        if (! $deleted) {
            return ApiResponse::error(
                'Favorite not found.',
                [],
                'favorite_not_found',
                404,
            );
        }

        return ApiResponse::success(
            [],
            'Favorite removed successfully.',
        );
    }
}
