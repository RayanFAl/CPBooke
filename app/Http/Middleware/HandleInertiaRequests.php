<?php

namespace App\Http\Middleware;

use App\Modules\Settings\Services\SystemSettingsService;
use App\Modules\Content\Services\MobileAppReleaseService;
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
            'platform' => fn () => app(SystemSettingsService::class)->platformPayload(),
            'mobileApp' => fn () => [
                'download_url' => route('app.download.file'),
                'page_url' => route('app.download.page'),
                'available' => app(MobileAppReleaseService::class)->hasAvailableRelease(),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
