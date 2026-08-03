<?php

namespace App\Modules\Admin\Settings\Http\Controllers;

use App\Modules\Admin\Settings\Http\Requests\UpdateSystemSettingsRequest;
use App\Modules\Admin\Settings\Services\SystemSettingsAdminService;
use App\Modules\Notifications\Services\NotificationChannelManager;
use App\Modules\Settings\Services\SystemSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

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
                'support_email' => $settings->support_email,
                'support_phone' => $settings->support_phone,
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
                'sms_sender_name' => $settings->sms_sender_name,
                'whatsapp_sender_name' => $settings->whatsapp_sender_name,
                'feature_maintenance_mode' => (bool) $settings->feature_maintenance_mode,
                'feature_chat_enabled' => (bool) $settings->feature_chat_enabled,
                'feature_legacy_order_create' => (bool) $settings->feature_legacy_order_create,
                'settings_version' => (int) ($settings->settings_version ?? 1),
                'updated_at' => $settings->updated_at?->toDateTimeString(),
            ],
            'channel_statuses' => $this->integrationStatuses(),
            'can_manage_sensitive_flags' => $request->user()?->hasRole('super_admin') ?? false,
            'update_url' => route('admin.settings.update', absolute: false),
        ]);
    }

    public function update(UpdateSystemSettingsRequest $request): RedirectResponse
    {
        $this->adminService->update($request->user(), $request->validated());

        return redirect()
            ->route('admin.settings.index')
            ->with('success', 'Settings saved successfully.');
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
