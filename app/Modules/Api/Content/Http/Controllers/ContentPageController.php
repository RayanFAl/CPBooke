<?php

namespace App\Modules\Api\Content\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Api\Content\Services\ContentPageService;
use App\Modules\Api\Support\Http\Responses\ApiResponse;
use App\Modules\Content\Support\ContentPageCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use stdClass;

class ContentPageController extends Controller
{
    public function __construct(
        private readonly ContentPageService $contentPageService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'category' => ['sometimes', 'nullable', 'string', Rule::in(ContentPageCatalog::categories())],
            'product' => ['sometimes', 'nullable', 'string', Rule::in(ContentPageCatalog::products())],
        ]);

        $locale = $this->contentPageService->resolveLocale($request);
        $data = $this->contentPageService->list(
            $locale,
            isset($filters['category']) && $filters['category'] !== '' ? $filters['category'] : null,
            isset($filters['product']) && $filters['product'] !== '' ? $filters['product'] : null,
        );

        return $this->cachedList($data, 'Content pages fetched successfully.');
    }

    public function workspace(Request $request): JsonResponse
    {
        $locale = $this->contentPageService->resolveLocale($request);
        $data = $this->contentPageService->workspace($locale);

        return $this->cachedDetail($data, 'Content workspace fetched successfully.');
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $locale = $this->contentPageService->resolveLocale($request);
        $data = $this->contentPageService->findBySlug($slug, $locale);

        if ($data === null) {
            return ApiResponse::error('Content page not found.', [], 'not_found', 404);
        }

        return $this->cachedDetail($data, 'Content page fetched successfully.');
    }

    public function showForProduct(Request $request, string $product): JsonResponse
    {
        $locale = $this->contentPageService->resolveLocale($request);
        $data = $this->contentPageService->findByProduct($product, $locale);

        if ($data === null) {
            return ApiResponse::error('Content page not found.', [], 'not_found', 404);
        }

        return $this->cachedDetail($data, 'Content page fetched successfully.');
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
            'meta' => new stdClass,
        ])->withHeaders($this->cacheHeaders($etag));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function cachedDetail(array $data, string $message): JsonResponse
    {
        $etag = $this->contentPageService->etagFor($data);

        if ($this->clientHasFreshCopy($etag)) {
            return response()->json(null, 304)->withHeaders($this->cacheHeaders($etag));
        }

        return ApiResponse::success($data, $message)
            ->withHeaders($this->cacheHeaders($etag));
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
