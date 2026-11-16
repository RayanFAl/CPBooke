<?php

namespace App\Modules\Admin\Settings\Http\Controllers;

use App\Modules\Admin\Settings\Http\Requests\UpdateSystemSettingsRequest;
use App\Modules\Admin\Settings\Services\SystemSettingsAdminService;
use App\Modules\Notifications\Services\NotificationChannelManager;
use App\Modules\Settings\Services\SystemSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class SettingsController
{
    public function __construct(
        private readonly SystemSettingsAdminService $adminService,
        private readonly SystemSettingsService $systemSettingsService,
        private readonly NotificationChannelManager $channelManager,
    ) {
    }

    public function index(Request $request): Response
    {
        $settings = $this->adminService->getSettings();

        return Inertia::render('admin/settings/pages/Index', [
            'settings' => [
                'company_name' => $settings->company_name,
                'company_address' => $settings->company_address,
                'support_email' => $settings->support_email ?: $this->systemSettingsService->supportEmail(),
                'support_phone' => $settings->support_phone,
                'noreply_email' => $settings->noreply_email ?: $this->systemSettingsService->noreplyEmail(),
                'info_email' => $settings->info_email ?: $this->systemSettingsService->infoEmail(),
                'feedback_email' => $settings->feedback_email ?: $this->systemSettingsService->feedbackEmail(),
                'tax_id' => $settings->tax_id,
                'logo_path' => $settings->logo_path,
                'default_currency' => $settings->default_currency ?: $this->systemSettingsService->defaultCurrency(),
                'timezone' => $settings->timezone,
                'locale' => $settings->locale,
                'default_commission_percent' => $settings->default_commission_percent,
                'channel_email_enabled' => (bool) $settings->channel_email_enabled,
                'channel_sms_enabled' => (bool) $settings->channel_sms_enabled,
                'channel_whatsapp_enabled' => (bool) $settings->channel_whatsapp_enabled,
                'channel_push_enabled' => (bool) $settings->channel_push_enabled,
                'email_from_name' => $settings->email_from_name,
                'mail_mailer' => $settings->mail_mailer ?? '',
                'mail_host' => $settings->mail_host ?? '',
                'mail_port' => $settings->mail_port ?? '',
                'mail_encryption' => $settings->mail_encryption ?? '',
                'mail_username' => $settings->mail_username ?? '',
                'mail_password_set' => $this->systemSettingsService->mailPasswordIsSet(),
                'sms_sender_name' => $settings->sms_sender_name,
                'sms_endpoint' => $settings->sms_endpoint ?: $this->systemSettingsService->smsEndpoint(),
                'sms_token_set' => $this->systemSettingsService->smsToken() !== null,
                'whatsapp_sender_name' => $settings->whatsapp_sender_name,
                'whatsapp_endpoint' => $settings->whatsapp_endpoint ?: $this->systemSettingsService->whatsappEndpoint(),
                'whatsapp_token_set' => $this->systemSettingsService->whatsappToken() !== null,
                'feature_maintenance_mode' => (bool) $settings->feature_maintenance_mode,
                'feature_chat_enabled' => (bool) $settings->feature_chat_enabled,
                'feature_legacy_order_create' => (bool) $settings->feature_legacy_order_create,
                'settings_version' => (int) ($settings->settings_version ?? 1),
                'updated_at' => $settings->updated_at?->toDateTimeString(),
            ],
            'channel_statuses' => $this->integrationStatuses(),
            'can_manage_sensitive_flags' => $request->user()?->hasRole('super_admin') ?? false,
            'update_url' => route('admin.settings.update', absolute: false),
            'test_mail_url' => route('admin.settings.test-mail', absolute: false),
            'notifications_url' => route('admin.notifications.index', absolute: false),
        ]);
    }

    public function update(UpdateSystemSettingsRequest $request): RedirectResponse
    {
        $this->adminService->update($request->user(), $request->validated());

        return redirect()
            ->route('admin.settings.index')
            ->with('success', 'Settings saved successfully.');
    }

    public function testMail(Request $request): RedirectResponse
    {
        $rawRecipient = $request->input('recipient');
        $normalizedRecipient = is_string($rawRecipient) ? trim($rawRecipient) : '';
        $request->merge([
            'recipient' => $normalizedRecipient === '' ? null : $normalizedRecipient,
        ]);

        $request->validate([
            'recipient' => ['nullable', 'email:rfc', 'max:255'],
        ]);

        if (! $this->systemSettingsService->isMailDeliveryConfigured()) {
            return redirect()
                ->route('admin.settings.index')
                ->with('error', 'Configure SMTP or choose the log mailer before sending a test email.');
        }

        $recipient = (string) ($request->input('recipient') ?: $request->user()?->email ?: '');
        $recipient = trim($recipient);

        if ($recipient === '' || ! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return redirect()
                ->route('admin.settings.index')
                ->with('error', 'Enter a valid recipient email (your admin account email is not usable for SMTP testing).');
        }

        try {
            Mail::raw(
                'This is a test message from '.config('app.name').' (admin settings).',
                static function ($message) use ($recipient): void {
                    $message->to($recipient)->subject('Booke SMTP test');
                },
            );
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('admin.settings.index')
                ->with('error', 'Test email failed: '.$exception->getMessage());
        }

        return redirect()
            ->route('admin.settings.index')
            ->with('success', 'Test email sent to '.$recipient.'.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function integrationStatuses(): array
    {
        $statuses = $this->channelManager->statuses();

        return array_map(function (array $status): array {
            $configured = (bool) ($status['configured'] ?? false);
            $mode = $configured
                ? 'configured'
                : (app()->environment('production') ? 'missing' : 'simulated');

            return [
                ...$status,
                'mode' => $mode,
                'enabled' => $this->systemSettingsService->isChannelEnabled((string) $status['channel']),
            ];
        }, $statuses);
    }
}
