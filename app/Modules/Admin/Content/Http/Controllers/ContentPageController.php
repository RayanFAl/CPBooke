<?php

namespace App\Modules\Admin\Content\Http\Controllers;

use App\Models\ContentPage;
use App\Modules\Admin\Content\Http\Requests\StoreContentPageRequest;
use App\Modules\Admin\Content\Http\Requests\UpdateContentPageRequest;
use App\Modules\Admin\Content\Services\ContentPageAdminService;
use App\Modules\Content\Support\ContentPageCatalog;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ContentPageController
{
    public function __construct(
        private readonly ContentPageAdminService $contentPageAdminService,
    ) {}

    public function index(): Response
    {
        $pages = ContentPage::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (ContentPage $page) => $this->contentPageAdminService->serialize($page))
            ->values();

        return Inertia::render('admin/content/pages/Index', [
            'pages' => $pages,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/content/pages/Form', [
            'page' => null,
            'options' => $this->formOptions(),
        ]);
    }

    public function store(StoreContentPageRequest $request): RedirectResponse
    {
        $this->contentPageAdminService->create($request->validated());

        return redirect()
            ->route('admin.content.index')
            ->with('success', 'Page created successfully.');
    }

    public function edit(ContentPage $page): Response
    {
        return Inertia::render('admin/content/pages/Form', [
            'page' => $this->contentPageAdminService->serialize($page),
            'options' => $this->formOptions(),
        ]);
    }

    public function update(UpdateContentPageRequest $request, ContentPage $page): RedirectResponse
    {
        $this->contentPageAdminService->update($page, $request->validated());

        return redirect()
            ->route('admin.content.index')
            ->with('success', 'Page updated successfully.');
    }

    public function destroy(ContentPage $page): RedirectResponse
    {
        $this->contentPageAdminService->delete($page);

        return redirect()
            ->route('admin.content.index')
            ->with('success', 'Page deleted successfully.');
    }

    /**
     * @return array{categories: list<array<string, string>>, products: list<array<string, string>>}
     */
    private function formOptions(): array
    {
        return [
            'categories' => ContentPageCatalog::categoryOptions(),
            'products' => ContentPageCatalog::productOptions(),
        ];
    }
}
