<?php

namespace App\Modules\Api\Auth\Services;

use App\Models\PasswordResetOtp;
use App\Models\User;
use App\Notifications\PasswordResetOtpNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PasswordResetService
{
    /**
     * Issue (or resend) a password-reset OTP for the given email.
     *
     * Always returns the same public payload shape so callers cannot probe
     * whether an email is registered. OTP is only sent for active customers.
     *
     * @return array{otp_expires_in_seconds: int, resend_after_seconds: int}
     */
    public function forgotPassword(string $email): array
    {
        $email = Str::lower(trim($email));
        $otpExpireMinutes = (int) config('password_reset.otp_expire_minutes', 10);
        $resendThrottle = (int) config('password_reset.resend_throttle_seconds', 60);

        $challenge = PasswordResetOtp::query()->where('email', $email)->first();

        if ($challenge?->last_sent_at) {
            $elapsed = max(0, now()->getTimestamp() - $challenge->last_sent_at->getTimestamp());
            $retryAfter = $resendThrottle - $elapsed;

            if ($retryAfter > 0) {
                throw new HttpException(
                    429,
                    "Please wait {$retryAfter} seconds before requesting another code.",
                );
            }
        }

        $user = $this->findResettableCustomer($email);

        // Always record last_sent_at so resend throttle cannot reveal whether the email exists.
        if ($user) {
            $otp = $this->generateOtp();

            PasswordResetOtp::query()->updateOrCreate(
                ['email' => $email],
                [
                    'otp_hash' => Hash::make($otp),
                    'otp_expires_at' => now()->addMinutes($otpExpireMinutes),
                    'otp_attempts' => 0,
                    'last_sent_at' => now(),
                    'reset_token_hash' => null,
                    'reset_token_expires_at' => null,
                    'otp_verified_at' => null,
                ],
            );

            $user->notify(new PasswordResetOtpNotification($otp, $otpExpireMinutes));
        } else {
            PasswordResetOtp::query()->updateOrCreate(
                ['email' => $email],
                [
                    'otp_hash' => Hash::make(Str::random(32)),
                    'otp_expires_at' => now()->subMinute(),
                    'otp_attempts' => 0,
                    'last_sent_at' => now(),
                    'reset_token_hash' => null,
                    'reset_token_expires_at' => null,
                    'otp_verified_at' => null,
                ],
            );
        }

        return [
            'otp_expires_in_seconds' => $otpExpireMinutes * 60,
            'resend_after_seconds' => $resendThrottle,
        ];
    }

    /**
     * Verify an OTP and issue a short-lived reset_token.
     *
     * @return array{reset_token: string, reset_token_expires_in_seconds: int}
     *
     * @throws ValidationException
     */
    public function verifyResetOtp(string $email, string $otp): array
    {
        $email = Str::lower(trim($email));
        $maxAttempts = (int) config('password_reset.otp_max_attempts', 5);
        $resetTokenExpireMinutes = (int) config('password_reset.reset_token_expire_minutes', 15);

        $challenge = PasswordResetOtp::query()->where('email', $email)->first();

        if (! $challenge || $challenge->otp_expires_at->isPast()) {
            throw ValidationException::withMessages([
                'otp' => ['The OTP is invalid or has expired.'],
            ]);
        }

        if ($challenge->otp_attempts >= $maxAttempts) {
            throw ValidationException::withMessages([
                'otp' => ['Too many invalid OTP attempts. Please request a new code.'],
            ]);
        }

        if (! Hash::check($otp, $challenge->otp_hash)) {
            $challenge->increment('otp_attempts');

            throw ValidationException::withMessages([
                'otp' => ['The OTP is invalid or has expired.'],
            ]);
        }

        $resetToken = Str::random(64);

        $challenge->forceFill([
            'reset_token_hash' => Hash::make($resetToken),
            'reset_token_expires_at' => now()->addMinutes($resetTokenExpireMinutes),
            'otp_verified_at' => now(),
            // Invalidate OTP reuse after successful verification.
            'otp_hash' => Hash::make(Str::random(32)),
            'otp_expires_at' => now()->subMinute(),
        ])->save();

        return [
            'reset_token' => $resetToken,
            'reset_token_expires_in_seconds' => $resetTokenExpireMinutes * 60,
        ];
    }

    /**
     * Reset the password using a verified reset_token.
     *
     * @throws ValidationException
     */
    public function resetPassword(string $resetToken, string $password): void
    {
        $challenge = PasswordResetOtp::query()
            ->whereNotNull('reset_token_hash')
            ->where('reset_token_expires_at', '>', now())
            ->get()
            ->first(fn (PasswordResetOtp $row): bool => Hash::check($resetToken, (string) $row->reset_token_hash));

        if (! $challenge) {
            throw ValidationException::withMessages([
                'reset_token' => ['The reset token is invalid or has expired.'],
            ]);
        }

        $user = $this->findResettableCustomer($challenge->email);

        if (! $user) {
            $challenge->delete();

            throw ValidationException::withMessages([
                'reset_token' => ['The reset token is invalid or has expired.'],
            ]);
        }

        $user->forceFill([
            'password' => $password,
        ])->save();

        // Force re-login on all devices after a forgotten-password reset.
        $user->tokens()->delete();

        $challenge->delete();
    }

    private function findResettableCustomer(string $email): ?User
    {
        $user = User::query()->where('email', $email)->first();

        if (! $user || ! $user->is_active || ! $user->isCustomerAccount()) {
            return null;
        }

        return $user;
    }

    private function generateOtp(): string
    {
        $length = max(4, (int) config('password_reset.otp_length', 6));
        $max = (10 ** $length) - 1;

        return str_pad((string) random_int(0, $max), $length, '0', STR_PAD_LEFT);
    }
}
