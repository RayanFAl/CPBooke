<?php

namespace App\Modules\Api\Auth\Services;

use App\Models\User;
use App\Modules\Api\Auth\Contracts\GoogleIdTokenVerifierInterface;
use App\Modules\Api\DTO\AuthResultDTO;
use App\Modules\Api\DTO\GoogleAuthDTO;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class GoogleAuthService
{
    public function __construct(
        private readonly GoogleIdTokenVerifierInterface $tokenVerifier,
        private readonly AuthService $authService,
    ) {
    }

    /**
     * Authenticate or register a customer using a verified Google ID token.
     *
     * @throws ValidationException
     * @throws HttpException
     */
    public function authenticate(GoogleAuthDTO $data, ?string $ip = null): AuthResultDTO
    {
        $claims = $this->tokenVerifier->verify($data->idToken);

        return DB::transaction(function () use ($claims, $data, $ip): AuthResultDTO {
            $user = $this->resolveUser($claims);

            if ($user === null) {
                $user = $this->createCustomerFromGoogle($claims);
            } else {
                $this->linkGoogleAccount($user, $claims);
            }

            return $this->authService->loginViaProvider(
                $user->refresh(),
                $data->deviceName,
                $data->rememberMe,
                $ip,
            );
        });
    }

    /**
     * @param  array{sub: string, email: string, name?: string, picture?: string, email_verified?: bool|string}  $claims
     */
    private function resolveUser(array $claims): ?User
    {
        $byGoogleId = User::query()
            ->where('google_id', $claims['sub'])
            ->first();

        if ($byGoogleId) {
            return $byGoogleId;
        }

        return User::query()
            ->where('email', $claims['email'])
            ->first();
    }

    /**
     * @param  array{sub: string, email: string, name?: string, picture?: string, email_verified?: bool|string}  $claims
     */
    private function linkGoogleAccount(User $user, array $claims): void
    {
        if ($user->google_id !== null && $user->google_id !== $claims['sub']) {
            throw new HttpException(
                409,
                'This email is linked to a different Google account.',
            );
        }

        $updates = [];

        if ($user->google_id === null) {
            $updates['google_id'] = $claims['sub'];
        }

        if ($user->email_verified_at === null) {
            $updates['email_verified_at'] = now();
        }

        $displayName = $this->resolveDisplayName($claims);

        if ($displayName !== '' && blank($user->full_name)) {
            $updates['full_name'] = $displayName;
        }

        if ($displayName !== '' && blank($user->name)) {
            $updates['name'] = $displayName;
        }

        if ($updates !== []) {
            $user->forceFill($updates)->save();
        }
    }

    /**
     * @param  array{sub: string, email: string, name?: string, picture?: string, email_verified?: bool|string}  $claims
     */
    private function createCustomerFromGoogle(array $claims): User
    {
        $displayName = $this->resolveDisplayName($claims) ?: Str::before($claims['email'], '@');

        return User::query()->create([
            'name' => $displayName,
            'full_name' => $displayName,
            'email' => $claims['email'],
            'google_id' => $claims['sub'],
            'phone' => null,
            'country' => null,
            'password' => Str::random(64),
            'is_active' => true,
            'is_admin' => false,
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'email_verified_at' => now(),
        ]);
    }

    /**
     * @param  array{sub: string, email: string, name?: string, picture?: string, email_verified?: bool|string}  $claims
     */
    private function resolveDisplayName(array $claims): string
    {
        $name = trim((string) ($claims['name'] ?? ''));

        return $name === '' ? '' : $name;
    }
}
