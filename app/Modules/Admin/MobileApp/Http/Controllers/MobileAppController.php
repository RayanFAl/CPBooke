<?php

namespace App\Modules\Admin\MobileApp\Http\Controllers;

use App\Modules\Admin\MobileApp\Http\Requests\UpdateMobileReleaseRequest;
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
            'release_update_url' => route('admin.mobile-app.release.update', absolute: false),
            'expected_filename' => $this->adminService->buildApkFilename(
                $manifest['version'],
                $manifest['version_code'],
            ),
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

    public function updateRelease(UpdateMobileReleaseRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $this->adminService->updateReleaseManifest([
            'version' => $validated['version'],
            'version_code' => (int) $validated['version_code'],
            'apk' => $validated['apk'],
            'force_update' => (bool) ($validated['force_update'] ?? false),
            'min_version_code' => isset($validated['min_version_code']) && $validated['min_version_code'] !== null
                ? (int) $validated['min_version_code']
                : null,
            'notes_ar' => $validated['notes_ar'] ?? '',
            'notes_en' => $validated['notes_en'] ?? '',
        ]);

        return redirect()
            ->route('admin.mobile-app.index')
            ->with('success', 'Release settings saved successfully.');
    }
}
