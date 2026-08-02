<?php

namespace App\Http\Middleware;

use App\Modules\Admin\Settings\Services\SystemSettingsAdminService;
use App\Support\Platform\PlatformSettings;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $platform = null;

        if ($user?->isAdminAccount()) {
            try {
                $settings = app(SystemSettingsAdminService::class)->getSettings();
                $platform = [
                    'default_currency' => PlatformSettings::defaultCurrency(),
                    'company_display_name' => $settings->company_display_name ?: config('app.name'),
                    'timezone' => $settings->timezone ?: config('app.timezone'),
                    'maintenance_mode' => PlatformSettings::maintenanceMode(),
                    'support_chat_enabled' => PlatformSettings::supportChatEnabled(),
                ];
            } catch (\Throwable) {
                $platform = [
                    'default_currency' => PlatformSettings::defaultCurrency(),
                    'company_display_name' => config('app.name'),
                    'timezone' => config('app.timezone'),
                    'maintenance_mode' => false,
                    'support_chat_enabled' => true,
                ];
            }
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'full_name' => $user->full_name,
                    'email' => $user->email,
                    'account_type' => $user->account_type,
                    'is_active' => (bool) $user->is_active,
                    'is_admin' => (bool) $user->is_admin,
                    'primary_role' => $user->primaryRole()?->name,
                    'roles' => $user->roleNames(),
                    'permissions' => $user->permissionNames(),
                ] : null,
            ],
            'platform' => $platform,
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
