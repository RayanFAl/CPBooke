<?php

namespace App\Modules\Admin\Support\Http\Controllers;

use App\Models\SupportTicket;
use App\Modules\Admin\Support\Http\Requests\StoreSupportResolutionReportRequest;
use App\Modules\Admin\Support\Services\SupportResolutionReportService;
use App\Modules\Support\Services\SupportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class ResolutionReportController
{
    public function __construct(
        private readonly SupportResolutionReportService $supportResolutionReportService,
        private readonly SupportService $supportService,
    ) {
    }

    public function upsert(StoreSupportResolutionReportRequest $request, SupportTicket $supportTicket): RedirectResponse
    {
        Gate::authorize('support.view');

        if (! $this->supportResolutionReportService->isAvailable()) {
            return back()
                ->withErrors([
                    'resolution_report' => 'Support resolution reports are unavailable until the related migration is applied.',
                ])
                ->withInput();
        }

        $report = $this->supportResolutionReportService->saveAndApplyStatus(
            $supportTicket,
            $request->validated(),
            $request->user()?->id,
            $this->supportService,
        );

        return redirect()
            ->route('admin.support.show', $supportTicket)
            ->with('success', sprintf('Resolution report saved and ticket moved to %s.', $report->status_after));
    }
}