<?php

namespace App\Modules\Notifications\Services;

use App\Models\User;
use App\Models\UserNotificationPreference;
use App\Modules\Notifications\Support\NotificationChannels;

class NotificationPreferenceResolver
{
    /**
     * @param  array<int, string>  $requestedChannels
     * @param  array<string, mixed>  $definition
     * @return array<int, string>
     */
    public function allowedChannels(User $user, array $requestedChannels, array $definition): array
    {
        $preferences = $this->preferencesFor($user);
        $category = $definition['notification_type'] ?? null;
        $disabledCategories = collect($preferences->disabled_categories ?? [])
            ->filter(fn (mixed $value): bool => is_string($value))
            ->values();

        if (is_string($category) && $disabledCategories->contains($category)) {
            return [];
        }

        return collect($requestedChannels)
            ->filter(fn (mixed $channel): bool => is_string($channel) && in_array($channel, NotificationChannels::all(), true))
            ->filter(fn (string $channel): bool => $this->channelEnabled($preferences, $channel))
            ->values()
            ->all();
    }

    public function preferencesFor(User $user): UserNotificationPreference
    {
        return UserNotificationPreference::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'email_enabled' => true,
                'in_app_enabled' => true,
                'sms_enabled' => true,
                'push_enabled' => true,
                'whatsapp_enabled' => true,
                'disabled_categories' => [],
            ],
        );
    }

    private function channelEnabled(UserNotificationPreference $preferences, string $channel): bool
    {
        return match ($channel) {
            NotificationChannels::EMAIL => $preferences->email_enabled,
            NotificationChannels::IN_APP => $preferences->in_app_enabled,
            NotificationChannels::SMS => $preferences->sms_enabled,
            NotificationChannels::PUSH => $preferences->push_enabled,
            NotificationChannels::WHATSAPP => $preferences->whatsapp_enabled,
            default => false,
        };
    }
}