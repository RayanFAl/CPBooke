<?php

namespace App\Modules\Api\SavedVehicles\Services;

use App\Models\SavedVehicle;
use App\Models\User;
use App\Modules\Api\DTO\CreateSavedVehicleDTO;
use App\Modules\Api\DTO\UpdateSavedVehicleDTO;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class SavedVehicleService
{
    public function paginateForUser(
        User $user,
        ?string $search = null,
        int $page = 1,
        int $perPage = 20,
        ?string $type = null,
    ): LengthAwarePaginator {
        $perPage = min(max($perPage, 1), 50);
        $page = max($page, 1);
        $type = is_string($type) ? trim($type) : null;
        if ($type !== null && $type !== '' && ! in_array($type, SavedVehicle::types(), true)) {
            $type = null;
        }

        return SavedVehicle::query()
            ->where('user_id', $user->id)
            ->when($type !== null && $type !== '', fn ($query) => $query->where('type', $type))
            ->when($search !== null && trim($search) !== '', fn ($query) => $this->applySearch($query, trim($search)))
            ->latest('created_at')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function createForUser(User $user, CreateSavedVehicleDTO $data): SavedVehicle
    {
        return DB::transaction(function () use ($user, $data): SavedVehicle {
            if ($data->isDefault) {
                $this->clearDefaultForUser($user);
            }

            return SavedVehicle::query()->create($data->toAttributes($user->id));
        });
    }

    public function update(SavedVehicle $vehicle, UpdateSavedVehicleDTO $data): SavedVehicle
    {
        return DB::transaction(function () use ($vehicle, $data): SavedVehicle {
            if ($data->isDefault) {
                $this->clearDefaultForUser($vehicle->user, $vehicle->id);
            }

            $vehicle->fill($data->toAttributes());
            $vehicle->save();

            return $vehicle->refresh();
        });
    }

    public function delete(SavedVehicle $vehicle): void
    {
        $vehicle->delete();
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

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<SavedVehicle>  $query
     */
    private function applySearch($query, string $search): void
    {
        $likeTerm = '%'.$search.'%';
        $phoneHash = SavedVehicle::hashPhone($search);
        $chassisHash = SavedVehicle::hashChassis($search);
        $plateHash = SavedVehicle::hashPlate($search);

        $query->where(function ($inner) use ($likeTerm, $phoneHash, $chassisHash, $plateHash): void {
            $inner
                ->where('beneficiary_name', 'like', $likeTerm)
                ->orWhere('label', 'like', $likeTerm)
                ->orWhere('vehicle_chassis_number_hash', $chassisHash)
                ->orWhere('vehicle_plate_number_hash', $plateHash);

            if ($phoneHash !== null) {
                $inner->orWhere('beneficiary_phone_hash', $phoneHash);
            }
        });
    }

    private function clearDefaultForUser(User $user, ?string $ignoreVehicleId = null): void
    {
        SavedVehicle::query()
            ->where('user_id', $user->id)
            ->when($ignoreVehicleId, fn ($query) => $query->where('id', '!=', $ignoreVehicleId))
            ->update(['is_default' => false]);
    }
}
