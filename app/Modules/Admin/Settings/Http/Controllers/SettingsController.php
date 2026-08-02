<?php

namespace App\Modules\Admin\Settings\Http\Controllers;

use App\Modules\Admin\Settings\Http\Requests\UpdateSystemSettingsRequest;
use App\Modules\Admin\Settings\Services\SystemSettingsAdminService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController
{
    public function __construct(
        private readonly SystemSettingsAdminService $settingsAdminService,
    ) {}

    public function index(Request $request): Response
    {
        $settings = $this->settingsAdminService->getSettings();

        return Inertia::render('admin/settings/pages/Index', [
            'settings' => $settings->toAdminPayload(),
            'channelStatus' => [
                'email' => filled(config('mail.from.address')),
                'sms' => filled(config('services.notifications.sms_endpoint'))
                    && filled(config('services.notifications.sms_token')),
                'whatsapp' => filled(config('services.notifications.whatsapp_endpoint'))
                    && filled(config('services.notifications.whatsapp_token')),
                'push' => filled(config('services.notifications.firebase_credentials'))
                    || filled(config('services.notifications.fcm_server_key')),
            ],
            'currencyOptions' => ['LYD', 'USD', 'EUR', 'GBP', 'AED', 'SAR', 'TND', 'EGP'],
        ]);
    }

    public function update(UpdateSystemSettingsRequest $request): RedirectResponse
    {
        $this->settingsAdminService->update($request->user(), $request->validated());

        return redirect()
            ->route('admin.settings.index')
            ->with('success', 'System settings updated successfully.');
    }
}
