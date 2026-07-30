<?php

namespace App\Modules\Api\SavedAddresses\Services;

use App\Models\SavedAddress;
use App\Models\User;
use App\Modules\Api\DTO\CreateSavedAddressDTO;
use App\Modules\Api\DTO\UpdateSavedAddressDTO;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class SavedAddressService
{
    public function paginateForUser(
        User $user,
        int $page = 1,
        int $perPage = 20,
    ): LengthAwarePaginator {
        $perPage = min(max($perPage, 1), 50);
        $page = max($page, 1);

        return SavedAddress::query()
            ->where('user_id', $user->id)
            ->orderByDesc('is_default')
            ->latest('updated_at')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function createForUser(User $user, CreateSavedAddressDTO $data): SavedAddress
    {
        return DB::transaction(function () use ($user, $data): SavedAddress {
            if ($data->isDefault) {
                $this->clearDefaultForUser($user);
            } elseif (! SavedAddress::query()->where('user_id', $user->id)->exists()) {
                // First address becomes default automatically.
                $data = new CreateSavedAddressDTO(
                    title: $data->title,
                    address: $data->address,
                    latitude: $data->latitude,
                    longitude: $data->longitude,
                    isDefault: true,
                );
            }

            return SavedAddress::query()->create($data->toAttributes($user->id));
        });
    }

    public function update(SavedAddress $address, UpdateSavedAddressDTO $data): SavedAddress
    {
        return DB::transaction(function () use ($address, $data): SavedAddress {
            if ($data->isDefault) {
                $this->clearDefaultForUser($address->user, $address->id);
            }

            $address->fill($data->toAttributes());
            $address->save();

            return $address->refresh();
        });
    }

    public function setDefault(SavedAddress $address): SavedAddress
    {
        return DB::transaction(function () use ($address): SavedAddress {
            $this->clearDefaultForUser($address->user, $address->id);

            $address->forceFill(['is_default' => true])->save();

            return $address->refresh();
        });
    }

    public function delete(SavedAddress $address): void
    {
        DB::transaction(function () use ($address): void {
            $wasDefault = $address->is_default;
            $userId = $address->user_id;

            $address->delete();

            if ($wasDefault) {
                $next = SavedAddress::query()
                    ->where('user_id', $userId)
                    ->latest('updated_at')
                    ->first();

                if ($next) {
                    $next->forceFill(['is_default' => true])->save();
                }
            }
        });
    }

    /**
     * @return array{current_page: int, last_page: int, per_page: int, total: int}
     */
    public function paginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }

    private function clearDefaultForUser(User $user, ?string $ignoreAddressId = null): void
    {
        SavedAddress::query()
            ->where('user_id', $user->id)
            ->when($ignoreAddressId, fn ($query) => $query->where('id', '!=', $ignoreAddressId))
            ->update(['is_default' => false]);
    }
}
