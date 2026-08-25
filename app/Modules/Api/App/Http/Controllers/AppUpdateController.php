<?php

namespace App\Modules\Api\App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Api\Content\Services\ContentPageService;
use App\Modules\Api\Support\Http\Responses\ApiResponse;
use App\Modules\Content\Services\MobileAppReleaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppUpdateController extends Controller
{
    public function __construct(
        private readonly MobileAppReleaseService $mobileAppReleaseService,
        private readonly ContentPageService $contentPageService,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'version_code' => ['required', 'integer', 'min:0'],
        ]);

        $locale = $this->contentPageService->resolveLocale($request);

        return ApiResponse::success(
            $this->mobileAppReleaseService->checkUpdate((int) $validated['version_code'], $locale),
            'App update status fetched successfully.',
        );
    }
}
