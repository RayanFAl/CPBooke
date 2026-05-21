<?php

namespace App\Support\Rbac;

use App\Models\User;
use App\Modules\Admin\Governance\Events\AccessDenied;
use App\Modules\Admin\Governance\Events\AccessGranted;
use App\Modules\Admin\Governance\Services\GovernanceEventDispatcher;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RbacAuthorizer
{
    public function __construct(
        private readonly GovernanceEventDispatcher $governanceEventDispatcher,
    ) {
    }

    /**
     * Authorize a permission in the current execution context.
     *
     * When allowSystem is true, queue/observer/system contexts without an authenticated
     * actor are allowed to proceed. Authenticated actors must still pass the gate.
     */
    public function authorize(string $permission, ?User $actor = null, bool $allowSystem = false): ?User
    {
        $actor ??= auth()->user();

        if (! $actor instanceof User) {
            if ($allowSystem) {
                $this->dispatchGranted(
                    actorId: null,
                    actorType: 'system',
                    permissions: [$permission],
                    roles: [],
                );

                return null;
            }

            $this->dispatchDenied(
                actorId: null,
                actorType: 'guest',
                permissions: [$permission],
                roles: [],
                reason: 'authenticated_actor_required',
            );

            throw new AuthorizationException('This action requires an authenticated actor.');
        }

        try {
            Gate::forUser($actor)->authorize($permission);
        } catch (AuthorizationException $exception) {
            $this->dispatchDenied(
                actorId: $actor->id,
                actorType: 'user',
                permissions: [$permission],
                roles: [],
                reason: 'permission_denied',
            );

            throw $exception;
        }

        $this->dispatchGranted(
            actorId: $actor->id,
            actorType: 'user',
            permissions: [$permission],
            roles: [],
        );

        return $actor;
    }

    /**
     * @param  array<int, string>  $permissions
     * @param  array<int, string>  $roles
     */
    private function dispatchGranted(?int $actorId, string $actorType, array $permissions, array $roles): void
    {
        $request = request();

        $this->governanceEventDispatcher->dispatch(new AccessGranted(
            actorId: $actorId,
            actorType: $actorType,
            guard: 'rbac.authorizer',
            permissions: $permissions,
            roles: $roles,
            routeName: $this->routeName($request),
            method: $request?->method(),
            path: $request?->path(),
            ipAddress: $request?->ip(),
            occurredAt: now()->toIso8601String(),
        ));
    }

    /**
     * @param  array<int, string>  $permissions
     * @param  array<int, string>  $roles
     */
    private function dispatchDenied(?int $actorId, string $actorType, array $permissions, array $roles, string $reason): void
    {
        $request = request();

        $this->governanceEventDispatcher->dispatch(new AccessDenied(
            actorId: $actorId,
            actorType: $actorType,
            guard: 'rbac.authorizer',
            permissions: $permissions,
            roles: $roles,
            reason: $reason,
            routeName: $this->routeName($request),
            method: $request?->method(),
            path: $request?->path(),
            ipAddress: $request?->ip(),
            occurredAt: now()->toIso8601String(),
        ));
    }

    private function routeName(?Request $request): ?string
    {
        $routeName = $request?->route()?->getName();

        return is_string($routeName) ? $routeName : null;
    }
}