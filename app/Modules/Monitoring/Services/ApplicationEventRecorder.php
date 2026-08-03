<?php

namespace App\Modules\Monitoring\Services;

use App\Models\ApplicationEvent;
use Throwable;

class ApplicationEventRecorder
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function record(
        string $category,
        string $severity,
        string $message,
        ?string $source = null,
        array $context = [],
    ): ?ApplicationEvent {
        try {
            return ApplicationEvent::query()->create([
                'category' => $category,
                'severity' => $severity,
                'source' => $source,
                'message' => mb_substr($message, 0, 500),
                'context' => $context === [] ? null : $context,
                'created_at' => now(),
            ]);
        } catch (Throwable) {
            return null;
        }
    }

    public function exception(Throwable $exception, ?string $source = null, array $context = []): void
    {
        $this->record(
            ApplicationEvent::CATEGORY_EXCEPTION,
            ApplicationEvent::SEVERITY_CRITICAL,
            $exception->getMessage() ?: $exception::class,
            $source ?? 'app',
            array_merge([
                'exception' => $exception::class,
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ], $context),
        );
    }

    public function slowRequest(string $path, int $durationMs, string $method = 'GET'): void
    {
        $this->record(
            ApplicationEvent::CATEGORY_SLOW_REQUEST,
            ApplicationEvent::SEVERITY_WARNING,
            "Slow request {$method} {$path} ({$durationMs}ms)",
            'http',
            [
                'path' => $path,
                'method' => $method,
                'duration_ms' => $durationMs,
            ],
        );
    }
}
