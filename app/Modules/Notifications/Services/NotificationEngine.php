<?php

namespace App\Modules\Notifications\Services;

use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Models\User;
use App\Modules\Admin\Governance\Events\NotificationQueued;
use App\Modules\Admin\Governance\Services\GovernanceEventDispatcher;
use App\Modules\Notifications\Jobs\SendNotificationChannelJob;
use App\Modules\Notifications\Support\NotificationChannels;
use App\Modules\Notifications\Support\NotificationDefinitionRegistry;
use App\Modules\Notifications\Support\NotificationInboxContract;
use App\Support\Rbac\RbacAuthorizer;

class NotificationEngine
{
    public function __construct(
        private readonly NotificationDefinitionRegistry $definitionRegistry,
        private readonly NotificationTemplateResolver $templateResolver,
        private readonly NotificationTemplateRenderer $templateRenderer,
        private readonly NotificationPreferenceResolver $preferenceResolver,
        private readonly NotificationLocaleResolver $localeResolver,
        private readonly RbacAuthorizer $rbacAuthorizer,
        private readonly GovernanceEventDispatcher $governanceEventDispatcher,
    ) {}

    public function dispatch(object $event, ?User $actor = null): void
    {
        if ($actor instanceof User) {
            $this->rbacAuthorizer->authorize('notifications.view', actor: $actor);
        }

        foreach ($this->definitionRegistry->definitionsFor($event) as $definition) {
            $template = $this->templateResolver->resolve($definition);

            if (! $template->is_active) {
                continue;
            }

            $templateChannels = array_values(array_intersect($definition['channels'] ?? [], $template->enabledChannels()));
            $definition = $this->withInboxContract($template->code, $definition);

            foreach ($this->uniqueUsers($definition['users'] ?? []) as $user) {
                $channels = $this->preferenceResolver->allowedChannels($user, $templateChannels, $definition);
                $this->deliverToUser($user, $template, $definition, $channels, $event::class, false);
            }
        }
    }

    /**
     * Send one persisted template to a user with sample/test payload.
     * Bypasses preference gates so admins can verify copy and delivery.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $channels
     * @return array<int, NotificationLog>
     */
    public function dispatchTemplate(
        User $user,
        NotificationTemplate $template,
        array $payload = [],
        array $channels = [],
        bool $syncOutbound = true,
    ): array {
        $requested = $channels !== []
            ? $channels
            : $template->enabledChannels();

        $requested = array_values(array_filter(
            $requested,
            static fn (mixed $channel): bool => is_string($channel) && in_array($channel, NotificationChannels::all(), true),
        ));

        $requested = array_values(array_diff($requested, [
            NotificationChannels::SMS,
            NotificationChannels::WHATSAPP,
        ]));

        if (! in_array(NotificationChannels::IN_APP, $requested, true)) {
            $requested[] = NotificationChannels::IN_APP;
        }

        $definition = [
            'code' => $template->code,
            'channels' => $requested,
            'notification_type' => $payload['notification_type'] ?? null,
            'payload' => $payload,
            'related_type' => $payload['related_type'] ?? null,
            'related_id' => $payload['related_id'] ?? null,
        ];

        $definition = $this->withInboxContract($template->code, $definition);

        return $this->deliverToUser(
            $user,
            $template,
            $definition,
            $requested,
            'admin_template_test',
            $syncOutbound,
        );
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<int, string>  $channels
     * @return array<int, NotificationLog>
     */
    private function deliverToUser(
        User $user,
        NotificationTemplate $template,
        array $definition,
        array $channels,
        string $eventClass,
        bool $syncOutbound,
    ): array {
        $channels = $this->prioritizeInAppChannel($channels);
        $logs = [];
        $inAppNotificationId = null;

        foreach ($channels as $channel) {
            $locale = $this->localeResolver->forUser($user);
            $payload = array_merge($definition['payload'] ?? [], array_filter([
                'notification_id' => $inAppNotificationId,
            ], static fn (mixed $value): bool => $value !== null && $value !== ''));

            $subject = $this->templateRenderer->render(
                $template->localizedSubject($locale),
                $payload,
            );
            $body = $this->templateRenderer->render(
                $template->localizedBody($locale),
                $payload,
            );

            $log = NotificationLog::query()->create([
                'user_id' => $user->id,
                'channel' => $channel,
                'template_code' => $template->code,
                'template_version' => $template->version,
                'event_class' => $eventClass,
                'notification_type' => $definition['notification_type'] ?? null,
                'subject' => $subject,
                'body' => $body,
                'variables' => array_merge($payload, ['locale' => $locale]),
                'audit_context' => [
                    'engine' => 'notification_core_v2',
                    'template_snapshot' => [
                        'code' => $template->code,
                        'version' => $template->version,
                        'channels' => $template->enabledChannels(),
                    ],
                    'requested_channels' => $definition['channels'] ?? [],
                    'resolved_channels' => $channels,
                ],
                'status' => NotificationLog::STATUS_PENDING,
                'retry_count' => 0,
                'related_type' => $definition['related_type'] ?? null,
                'related_id' => $definition['related_id'] ?? null,
            ]);

            $queue = 'notifications-'.$channel;

            if ($channel === NotificationChannels::IN_APP || $syncOutbound) {
                SendNotificationChannelJob::dispatchSync($log->id);
                $log->refresh();
                $response = is_array($log->response_payload) ? $log->response_payload : [];
                $inAppNotificationId = isset($response['notification_id'])
                    ? (string) $response['notification_id']
                    : $inAppNotificationId;
            } else {
                SendNotificationChannelJob::dispatch($log->id)
                    ->onQueue($queue);
            }

            $this->governanceEventDispatcher->dispatch(new NotificationQueued(
                notificationLogId: $log->id,
                userId: $log->user_id,
                channel: $log->channel,
                templateCode: $log->template_code,
                templateVersion: $log->template_version,
                eventClass: $log->event_class,
                notificationType: $log->notification_type,
                relatedType: $log->related_type,
                relatedId: $log->related_id,
                status: $log->status,
                queue: $queue,
                occurredAt: $log->created_at?->toIso8601String() ?? now()->toIso8601String(),
            ));

            $logs[] = $log;
        }

        return $logs;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    private function withInboxContract(string $code, array $definition): array
    {
        $payload = is_array($definition['payload'] ?? null) ? $definition['payload'] : [];
        if (! isset($payload['channels']) && isset($definition['channels']) && is_array($definition['channels'])) {
            $payload['channels'] = $definition['channels'];
        }
        $inbox = NotificationInboxContract::enrich($code, $payload);
        $definition['payload'] = array_merge($payload, $inbox);

        $family = $inbox['family'];
        $bypassTopics = in_array($family, [
            NotificationInboxContract::FAMILY_TRANSACTIONAL,
            NotificationInboxContract::FAMILY_OPERATIONAL,
        ], true) && $code !== 'LOGIN_ALERT';

        if ($bypassTopics) {
            $definition['topic'] = null;
            $definition['payload']['topic'] = null;
        }

        return $definition;
    }

    /**
     * @param  array<int, string>  $channels
     * @return array<int, string>
     */
    private function prioritizeInAppChannel(array $channels): array
    {
        if (! in_array(NotificationChannels::IN_APP, $channels, true)) {
            return $channels;
        }

        return array_values(array_unique([
            NotificationChannels::IN_APP,
            ...array_values(array_filter(
                $channels,
                static fn (string $channel): bool => $channel !== NotificationChannels::IN_APP,
            )),
        ]));
    }

    /**
     * @param  array<int, mixed>  $users
     * @return array<int, User>
     */
    private function uniqueUsers(array $users): array
    {
        return collect($users)
            ->filter(fn (mixed $user): bool => $user instanceof User && $user->exists)
            ->unique(fn (User $user): int => $user->id)
            ->values()
            ->all();
    }
}
