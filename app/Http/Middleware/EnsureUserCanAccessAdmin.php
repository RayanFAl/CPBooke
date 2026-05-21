<?php

namespace App\Http\Middleware;

use App\Modules\Admin\Governance\Events\AccessDenied;
use App\Modules\Admin\Governance\Events\AccessGranted;
use App\Modules\Admin\Governance\Services\GovernanceEventDispatcher;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserCanAccessAdmin
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            app(GovernanceEventDispatcher::class)->dispatch(new AccessDenied(
                actorId: null,
                actorType: 'guest',
                guard: 'http.middleware.admin',
                permissions: ['access-admin'],
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

        if (Gate::forUser($request->user())->denies('access-admin')) {
            app(GovernanceEventDispatcher::class)->dispatch(new AccessDenied(
                actorId: $request->user()->id,
                actorType: 'user',
                guard: 'http.middleware.admin',
                permissions: ['access-admin'],
                roles: [],
                reason: 'permission_denied',
                routeName: $this->routeName($request),
                method: $request->method(),
                path: $request->path(),
                ipAddress: $request->ip(),
                occurredAt: now()->toIso8601String(),
            ));

            abort(Response::HTTP_FORBIDDEN, 'Administrative access is required.');
        }

        app(GovernanceEventDispatcher::class)->dispatch(new AccessGranted(
            actorId: $request->user()->id,
            actorType: 'user',
            guard: 'http.middleware.admin',
            permissions: ['access-admin'],
            roles: [],
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