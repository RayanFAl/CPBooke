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
    /**
     * Register a new API user and return the user with a token.
     *
     * @return AuthResultDTO
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

        return new AuthResultDTO(
            user: $user->refresh(),
            token: $user->createToken($data->deviceName)->plainTextToken,
        );
    }

    /**
     * Authenticate an API user by email or phone and return a token.
     *
     * @return AuthResultDTO
     *
     * @throws ValidationException
     */
    public function login(LoginDTO $data): AuthResultDTO
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

        $user->forceFill([
            'last_login_at' => now(),
        ])->save();

        return new AuthResultDTO(
            user: $user->refresh(),
            token: $user->createToken($data->deviceName)->plainTextToken,
        );
    }

    /**
     * Return the authenticated API user.
     */
    public function me(User $user): User
    {
        $this->ensureCustomerAccount($user);

        return $user;
    }

    /**
     * Revoke the current API token for the authenticated user.
     */
    public function logout(User $user): void
    {
        $this->ensureCustomerAccount($user);

        $user->currentAccessToken()?->delete();
    }

    /**
     * Ensure the authenticated mobile account belongs to the customer side.
     *
     * @throws ValidationException
     */
    private function ensureCustomerAccount(User $user): void
    {
        if ($user->isCustomerAccount()) {
            return;
        }

        throw ValidationException::withMessages([
            'login' => ['Administrative accounts cannot access the mobile application.'],
        ]);
    }
}