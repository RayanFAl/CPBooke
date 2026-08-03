<?php

namespace App\Modules\Admin\Support\Http\Controllers;

use App\Modules\Admin\Support\Services\SupportResolutionExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupportReportsController
{
    public function __construct(
        private readonly SupportResolutionExportService $supportResolutionExportService,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('support.view');

        $filters = $this->validatedFilters($request);
        $dashboard = $this->supportResolutionExportService->dashboard($filters);

        return Inertia::render('admin/support/pages/Reports', [
            'dashboard' => $dashboard,
            'filters' => $filters,
            'exports' => [
                'csv' => route('admin.support.reports.export.csv', $filters, absolute: false),
            ],
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        Gate::authorize('support.view');

        return $this->supportResolutionExportService->exportCsv($this->validatedFilters($request));
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedFilters(Request $request): array
    {
        $validated = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'status_after' => ['nullable', 'string', Rule::in(['resolved', 'closed'])],
            'resolution_type' => ['nullable', 'string', Rule::in([
                'resolved',
                'partially_resolved',
                'escalated',
                'duplicate',
                'invalid',
                'customer_cancelled',
            ])],
            'agent_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        return [
            'date_from' => $validated['date_from'] ?? null,
            'date_to' => $validated['date_to'] ?? null,
            'status_after' => $validated['status_after'] ?? null,
            'resolution_type' => $validated['resolution_type'] ?? null,
            'agent_id' => isset($validated['agent_id']) ? (int) $validated['agent_id'] : null,
        ];
    }
}
