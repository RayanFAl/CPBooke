<?php

namespace App\Modules\Admin\Monitoring\Http\Controllers;

use App\Jobs\RunSystemHealthProbesJob;
use App\Modules\Monitoring\Services\MonitoringDashboardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MonitoringController
{
    public function __construct(
        private readonly MonitoringDashboardService $monitoringDashboardService,
    ) {
    }

    public function index(Request $request): Response
    {
        Gate::authorize('monitoring.view');

        return Inertia::render('admin/monitoring/pages/Index', [
            'dashboard' => $this->monitoringDashboardService->dashboard(runLiveProbes: true),
            'can_manage' => $request->user()?->can('monitoring.manage') ?? false,
        ]);
    }

    public function runProbes(Request $request): RedirectResponse
    {
        Gate::authorize('monitoring.manage');

        RunSystemHealthProbesJob::dispatch();

        return redirect()
            ->route('admin.monitoring.index')
            ->with('success', 'Health probes dispatched to the queue.');
    }
}
