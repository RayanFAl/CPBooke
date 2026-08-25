<?php

namespace App\Modules\Api\Auth\Services;

use App\Models\NotificationTemplate;
use App\Models\User;
use App\Modules\Notifications\Services\NotificationLocaleResolver;
use App\Modules\Notifications\Services\NotificationPreferenceResolver;
use App\Modules\Notifications\Services\NotificationService;
use App\Modules\Notifications\Services\NotificationTemplateRenderer;
use App\Modules\Notifications\Support\NotificationTopics;
use App\Notifications\LoginAlertNotification;
use Illuminate\Support\Facades\Log;
use Throwable;

class LoginAlertService
{
    public function __construct(
        private readonly NotificationPreferenceResolver $preferenceResolver,
        private readonly NotificationService $notificationService,
        private readonly NotificationLocaleResolver $localeResolver,
        private readonly NotificationTemplateRenderer $templateRenderer,
    ) {}

    public function notify(User $user, string $deviceName, ?string $ip = null): void
    {
        if (! $this->preferenceResolver->topicEnabled($user, NotificationTopics::LOGIN_ALERTS)) {
            return;
        }

        $preferences = $this->preferenceResolver->preferencesFor($user);
        $locale = $this->localeResolver->forUser($user);
        [$title, $body] = $this->resolveCopy($user, $deviceName, $ip, $locale);

        try {
            $inApp = $this->notificationService->createSecurityNotification(
                $user,
                $title,
                $body,
                [
                    'topic' => NotificationTopics::LOGIN_ALERTS,
                    'device_name' => $deviceName,
                    'ip' => $ip,
                    'deep_link' => '/login',
                    'locale' => $locale,
                ],
            );

            if ($preferences->push_enabled) {
                $this->notificationService->sendPushOnly(
                    $user,
                    $title,
                    $body,
                    [
                        'deep_link' => '/login',
                        'topic' => NotificationTopics::LOGIN_ALERTS,
                        'template_code' => 'LOGIN_ALERT',
                        'notification_id' => (string) $inApp->id,
                        'notification_type' => 'system',
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

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveCopy(User $user, string $deviceName, ?string $ip, string $locale): array
    {
        $variables = [
            'user_name' => $user->full_name ?: $user->name ?: 'Customer',
            'device_name' => $deviceName !== '' ? $deviceName : ($locale === 'ar' ? 'جهاز' : 'a device'),
            'ip' => $ip
                ? ($locale === 'ar' ? " — IP: {$ip}" : " from IP {$ip}")
                : '',
            'deep_link' => '/login',
        ];

        $template = NotificationTemplate::query()
            ->where('code', 'LOGIN_ALERT')
            ->where('is_active', true)
            ->first();

        if ($template !== null) {
            $title = $this->templateRenderer->render($template->localizedSubject($locale), $variables)
                ?: 'New login to your account';
            $body = $this->templateRenderer->render($template->localizedBody($locale), $variables)
                ?: 'A new sign-in was detected.';

            return [$title, $body];
        }

        return [
            $locale === 'ar' ? 'تسجيل دخول جديد' : 'New login to your account',
            $locale === 'ar'
                ? "تم تسجيل الدخول إلى حسابك من {$variables['device_name']}{$variables['ip']}."
                : sprintf(
                    'A new sign-in was detected on %s%s.',
                    $variables['device_name'],
                    $variables['ip'],
                ),
        ];
    }
}
