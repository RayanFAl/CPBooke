<?php

return [
    'slow_request_ms' => (int) env('MONITORING_SLOW_REQUEST_MS', 1500),

    'queue' => [
        'pending_warn' => (int) env('MONITORING_QUEUE_PENDING_WARN', 50),
        'pending_critical' => (int) env('MONITORING_QUEUE_PENDING_CRITICAL', 200),
        'failed_warn' => (int) env('MONITORING_FAILED_JOBS_WARN', 5),
        'failed_critical' => (int) env('MONITORING_FAILED_JOBS_CRITICAL', 20),
    ],

    'notifications' => [
        'failure_warn_percent' => (float) env('MONITORING_NOTIF_FAIL_WARN', 5),
        'failure_critical_percent' => (float) env('MONITORING_NOTIF_FAIL_CRITICAL', 15),
    ],

    'approvals' => [
        'expire_pending_after_hours' => (int) env('MONITORING_APPROVAL_EXPIRE_HOURS', 72),
    ],

    'retention' => [
        'application_events_days' => (int) env('MONITORING_EVENTS_RETENTION_DAYS', 30),
        'health_checks_days' => (int) env('MONITORING_HEALTH_RETENTION_DAYS', 14),
        'api_events_days' => (int) env('MONITORING_API_EVENTS_RETENTION_DAYS', 30),
    ],

    'execute_approvals_async' => (bool) env('MONITORING_APPROVALS_ASYNC', false),
];
