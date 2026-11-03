<?php

namespace App\Modules\Admin\Home\Http\Controllers;

use App\Modules\Admin\Home\Services\AdminHotelLookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HomeHotelLookupController
{
    public function __construct(
        private readonly AdminHotelLookupService $hotelLookupService,
    ) {}

    public function status(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'configured' => $this->hotelLookupService->isConfigured(),
            ],
        ]);
    }

    public function cities(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));

        if (mb_strlen($query) < 2) {
            return response()->json([
                'success' => true,
                'data' => ['cities' => []],
                'message' => 'Type at least 2 characters.',
            ]);
        }

        $result = $this->hotelLookupService->autocomplete($query);

        return response()->json([
            'success' => (bool) ($result['ok'] ?? false),
            'message' => $result['error'] ?? 'Cities fetched.',
            'data' => [
                'cities' => $result['cities'] ?? [],
            ],
        ], ($result['ok'] ?? false) ? 200 : 422);
    }

    public function hotels(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'city' => ['required', 'string', 'max:160'],
            'city_id' => ['required', 'string', 'max:80'],
            'check_in' => ['required', 'date'],
            'check_out' => ['required', 'date', 'after:check_in'],
            'adults' => ['nullable', 'integer', 'min:1', 'max:8'],
        ]);

        $result = $this->hotelLookupService->search($validated);

        return response()->json([
            'success' => (bool) ($result['ok'] ?? false),
            'message' => $result['error'] ?? 'Hotels fetched.',
            'data' => [
                'hotels' => $result['hotels'] ?? [],
            ],
        ], ($result['ok'] ?? false) ? 200 : 422);
    }

    public function offers(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'hotel_id' => ['required', 'string', 'max:80'],
            'city_id' => ['required', 'string', 'max:80'],
            'city' => ['nullable', 'string', 'max:160'],
            'source' => ['nullable', 'string', 'max:80'],
            'check_in' => ['nullable', 'date'],
            'check_out' => ['nullable', 'date', 'after:check_in'],
        ]);

        $result = $this->hotelLookupService->offers(
            $validated['hotel_id'],
            $validated['city_id'],
            $validated['source'] ?? null,
            isset($validated['check_in']) ? (string) $validated['check_in'] : null,
            isset($validated['check_out']) ? (string) $validated['check_out'] : null,
            $validated['city'] ?? null,
        );

        return response()->json([
            'success' => (bool) ($result['ok'] ?? false),
            'message' => $result['error'] ?? 'Offers fetched.',
            'data' => [
                'offers' => $result['offers'] ?? [],
            ],
        ], ($result['ok'] ?? false) ? 200 : 422);
    }
}
