<?php

namespace App\Modules\Api\Auth\Services;

use App\Modules\Api\DTO\AuthResultDTO;
use App\Modules\Api\DTO\LoginDTO;
use App\Modules\Api\DTO\RegisterDTO;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function __construct(
        private readonly ApiTokenService $tokenService,
        private readonly TwoFactorService $twoFactorService,
        private readonly LoginAlertService $loginAlertService,
    ) {
    }

    /**
     * Register a new API user and return the user with a token pair.
     */
    public function register(RegisterDTO $data): AuthResultDTO
    {
        $user = User::query()->create([
            'name' => $data->name,
            'full_name' => $data->name,
            'email' => $data->email,
            'phone' => $data->phone,
            'country' => null,
            'password' => $data->password,
            'is_active' => true,
            'is_admin' => false,
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
        ]);

        return $this->tokenService->issueTokenPair(
            $user->refresh(),
            $data->deviceName,
            $data->rememberMe,
        );
    }

    /**
     * Authenticate an API user by email or phone.
     *
     * @return AuthResultDTO|array{requires_2fa: bool, temp_token: string, type: string}
     *
     * @throws ValidationException
     */
    public function login(LoginDTO $data, ?string $ip = null): AuthResultDTO|array
    {
        $login = $data->login;

        $user = User::query()
            ->where('email', $login)
            ->orWhere('phone', $login)
            ->first();

        if (! $user || ! Hash::check($data->password, $user->password)) {
            throw ValidationException::withMessages([
                'login' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'login' => ['This account has been deactivated.'],
            ]);
        }

        $this->ensureCustomerAccount($user);

        if ($this->twoFactorService->isEnabled($user)) {
            return [
                'requires_2fa' => true,
                'temp_token' => $this->twoFactorService->issueTempToken(
                    $user,
                    $data->deviceName,
                    $data->rememberMe,
                ),
                'type' => 'authenticator',
            ];
        }

        return $this->completeLogin($user, $data->deviceName, $data->rememberMe, $ip);
    }

    /**
     * Issue a session for an already-resolved customer (e.g. Google sign-in).
     *
     * Skips password and 2FA checks because the identity provider verified the user.
     *
     * @throws ValidationException
     */
    public function loginViaProvider(User $user, string $deviceName, bool $rememberMe, ?string $ip = null): AuthResultDTO
    {
        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'id_token' => ['This account has been deactivated.'],
            ]);
        }

        $this->ensureCustomerAccount($user, 'id_token');

        return $this->completeLogin($user, $deviceName, $rememberMe, $ip);
    }

    /**
     * Complete login after a valid 2FA code.
     *
     * @throws ValidationException
     */
    public function verifyTwoFactor(string $tempToken, string $code, ?string $ip = null): AuthResultDTO
    {
        $payload = $this->twoFactorService->consumeTempToken($tempToken, $code);

        return $this->completeLogin(
            $payload['user'],
            $payload['device_name'],
            $payload['remember_me'],
            $ip,
        );
    }

    /**
     * Complete login after a valid 2FA code.
     *
     * @throws ValidationException
     */
    public function loginAdmin(LoginDTO $data): AuthResultDTO
    {
        $user = $this->resolveUserForLogin($data->login);

        if (! $user || ! Hash::check($data->password, $user->password)) {
            throw ValidationException::withMessages([
                'login' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'login' => ['This account has been deactivated.'],
            ]);
        }

        $this->ensureAdministrativeAccount($user);

        $user->forceFill([
            'last_login_at' => now(),
        ])->save();

        return $this->tokenService->issueTokenPair(
            $user->refresh(),
            $data->deviceName,
            $data->rememberMe,
        );
    }

    /**
     * Refresh customer access credentials.
     *
     * @throws ValidationException
     */
    public function refresh(string $refreshToken): AuthResultDTO
    {
        return $this->tokenService->refresh($refreshToken);
    }

    /**
     * Return the authenticated API user.
     */
    public function me(User $user): User
    {
        $this->ensureCustomerAccount($user);

        return $user;
    }

    public function meAdmin(User $user): User
    {
        $this->ensureAdministrativeAccount($user);

        return $user;
    }

    /**
     * Update the authenticated customer's password.
     *
     * The current Sanctum token remains valid (no forced logout).
     *
     * @throws ValidationException
     */
    public function changePassword(User $user, string $currentPassword, string $newPassword): void
    {
        $this->ensureCustomerAccount($user);

        if (! Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The provided password does not match your current password.'],
            ]);
        }

        $user->forceFill([
            'password' => $newPassword,
        ])->save();
    }

    /**
     * Revoke the current API token for the authenticated user.
     */
    public function logout(User $user): void
    {
        $this->ensureCustomerAccount($user);

        $this->tokenService->revokeCurrentSession($user);
    }

    /**
     * Revoke every session for the authenticated customer.
     */
    public function logoutAll(User $user): void
    {
        $this->ensureCustomerAccount($user);

        $this->tokenService->revokeAllSessions($user);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function sessions(User $user): array
    {
        $this->ensureCustomerAccount($user);

        return $this->tokenService->listSessions($user);
    }

    public function revokeSession(User $user, int|string $sessionId): void
    {
        $this->ensureCustomerAccount($user);

        $this->tokenService->revokeSessionById($user, $sessionId);
    }

    public function logoutAdmin(User $user): void
    {
        $this->ensureAdministrativeAccount($user);

        $this->tokenService->revokeCurrentSession($user);
    }

    private function completeLogin(User $user, string $deviceName, bool $rememberMe, ?string $ip = null): AuthResultDTO
    {
        $user->forceFill([
            'last_login_at' => now(),
        ])->save();

        $auth = $this->tokenService->issueTokenPair(
            $user->refresh(),
            $deviceName,
            $rememberMe,
        );

        $this->loginAlertService->notify($user, $deviceName, $ip);

        return $auth;
    }

    private function resolveUserForLogin(string $login): ?User
    {
        return User::query()
            ->where('email', $login)
            ->orWhere('phone', $login)
            ->first();
    }

    /**
     * Ensure the authenticated mobile account belongs to the customer side.
     *
     * @throws ValidationException
     */
    private function ensureCustomerAccount(User $user, string $field = 'login'): void
    {
        if ($user->isCustomerAccount()) {
            return;
        }

        throw ValidationException::withMessages([
            $field => ['Administrative accounts cannot access the mobile application.'],
        ]);
    }

    /**
     * @throws ValidationException
     */
    private function ensureAdministrativeAccount(User $user): void
    {
        if ($user->canAccessAdminPanel()) {
            return;
        }

        throw ValidationException::withMessages([
            'login' => ['This account does not have administrative API access.'],
        ]);
    }
}
