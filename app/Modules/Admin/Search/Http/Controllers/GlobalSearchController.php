<?php

namespace App\Modules\Admin\Search\Http\Controllers;

use App\Modules\Audit\Services\GlobalSearchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class GlobalSearchController
{
    public function __construct(
        private readonly GlobalSearchService $globalSearchService,
    ) {
    }

    public function index(Request $request): Response
    {
        Gate::authorize('search.view');

        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $query = trim((string) ($validated['q'] ?? ''));

        return Inertia::render('admin/search/pages/Index', [
            'result' => $this->globalSearchService->search($query),
            'q' => $query,
        ]);
    }
}
