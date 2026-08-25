<?php

namespace App\Modules\Admin\AI\Http\Controllers;

use App\Modules\AI\Services\AiTravelAssistantLogService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AiRequestLogController
{
    public function __construct(
        private readonly AiTravelAssistantLogService $logService,
    ) {
    }

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'source' => ['nullable', 'string', 'max:32'],
            'mode' => ['nullable', 'string', 'max:32'],
            'intent' => ['nullable', 'string', 'max:64'],
            'fallback' => ['nullable', 'string', 'max:5'],
            'success' => ['nullable', 'string', 'max:5'],
        ]);

        $payload = $this->logService->listForAdmin($filters);

        return Inertia::render('admin/ai/pages/RequestLogs', [
            'logs' => $payload['logs'],
            'filters' => $payload['filters'],
            'sources' => $payload['sources'],
            'modes' => $payload['modes'],
        ]);
    }
}
