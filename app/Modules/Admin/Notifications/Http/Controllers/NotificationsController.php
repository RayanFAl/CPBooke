<?php

namespace App\Modules\Admin\Notifications\Http\Controllers;

use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Models\User;
use App\Models\UserNotification;
use App\Modules\Admin\Notifications\Http\Requests\SendTestPushRequest;
use App\Modules\Admin\Notifications\Http\Requests\SendTestTemplateRequest;
use App\Modules\Admin\Notifications\Http\Requests\UpdateNotificationTemplateRequest;
use App\Modules\Notifications\Services\NotificationChannelManager;
use App\Modules\Notifications\Services\NotificationService;
use App\Modules\Notifications\Services\NotificationTemplateSyncService;
use App\Modules\Notifications\Support\NotificationChannels;
use App\Modules\Notifications\Support\NotificationLocales;
use App\Modules\Notifications\Support\NotificationTemplateCategories;
use App\Modules\Notifications\Support\NotificationTemplateSamples;
use App\Modules\Notifications\Support\NotificationTemplateStaffLabels;
use App\Modules\Notifications\Support\WhatsAppSandboxInbox;
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
    ) {}

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
                    'template_categories' => NotificationTemplateCategories::options(),
                    'template_locales' => NotificationLocales::labels(),
                    'sample_variables' => NotificationTemplateSamples::defaults(),
                    'available_channels' => NotificationChannels::all(),
                    'push_targets' => $this->pushTargets(),
                    'test_targets' => $this->testTargets(),
                    'whatsapp_sandbox' => app(WhatsAppSandboxInbox::class)->all(),
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
            ->orderBy('category')
            ->orderBy('name')
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
                'templates' => $templates->map(fn (NotificationTemplate $template): array => $this->templatePayload($template))->values()->all(),
                'template_categories' => NotificationTemplateCategories::options(),
                'template_locales' => NotificationLocales::labels(),
                'sample_variables' => NotificationTemplateSamples::defaults(),
                'available_channels' => NotificationChannels::all(),
                'push_targets' => $this->pushTargets(),
                'test_targets' => $this->testTargets(),
                'whatsapp_sandbox' => app(WhatsAppSandboxInbox::class)->all(),
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
            ->with('success', "Templates synced — created {$result['created']}, existing {$result['existing']}, Arabic seeded {$result['translations_seeded']}, metadata updated {$result['metadata_updated']}.");
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
            ->route('admin.notifications.index', ['tab' => 'tools'])
            ->with($delivered ? 'success' : 'error', $message);
    }

    public function sendTestTemplate(SendTestTemplateRequest $request): RedirectResponse
    {
        Gate::authorize('notifications.view');

        $user = User::query()->findOrFail((int) $request->validated('user_id'));
        $templateCode = strtoupper((string) $request->validated('template_code'));
        $includeEmail = $request->boolean('include_email');
        $includeWhatsapp = $request->boolean('include_whatsapp');

        $channels = [
            NotificationChannels::IN_APP,
            NotificationChannels::PUSH,
        ];

        if ($includeEmail) {
            $channels[] = NotificationChannels::EMAIL;
        }

        if ($includeWhatsapp) {
            $channels[] = NotificationChannels::WHATSAPP;
        }

        $result = $this->notificationService->sendTestTemplates(
            $user,
            $templateCode === 'ALL' ? null : $templateCode,
            $channels,
        );

        $this->rbacAuditLogger->log(
            'notifications.template_test.sent',
            'notifications.view',
            auth()->user(),
            'user',
            $user->id,
            [
                'template_code' => $templateCode,
                'count' => $result['count'],
                'include_email' => $includeEmail,
                'include_whatsapp' => $includeWhatsapp,
            ],
        );

        $label = $templateCode === 'ALL'
            ? "{$result['count']} templates"
            : $templateCode;

        $via = 'in-app + push';
        if ($includeEmail) {
            $via .= ' + email';
        }
        if ($includeWhatsapp) {
            $via .= ' + WhatsApp sandbox';
        }

        return redirect()
            ->route('admin.notifications.index', ['tab' => 'tools'])
            ->with('success', "Test sent to {$user->email}: {$label} ({$via}). Check Logs, and Tools for the WhatsApp sandbox.");
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

        if ($notificationTemplate->isDirty(['name', 'category', 'description', 'subject', 'body', 'translations', 'channels', 'variables', 'is_active'])) {
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
     * Recent users that can receive a template test (in-app works without a device).
     *
     * @return array<int, array<string, mixed>>
     */
    private function testTargets(): array
    {
        $query = User::query()->orderByDesc('id')->limit(50);

        if (Schema::hasTable('user_notification_devices')) {
            $query->withCount([
                'notificationDevices as active_devices_count' => function ($query): void {
                    $query->where('is_active', true);
                },
            ]);
        }

        return $query
            ->get(['id', 'name', 'full_name', 'email', 'phone'])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->full_name ?: $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'devices' => (int) ($user->active_devices_count ?? 0),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function templatePayload(NotificationTemplate $template): array
    {
        $categoryLabels = NotificationTemplateCategories::labels();
        $categoryLabelsAr = NotificationTemplateCategories::labelsAr();
        $staff = NotificationTemplateStaffLabels::for((string) $template->code);

        return [
            'id' => $template->id,
            'code' => $template->code,
            'name' => $template->name,
            'label' => $staff['en'],
            'label_ar' => $staff['ar'],
            'category' => $template->category,
            'category_label' => $categoryLabels[$template->category] ?? $template->category,
            'category_label_ar' => $categoryLabelsAr[$template->category] ?? $template->category,
            'description' => $template->description,
            'subject' => $template->subject,
            'body' => $template->body,
            'translations' => $template->translations ?? [],
            'has_arabic' => $template->hasLocale(NotificationLocales::AR),
            'has_english' => $template->hasLocale(NotificationLocales::EN),
            'channels' => $template->channels ?? [],
            'variables' => $template->variables ?? [],
            'sample_variables' => NotificationTemplateSamples::forCode($template->code),
            'version' => $template->version,
            'is_active' => $template->is_active,
        ];
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
            'template_label' => $log->template_code
                ? NotificationTemplateStaffLabels::english((string) $log->template_code)
                : null,
            'template_label_ar' => $log->template_code
                ? NotificationTemplateStaffLabels::arabic((string) $log->template_code)
                : null,
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
