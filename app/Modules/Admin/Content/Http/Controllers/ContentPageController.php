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
        return Inertia::render('admin/content/pages/Index', [
            'tabs' => $this->contentPageAdminService->workspaceTabs(),
        ]);
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('admin.content.index');
    }

    public function store(StoreContentPageRequest $request): RedirectResponse
    {
        $this->contentPageAdminService->create($request->validated());

        return redirect()
            ->route('admin.content.index')
            ->with('success', 'Page created successfully.');
    }

    public function edit(ContentPage $page): RedirectResponse
    {
        $tabId = $page->category === ContentPageCatalog::CATEGORY_PRODUCT_POLICY && $page->product
            ? (string) $page->product
            : (string) $page->slug;

        return redirect()->route('admin.content.index', ['tab' => $tabId]);
    }

    public function update(UpdateContentPageRequest $request, ContentPage $page): RedirectResponse
    {
        $this->contentPageAdminService->update($page, $request->validated());

        $tabId = $page->category === ContentPageCatalog::CATEGORY_PRODUCT_POLICY && $page->product
            ? (string) $page->product
            : (string) $page->slug;

        return redirect()
            ->route('admin.content.index', ['tab' => $tabId])
            ->with('success', 'Page updated successfully.');
    }

    public function destroy(ContentPage $page): RedirectResponse
    {
        $this->contentPageAdminService->delete($page);

        return redirect()
            ->route('admin.content.index')
            ->with('success', 'Page deleted successfully.');
    }
}
