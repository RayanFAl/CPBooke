<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserCanAccessAdmin
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->canAccessAdminPanel()) {
            abort(Response::HTTP_FORBIDDEN, 'Administrative access is required.');
        }

        return $next($request);
    }
}