<?php

namespace App\Http\Middleware;

use App\Modules\Monitoring\Services\ApplicationEventRecorder;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RecordSlowRequests
{
    public function __construct(
        private readonly ApplicationEventRecorder $recorder,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $started = microtime(true);

        /** @var Response $response */
        $response = $next($request);

        $durationMs = (int) max(0, round((microtime(true) - $started) * 1000));
        $threshold = (int) config('monitoring.slow_request_ms', 1500);

        if ($durationMs >= $threshold && ! $request->is('up')) {
            $this->recorder->slowRequest(
                '/'.ltrim($request->path(), '/'),
                $durationMs,
                $request->method(),
            );
        }

        return $response;
    }
}
