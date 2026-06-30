<?php

namespace App\Modules\Notifications\Services;

use App\Models\NotificationLog;
use App\Models\User;
use App\Modules\Admin\Governance\Events\NotificationQueued;
use App\Modules\Admin\Governance\Services\GovernanceEventDispatcher;
use App\Modules\Notifications\Jobs\SendNotificationChannelJob;
use App\Modules\Notifications\Support\NotificationDefinitionRegistry;
use App\Support\Rbac\RbacAuthorizer;

class NotificationEngine
{
    public function __construct(
        private readonly NotificationDefinitionRegistry $definitionRegistry,
        private readonly NotificationTemplateResolver $templateResolver,
        private readonly NotificationTemplateRenderer $templateRenderer,
        private readonly NotificationPreferenceResolver $preferenceResolver,
        private readonly RbacAuthorizer $rbacAuthorizer,
        private readonly GovernanceEventDispatcher $governanceEventDispatcher,
    ) {
    }

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

            foreach ($this->uniqueUsers($definition['users'] ?? []) as $user) {
                $channels = $this->preferenceResolver->allowedChannels($user, $templateChannels, $definition);

                foreach ($channels as $channel) {
                    $payload = $definition['payload'] ?? [];
                    $log = NotificationLog::query()->create([
                        'user_id' => $user->id,
                        'channel' => $channel,
                        'template_code' => $template->code,
                        'template_version' => $template->version,
                        'event_class' => $event::class,
                        'notification_type' => $definition['notification_type'] ?? null,
                        'subject' => $this->templateRenderer->render($template->subject, $payload),
                        'body' => $this->templateRenderer->render($template->body, $payload),
                        'variables' => $payload,
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

                    SendNotificationChannelJob::dispatch($log->id)
                        ->onQueue($queue);

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
                }
            }
        }
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