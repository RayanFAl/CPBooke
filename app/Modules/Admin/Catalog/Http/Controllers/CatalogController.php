<?php

namespace App\Modules\Admin\Catalog\Http\Controllers;

use App\Models\MobileCatalogType;
use App\Modules\Admin\Catalog\Http\Requests\StoreCatalogTypeRequest;
use App\Modules\Admin\Catalog\Http\Requests\UpdateCatalogTypeRequest;
use App\Modules\Admin\Catalog\Services\CatalogAdminService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CatalogController
{
    public function __construct(
        private readonly CatalogAdminService $catalogAdminService,
    ) {
    }

    public function index(): Response
    {
        return Inertia::render('admin/catalog/pages/Index', [
            'types' => MobileCatalogType::query()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(fn (MobileCatalogType $type) => $this->catalogAdminService->serialize($type))
                ->values(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/catalog/pages/Form', [
            'type' => null,
            'options' => $this->formOptions(),
        ]);
    }

    public function store(StoreCatalogTypeRequest $request): RedirectResponse
    {
        $this->catalogAdminService->create(
            $request->validated(),
            $request->file('options_image'),
            $request->file('market_image'),
        );

        return redirect()
            ->route('admin.catalog.index')
            ->with('success', 'Catalog type created successfully.');
    }

    public function edit(MobileCatalogType $catalog): Response
    {
        return Inertia::render('admin/catalog/pages/Form', [
            'type' => $this->catalogAdminService->serialize($catalog),
            'options' => $this->formOptions(),
        ]);
    }

    public function update(UpdateCatalogTypeRequest $request, MobileCatalogType $catalog): RedirectResponse
    {
        $this->catalogAdminService->update(
            $catalog,
            $request->validated(),
            $request->file('options_image'),
            $request->file('market_image'),
        );

        return redirect()
            ->route('admin.catalog.index')
            ->with('success', 'Catalog type updated successfully.');
    }

    public function destroy(MobileCatalogType $catalog): RedirectResponse
    {
        $this->catalogAdminService->delete($catalog);

        return redirect()
            ->route('admin.catalog.index')
            ->with('success', 'Catalog type deleted successfully.');
    }

    /**
     * @return array{action_types: list<string>, platforms: list<string>}
     */
    private function formOptions(): array
    {
        return [
            'action_types' => MobileCatalogType::ACTION_TYPES,
            'platforms' => ['ios', 'android'],
        ];
    }
}
