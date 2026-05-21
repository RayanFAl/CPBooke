<?php

namespace App\Http\Middleware;

use App\Modules\Admin\Governance\Events\AccessDenied;
use App\Modules\Admin\Governance\Events\AccessGranted;
use App\Modules\Admin\Governance\Services\GovernanceEventDispatcher;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasPermission
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (! $user) {
            app(GovernanceEventDispatcher::class)->dispatch(new AccessDenied(
                actorId: null,
                actorType: 'guest',
                guard: 'http.middleware.permission',
                permissions: array_values($permissions),
                roles: [],
                reason: 'authentication_required',
                routeName: $this->routeName($request),
                method: $request->method(),
                path: $request->path(),
                ipAddress: $request->ip(),
                occurredAt: now()->toIso8601String(),
            ));

            abort(Response::HTTP_FORBIDDEN, 'Authentication is required.');
        }

        foreach ($permissions as $permission) {
            if (Gate::forUser($user)->allows($permission)) {
                app(GovernanceEventDispatcher::class)->dispatch(new AccessGranted(
                    actorId: $user->id,
                    actorType: 'user',
                    guard: 'http.middleware.permission',
                    permissions: [$permission],
                    roles: [],
                    routeName: $this->routeName($request),
                    method: $request->method(),
                    path: $request->path(),
                    ipAddress: $request->ip(),
                    occurredAt: now()->toIso8601String(),
                ));

                return $next($request);
            }
        }

        app(GovernanceEventDispatcher::class)->dispatch(new AccessDenied(
            actorId: $user->id,
            actorType: 'user',
            guard: 'http.middleware.permission',
            permissions: array_values($permissions),
            roles: [],
            reason: 'permission_denied',
            routeName: $this->routeName($request),
            method: $request->method(),
            path: $request->path(),
            ipAddress: $request->ip(),
            occurredAt: now()->toIso8601String(),
        ));

        abort(Response::HTTP_FORBIDDEN, 'You do not have the required permission for this action.');
    }

    private function routeName(Request $request): ?string
    {
        $routeName = $request->route()?->getName();

        return is_string($routeName) ? $routeName : null;
    }
}