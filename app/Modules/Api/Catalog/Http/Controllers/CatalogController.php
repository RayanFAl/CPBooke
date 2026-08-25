<?php

namespace App\Modules\Api\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Api\Catalog\Services\CatalogService;
use App\Modules\Api\Support\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use stdClass;

class CatalogController extends Controller
{
    public function __construct(
        private readonly CatalogService $catalogService,
    ) {
    }

    public function content(Request $request): JsonResponse
    {
        $data = $this->catalogService->content($request);

        return $this->cachedSuccess($data, 'Mobile catalog fetched successfully.');
    }

    public function options(Request $request): JsonResponse
    {
        $data = $this->catalogService->section($request, 'options');

        return $this->cachedList($data, 'Options catalog fetched successfully.');
    }

    public function market(Request $request): JsonResponse
    {
        $data = $this->catalogService->section($request, 'market');

        return $this->cachedList($data, 'Market catalog fetched successfully.');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function cachedSuccess(array $data, string $message): JsonResponse
    {
        $etag = $this->catalogService->etagFor($data);

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
        $etag = $this->catalogService->etagFor($data);

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
