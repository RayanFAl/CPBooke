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
            'upload_limits' => $this->adminService->uploadLimits(),
        ]);
    }

    public function uploadApk(UploadMobileApkRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $notifyUsers = (bool) ($validated['notify_users'] ?? true);

        $result = $this->adminService->uploadApk(
            $request->file('apk'),
            $validated['version'],
            (int) $validated['version_code'],
            $notifyUsers,
        );

        $message = "APK uploaded successfully as {$result['filename']}.";
        if ($notifyUsers) {
            $message .= ' '.$this->formatNotifySummary($result['notify']);
        }

        return redirect()
            ->route('admin.mobile-app.index')
            ->with('success', $message);
    }

    public function updateRelease(UpdateMobileReleaseRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $notifyUsers = (bool) ($validated['notify_users'] ?? false);
        unset($validated['notify_users']);

        $notifySummary = $this->adminService->updateReleaseSettings($validated, $notifyUsers);

        $message = 'Release settings saved.';
        if ($notifyUsers) {
            $message .= ' '.$this->formatNotifySummary($notifySummary);
        }

        return redirect()
            ->route('admin.mobile-app.index')
            ->with('success', $message);
    }

    /**
     * @param  array{
     *     total_customers?: int,
     *     with_push_devices?: int,
     *     without_devices?: int,
     *     recipients: int,
     *     delivered: int,
     *     failed: int,
     *     skipped_up_to_date: int
     * }|null  $summary
     */
    private function formatNotifySummary(?array $summary): string
    {
        return $this->adminService->formatNotifySummary($summary);
    }
}
