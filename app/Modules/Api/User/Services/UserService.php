<?php

namespace App\Modules\Api\User\Services;

use App\Models\User;
use App\Modules\Api\DTO\UpdateProfileDTO;
use App\Modules\Loyalty\Services\LoyaltyService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UserService
{
    public function __construct(
        private readonly LoyaltyService $loyaltyService,
        private readonly ProfileOtpService $profileOtpService,
    ) {
    }

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
        $phoneChanged = $data->phone !== null
            && trim((string) $data->phone) !== trim((string) $user->phone);

        $this->forgetTransientAttributes($user);

        $user->fill([
            'name' => $data->name,
            'full_name' => $data->name,
            'phone' => $data->phone,
            'country' => $data->country,
        ]);

        if ($phoneChanged) {
            $user->phone_verified_at = null;
        }

        $user->save();

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
            throw \Illuminate\Validation\ValidationException::withMessages([
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
            throw \Illuminate\Validation\ValidationException::withMessages([
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

    private function forgetTransientAttributes(User $user): void
    {
        $user->offsetUnset('loyalty');
    }
}
