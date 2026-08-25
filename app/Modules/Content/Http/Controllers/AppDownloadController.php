<?php

namespace App\Modules\Content\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Dashboard\Services\AppPulseService;
use App\Modules\Api\Content\Services\ContentPageService;
use App\Modules\Content\Services\MobileAppReleaseService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AppDownloadController extends Controller
{
    public function __construct(
        private readonly ContentPageService $contentPageService,
        private readonly MobileAppReleaseService $mobileAppReleaseService,
        private readonly AppPulseService $appPulseService,
    ) {}

    public function show(Request $request): View
    {
        $locale = $this->contentPageService->resolveLocale($request);
        $release = $this->mobileAppReleaseService->latestRelease();
        $this->appPulseService->recordPageView($request, $release);

        return view('content.download', [
            'locale' => $locale,
            'dir' => $locale === 'ar' ? 'rtl' : 'ltr',
            'appName' => (string) config('mobile_app.name'),
            'version' => $release['version'] ?? (string) config('mobile_app.version'),
            'downloadAvailable' => $release !== null,
            'downloadUrl' => route('app.download.file'),
            'playStoreUrl' => config('mobile_app.play_store_url'),
            'appStoreUrl' => config('mobile_app.app_store_url'),
            'releaseNotes' => $release !== null
                ? ($release['notes'][$locale] ?? $release['notes']['en'] ?? $release['notes']['ar'])
                : null,
        ]);
    }

    public function download(Request $request): BinaryFileResponse
    {
        $release = $this->mobileAppReleaseService->latestRelease();

        if ($release === null) {
            throw new NotFoundHttpException('APK file is not available.');
        }

        $this->appPulseService->recordApkDownload($request, $release);

        return response()->download(
            $release['apk_path'],
            $release['apk_filename'],
            ['Content-Type' => 'application/vnd.android.package-archive'],
        );
    }
}
