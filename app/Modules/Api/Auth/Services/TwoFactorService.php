<?php

namespace App\Modules\Api\Auth\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorService
{
    private const TEMP_TOKEN_TTL_SECONDS = 300;

    private const PENDING_SECRET_TTL_SECONDS = 600;

    public function __construct(
        private readonly Google2FA $google2fa = new Google2FA(),
    ) {
    }

    /**
     * @return array{enabled: bool, confirmed_at: ?string, type: string}
     */
    public function status(User $user): array
    {
        return [
            'enabled' => $this->isEnabled($user),
            'confirmed_at' => $user->two_factor_confirmed_at?->toIso8601String(),
            'type' => 'authenticator',
        ];
    }

    public function isEnabled(User $user): bool
    {
        return filled($user->two_factor_secret) && $user->two_factor_confirmed_at !== null;
    }

    /**
     * @return array{secret: string, otpauth_url: string, type: string}
     */
    public function enable(User $user): array
    {
        $secret = $this->google2fa->generateSecretKey();

        Cache::put(
            $this->pendingSecretKey($user),
            Crypt::encryptString($secret),
            now()->addSeconds(self::PENDING_SECRET_TTL_SECONDS),
        );

        $otpauthUrl = $this->google2fa->getQRCodeUrl(
            config('app.name', 'Booke'),
            (string) $user->email,
            $secret,
        );

        return [
            'secret' => $secret,
            'otpauth_url' => $otpauthUrl,
            'type' => 'authenticator',
        ];
    }

    /**
     * @throws ValidationException
     */
    public function confirm(User $user, string $code): void
    {
        $encrypted = Cache::get($this->pendingSecretKey($user));

        if (! is_string($encrypted) || $encrypted === '') {
            throw ValidationException::withMessages([
                'code' => ['Two-factor setup expired. Please start enable again.'],
            ]);
        }

        $secret = Crypt::decryptString($encrypted);

        if (! $this->verifyCode($secret, $code)) {
            throw ValidationException::withMessages([
                'code' => ['The provided authentication code is invalid.'],
            ]);
        }

        $user->forceFill([
            'two_factor_secret' => Crypt::encryptString($secret),
            'two_factor_confirmed_at' => now(),
        ])->save();

        Cache::forget($this->pendingSecretKey($user));
    }

    /**
     * @throws ValidationException
     */
    public function disable(User $user, string $password, string $code): void
    {
        if (! $this->isEnabled($user)) {
            throw ValidationException::withMessages([
                'code' => ['Two-factor authentication is not enabled.'],
            ]);
        }

        if (! Hash::check($password, (string) $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['The provided password is incorrect.'],
            ]);
        }

        $secret = Crypt::decryptString((string) $user->two_factor_secret);

        if (! $this->verifyCode($secret, $code)) {
            throw ValidationException::withMessages([
                'code' => ['The provided authentication code is invalid.'],
            ]);
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
    }

    /**
     * Create a short-lived temp token after password login when 2FA is required.
     */
    public function issueTempToken(User $user, string $deviceName, bool $rememberMe): string
    {
        $tempToken = Str::random(64);

        Cache::put(
            $this->tempTokenKey($tempToken),
            [
                'user_id' => $user->id,
                'device_name' => $deviceName,
                'remember_me' => $rememberMe,
            ],
            now()->addSeconds(self::TEMP_TOKEN_TTL_SECONDS),
        );

        return $tempToken;
    }

    /**
     * @return array{user: User, device_name: string, remember_me: bool}
     *
     * @throws ValidationException
     */
    public function consumeTempToken(string $tempToken, string $code): array
    {
        $payload = Cache::get($this->tempTokenKey($tempToken));

        if (! is_array($payload) || ! isset($payload['user_id'])) {
            throw ValidationException::withMessages([
                'temp_token' => ['The temporary token is invalid or has expired.'],
            ]);
        }

        $user = User::query()->find($payload['user_id']);

        if (! $user || ! $user->is_active || ! $user->isCustomerAccount() || ! $this->isEnabled($user)) {
            Cache::forget($this->tempTokenKey($tempToken));

            throw ValidationException::withMessages([
                'temp_token' => ['The temporary token is invalid or has expired.'],
            ]);
        }

        $secret = Crypt::decryptString((string) $user->two_factor_secret);

        if (! $this->verifyCode($secret, $code)) {
            throw ValidationException::withMessages([
                'code' => ['The provided authentication code is invalid.'],
            ]);
        }

        Cache::forget($this->tempTokenKey($tempToken));

        return [
            'user' => $user,
            'device_name' => (string) ($payload['device_name'] ?? 'mobile-app'),
            'remember_me' => (bool) ($payload['remember_me'] ?? false),
        ];
    }

    private function verifyCode(string $secret, string $code): bool
    {
        $normalized = preg_replace('/\s+/', '', $code) ?? '';

        if ($normalized === '' || ! ctype_digit($normalized)) {
            return false;
        }

        return (bool) $this->google2fa->verifyKey($secret, $normalized, 1);
    }

    private function pendingSecretKey(User $user): string
    {
        return '2fa:pending:'.$user->id;
    }

    private function tempTokenKey(string $tempToken): string
    {
        return '2fa:temp:'.hash('sha256', $tempToken);
    }
}
