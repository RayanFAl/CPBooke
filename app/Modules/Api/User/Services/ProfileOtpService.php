<?php

namespace App\Modules\Api\User\Services;

use App\Models\User;
use App\Notifications\ProfileOtpNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ProfileOtpService
{
    public const PURPOSE_EMAIL_VERIFY = 'email_verify';

    public const PURPOSE_PHONE_VERIFY = 'phone_verify';

    public const PURPOSE_EMAIL_CHANGE = 'email_change';

    /**
     * @return array{expires_in_seconds: int, resend_after_seconds: int, channel: string, target: string}
     */
    public function send(User $user, string $purpose, ?string $target = null): array
    {
        $expireMinutes = (int) config('profile.otp_expire_minutes', 10);
        $resendThrottle = (int) config('profile.resend_throttle_seconds', 60);

        [$channel, $resolvedTarget] = $this->resolveTarget($user, $purpose, $target);

        $existing = Cache::get($this->cacheKey($user->id, $purpose));
        if (is_array($existing) && isset($existing['last_sent_at'])) {
            $elapsed = max(0, now()->getTimestamp() - (int) $existing['last_sent_at']);
            $retryAfter = $resendThrottle - $elapsed;
            if ($retryAfter > 0) {
                throw new HttpException(
                    429,
                    "Please wait {$retryAfter} seconds before requesting another code.",
                );
            }
        }

        $otp = $this->generateOtp();

        Cache::put($this->cacheKey($user->id, $purpose), [
            'otp_hash' => Hash::make($otp),
            'target' => $resolvedTarget,
            'channel' => $channel,
            'attempts' => 0,
            'expires_at' => now()->addMinutes($expireMinutes)->getTimestamp(),
            'last_sent_at' => now()->getTimestamp(),
        ], now()->addMinutes($expireMinutes + 5));

        if ($channel === 'email') {
            $mailableUser = $user;
            if ($purpose === self::PURPOSE_EMAIL_CHANGE) {
                // Send to the new email address.
                $mailableUser = (clone $user)->forceFill(['email' => $resolvedTarget]);
            }
            $mailableUser->notify(new ProfileOtpNotification($otp, $purpose, $expireMinutes));
        } else {
            $this->sendSms($resolvedTarget, $otp, $expireMinutes);
        }

        if (app()->runningUnitTests()) {
            Cache::put(
                $this->debugCacheKey($user->id, $purpose),
                $otp,
                now()->addMinutes($expireMinutes + 5),
            );
        }

        return [
            'expires_in_seconds' => $expireMinutes * 60,
            'resend_after_seconds' => $resendThrottle,
            'channel' => $channel,
            'target' => $this->maskTarget($channel, $resolvedTarget),
        ];
    }

    public function debugOtpForTests(User $user, string $purpose): ?string
    {
        if (! app()->runningUnitTests()) {
            return null;
        }

        $otp = Cache::get($this->debugCacheKey($user->id, $purpose));

        return is_string($otp) ? $otp : null;
    }

    /**
     * @throws ValidationException
     */
    public function consume(User $user, string $purpose, string $otp, ?string $expectedTarget = null): string
    {
        $maxAttempts = (int) config('profile.otp_max_attempts', 5);
        $payload = Cache::get($this->cacheKey($user->id, $purpose));

        if (! is_array($payload) || ! isset($payload['otp_hash'], $payload['expires_at'], $payload['target'])) {
            throw ValidationException::withMessages([
                'otp' => ['The OTP is invalid or has expired.'],
            ]);
        }

        if ((int) $payload['expires_at'] < now()->getTimestamp()) {
            Cache::forget($this->cacheKey($user->id, $purpose));

            throw ValidationException::withMessages([
                'otp' => ['The OTP is invalid or has expired.'],
            ]);
        }

        if ((int) ($payload['attempts'] ?? 0) >= $maxAttempts) {
            throw ValidationException::withMessages([
                'otp' => ['Too many invalid OTP attempts. Please request a new code.'],
            ]);
        }

        if ($expectedTarget !== null && strcasecmp((string) $payload['target'], $expectedTarget) !== 0) {
            throw ValidationException::withMessages([
                'email' => ['The email does not match the pending change request.'],
            ]);
        }

        if (! Hash::check($otp, (string) $payload['otp_hash'])) {
            $payload['attempts'] = (int) ($payload['attempts'] ?? 0) + 1;
            Cache::put(
                $this->cacheKey($user->id, $purpose),
                $payload,
                now()->addMinutes((int) config('profile.otp_expire_minutes', 10) + 5),
            );

            throw ValidationException::withMessages([
                'otp' => ['The OTP is invalid or has expired.'],
            ]);
        }

        Cache::forget($this->cacheKey($user->id, $purpose));

        return (string) $payload['target'];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveTarget(User $user, string $purpose, ?string $target): array
    {
        return match ($purpose) {
            self::PURPOSE_EMAIL_VERIFY => ['email', (string) $user->email],
            self::PURPOSE_PHONE_VERIFY => (function () use ($user): array {
                if (! filled($user->phone)) {
                    throw ValidationException::withMessages([
                        'phone' => ['Add a phone number to your profile before verifying.'],
                    ]);
                }

                return ['sms', (string) $user->phone];
            })(),
            self::PURPOSE_EMAIL_CHANGE => (function () use ($user, $target): array {
                $email = strtolower(trim((string) $target));
                if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw ValidationException::withMessages([
                        'email' => ['A valid email address is required.'],
                    ]);
                }
                if (strcasecmp($email, (string) $user->email) === 0) {
                    throw ValidationException::withMessages([
                        'email' => ['The new email must be different from your current email.'],
                    ]);
                }
                if (User::query()->where('email', $email)->whereKeyNot($user->id)->exists()) {
                    throw ValidationException::withMessages([
                        'email' => ['This email is already taken.'],
                    ]);
                }

                return ['email', $email];
            })(),
            default => throw ValidationException::withMessages([
                'purpose' => ['Unsupported verification purpose.'],
            ]),
        };
    }

    private function sendSms(string $phone, string $otp, int $expireMinutes): void
    {
        $message = "Your Booke verification code is {$otp}. It expires in {$expireMinutes} minutes.";
        $endpoint = config('services.notifications.sms_endpoint');

        if (! $endpoint) {
            Log::info('Profile phone OTP (simulated SMS)', [
                'phone' => $phone,
                'otp' => $otp,
            ]);

            return;
        }

        Http::withToken((string) config('services.notifications.sms_token'))
            ->post((string) $endpoint, [
                'to' => $phone,
                'message' => $message,
            ])
            ->throw();
    }

    private function generateOtp(): string
    {
        $length = max(4, (int) config('profile.otp_length', 6));
        $max = (10 ** $length) - 1;

        return str_pad((string) random_int(0, $max), $length, '0', STR_PAD_LEFT);
    }

    private function cacheKey(int $userId, string $purpose): string
    {
        return "profile_otp:{$userId}:{$purpose}";
    }

    private function debugCacheKey(int $userId, string $purpose): string
    {
        return "profile_otp_debug:{$userId}:{$purpose}";
    }

    private function maskTarget(string $channel, string $target): string
    {
        if ($channel === 'email') {
            [$local, $domain] = array_pad(explode('@', $target, 2), 2, '');
            $visible = substr($local, 0, 2);

            return $visible.str_repeat('*', max(strlen($local) - 2, 1)).'@'.$domain;
        }

        $digits = preg_replace('/\D+/', '', $target) ?? $target;
        $suffix = substr($digits, -4);

        return str_repeat('*', max(strlen($digits) - 4, 0)).$suffix;
    }
}
