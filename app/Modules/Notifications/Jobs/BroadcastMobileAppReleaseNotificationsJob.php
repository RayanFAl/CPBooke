<?php

namespace App\Modules\Notifications\Jobs;

use App\Models\NotificationLog;
use App\Models\User;
use App\Modules\Admin\MobileApp\Events\MobileAppReleasePublished;
use App\Modules\Notifications\Services\NotificationService;
use App\Modules\Notifications\Services\NotificationTemplateSyncService;
use App\Modules\Notifications\Support\NotificationChannels;
use Illuminate\Foundation\Bus\Dispatchable;

class BroadcastMobileAppReleaseNotificationsJob
{
    use Dispatchable;

    public function __construct(
        public readonly string $version,
        public readonly int $versionCode,
        public readonly string $notesAr,
        public readonly string $notesEn,
        public readonly string $downloadUrl,
        public readonly string $pageUrl,
        public readonly bool $forceUpdate,
    ) {}

    /**
     * @return array{recipients: int, delivered: int, failed: int, skipped_up_to_date: int}
     */
    public function handle(
        NotificationService $notificationService,
        NotificationTemplateSyncService $templateSyncService,
    ): array {
        $templateSyncService->syncMissing();

        $recipients = 0;
        $delivered = 0;
        $failed = 0;
        $skippedUpToDate = 0;

        User::query()
            ->where('account_type', User::ACCOUNT_TYPE_CUSTOMER)
            ->where('is_active', true)
            ->whereHas('notificationDevices', function ($query): void {
                $query->where('is_active', true)
                    ->where('channel', NotificationChannels::PUSH);
            })
            ->orderBy('id')
            ->chunkById(50, function ($users) use ($notificationService, &$recipients, &$delivered, &$failed, &$skippedUpToDate): void {
                foreach ($users as $user) {
                    $hasOutdatedDevice = $user->notificationDevices()
                        ->where('is_active', true)
                        ->where('channel', NotificationChannels::PUSH)
                        ->where(function ($query): void {
                            $query->whereNull('app_version_code')
                                ->orWhere('app_version_code', '<', $this->versionCode);
                        })
                        ->exists();

                    if (! $hasOutdatedDevice) {
                        $skippedUpToDate++;

                        continue;
                    }

                    $recipients++;

                    $notificationService->dispatchForEvent(new MobileAppReleasePublished(
                        version: $this->version,
                        versionCode: $this->versionCode,
                        notesAr: $this->notesAr,
                        notesEn: $this->notesEn,
                        downloadUrl: $this->downloadUrl,
                        pageUrl: $this->pageUrl,
                        forceUpdate: $this->forceUpdate,
                        users: [$user],
                    ));

                    $pushLog = NotificationLog::query()
                        ->where('user_id', $user->id)
                        ->where('template_code', 'APP_UPDATE_AVAILABLE')
                        ->where('channel', NotificationChannels::PUSH)
                        ->where('related_type', 'mobile_app_release')
                        ->where('related_id', $this->versionCode)
                        ->latest('id')
                        ->first();

                    if ($pushLog !== null && $pushLog->status === NotificationLog::STATUS_SENT) {
                        $delivered++;
                    } else {
                        $failed++;
                    }
                }
            });

        return [
            'recipients' => $recipients,
            'delivered' => $delivered,
            'failed' => $failed,
            'skipped_up_to_date' => $skippedUpToDate,
        ];
    }
}
