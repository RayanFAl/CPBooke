<?php

namespace App\Modules\Admin\MobileApp\Http\Controllers;

use App\Modules\Admin\MobileApp\Http\Requests\UploadMobileApkRequest;
use App\Modules\Admin\MobileApp\Services\MobileAppAdminService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MobileAppController
{
    public function __construct(
        private readonly MobileAppAdminService $adminService,
    ) {}

    public function index(Request $request): Response
    {
        $manifest = $this->adminService->readManifestForForm();

        return Inertia::render('admin/mobile-app/pages/Index', [
            'release' => $this->adminService->currentReleaseSummary(),
            'manifest' => $manifest,
            'apk_files' => $this->adminService->listApkFiles(),
            'download_page_url' => route('app.download.page'),
            'download_file_url' => route('app.download.file'),
            'update_check_url' => route('api.v1.app.update'),
            'upload_url' => route('admin.mobile-app.apk.upload', absolute: false),
            'expected_filename' => $this->adminService->buildApkFilename(
                $manifest['version'],
                $manifest['version_code'],
            ),
            'upload_limits' => $this->adminService->uploadLimits(),
        ]);
    }

    public function uploadApk(UploadMobileApkRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $filename = $this->adminService->uploadApk(
            $request->file('apk'),
            $validated['version'],
            (int) $validated['version_code'],
        );

        return redirect()
            ->route('admin.mobile-app.index')
            ->with('success', "APK uploaded successfully as {$filename}.");
    }
}
