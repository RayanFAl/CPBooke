<?php

namespace App\Modules\Partners\Http\Controllers;

use App\Models\Partner;
use App\Models\PartnerApiKey;
use App\Modules\Api\Support\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PartnerMeController
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var Partner $partner */
        $partner = $request->attributes->get('partner');
        /** @var PartnerApiKey $apiKey */
        $apiKey = $request->attributes->get('partner_api_key');

        return ApiResponse::success([
            'partner' => [
                'id' => $partner->id,
                'name' => $partner->name,
                'slug' => $partner->slug,
                'status' => $partner->status,
            ],
            'api_key' => [
                'id' => $apiKey->id,
                'name' => $apiKey->name,
                'key_prefix' => $apiKey->key_prefix,
                'last_used_at' => optional($apiKey->last_used_at)?->toIso8601String(),
            ],
        ]);
    }
}
