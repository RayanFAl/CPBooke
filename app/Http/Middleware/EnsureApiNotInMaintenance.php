<?php

namespace App\Http\Middleware;

use App\Modules\Api\Support\Http\Responses\ApiResponse;
use App\Support\Platform\PlatformSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiNotInMaintenance
{
    /**
     * Block customer API traffic during platform maintenance mode.
     * Auth endpoints and admin accounts remain available.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! PlatformSettings::maintenanceMode()) {
            return $next($request);
        }

        if ($request->is('api/v1/auth/*')
            || $request->is('api/v1/admin/*')
            || $request->is('api/v1/partner/*')
            || $request->is('up')) {
            return $next($request);
        }

        $user = $request->user();

        if ($user?->isAdminAccount()) {
            return $next($request);
        }

        return ApiResponse::error(
            'The platform is temporarily under maintenance. Please try again later.',
            [],
            'maintenance_mode',
            503,
        );
    }
}
