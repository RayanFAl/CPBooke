<?php

namespace App\Modules\Api\Resources;

use App\Modules\Api\DTO\AuthResultDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthUserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var AuthResultDTO $authResult */
        $authResult = $this->resource;

        return [
            // Canonical token pair for mobile clients.
            'access_token' => $authResult->accessToken,
            'refresh_token' => $authResult->refreshToken,
            'expires_in' => $authResult->expiresIn,
            'expires_at' => $authResult->expiresAt,
            'remember_me' => $authResult->rememberMe,
            // Backward-compatible alias of access_token.
            'token' => $authResult->accessToken,
            'user' => UserResource::make($authResult->user)->resolve($request),
        ];
    }
}
