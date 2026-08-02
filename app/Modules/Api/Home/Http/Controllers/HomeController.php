<?php

namespace App\Modules\Api\Home\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Api\Home\Services\HomeContentService;
use App\Modules\Api\Support\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use stdClass;

class HomeController extends Controller
{
    public function __construct(
        private readonly HomeContentService $homeContentService,
    ) {
    }

    public function content(Request $request): JsonResponse
    {
        $data = $this->homeContentService->content($request);

        return $this->cachedSuccess($data, 'Home content fetched successfully.', $data);
    }

    public function banners(Request $request): JsonResponse
    {
        $locale = $this->homeContentService->resolveLocale($request);
        $platform = $this->homeContentService->resolvePlatform($request);
        $data = $this->homeContentService->banners($locale, $platform);

        return $this->cachedList($data, 'Home banners fetched successfully.');
    }

    public function offers(Request $request): JsonResponse
    {
        $locale = $this->homeContentService->resolveLocale($request);
        $platform = $this->homeContentService->resolvePlatform($request);
        $limit = $request->integer('limit', 20);
        $data = $this->homeContentService->offers($locale, $platform, $limit);

        return $this->cachedList($data, 'Home offers fetched successfully.');
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $etagSource
     */
    private function cachedSuccess(array $data, string $message, array $etagSource): JsonResponse
    {
        $etag = $this->homeContentService->etagFor($etagSource);

        if ($this->clientHasFreshCopy($etag)) {
            return response()->json(null, 304)->withHeaders($this->cacheHeaders($etag));
        }

        return ApiResponse::success($data, $message)
            ->withHeaders($this->cacheHeaders($etag));
    }

    /**
     * @param  list<array<string, mixed>>  $data
     */
    private function cachedList(array $data, string $message): JsonResponse
    {
        $etag = $this->homeContentService->etagFor($data);

        if ($this->clientHasFreshCopy($etag)) {
            return response()->json(null, 304)->withHeaders($this->cacheHeaders($etag));
        }

        // Preserve empty arrays (ApiResponse collapses [] to {}).
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'meta' => new stdClass(),
        ])->withHeaders($this->cacheHeaders($etag));
    }

    private function clientHasFreshCopy(string $etag): bool
    {
        $ifNoneMatch = (string) request()->header('If-None-Match', '');

        return $ifNoneMatch !== '' && trim($ifNoneMatch) === $etag;
    }

    /**
     * @return array<string, string>
     */
    private function cacheHeaders(string $etag): array
    {
        return [
            'Cache-Control' => 'public, max-age=60',
            'ETag' => $etag,
        ];
    }
}
