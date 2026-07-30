<?php

namespace App\Jobs;

use App\Models\ApplicationEvent;
use App\Models\AuditLog;
use App\Models\ProviderApiEvent;
use App\Models\SystemHealthCheck;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Schema;

class CleanupMonitoringDataJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $eventsDays = (int) config('monitoring.retention.application_events_days', 30);
        $healthDays = (int) config('monitoring.retention.health_checks_days', 14);
        $apiDays = (int) config('monitoring.retention.api_events_days', 30);
        $auditDays = (int) config('audit.retention_days', 180);

        ApplicationEvent::query()
            ->where('created_at', '<', now()->subDays($eventsDays))
            ->delete();

        SystemHealthCheck::query()
            ->where('created_at', '<', now()->subDays($healthDays))
            ->delete();

        ProviderApiEvent::query()
            ->where('created_at', '<', now()->subDays($apiDays))
            ->delete();

        if (Schema::hasTable('audit_logs')) {
            AuditLog::query()
                ->where('created_at', '<', now()->subDays($auditDays))
                ->delete();
        }
    }
}
