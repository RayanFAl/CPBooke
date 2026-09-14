<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RejectInactiveOrDeletedApiUsers
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && ($user->trashed() || ! $user->is_active)) {
            throw new AuthenticationException('Unauthenticated.');
        }

        return $next($request);
    }
}
