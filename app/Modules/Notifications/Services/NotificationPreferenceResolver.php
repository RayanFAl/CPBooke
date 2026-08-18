<?php

namespace App\Modules\Notifications\Services;

use App\Models\User;
use App\Models\UserNotificationPreference;
use App\Modules\Notifications\Support\NotificationChannels;
use App\Modules\Notifications\Support\NotificationTopics;
use Illuminate\Support\Collection;

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

        if (! $this->topicAllowed($preferences, $definition)) {
            return [];
        }

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
            ->when(
                in_array(NotificationChannels::IN_APP, $requestedChannels, true),
                function ($collection): Collection {
                    if ($collection->contains(NotificationChannels::IN_APP)) {
                        return $collection;
                    }

                    return $collection->prepend(NotificationChannels::IN_APP);
                },
            )
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
                'sms_enabled' => false,
                'push_enabled' => true,
                'whatsapp_enabled' => false,
                'disabled_categories' => [],
                'topics' => NotificationTopics::defaults(),
            ],
        );
    }

    /**
     * Passenger-facing preference payload.
     *
     * @return array<string, bool>
     */
    public function toPassengerPayload(UserNotificationPreference $preferences): array
    {
        $topics = array_merge(
            NotificationTopics::defaults(),
            is_array($preferences->topics) ? $preferences->topics : [],
        );

        return [
            'push' => (bool) $preferences->push_enabled,
            'email' => (bool) $preferences->email_enabled,
            'sms' => (bool) $preferences->sms_enabled,
            'flight_updates' => (bool) ($topics[NotificationTopics::FLIGHT_UPDATES] ?? true),
            'booking_reminders' => (bool) ($topics[NotificationTopics::BOOKING_REMINDERS] ?? true),
            'promotional' => (bool) ($topics[NotificationTopics::PROMOTIONAL] ?? false),
            'insurance' => (bool) ($topics[NotificationTopics::INSURANCE] ?? true),
            'hotel' => (bool) ($topics[NotificationTopics::HOTEL] ?? true),
            'car_rental' => (bool) ($topics[NotificationTopics::CAR_RENTAL] ?? false),
            'login_alerts' => (bool) ($topics[NotificationTopics::LOGIN_ALERTS] ?? true),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function updateFromPassengerPayload(User $user, array $payload): UserNotificationPreference
    {
        $preferences = $this->preferencesFor($user);
        $topics = array_merge(
            NotificationTopics::defaults(),
            is_array($preferences->topics) ? $preferences->topics : [],
        );

        foreach (NotificationTopics::keys() as $topic) {
            if (array_key_exists($topic, $payload)) {
                $topics[$topic] = (bool) $payload[$topic];
            }
        }

        $preferences->forceFill([
            'push_enabled' => array_key_exists('push', $payload) ? (bool) $payload['push'] : $preferences->push_enabled,
            'email_enabled' => array_key_exists('email', $payload) ? (bool) $payload['email'] : $preferences->email_enabled,
            'sms_enabled' => array_key_exists('sms', $payload) ? (bool) $payload['sms'] : $preferences->sms_enabled,
            'topics' => $topics,
        ])->save();

        return $preferences->refresh();
    }

    public function topicEnabled(User $user, string $topic): bool
    {
        $preferences = $this->preferencesFor($user);
        $topics = array_merge(
            NotificationTopics::defaults(),
            is_array($preferences->topics) ? $preferences->topics : [],
        );

        return (bool) ($topics[$topic] ?? false);
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function topicAllowed(UserNotificationPreference $preferences, array $definition): bool
    {
        $topic = $definition['topic'] ?? $this->inferTopic($definition);

        if (! is_string($topic) || $topic === '') {
            return true;
        }

        $topics = array_merge(
            NotificationTopics::defaults(),
            is_array($preferences->topics) ? $preferences->topics : [],
        );

        return (bool) ($topics[$topic] ?? true);
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function inferTopic(array $definition): ?string
    {
        $payload = is_array($definition['payload'] ?? null) ? $definition['payload'] : [];
        $serviceType = strtolower((string) ($payload['service_type'] ?? $payload['product_type'] ?? ''));

        return match ($serviceType) {
            'flight' => NotificationTopics::FLIGHT_UPDATES,
            'hotel' => NotificationTopics::HOTEL,
            'insurance' => NotificationTopics::INSURANCE,
            'car', 'car_rental', 'transfer' => NotificationTopics::CAR_RENTAL,
            default => match ($definition['notification_type'] ?? null) {
                'promo', 'promotional', 'marketing' => NotificationTopics::PROMOTIONAL,
                'reminder' => NotificationTopics::BOOKING_REMINDERS,
                'login' => NotificationTopics::LOGIN_ALERTS,
                default => null,
            },
        };
    }

    private function channelEnabled(UserNotificationPreference $preferences, string $channel): bool
    {
        return match ($channel) {
            NotificationChannels::EMAIL => $preferences->email_enabled,
            NotificationChannels::IN_APP => true,
            NotificationChannels::SMS => $preferences->sms_enabled,
            NotificationChannels::PUSH => $preferences->push_enabled,
            NotificationChannels::WHATSAPP => $preferences->whatsapp_enabled,
            default => false,
        };
    }
}
