<?php

namespace App\Modules\Admin\Notifications\Http\Controllers;

use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Models\User;
use App\Models\UserNotification;
use App\Modules\Admin\Notifications\Http\Requests\SendTestPushRequest;
use App\Modules\Admin\Notifications\Http\Requests\UpdateNotificationTemplateRequest;
use App\Modules\Notifications\Services\NotificationChannelManager;
use App\Modules\Notifications\Services\NotificationService;
use App\Modules\Notifications\Services\NotificationTemplateSyncService;
use App\Modules\Notifications\Support\NotificationChannels;
use App\Support\Rbac\RbacAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class NotificationsController
{
    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly NotificationChannelManager $channelManager,
        private readonly NotificationTemplateSyncService $templateSyncService,
        private readonly RbacAuditLogger $rbacAuditLogger,
    ) {
    }

    public function index(Request $request): Response
    {
        Gate::authorize('notifications.view');

        if (! Schema::hasTable('notification_logs')) {
            return Inertia::render('admin/notifications/pages/Index', [
                'dashboard' => [
                    'metrics' => [
                        'total_logs' => 0,
                        'pending_logs' => 0,
                        'sent_logs' => 0,
                        'failed_logs' => 0,
                        'unread_in_app' => 0,
                    ],
                    'channel_statuses' => $this->channelManager->statuses(),
                    'logs' => [],
                    'failed_logs' => [],
                    'templates' => [],
                    'available_channels' => NotificationChannels::all(),
                    'push_targets' => $this->pushTargets(),
                ],
            ]);
        }

        $logs = NotificationLog::query()
            ->with('user:id,name,full_name,email')
            ->latest('id')
            ->limit(40)
            ->get();

        $failedLogs = NotificationLog::query()
            ->with('user:id,name,full_name,email')
            ->where('status', NotificationLog::STATUS_FAILED)
            ->latest('id')
            ->limit(20)
            ->get();

        $templates = NotificationTemplate::query()
            ->orderBy('code')
            ->get();

        return Inertia::render('admin/notifications/pages/Index', [
            'dashboard' => [
                'metrics' => [
                    'total_logs' => NotificationLog::query()->count(),
                    'pending_logs' => NotificationLog::query()->where('status', NotificationLog::STATUS_PENDING)->count(),
                    'sent_logs' => NotificationLog::query()->where('status', NotificationLog::STATUS_SENT)->count(),
                    'failed_logs' => NotificationLog::query()->where('status', NotificationLog::STATUS_FAILED)->count(),
                    'unread_in_app' => Schema::hasTable('user_notifications')
                        ? UserNotification::query()->whereNull('read_at')->count()
                        : 0,
                ],
                'channel_statuses' => $this->channelManager->statuses(),
                'logs' => $logs->map(fn (NotificationLog $log): array => $this->logPayload($log))->values()->all(),
                'failed_logs' => $failedLogs->map(fn (NotificationLog $log): array => $this->logPayload($log))->values()->all(),
                'templates' => $templates->map(fn (NotificationTemplate $template): array => [
                    'id' => $template->id,
                    'code' => $template->code,
                    'name' => $template->name,
                    'subject' => $template->subject,
                    'body' => $template->body,
                    'channels' => $template->channels ?? [],
                    'variables' => $template->variables ?? [],
                    'version' => $template->version,
                    'is_active' => $template->is_active,
                ])->values()->all(),
                'available_channels' => NotificationChannels::all(),
                'push_targets' => $this->pushTargets(),
            ],
        ]);
    }

    public function syncTemplates(): RedirectResponse
    {
        Gate::authorize('notifications.manage-templates');

        $result = $this->templateSyncService->syncMissing();

        $this->rbacAuditLogger->log(
            'notifications.templates.synced',
            'notifications.manage-templates',
            auth()->user(),
            'notification_template',
            null,
            $result,
        );

        return redirect()
            ->route('admin.notifications.index')
            ->with('success', "Templates synced — created {$result['created']}, already present {$result['existing']}.");
    }

    public function sendTestPush(SendTestPushRequest $request): RedirectResponse
    {
        Gate::authorize('notifications.view');

        $user = User::query()->findOrFail((int) $request->validated('user_id'));

        $result = $this->notificationService->sendTestPush(
            $user,
            $request->validated('title'),
            $request->validated('body'),
        );

        $push = is_array($result['push'] ?? null) ? $result['push'] : [];
        $delivered = ($push['delivered'] ?? false) === true;
        $provider = (string) ($push['provider'] ?? 'unknown');
        $success = (int) ($push['success'] ?? ($delivered ? 1 : 0));
        $failure = (int) ($push['failure'] ?? ($delivered ? 0 : 1));

        $this->rbacAuditLogger->log(
            'notifications.push_test.sent',
            'notifications.view',
            auth()->user(),
            'user',
            $user->id,
            [
                'provider' => $provider,
                'delivered' => $delivered,
                'success' => $success,
                'failure' => $failure,
            ],
        );

        $message = $delivered
            ? "Push sent to {$user->email} via {$provider} (ok: {$success}, failed: {$failure})."
            : "Push failed for {$user->email} via {$provider}"
                .(isset($push['reason']) ? ' — '.$push['reason'] : '')
                .'.';

        return redirect()
            ->route('admin.notifications.index')
            ->with($delivered ? 'success' : 'error', $message);
    }

    public function retry(NotificationLog $notificationLog): RedirectResponse
    {
        Gate::authorize('notifications.retry-failed');

        $this->notificationService->retry($notificationLog);
        $this->rbacAuditLogger->log('notifications.retry.queued', 'notifications.retry-failed', auth()->user(), 'notification_log', $notificationLog->id, [
            'channel' => $notificationLog->channel,
            'template_code' => $notificationLog->template_code,
        ]);

        return redirect()
            ->route('admin.notifications.index')
            ->with('success', 'Notification retry queued successfully.');
    }

    public function updateTemplate(UpdateNotificationTemplateRequest $request, NotificationTemplate $notificationTemplate): RedirectResponse
    {
        Gate::authorize('notifications.manage-templates');

        $notificationTemplate->forceFill($request->validated());

        if ($notificationTemplate->isDirty(['name', 'subject', 'body', 'channels', 'variables', 'is_active'])) {
            $notificationTemplate->version = max(1, (int) $notificationTemplate->version) + 1;
        }

        $notificationTemplate->save();
        $this->rbacAuditLogger->log('notifications.template.updated', 'notifications.manage-templates', auth()->user(), 'notification_template', $notificationTemplate->id, [
            'code' => $notificationTemplate->code,
            'version' => $notificationTemplate->version,
        ]);

        return redirect()
            ->route('admin.notifications.index')
            ->with('success', 'Notification template updated successfully.');
    }

    /**
     * Users with at least one active push device (for temporary admin tester).
     *
     * @return array<int, array<string, mixed>>
     */
    private function pushTargets(): array
    {
        if (! Schema::hasTable('user_notification_devices')) {
            return [];
        }

        return User::query()
            ->whereHas('notificationDevices', function ($query): void {
                $query->where('is_active', true);
            })
            ->withCount([
                'notificationDevices as active_devices_count' => function ($query): void {
                    $query->where('is_active', true);
                },
            ])
            ->orderByDesc('id')
            ->limit(50)
            ->get(['id', 'name', 'full_name', 'email'])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->full_name ?: $user->name,
                'email' => $user->email,
                'devices' => (int) $user->active_devices_count,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function logPayload(NotificationLog $log): array
    {
        return [
            'id' => $log->id,
            'user' => [
                'id' => $log->user?->id,
                'name' => $log->user?->full_name ?: $log->user?->name,
                'email' => $log->user?->email,
            ],
            'channel' => $log->channel,
            'template_code' => $log->template_code,
            'template_version' => $log->template_version,
            'notification_type' => $log->notification_type,
            'status' => $log->status,
            'retry_count' => $log->retry_count,
            'subject' => $log->subject,
            'body' => $log->body,
            'audit_context' => $log->audit_context ?? [],
            'response_payload' => $log->response_payload ?? [],
            'sent_at' => $log->sent_at?->toDateTimeString(),
            'failed_at' => $log->failed_at?->toDateTimeString(),
            'created_at' => $log->created_at?->toDateTimeString(),
        ];
    }
}