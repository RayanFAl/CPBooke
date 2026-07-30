<?php

namespace App\Modules\Api\Favorites\Services;

use App\Models\Favorite;
use App\Models\User;
use App\Modules\Api\DTO\CreateFavoriteDTO;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FavoriteService
{
    /**
     * Expire flight favorites whose expires_at has passed.
     */
    public function expireStaleForUser(User $user): int
    {
        return Favorite::query()
            ->forUser($user)
            ->ofType(Favorite::TYPE_FLIGHT)
            ->where('status', Favorite::STATUS_ACTIVE)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', Carbon::now())
            ->update(['status' => Favorite::STATUS_EXPIRED]);
    }

    /**
     * Paginate favorites for the authenticated user.
     *
     * @return array{paginator: LengthAwarePaginator, active_count: int, expired_count: int}
     */
    public function paginateForUser(
        User $user,
        string $type = 'all',
        string $status = 'all',
        int $page = 1,
        int $perPage = 20,
    ): array {
        $this->expireStaleForUser($user);

        $perPage = min(max($perPage, 1), 50);
        $page = max($page, 1);

        $baseQuery = Favorite::query()->forUser($user);

        if ($type !== 'all') {
            $baseQuery->ofType($type);
        }

        $activeCount = (clone $baseQuery)->withStatus(Favorite::STATUS_ACTIVE)->count();
        $expiredCount = (clone $baseQuery)->withStatus(Favorite::STATUS_EXPIRED)->count();

        $listQuery = Favorite::query()->forUser($user);

        if ($type !== 'all') {
            $listQuery->ofType($type);
        }

        if ($status !== 'all') {
            $listQuery->withStatus($status);
        }

        $paginator = $listQuery
            ->latest('created_at')
            ->paginate($perPage, ['*'], 'page', $page);

        return [
            'paginator' => $paginator,
            'active_count' => $activeCount,
            'expired_count' => $expiredCount,
        ];
    }

    /**
     * Create or return an existing favorite (idempotent by user/type/item_key).
     *
     * @return array{favorite: Favorite, created: bool}
     */
    public function createForUser(User $user, CreateFavoriteDTO $data): array
    {
        return DB::transaction(function () use ($user, $data): array {
            $existing = Favorite::query()
                ->forUser($user)
                ->where('type', $data->type)
                ->where('item_key', $data->itemKey)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                $existing->syncExpiryStatus();

                return [
                    'favorite' => $existing->refresh(),
                    'created' => false,
                ];
            }

            $currentCount = Favorite::query()
                ->forUser($user)
                ->ofType($data->type)
                ->count();

            if ($currentCount >= Favorite::MAX_PER_TYPE) {
                throw ValidationException::withMessages([
                    'type' => [sprintf(
                        'You can save at most %d %s favorites.',
                        Favorite::MAX_PER_TYPE,
                        $data->type,
                    )],
                ]);
            }

            $status = Favorite::STATUS_ACTIVE;

            if (
                $data->type === Favorite::TYPE_FLIGHT
                && $data->expiresAt !== null
                && $data->expiresAt->lessThanOrEqualTo(Carbon::now())
            ) {
                $status = Favorite::STATUS_EXPIRED;
            }

            $favorite = Favorite::query()->create(
                $data->toAttributes($user->id, $status),
            );

            return [
                'favorite' => $favorite,
                'created' => true,
            ];
        });
    }

    /**
     * Find a favorite by type and item key for the user.
     */
    public function findByKey(User $user, string $type, string $itemKey): ?Favorite
    {
        $favorite = Favorite::query()
            ->forUser($user)
            ->where('type', $type)
            ->where('item_key', $itemKey)
            ->first();

        if ($favorite !== null) {
            $favorite->syncExpiryStatus();
            $favorite->refresh();
        }

        return $favorite;
    }

    /**
     * Check whether an item is favorited.
     *
     * @return array{is_favorite: bool, favorite_id: string|null}
     */
    public function check(User $user, string $type, string $itemKey): array
    {
        $favorite = $this->findByKey($user, $type, $itemKey);

        return [
            'is_favorite' => $favorite !== null,
            'favorite_id' => $favorite?->id,
        ];
    }

    /**
     * Hard-delete a favorite.
     */
    public function delete(Favorite $favorite): void
    {
        $favorite->delete();
    }

    /**
     * Delete by type + item_key for the user.
     */
    public function deleteByKey(User $user, string $type, string $itemKey): bool
    {
        $favorite = Favorite::query()
            ->forUser($user)
            ->where('type', $type)
            ->where('item_key', $itemKey)
            ->first();

        if ($favorite === null) {
            return false;
        }

        $this->delete($favorite);

        return true;
    }

    /**
     * Build pagination metadata for API responses.
     *
     * @return array<string, mixed>
     */
    public function paginationMeta(LengthAwarePaginator $paginator, int $activeCount, int $expiredCount): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'active_count' => $activeCount,
            'expired_count' => $expiredCount,
        ];
    }
}
