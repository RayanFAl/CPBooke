<?php

namespace App\Modules\Api\Auth\Services;

use App\Models\User;
use App\Modules\Notifications\Services\NotificationPreferenceResolver;
use App\Modules\Notifications\Services\NotificationService;
use App\Modules\Notifications\Support\NotificationTopics;
use App\Notifications\LoginAlertNotification;
use Illuminate\Support\Facades\Log;
use Throwable;

class LoginAlertService
{
    public function __construct(
        private readonly NotificationPreferenceResolver $preferenceResolver,
        private readonly NotificationService $notificationService,
    ) {
    }

    public function notify(User $user, string $deviceName, ?string $ip = null): void
    {
        if (! $this->preferenceResolver->topicEnabled($user, NotificationTopics::LOGIN_ALERTS)) {
            return;
        }

        $preferences = $this->preferenceResolver->preferencesFor($user);
        $title = 'New login to your account';
        $body = sprintf(
            'A new sign-in was detected on %s%s.',
            $deviceName !== '' ? $deviceName : 'a device',
            $ip ? ' from IP '.$ip : '',
        );

        try {
            // Always keep an in-app trail for security visibility when login alerts are on.
            $this->notificationService->createSecurityNotification(
                $user,
                $title,
                $body,
                [
                    'topic' => NotificationTopics::LOGIN_ALERTS,
                    'device_name' => $deviceName,
                    'ip' => $ip,
                ],
            );

            if ($preferences->push_enabled) {
                $this->notificationService->sendPushOnly(
                    $user,
                    $title,
                    $body,
                    [
                        'deep_link' => '/security/sessions',
                        'topic' => NotificationTopics::LOGIN_ALERTS,
                    ],
                );
            }

            if ($preferences->email_enabled) {
                $user->notify(new LoginAlertNotification($title, $body, $deviceName, $ip));
            }
        } catch (Throwable $exception) {
            Log::warning('Login alert failed', [
                'user_id' => $user->id,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
