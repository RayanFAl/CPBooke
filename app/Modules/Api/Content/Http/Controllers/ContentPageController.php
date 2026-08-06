<?php

namespace App\Modules\Api\Content\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Api\Content\Services\ContentPageService;
use App\Modules\Api\Support\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use stdClass;

class ContentPageController extends Controller
{
    public function __construct(
        private readonly ContentPageService $contentPageService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $locale = $this->contentPageService->resolveLocale($request);
        $data = $this->contentPageService->list($locale);

        return $this->cachedList($data, 'Content pages fetched successfully.');
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $locale = $this->contentPageService->resolveLocale($request);
        $data = $this->contentPageService->findBySlug($slug, $locale);

        if ($data === null) {
            return ApiResponse::error('Content page not found.', [], 'not_found', 404);
        }

        $etag = $this->contentPageService->etagFor($data);

        if ($this->clientHasFreshCopy($etag)) {
            return response()->json(null, 304)->withHeaders($this->cacheHeaders($etag));
        }

        return ApiResponse::success($data, 'Content page fetched successfully.')
            ->withHeaders($this->cacheHeaders($etag));
    }

    /**
     * @param  list<array<string, mixed>>  $data
     */
    private function cachedList(array $data, string $message): JsonResponse
    {
        $etag = $this->contentPageService->etagFor($data);

        if ($this->clientHasFreshCopy($etag)) {
            return response()->json(null, 304)->withHeaders($this->cacheHeaders($etag));
        }

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
