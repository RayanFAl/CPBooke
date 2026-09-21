<?php

namespace App\Modules\Notifications\Jobs;

use App\Models\User;
use App\Modules\Loyalty\Events\LoyaltyDiscountCampaignAnnounced;
use App\Modules\Notifications\Services\NotificationService;
use App\Modules\Notifications\Services\NotificationTemplateSyncService;
use App\Modules\Notifications\Support\NotificationChannels;
use Illuminate\Foundation\Bus\Dispatchable;

class BroadcastLoyaltyDiscountCampaignJob
{
    use Dispatchable;

    public function __construct(
        public readonly int $tierId,
        public readonly string $tierName,
        public readonly string $discountPercentage,
        public readonly string $durationLabelEn,
        public readonly string $durationLabelAr,
    ) {}

    /**
     * @return array{recipients: int, delivered: int, failed: int}
     */
    public function handle(
        NotificationService $notificationService,
        NotificationTemplateSyncService $templateSyncService,
    ): array {
        $templateSyncService->syncMissing();

        $recipients = 0;
        $delivered = 0;
        $failed = 0;

        User::query()
            ->where('account_type', User::ACCOUNT_TYPE_CUSTOMER)
            ->where('is_active', true)
            ->whereNull('account_deleted_at')
            ->orderBy('id')
            ->chunkById(50, function ($users) use ($notificationService, &$recipients, &$delivered, &$failed): void {
                foreach ($users as $user) {
                    $recipients++;

                    $notificationService->dispatchForEvent(new LoyaltyDiscountCampaignAnnounced(
                        tierId: $this->tierId,
                        tierName: $this->tierName,
                        discountPercentage: $this->discountPercentage,
                        durationLabelEn: $this->durationLabelEn,
                        durationLabelAr: $this->durationLabelAr,
                        users: [$user],
                    ));

                    $hasPushDevice = $user->notificationDevices()
                        ->where('is_active', true)
                        ->where('channel', NotificationChannels::PUSH)
                        ->exists();

                    if ($hasPushDevice) {
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
        ];
    }
}
