<?php

namespace App\Http\Middleware;

use App\Modules\Admin\Governance\Events\AccessDenied;
use App\Modules\Admin\Governance\Events\AccessGranted;
use App\Modules\Admin\Governance\Services\GovernanceEventDispatcher;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            app(GovernanceEventDispatcher::class)->dispatch(new AccessDenied(
                actorId: null,
                actorType: 'guest',
                guard: 'http.middleware.role',
                permissions: [],
                roles: array_values($roles),
                reason: 'authentication_required',
                routeName: $this->routeName($request),
                method: $request->method(),
                path: $request->path(),
                ipAddress: $request->ip(),
                occurredAt: now()->toIso8601String(),
            ));

            abort(Response::HTTP_FORBIDDEN, 'You do not have the required role for this area.');
        }

        if (! $user->hasRole($roles)) {
            app(GovernanceEventDispatcher::class)->dispatch(new AccessDenied(
                actorId: $user->id,
                actorType: 'user',
                guard: 'http.middleware.role',
                permissions: [],
                roles: array_values($roles),
                reason: 'role_denied',
                routeName: $this->routeName($request),
                method: $request->method(),
                path: $request->path(),
                ipAddress: $request->ip(),
                occurredAt: now()->toIso8601String(),
            ));

            abort(Response::HTTP_FORBIDDEN, 'You do not have the required role for this area.');
        }

        app(GovernanceEventDispatcher::class)->dispatch(new AccessGranted(
            actorId: $user->id,
            actorType: 'user',
            guard: 'http.middleware.role',
            permissions: [],
            roles: array_values($roles),
            routeName: $this->routeName($request),
            method: $request->method(),
            path: $request->path(),
            ipAddress: $request->ip(),
            occurredAt: now()->toIso8601String(),
        ));

        return $next($request);
    }

    private function routeName(Request $request): ?string
    {
        $routeName = $request->route()?->getName();

        return is_string($routeName) ? $routeName : null;
    }
}