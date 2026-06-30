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
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var AuthResultDTO $authResult */
        $authResult = $this->resource;

        return [
            'token' => $authResult->token,
            'user' => UserResource::make($authResult->user)->resolve($request),
        ];
    }
}