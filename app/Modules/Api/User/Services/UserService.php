<?php

namespace App\Modules\Api\User\Services;

use App\Models\User;
use App\Modules\Api\DTO\UpdateProfileDTO;
use App\Modules\Api\SavedPassengers\Services\SavedPassengerService;
use App\Modules\Loyalty\Services\LoyaltyService;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UserService
{
    public function __construct(
        private readonly LoyaltyService $loyaltyService,
        private readonly ProfileOtpService $profileOtpService,
        private readonly SavedPassengerService $savedPassengerService,
        private readonly CustomerAccountDeletionService $customerAccountDeletionService,
    ) {}

    /**
     * Return the authenticated user's current profile entity.
     */
    public function profile(User $user): User
    {
        return tap($user, function (User $profile): void {
            $profile->setAttribute('loyalty', $this->loyaltyService->profilePayload($profile));
        });
    }

    /**
     * Update the authenticated user profile.
     */
    public function update(User $user, UpdateProfileDTO $data): User
    {
        $phoneChanged = $data->phoneProvided
            && trim((string) ($data->phone ?? '')) !== trim((string) ($user->phone ?? ''));

        $this->forgetTransientAttributes($user);

        $attributes = [
            'name' => $data->name,
            'full_name' => $data->name,
        ];

        if ($data->phoneProvided) {
            $attributes['phone'] = $data->phone;
        }

        if ($data->countryProvided) {
            $attributes['country'] = $data->country;
        }

        $user->fill($attributes);

        if ($phoneChanged) {
            $user->phone_verified_at = null;
        }

        try {
            $user->save();
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages([
                'phone' => [__('validation.unique', ['attribute' => 'phone'])],
            ]);
        } catch (QueryException $exception) {
            if ($this->isDuplicatePhoneConstraint($exception)) {
                throw ValidationException::withMessages([
                    'phone' => [__('validation.unique', ['attribute' => 'phone'])],
                ]);
            }

            throw $exception;
        }

        return $this->profile($user->refresh());
    }

    public function uploadAvatar(User $user, UploadedFile $avatar): User
    {
        $extension = strtolower($avatar->getClientOriginalExtension() ?: $avatar->extension() ?: 'jpg');
        $path = 'avatars/'.$user->id.'_'.Str::lower(Str::random(8)).'.'.$extension;

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        Storage::disk('public')->putFileAs(
            dirname($path),
            $avatar,
            basename($path),
        );

        $this->forgetTransientAttributes($user);
        $user->forceFill(['avatar_path' => $path])->save();

        return $this->profile($user->refresh());
    }

    public function deleteAvatar(User $user): User
    {
        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
            $this->forgetTransientAttributes($user);
            $user->forceFill(['avatar_path' => null])->save();
        }

        return $this->profile($user->refresh());
    }

    /**
     * @return array{expires_in_seconds: int, resend_after_seconds: int, channel: string, target: string}
     */
    public function requestEmailChange(User $user, string $email): array
    {
        return $this->profileOtpService->send(
            $user,
            ProfileOtpService::PURPOSE_EMAIL_CHANGE,
            $email,
        );
    }

    public function confirmEmailChange(User $user, string $email, string $otp): User
    {
        $confirmedEmail = $this->profileOtpService->consume(
            $user,
            ProfileOtpService::PURPOSE_EMAIL_CHANGE,
            $otp,
            strtolower(trim($email)),
        );

        $this->forgetTransientAttributes($user);
        $user->forceFill([
            'email' => $confirmedEmail,
            'email_verified_at' => now(),
        ])->save();

        return $this->profile($user->refresh());
    }

    /**
     * @return array{expires_in_seconds: int, resend_after_seconds: int, channel: string, target: string}
     */
    public function sendEmailVerification(User $user): array
    {
        if ($user->email_verified_at !== null) {
            throw ValidationException::withMessages([
                'email' => ['Your email is already verified.'],
            ]);
        }

        return $this->profileOtpService->send($user, ProfileOtpService::PURPOSE_EMAIL_VERIFY);
    }

    public function confirmEmailVerification(User $user, string $otp): User
    {
        if ($user->email_verified_at !== null) {
            return $this->profile($user);
        }

        $this->profileOtpService->consume($user, ProfileOtpService::PURPOSE_EMAIL_VERIFY, $otp);

        $this->forgetTransientAttributes($user);
        $user->forceFill(['email_verified_at' => now()])->save();

        return $this->profile($user->refresh());
    }

    /**
     * @return array{expires_in_seconds: int, resend_after_seconds: int, channel: string, target: string}
     */
    public function sendPhoneVerification(User $user): array
    {
        if ($user->phone_verified_at !== null) {
            throw ValidationException::withMessages([
                'phone' => ['Your phone is already verified.'],
            ]);
        }

        return $this->profileOtpService->send($user, ProfileOtpService::PURPOSE_PHONE_VERIFY);
    }

    public function confirmPhoneVerification(User $user, string $otp): User
    {
        if ($user->phone_verified_at !== null) {
            return $this->profile($user);
        }

        $this->profileOtpService->consume($user, ProfileOtpService::PURPOSE_PHONE_VERIFY, $otp);

        $this->forgetTransientAttributes($user);
        $user->forceFill(['phone_verified_at' => now()])->save();

        return $this->profile($user->refresh());
    }

    /**
     * Schedule deletion of the authenticated customer account (365-day grace period).
     *
     * @return array<string, mixed>
     */
    public function deleteAccount(User $user, string $mode): array
    {
        return $this->customerAccountDeletionService->requestDeletion($user, $mode);
    }

    /**
     * Cancel a pending scheduled account deletion using credentials or Google token.
     *
     * @return array<string, mixed>
     */
    public function cancelAccountDeletion(User $user): array
    {
        return $this->customerAccountDeletionService->cancel($user);
    }

    private function forgetTransientAttributes(User $user): void
    {
        $user->offsetUnset('loyalty');
    }

    private function isDuplicatePhoneConstraint(QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        if (! str_contains($message, 'phone')) {
            return false;
        }

        return str_contains($message, 'unique')
            || str_contains($message, 'duplicate')
            || (string) $exception->getCode() === '23000';
    }
}
