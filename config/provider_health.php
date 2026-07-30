<?php

return [
    'weights' => [
        'api' => 40,
        'wallet' => 25,
        'error_rate' => 15,
        'settlement' => 10,
        'pending' => 10,
    ],

    'bands' => [
        'excellent' => 95,
        'watch' => 75,
    ],

    'alerts' => [
        'wallet_critical_balance' => (float) env('PROVIDER_HEALTH_WALLET_CRITICAL', 500),
        'api_offline_minutes' => (int) env('PROVIDER_HEALTH_API_OFFLINE_MINUTES', 10),
        'error_rate_warn_percent' => (float) env('PROVIDER_HEALTH_ERROR_RATE_WARN', 5),
        'error_rate_critical_percent' => (float) env('PROVIDER_HEALTH_ERROR_RATE_CRITICAL', 15),
        'failed_ops_critical' => (int) env('PROVIDER_HEALTH_FAILED_OPS_CRITICAL', 20),
        'latency_degraded_ms' => (int) env('PROVIDER_HEALTH_LATENCY_DEGRADED_MS', 2000),
    ],

    'windows' => [
        'hour' => 60,
        'day' => 1440,
    ],
];
