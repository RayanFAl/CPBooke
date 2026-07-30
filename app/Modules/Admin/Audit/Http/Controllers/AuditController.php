<?php

namespace App\Modules\Admin\Audit\Http\Controllers;

use App\Modules\Audit\Services\AuditCenterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AuditController
{
    public function __construct(
        private readonly AuditCenterService $auditCenterService,
    ) {
    }

    public function index(Request $request): Response
    {
        Gate::authorize('audit.view');

        $filters = $request->validate([
            'module' => ['nullable', 'string', 'max:60'],
            'action' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string', 'max:20'],
            'entity_type' => ['nullable', 'string', 'max:80'],
            'actor_id' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $payload = $this->auditCenterService->list($filters);

        return Inertia::render('admin/audit/pages/Index', [
            'logs' => $payload['logs'],
            'filters' => $payload['filters'],
            'modules' => $payload['modules'],
            'statuses' => $payload['statuses'],
            'entity_types' => $payload['entity_types'],
        ]);
    }
}
