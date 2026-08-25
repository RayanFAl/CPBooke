<?php

namespace App\Modules\Content\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Content\Services\ContentPageAdminService;
use App\Modules\Api\Content\Services\ContentPageService;
use App\Modules\Content\Support\ContentPageCatalog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PublicContentPageController extends Controller
{
    private const SCREEN_PRIVACY = 'privacy';

    private const SCREEN_TERMS = 'terms';

    public function __construct(
        private readonly ContentPageService $contentPageService,
        private readonly ContentPageAdminService $contentPageAdminService,
    ) {}

    public function index(Request $request): View
    {
        return $this->renderScreen($request, self::SCREEN_PRIVACY);
    }

    public function show(Request $request, string $slug): View
    {
        $this->contentPageAdminService->ensureWorkspacePages();

        $locale = $this->contentPageService->resolveLocale($request);
        $page = $this->contentPageService->findBySlug($slug, $locale);

        if ($page === null) {
            throw new NotFoundHttpException;
        }

        $screen = $slug === ContentPageCatalog::SLUG_TERMS_OF_SERVICE
            ? self::SCREEN_TERMS
            : self::SCREEN_PRIVACY;

        return $this->renderScreen($request, $screen);
    }

    public function showForProduct(Request $request, string $product): View
    {
        if (! in_array($product, ContentPageCatalog::products(), true)) {
            throw new NotFoundHttpException;
        }

        $this->contentPageAdminService->ensureWorkspacePages();

        $locale = $this->contentPageService->resolveLocale($request);
        $page = $this->contentPageService->findByProduct($product, $locale);

        if ($page === null) {
            throw new NotFoundHttpException;
        }

        return $this->renderScreen($request, self::SCREEN_PRIVACY);
    }

    private function renderScreen(Request $request, string $screen): View
    {
        $this->contentPageAdminService->ensureWorkspacePages();

        $locale = $this->contentPageService->resolveLocale($request);
        $workspace = $this->contentPageService->workspace($locale);
        $sections = [];

        foreach (ContentPageCatalog::workspaceDefinitions() as $definition) {
            $isTerms = $definition['slug'] === ContentPageCatalog::SLUG_TERMS_OF_SERVICE;

            if ($screen === self::SCREEN_TERMS && ! $isTerms) {
                continue;
            }

            if ($screen === self::SCREEN_PRIVACY && $isTerms) {
                continue;
            }

            $page = $definition['product'] !== null
                ? ($workspace['products'][$definition['product']] ?? null)
                : ($workspace['legal'][$definition['slug']] ?? null);

            if (! is_array($page)) {
                continue;
            }

            $sections[] = $page;
        }

        if ($sections === []) {
            throw new NotFoundHttpException;
        }

        return view('content.page', [
            'locale' => $locale,
            'dir' => $locale === 'ar' ? 'rtl' : 'ltr',
            'sections' => $sections,
        ]);
    }
}
