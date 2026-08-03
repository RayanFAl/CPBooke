<?php

namespace App\Modules\Api\Auth\Services;

use App\Models\RefreshToken;
use App\Models\User;
use App\Modules\Api\DTO\AuthResultDTO;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class ApiTokenService
{
    /**
     * Issue a Sanctum access token + opaque refresh token pair.
     */
    public function issueTokenPair(User $user, string $deviceName, bool $rememberMe = false): AuthResultDTO
    {
        $accessTtl = $this->accessTtlSeconds($rememberMe);
        $refreshTtl = $this->refreshTtlSeconds($rememberMe);
        $expiresAt = now()->addSeconds($accessTtl);

        $newAccessToken = $user->createToken($deviceName, ['*'], $expiresAt);
        $plainRefreshToken = Str::random(64);

        RefreshToken::query()->create([
            'user_id' => $user->id,
            'access_token_id' => $newAccessToken->accessToken->id,
            'token_hash' => hash('sha256', $plainRefreshToken),
            'device_name' => $deviceName,
            'remember_me' => $rememberMe,
            'expires_at' => now()->addSeconds($refreshTtl),
        ]);

        return new AuthResultDTO(
            user: $user,
            accessToken: $newAccessToken->plainTextToken,
            refreshToken: $plainRefreshToken,
            expiresIn: $accessTtl,
            expiresAt: $expiresAt->toIso8601String(),
            rememberMe: $rememberMe,
        );
    }

    /**
     * Rotate tokens using a valid refresh token.
     *
     * @throws ValidationException
     */
    public function refresh(string $refreshToken): AuthResultDTO
    {
        $record = RefreshToken::query()
            ->where('token_hash', hash('sha256', $refreshToken))
            ->first();

        if (! $record || ! $record->isValid()) {
            throw ValidationException::withMessages([
                'refresh_token' => ['The refresh token is invalid or has expired.'],
            ]);
        }

        $user = User::query()->find($record->user_id);

        if (! $user || ! $user->is_active || ! $user->isCustomerAccount()) {
            $this->revokeRefreshRecord($record);

            throw ValidationException::withMessages([
                'refresh_token' => ['The refresh token is invalid or has expired.'],
            ]);
        }

        if ($record->access_token_id) {
            PersonalAccessToken::query()->whereKey($record->access_token_id)->delete();
        }

        $this->revokeRefreshRecord($record);

        return $this->issueTokenPair(
            $user,
            $record->device_name ?: 'mobile-app',
            (bool) $record->remember_me,
        );
    }

    /**
     * Revoke the current access token and its linked refresh token.
     */
    public function revokeCurrentSession(User $user): void
    {
        $accessToken = $user->currentAccessToken();

        if (! $accessToken instanceof PersonalAccessToken) {
            return;
        }

        RefreshToken::query()
            ->where('user_id', $user->id)
            ->where('access_token_id', $accessToken->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        $accessToken->delete();
    }

    /**
     * Revoke every access + refresh token for the user.
     */
    public function revokeAllSessions(User $user): void
    {
        RefreshToken::query()
            ->where('user_id', $user->id)
            ->update(['revoked_at' => now()]);

        PersonalAccessToken::query()
            ->where('tokenable_type', $user->getMorphClass())
            ->where('tokenable_id', $user->getKey())
            ->delete();
    }

    /**
     * Revoke a single session by personal access token id.
     */
    public function revokeSessionById(User $user, int|string $sessionId): void
    {
        $token = PersonalAccessToken::query()
            ->where('tokenable_type', $user->getMorphClass())
            ->where('tokenable_id', $user->getKey())
            ->whereKey($sessionId)
            ->first();

        if (! $token) {
            abort(404, 'Session not found.');
        }

        RefreshToken::query()
            ->where('user_id', $user->id)
            ->where('access_token_id', $token->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        $token->delete();
    }

    /**
     * List active access-token sessions for the user.
     *
     * @return list<array<string, mixed>>
     */
    public function listSessions(User $user): array
    {
        $currentId = $user->currentAccessToken() instanceof PersonalAccessToken
            ? $user->currentAccessToken()->id
            : null;

        $rememberByAccessId = RefreshToken::query()
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->whereNotNull('access_token_id')
            ->get(['access_token_id', 'remember_me'])
            ->keyBy('access_token_id');

        return $user->tokens()
            ->orderByDesc('last_used_at')
            ->orderByDesc('id')
            ->get()
            ->map(function (PersonalAccessToken $token) use ($currentId, $rememberByAccessId): array {
                $remember = $rememberByAccessId->get($token->id);

                return [
                    'id' => $token->id,
                    'device_name' => $token->name,
                    'last_used_at' => $token->last_used_at?->toIso8601String(),
                    'expires_at' => $token->expires_at?->toIso8601String(),
                    'created_at' => $token->created_at?->toIso8601String(),
                    'is_current' => $currentId !== null && (int) $token->id === (int) $currentId,
                    'remember_me' => (bool) ($remember?->remember_me ?? false),
                ];
            })
            ->values()
            ->all();
    }

    public function accessTtlSeconds(bool $rememberMe): int
    {
        return max(60, (int) (
            $rememberMe
                ? config('api_auth.access_token_remember_ttl_seconds', 604800)
                : config('api_auth.access_token_ttl_seconds', 3600)
        ));
    }

    public function refreshTtlSeconds(bool $rememberMe): int
    {
        return max(60, (int) (
            $rememberMe
                ? config('api_auth.refresh_token_remember_ttl_seconds', 2592000)
                : config('api_auth.refresh_token_ttl_seconds', 604800)
        ));
    }

    /**
     * Detect whether a bearer token exists but is expired.
     */
    public function bearerTokenIsExpired(?string $bearerToken): bool
    {
        if ($bearerToken === null || $bearerToken === '') {
            return false;
        }

        $accessToken = PersonalAccessToken::findToken($bearerToken);

        if (! $accessToken) {
            return false;
        }

        return $accessToken->expires_at instanceof Carbon
            && $accessToken->expires_at->isPast();
    }

    private function revokeRefreshRecord(RefreshToken $record): void
    {
        if ($record->revoked_at === null) {
            $record->forceFill(['revoked_at' => now()])->save();
        }
    }
}
