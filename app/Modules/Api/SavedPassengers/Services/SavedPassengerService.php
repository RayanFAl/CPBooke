<?php

namespace App\Modules\Api\SavedPassengers\Services;

use App\Models\SavedPassenger;
use App\Models\User;
use App\Modules\Api\DTO\CreateSavedPassengerDTO;
use App\Modules\Api\DTO\UpdateSavedPassengerDTO;
use App\Modules\Api\SavedPassengers\Storage\PassportImageStorage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class SavedPassengerService
{
    public function __construct(
        private readonly PassportImageStorage $passportImageStorage,
    ) {
    }

    /**
     * Paginate saved passengers for the authenticated user.
     */
    public function paginateForUser(User $user, ?string $search = null, int $page = 1, int $perPage = 20): LengthAwarePaginator
    {
        $perPage = min(max($perPage, 1), 50);
        $page = max($page, 1);

        return SavedPassenger::query()
            ->where('user_id', $user->id)
            ->when($search !== null && trim($search) !== '', fn ($query) => $this->applySearch($query, trim($search)))
            ->latest('created_at')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Create a saved passenger for the authenticated user.
     */
    public function createForUser(User $user, CreateSavedPassengerDTO $data): SavedPassenger
    {
        return DB::transaction(function () use ($user, $data): SavedPassenger {
            if ($data->isDefault) {
                $this->clearDefaultForUser($user);
            }

            return SavedPassenger::query()->create($data->toAttributes($user->id));
        });
    }

    /**
     * Update the supplied saved passenger.
     */
    public function update(SavedPassenger $passenger, UpdateSavedPassengerDTO $data): SavedPassenger
    {
        return DB::transaction(function () use ($passenger, $data): SavedPassenger {
            if ($data->isDefault) {
                $this->clearDefaultForUser($passenger->user, $passenger->id);
            }

            $passenger->fill($data->toAttributes());
            $passenger->save();

            return $passenger->refresh();
        });
    }

    /**
     * Soft delete the supplied saved passenger (also removes passport image file).
     */
    public function delete(SavedPassenger $passenger): void
    {
        $passenger->delete();
    }

    public function uploadPassportImage(SavedPassenger $passenger, UploadedFile $file): SavedPassenger
    {
        return $this->passportImageStorage->store($passenger, $file);
    }

    public function deletePassportImage(SavedPassenger $passenger): SavedPassenger
    {
        return $this->passportImageStorage->clear($passenger);
    }

    /**
     * Remove all passport image files for a user (account deletion).
     */
    public function purgePassportImagesForUser(User $user): void
    {
        SavedPassenger::withTrashed()
            ->where('user_id', $user->id)
            ->whereNotNull('passport_image_path')
            ->orderBy('id')
            ->each(function (SavedPassenger $passenger): void {
                $this->passportImageStorage->deleteStoredFile($passenger);
            });
    }

    /**
     * Purge retained passport images older than the configured retention window.
     */
    public function purgeExpiredPassportImages(?int $retentionDays = null): int
    {
        $days = $retentionDays ?? (int) config('saved-passengers.passport_image_retention_days', 365);
        if ($days <= 0) {
            return 0;
        }

        $purged = 0;

        SavedPassenger::withTrashed()
            ->whereNotNull('passport_image_path')
            ->where('passport_image_uploaded_at', '<', now()->subDays($days))
            ->orderBy('id')
            ->chunkById(100, function ($passengers) use (&$purged): void {
                foreach ($passengers as $passenger) {
                    $this->passportImageStorage->clear($passenger);
                    $purged++;
                }
            });

        return $purged;
    }

    /**
     * Build pagination metadata for API responses.
     *
     * @return array<string, mixed>
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
     * Apply indexed search filters at the database level.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<SavedPassenger>  $query
     */
    private function applySearch($query, string $search): void
    {
        $likeTerm = '%'.$search.'%';
        $passportHash = SavedPassenger::hashPassportNumber($search);
        $phoneHash = SavedPassenger::hashPhone($search);

        $query->where(function ($inner) use ($likeTerm, $passportHash, $phoneHash): void {
            $inner->where('first_name', 'like', $likeTerm)
                ->orWhere('last_name', 'like', $likeTerm)
                ->orWhere('passport_number_hash', $passportHash);

            if ($phoneHash !== null) {
                $inner->orWhere('phone_hash', $phoneHash);
            }
        });
    }

    /**
     * Ensure only one default passenger exists per user.
     */
    private function clearDefaultForUser(User $user, ?string $ignorePassengerId = null): void
    {
        SavedPassenger::query()
            ->where('user_id', $user->id)
            ->when($ignorePassengerId, fn ($query) => $query->where('id', '!=', $ignorePassengerId))
            ->update(['is_default' => false]);
    }
}
