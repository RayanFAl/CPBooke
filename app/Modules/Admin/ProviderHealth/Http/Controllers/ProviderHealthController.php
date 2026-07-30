<?php

namespace App\Modules\Admin\ProviderHealth\Http\Controllers;

use App\Modules\ProviderHealth\Services\ProviderHealthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ProviderHealthController
{
    public function __construct(
        private readonly ProviderHealthService $providerHealthService,
    ) {
    }

    public function index(Request $request): Response
    {
        Gate::authorize('provider-health.view');

        $dashboard = $this->providerHealthService->dashboard();

        return Inertia::render('admin/provider-health/pages/Index', [
            'dashboard' => $dashboard,
            'thresholds' => [
                'wallet_critical' => config('provider_health.alerts.wallet_critical_balance'),
                'error_rate_warn' => config('provider_health.alerts.error_rate_warn_percent'),
                'failed_ops_critical' => config('provider_health.alerts.failed_ops_critical'),
                'api_offline_minutes' => config('provider_health.alerts.api_offline_minutes'),
            ],
            'weights' => config('provider_health.weights'),
        ]);
    }
}
