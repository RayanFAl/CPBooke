<?php

namespace App\Modules\Api\User\Services;

use App\Models\CustomerWallet;
use App\Models\Favorite;
use App\Models\HotelReview;
use App\Models\LinkedAccount;
use App\Models\LinkedAccountRequest;
use App\Models\LoyaltyHistory;
use App\Models\Order;
use App\Models\PriceAlert;
use App\Models\RefreshToken;
use App\Models\SavedAddress;
use App\Models\SavedPassenger;
use App\Models\SavedVehicle;
use App\Models\SeatAlert;
use App\Models\TravelSearchIntent;
use App\Models\User;
use App\Models\UserLoyaltyProfile;
use App\Models\UserNotification;
use App\Models\UserNotificationDevice;
use App\Models\UserNotificationPreference;
use App\Modules\Api\Auth\Services\ApiTokenService;
use App\Modules\Api\SavedPassengers\Services\SavedPassengerService;
use App\Modules\Audit\Services\AuditRecorder;
use App\Notifications\AccountDeletionCompletedNotification;
use App\Notifications\AccountDeletionScheduledNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Throwable;

class CustomerAccountDeletionService
{
    public const MODE_IMMEDIATE = 'immediate';

    public const MODE_SCHEDULED = 'scheduled';

    public const GRACE_PERIOD_DAYS = 365;

    public function __construct(
        private readonly ApiTokenService $apiTokenService,
        private readonly SavedPassengerService $savedPassengerService,
        private readonly AuditRecorder $auditRecorder,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function requestDeletion(User $user, string $mode): array
    {
        if (! $user->isCustomerAccount()) {
            throw ValidationException::withMessages([
                'account' => ['Only customer accounts can be deleted from the mobile app.'],
            ]);
        }

        if ($user->isDeletedCustomerAccount()) {
            return $this->immediateDeletionPayload($user);
        }

        if ($mode !== self::MODE_SCHEDULED) {
            throw ValidationException::withMessages([
                'mode' => ['Account deletion from the app is scheduled only.'],
            ]);
        }

        return $this->schedule($user);
    }

    /**
     * Schedule mobile self-service account deletion. Permanent closure runs after the grace period.
     *
     * @return array<string, mixed>
     */
    public function delete(User $user, string $mode = self::MODE_SCHEDULED): array
    {
        return $this->requestDeletion($user, $mode);
    }

    /**
     * @return array<string, mixed>
     */
    public function schedule(User $user): array
    {
        if (! $user->isCustomerAccount()) {
            throw ValidationException::withMessages([
                'account' => ['Only customer accounts can be deleted from the mobile app.'],
            ]);
        }

        if ($user->isDeletedCustomerAccount()) {
            return $this->immediateDeletionPayload($user);
        }

        if ($user->hasPendingAccountDeletion()) {
            return $this->scheduledDeletionPayload($user);
        }

        $this->assertWalletAllowsDeletion($user);
        $this->assertNoBlockingOrders($user);

        $scheduledAt = now();
        $dueAt = $scheduledAt->copy()->addDays(self::GRACE_PERIOD_DAYS);

        DB::transaction(function () use ($user, $scheduledAt, $dueAt): void {
            $this->revokeAccess($user);

            $user->forceFill([
                'is_active' => false,
                'deletion_scheduled_at' => $scheduledAt,
                'deletion_due_at' => $dueAt,
            ])->save();

            if (Schema::hasTable('customer_wallets')) {
                CustomerWallet::query()
                    ->where('user_id', $user->id)
                    ->update(['status' => CustomerWallet::STATUS_FROZEN]);
            }

            $this->auditRecorder->record(
                module: 'customer',
                action: 'account.deletion_scheduled',
                subject: 'Customer account deletion scheduled via mobile API',
                entityType: 'user',
                entityId: $user->id,
                actor: $user,
                oldValues: null,
                newValues: [
                    'user_id' => $user->id,
                    'deletion_scheduled_at' => $scheduledAt->toIso8601String(),
                    'deletion_due_at' => $dueAt->toIso8601String(),
                    'grace_period_days' => self::GRACE_PERIOD_DAYS,
                ],
                context: [
                    'channel' => 'mobile_api',
                    'mode' => self::MODE_SCHEDULED,
                ],
            );
        });

        $user->refresh();

        $this->notifyScheduled($user);

        return $this->scheduledDeletionPayload($user);
    }

    /**
     * Cancel a pending scheduled deletion and restore account access.
     *
     * @return array<string, mixed>
     */
    public function cancel(User $user): array
    {
        if (! $user->isCustomerAccount()) {
            throw ValidationException::withMessages([
                'account' => ['Only customer accounts can cancel account deletion from the mobile app.'],
            ]);
        }

        if ($user->isDeletedCustomerAccount()) {
            throw ValidationException::withMessages([
                'account' => ['This account has already been permanently deleted.'],
            ]);
        }

        if (! $user->hasPendingAccountDeletion()) {
            throw ValidationException::withMessages([
                'account' => ['This account is not scheduled for deletion.'],
            ]);
        }

        DB::transaction(function () use ($user): void {
            $user->forceFill([
                'is_active' => true,
                'deletion_scheduled_at' => null,
                'deletion_due_at' => null,
            ])->save();

            if (Schema::hasTable('customer_wallets')) {
                CustomerWallet::query()
                    ->where('user_id', $user->id)
                    ->where('status', CustomerWallet::STATUS_FROZEN)
                    ->update(['status' => CustomerWallet::STATUS_ACTIVE]);
            }

            $this->auditRecorder->record(
                module: 'customer',
                action: 'account.deletion_cancelled',
                subject: 'Customer account deletion cancelled via mobile API',
                entityType: 'user',
                entityId: $user->id,
                actor: $user,
                oldValues: null,
                newValues: [
                    'user_id' => $user->id,
                    'cancelled_at' => now()->toIso8601String(),
                ],
                context: [
                    'channel' => 'mobile_api',
                ],
            );
        });

        return [
            'mode' => 'cancelled',
            'deletion_scheduled_at' => null,
            'deletion_due_at' => null,
            'is_active' => true,
        ];
    }

    /**
     * Permanently delete accounts whose scheduled grace period has ended.
     */
    public function processDueDeletions(?int $limit = 100): int
    {
        $processed = 0;

        User::query()
            ->where('account_type', User::ACCOUNT_TYPE_CUSTOMER)
            ->whereNotNull('deletion_due_at')
            ->where('deletion_due_at', '<=', now())
            ->whereNull('account_deleted_at')
            ->orderBy('deletion_due_at')
            ->limit($limit ?? 100)
            ->get()
            ->each(function (User $user) use (&$processed): void {
                try {
                    $this->deleteNow($user, self::MODE_SCHEDULED);
                    $processed++;
                } catch (Throwable $exception) {
                    Log::warning('Scheduled account deletion failed', [
                        'user_id' => $user->id,
                        'message' => $exception->getMessage(),
                    ]);
                }
            });

        return $processed;
    }

    /**
     * @return array<string, mixed>
     */
    private function deleteNow(User $user, string $mode = self::MODE_IMMEDIATE): array
    {
        if (! $user->isCustomerAccount()) {
            throw ValidationException::withMessages([
                'account' => ['Only customer accounts can be deleted from the mobile app.'],
            ]);
        }

        if ($user->trashed() || $user->account_deleted_at !== null) {
            return $this->immediateDeletionPayload($user);
        }

        $this->assertWalletAllowsDeletion($user);
        $this->assertNoBlockingOrders($user);

        $userId = $user->id;
        $email = (string) $user->email;
        $displayName = (string) ($user->full_name ?: $user->name ?: 'Customer');

        DB::transaction(function () use ($user, $userId, $mode): void {
            $this->revokeAccess($user);

            if ($user->avatar_path) {
                Storage::disk('public')->delete($user->avatar_path);
            }

            $this->savedPassengerService->purgePassportImagesForUser($user);

            $this->purgePersonalRecords($userId);

            $this->anonymizeAndCloseAccount($user);

            $this->auditRecorder->record(
                module: 'customer',
                action: 'account.deleted',
                subject: 'Customer account deleted via mobile API',
                entityType: 'user',
                entityId: $userId,
                actor: $user,
                oldValues: null,
                newValues: [
                    'user_id' => $userId,
                    'account_deleted_at' => now()->toIso8601String(),
                    'mode' => $mode,
                ],
                context: [
                    'channel' => 'mobile_api',
                    'mode' => $mode,
                ],
            );
        });

        $this->notifyCompleted($email, $displayName, $mode);

        $tombstone = User::withTrashed()->findOrFail($userId);

        return $this->immediateDeletionPayload($tombstone);
    }

    private function revokeAccess(User $user): void
    {
        $this->apiTokenService->revokeAllSessions($user);

        RefreshToken::query()
            ->where('user_id', $user->id)
            ->update(['revoked_at' => now()]);

        PersonalAccessToken::query()
            ->where('tokenable_type', $user->getMorphClass())
            ->where('tokenable_id', $user->id)
            ->delete();

        if (Schema::hasTable('sessions')) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }
    }

    private function assertWalletAllowsDeletion(User $user): void
    {
        if (! Schema::hasTable('customer_wallets')) {
            return;
        }

        $hasPositiveBalance = CustomerWallet::query()
            ->where('user_id', $user->id)
            ->whereRaw('CAST(balance AS DECIMAL(14,2)) > 0')
            ->exists();

        if ($hasPositiveBalance) {
            throw ValidationException::withMessages([
                'wallet' => ['Your wallet still has a balance. Please use or withdraw remaining funds before deleting your account.'],
            ]);
        }
    }

    private function purgePersonalRecords(int $userId): void
    {
        Favorite::query()->where('user_id', $userId)->delete();
        SavedPassenger::query()->where('user_id', $userId)->forceDelete();
        SavedVehicle::query()->where('user_id', $userId)->delete();
        SavedAddress::query()->where('user_id', $userId)->delete();
        TravelSearchIntent::query()->where('user_id', $userId)->delete();
        PriceAlert::query()->where('user_id', $userId)->delete();

        if (Schema::hasTable('seat_alerts')) {
            SeatAlert::query()->where('user_id', $userId)->delete();
        }

        UserNotification::query()->where('user_id', $userId)->delete();
        UserNotificationDevice::query()->where('user_id', $userId)->delete();
        UserNotificationPreference::query()->where('user_id', $userId)->delete();

        if (Schema::hasTable('linked_account_requests')) {
            LinkedAccountRequest::query()
                ->where(function ($query) use ($userId): void {
                    $query->where('from_user_id', $userId)->orWhere('to_user_id', $userId);
                })
                ->delete();
        }

        if (Schema::hasTable('linked_accounts')) {
            LinkedAccount::query()
                ->where(function ($query) use ($userId): void {
                    $query->where('user_id', $userId)->orWhere('linked_user_id', $userId);
                })
                ->delete();
        }

        UserLoyaltyProfile::query()->where('user_id', $userId)->delete();
        LoyaltyHistory::query()->where('user_id', $userId)->delete();
        HotelReview::query()->where('user_id', $userId)->delete();
    }

    private function anonymizeAndCloseAccount(User $user): void
    {
        $placeholderEmail = sprintf(
            'deleted+%d+%s@account.invalid',
            $user->id,
            Str::lower(Str::random(12)),
        );

        $user->forceFill([
            'name' => 'Deleted User',
            'full_name' => 'Deleted User',
            'email' => $placeholderEmail,
            'phone' => null,
            'country' => null,
            'avatar_path' => null,
            'google_id' => null,
            'password' => Hash::make(Str::random(64)),
            'is_active' => false,
            'email_verified_at' => null,
            'phone_verified_at' => null,
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
            'remember_token' => null,
            'last_login_at' => null,
            'deletion_scheduled_at' => null,
            'deletion_due_at' => null,
            'account_deleted_at' => now(),
        ])->save();

        if (Schema::hasTable('customer_wallets')) {
            CustomerWallet::query()
                ->where('user_id', $user->id)
                ->update(['status' => CustomerWallet::STATUS_FROZEN]);
        }

        $user->delete();
    }

    private function assertNoBlockingOrders(User $user): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        $hasOpenOrders = Order::query()
            ->where('customer_id', $user->id)
            ->whereIn('status', [
                Order::STATUS_PENDING_PAYMENT,
                Order::STATUS_PAID,
                Order::STATUS_PROCESSING,
            ])
            ->exists();

        if ($hasOpenOrders) {
            throw ValidationException::withMessages([
                'orders' => ['You have active bookings in progress. Please complete or cancel them before deleting your account.'],
            ]);
        }
    }

    private function notifyScheduled(User $user): void
    {
        if (! filled($user->email)) {
            return;
        }

        try {
            $user->notify(new AccountDeletionScheduledNotification(
                $user->deletion_due_at ?? now()->addDays(self::GRACE_PERIOD_DAYS),
                self::GRACE_PERIOD_DAYS,
            ));
        } catch (Throwable $exception) {
            Log::warning('Account deletion scheduled notification failed', [
                'user_id' => $user->id,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function notifyCompleted(string $email, string $displayName, string $mode): void
    {
        if ($email === '' || str_ends_with($email, '@account.invalid')) {
            return;
        }

        try {
            Notification::route('mail', $email)
                ->notify(new AccountDeletionCompletedNotification($displayName, $mode));
        } catch (Throwable $exception) {
            Log::warning('Account deletion completed notification failed', [
                'email' => $email,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function scheduledDeletionPayload(User $user): array
    {
        return [
            'mode' => self::MODE_SCHEDULED,
            'grace_period_days' => self::GRACE_PERIOD_DAYS,
            'deletion_scheduled_at' => $user->deletion_scheduled_at?->toIso8601String(),
            'deletion_due_at' => $user->deletion_due_at?->toIso8601String(),
            'account_deleted_at' => null,
            'can_cancel' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function immediateDeletionPayload(User $user): array
    {
        return [
            'mode' => self::MODE_IMMEDIATE,
            'grace_period_days' => 0,
            'deletion_scheduled_at' => null,
            'deletion_due_at' => null,
            'account_deleted_at' => $user->account_deleted_at?->toIso8601String()
                ?? $user->deleted_at?->toIso8601String()
                ?? now()->toIso8601String(),
            'can_cancel' => false,
        ];
    }
}
