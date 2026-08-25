<?php

use App\Modules\Api\Support\Http\Responses\ApiResponse;
use App\Exceptions\InsufficientCustomerWalletBalanceException;
use App\Exceptions\InsufficientWalletBalanceException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();

        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
            \App\Http\Middleware\RecordSlowRequests::class,
        ]);

        $middleware->api(append: [
            \App\Http\Middleware\EnsureApiNotInMaintenance::class,
            \App\Http\Middleware\RecordSlowRequests::class,
        ]);

        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserCanAccessAdmin::class,
            'role' => \App\Http\Middleware\EnsureUserHasRole::class,
            'permission' => \App\Http\Middleware\EnsureUserHasPermission::class,
            'partner.key' => \App\Modules\Partners\Http\Middleware\AuthenticatePartnerApiKey::class,
        ]);

        $middleware->api(prepend: [
            \App\Http\Middleware\ForceJsonResponse::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->reportable(function (\Throwable $exception): void {
            try {
                app(\App\Modules\Monitoring\Services\ApplicationEventRecorder::class)
                    ->exception($exception);
            } catch (\Throwable) {
                // Never break reporting.
            }
        });

        $exceptions->render(function (ValidationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::validation(
                $exception->errors(),
                $exception->getMessage(),
                'validation_failed',
                $exception->status,
            );
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $tokenExpired = app(\App\Modules\Api\Auth\Services\ApiTokenService::class)
                ->bearerTokenIsExpired($request->bearerToken());

            if ($tokenExpired) {
                return ApiResponse::error('Session expired.', [], 'token_expired', 401);
            }

            return ApiResponse::error('Unauthenticated.', [], 'unauthenticated', 401);
        });

        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(
                $exception->getMessage() ?: 'Request could not be completed.',
                [],
                'http_error',
                $exception->getStatusCode(),
            );
        });

        $exceptions->render(function (InsufficientWalletBalanceException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(
                'Insufficient provider balance.',
                [
                    'requested_amount' => $exception->requestedAmount,
                    'available_balance' => $exception->availableBalance,
                ],
                'insufficient_provider_balance',
                409,
            );
        });

        $exceptions->render(function (InsufficientCustomerWalletBalanceException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(
                'Insufficient wallet balance.',
                [
                    'requested_amount' => $exception->requestedAmount,
                    'available_balance' => $exception->availableBalance,
                ],
                'insufficient_wallet_balance',
                422,
            );
        });

        $exceptions->render(function (\Throwable $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            report($exception);

            return ApiResponse::error('Server error.', [], 'server_error', 500);
        });
    })->create();
