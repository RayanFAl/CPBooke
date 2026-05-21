<?php

namespace App\Modules\Admin\Governance\Http\Controllers;

use App\Modules\Admin\Governance\Services\GovernanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class GovernanceController
{
    public function __invoke(Request $request, GovernanceService $governanceService): Response
    {
        Gate::authorize('governance.view');

        $filters = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'module' => ['nullable', 'in:rbac,finance,notifications,loyalty'],
        ]);

        return Inertia::render('admin/governance/pages/Index', [
            'dashboard' => $governanceService->snapshot($filters)->toArray(),
            'filters' => [
                'date_from' => $filters['date_from'] ?? now()->subDay()->toDateString(),
                'date_to' => $filters['date_to'] ?? now()->toDateString(),
                'module' => $filters['module'] ?? 'rbac',
            ],
            'filter_options' => [
                'modules' => [
                    ['name' => 'rbac', 'label' => 'RBAC Activity'],
                    ['name' => 'finance', 'label' => 'Finance Health'],
                    ['name' => 'notifications', 'label' => 'Notifications Health'],
                    ['name' => 'loyalty', 'label' => 'Loyalty Activity'],
                ],
            ],
        ]);
    }
}