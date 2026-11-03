<?php

namespace App\Modules\Api\SharedFlights\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\SharedFlight;
use App\Modules\Api\SharedFlights\Http\Requests\StoreSharedFlightRequest;
use App\Modules\Api\Support\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class SharedFlightController extends Controller
{
    public function store(StoreSharedFlightRequest $request): JsonResponse
    {
        $ttlDays = max(1, (int) config('services.shared_flight.ttl_days', 14));
        $validated = $request->validated();

        $shared = SharedFlight::query()->create([
            'token' => SharedFlight::makeToken(),
            'user_id' => $request->user('sanctum')?->id,
            'search' => $validated['search'],
            'selected_offer' => $validated['selected_offer'],
            'expires_at' => Carbon::now()->addDays($ttlDays),
        ]);

        return ApiResponse::success(
            $this->linkPayload($shared),
            'Shared flight link created.',
            [],
            201,
        );
    }

    public function show(string $token): JsonResponse
    {
        $shared = SharedFlight::query()->where('token', $token)->first();

        if ($shared === null) {
            return ApiResponse::error('Shared flight not found.', [], 'not_found', 404);
        }

        return ApiResponse::success(
            [
                'share_token' => $shared->token,
                'token' => $shared->token,
                'search' => $shared->search ?? [],
                'selected_offer' => $shared->selected_offer ?? [],
                'expires_at' => $shared->expires_at?->toIso8601String(),
                'is_expired' => $shared->isExpired(),
            ],
            $shared->isExpired()
                ? 'Shared flight link has expired.'
                : 'Shared flight fetched successfully.',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function linkPayload(SharedFlight $shared): array
    {
        return [
            'share_token' => $shared->token,
            'token' => $shared->token,
            'share_url' => $shared->shareUrl(),
            'url' => $shared->shareUrl(),
            'expires_at' => $shared->expires_at?->toIso8601String(),
        ];
    }
}
