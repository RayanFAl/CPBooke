<?php

namespace App\Modules\Admin\Notifications\Http\Controllers;

use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Models\User;
use App\Models\UserNotification;
use App\Modules\Admin\Notifications\Http\Requests\SendTestPushRequest;
use App\Modules\Admin\Notifications\Http\Requests\SendTestTemplateRequest;
use App\Modules\Admin\Notifications\Http\Requests\UpdateNotificationTemplateRequest;
use App\Modules\Admin\Notifications\Http\Requests\UploadFirebaseCredentialsRequest;
use App\Modules\Notifications\Services\FcmHttpV1Client;
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
use Illuminate\Support\Facades\File;
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
        private readonly FcmHttpV1Client $fcmHttpV1Client,
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
                    'push_audience_count' => $this->pushAudienceCount(),
                    'test_targets' => $this->testTargets(),
                    'whatsapp_sandbox' => app(WhatsAppSandboxInbox::class)->all(),
                    'firebase' => $this->firebaseStatus(),
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
                'push_audience_count' => $this->pushAudienceCount(),
                'test_targets' => $this->testTargets(),
                'whatsapp_sandbox' => app(WhatsAppSandboxInbox::class)->all(),
                'firebase' => $this->firebaseStatus(),
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

    public function uploadFirebaseCredentials(UploadFirebaseCredentialsRequest $request): RedirectResponse
    {
        Gate::authorize('notifications.manage-templates');

        $file = $request->file('credentials');
        $raw = (string) file_get_contents($file->getRealPath());
        /** @var array<string, mixed>|null $payload */
        $payload = json_decode($raw, true);

        if (! is_array($payload)) {
            return redirect()
                ->route('admin.notifications.index', ['tab' => 'status', 'setup' => 'firebase'])
                ->with('error', 'Firebase file must be valid JSON.');
        }

        $required = ['type', 'project_id', 'private_key', 'client_email'];
        foreach ($required as $key) {
            if (! filled($payload[$key] ?? null)) {
                return redirect()
                    ->route('admin.notifications.index', ['tab' => 'status', 'setup' => 'firebase'])
                    ->with('error', "Firebase JSON is missing \"{$key}\".");
            }
        }

        if (($payload['type'] ?? '') !== 'service_account') {
            return redirect()
                ->route('admin.notifications.index', ['tab' => 'status', 'setup' => 'firebase'])
                ->with('error', 'Firebase JSON type must be service_account.');
        }

        $targetPath = $this->firebaseCredentialsTargetPath();
        File::ensureDirectoryExists(dirname($targetPath));
        File::put($targetPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);

        $this->rbacAuditLogger->log(
            'notifications.firebase_credentials.uploaded',
            'notifications.manage-templates',
            auth()->user(),
            'firebase_credentials',
            null,
            [
                'project_id' => (string) $payload['project_id'],
                'client_email' => (string) $payload['client_email'],
            ],
        );

        return redirect()
            ->route('admin.notifications.index', ['tab' => 'status', 'setup' => 'firebase'])
            ->with('success', 'Firebase credentials saved. Push is ready.');
    }

    public function testFirebase(): RedirectResponse
    {
        Gate::authorize('notifications.view');

        $result = $this->fcmHttpV1Client->verifyConnection();

        $this->rbacAuditLogger->log(
            'notifications.firebase_credentials.tested',
            'notifications.view',
            auth()->user(),
            'firebase_credentials',
            null,
            [
                'ok' => $result['ok'],
                'project_id' => $result['project_id'] ?? null,
                'reason' => $result['reason'] ?? null,
            ],
        );

        if (($result['ok'] ?? false) === true) {
            $projectId = (string) ($result['project_id'] ?? '');

            return redirect()
                ->route('admin.notifications.index', ['tab' => 'status', 'setup' => 'firebase'])
                ->with('success', $projectId !== ''
                    ? "Firebase works. Project: {$projectId}."
                    : 'Firebase works.');
        }

        $reason = (string) ($result['reason'] ?? 'unknown');
        $message = match ($reason) {
            'missing_credentials' => 'Firebase file is missing. Upload it first.',
            default => "Firebase test failed: {$reason}",
        };

        return redirect()
            ->route('admin.notifications.index', ['tab' => 'status', 'setup' => 'firebase'])
            ->with('error', $message);
    }

    public function disconnectFirebase(): RedirectResponse
    {
        Gate::authorize('notifications.manage-templates');

        $targetPath = $this->firebaseCredentialsTargetPath();

        if (! is_file($targetPath)) {
            return redirect()
                ->route('admin.notifications.index', ['tab' => 'status', 'setup' => 'firebase'])
                ->with('error', 'No Firebase file to hide.');
        }

        $hiddenPath = $this->firebaseHiddenCredentialsPath();
        File::ensureDirectoryExists(dirname($hiddenPath));

        if (is_file($hiddenPath)) {
            File::delete($hiddenPath);
        }

        File::move($targetPath, $hiddenPath);

        $this->rbacAuditLogger->log(
            'notifications.firebase_credentials.disconnected',
            'notifications.manage-templates',
            auth()->user(),
            'firebase_credentials',
            null,
            ['hidden' => true],
        );

        return redirect()
            ->route('admin.notifications.index', ['tab' => 'status', 'setup' => 'firebase'])
            ->with('success', 'Firebase disconnected. Upload the file again to reconnect.');
    }

    public function restoreFirebase(): RedirectResponse
    {
        Gate::authorize('notifications.manage-templates');

        $hiddenPath = $this->firebaseHiddenCredentialsPath();
        $targetPath = $this->firebaseCredentialsTargetPath();

        if (! is_file($hiddenPath)) {
            return redirect()
                ->route('admin.notifications.index', ['tab' => 'status', 'setup' => 'firebase'])
                ->with('error', 'No hidden Firebase file to restore.');
        }

        File::ensureDirectoryExists(dirname($targetPath));

        if (is_file($targetPath)) {
            File::delete($targetPath);
        }

        File::move($hiddenPath, $targetPath);

        $this->rbacAuditLogger->log(
            'notifications.firebase_credentials.restored',
            'notifications.manage-templates',
            auth()->user(),
            'firebase_credentials',
            null,
            ['restored' => true],
        );

        return redirect()
            ->route('admin.notifications.index', ['tab' => 'status', 'setup' => 'firebase'])
            ->with('success', 'Previous Firebase file restored.');
    }

    /**
     * Safe public status for the admin UI — never expose private_key.
     *
     * @return array{configured: bool, project_id: string|null, client_email: string|null, path: string, has_backup: bool}
     */
    private function firebaseStatus(): array
    {
        $path = $this->fcmHttpV1Client->credentialsPath();
        $target = $this->firebaseCredentialsTargetPath();
        $hasBackup = is_file($this->firebaseHiddenCredentialsPath());

        if ($path === null) {
            return [
                'configured' => false,
                'project_id' => null,
                'client_email' => null,
                'path' => $this->displayPath($target),
                'has_backup' => $hasBackup,
            ];
        }

        /** @var array<string, mixed> $json */
        $json = json_decode((string) file_get_contents($path), true) ?: [];
        $email = trim((string) ($json['client_email'] ?? ''));

        return [
            'configured' => true,
            'project_id' => filled($json['project_id'] ?? null) ? (string) $json['project_id'] : null,
            'client_email' => $email !== '' ? $this->maskEmail($email) : null,
            'path' => $this->displayPath($path),
            'has_backup' => $hasBackup,
        ];
    }

    private function firebaseCredentialsTargetPath(): string
    {
        $configured = trim((string) config('services.notifications.firebase_credentials'));

        if ($configured === '') {
            return storage_path('app/firebase/firebase_credentials.json');
        }

        if (str_starts_with($configured, '/') || (bool) preg_match('/^[A-Za-z]:[\\\\\\/]/', $configured)) {
            return $configured;
        }

        return base_path($configured);
    }

    private function firebaseHiddenCredentialsPath(): string
    {
        return $this->firebaseCredentialsTargetPath().'.hidden';
    }

    private function displayPath(string $absolutePath): string
    {
        $base = str_replace('\\', '/', base_path());
        $path = str_replace('\\', '/', $absolutePath);

        if (str_starts_with($path, $base.'/')) {
            return substr($path, strlen($base) + 1);
        }

        return 'storage/app/firebase/firebase_credentials.json';
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        if ($domain === '') {
            return '***';
        }

        $prefix = substr($local, 0, min(3, strlen($local)));

        return $prefix.'***@'.$domain;
    }

    public function sendTestPush(SendTestPushRequest $request): RedirectResponse
    {
        Gate::authorize('notifications.view');

        $audience = (string) $request->validated('user_id');
        $title = $request->validated('title');
        $body = $request->validated('body');

        if ($audience === 'all') {
            $summary = $this->notificationService->sendTestPushToAllWithDevices($title, $body);

            $this->rbacAuditLogger->log(
                'notifications.push_test.broadcast',
                'notifications.view',
                auth()->user(),
                'user',
                null,
                $summary,
            );

            $message = "Custom push sent to {$summary['recipients']} users with devices (ok: {$summary['delivered']}, failed: {$summary['failed']}).";

            return redirect()
                ->route('admin.notifications.index', ['tab' => 'send'])
                ->with($summary['recipients'] > 0 && $summary['failed'] === $summary['recipients'] ? 'error' : 'success', $message);
        }

        $user = User::query()->findOrFail((int) $audience);

        $result = $this->notificationService->sendTestPush(
            $user,
            $title,
            $body,
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
            ->route('admin.notifications.index', ['tab' => 'send'])
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
            ->route('admin.notifications.index', ['tab' => 'send'])
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

    private function pushAudienceCount(): int
    {
        if (! Schema::hasTable('user_notification_devices')) {
            return 0;
        }

        return User::query()
            ->whereHas('notificationDevices', function ($query): void {
                $query->where('is_active', true);
            })
            ->count();
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
