<?php

namespace App\Modules\Admin\Home\Http\Controllers;

use App\Models\HomeBanner;
use App\Models\HomeOffer;
use App\Modules\Admin\Home\Http\Requests\StoreHomeBannerRequest;
use App\Modules\Admin\Home\Http\Requests\StoreHomeOfferRequest;
use App\Modules\Admin\Home\Http\Requests\UpdateHomeBannerRequest;
use App\Modules\Admin\Home\Http\Requests\UpdateHomeOfferRequest;
use App\Modules\Admin\Home\Services\HomeAdminService;
use App\Support\Home\HomeContentSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeContentController
{
    public function __construct(
        private readonly HomeAdminService $homeAdminService,
    ) {
    }

    public function index(Request $request): Response
    {
        $tab = $request->string('tab')->toString() === 'offers' ? 'offers' : 'banners';

        return Inertia::render('admin/home/pages/Index', [
            'tab' => $tab,
            'banners' => HomeBanner::query()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(fn (HomeBanner $banner) => $this->homeAdminService->serializeBanner($banner))
                ->values(),
            'offers' => HomeOffer::query()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(fn (HomeOffer $offer) => $this->homeAdminService->serializeOffer($offer))
                ->values(),
            'options' => [
                'action_types' => HomeBanner::ACTION_TYPES,
                'categories' => HomeOffer::CATEGORIES,
                'platforms' => ['ios', 'android'],
            ],
        ]);
    }

    public function createBanner(): Response
    {
        return Inertia::render('admin/home/pages/BannerForm', [
            'banner' => null,
            'options' => $this->formOptions(),
        ]);
    }

    public function storeBanner(StoreHomeBannerRequest $request): RedirectResponse
    {
        $this->homeAdminService->createBanner(
            $request->validated(),
            $request->file('image'),
        );

        return redirect()
            ->route('admin.home.index', ['tab' => 'banners'])
            ->with('success', 'Banner created successfully.');
    }

    public function editBanner(HomeBanner $banner): Response
    {
        return Inertia::render('admin/home/pages/BannerForm', [
            'banner' => $this->homeAdminService->serializeBanner($banner),
            'options' => $this->formOptions(),
        ]);
    }

    public function updateBanner(UpdateHomeBannerRequest $request, HomeBanner $banner): RedirectResponse
    {
        $this->homeAdminService->updateBanner(
            $banner,
            $request->validated(),
            $request->file('image'),
        );

        return redirect()
            ->route('admin.home.index', ['tab' => 'banners'])
            ->with('success', 'Banner updated successfully.');
    }

    public function destroyBanner(HomeBanner $banner): RedirectResponse
    {
        $this->homeAdminService->deleteBanner($banner);

        return redirect()
            ->route('admin.home.index', ['tab' => 'banners'])
            ->with('success', 'Banner deleted successfully.');
    }

    public function createOffer(): Response
    {
        return Inertia::render('admin/home/pages/OfferForm', [
            'offer' => null,
            'options' => $this->formOptions(),
        ]);
    }

    public function storeOffer(StoreHomeOfferRequest $request): RedirectResponse
    {
        $this->homeAdminService->createOffer(
            $request->validated(),
            $request->file('image'),
        );

        return redirect()
            ->route('admin.home.index', ['tab' => 'offers'])
            ->with('success', 'Offer created successfully.');
    }

    public function editOffer(HomeOffer $offer): Response
    {
        return Inertia::render('admin/home/pages/OfferForm', [
            'offer' => $this->homeAdminService->serializeOffer($offer),
            'options' => $this->formOptions(),
        ]);
    }

    public function updateOffer(UpdateHomeOfferRequest $request, HomeOffer $offer): RedirectResponse
    {
        $this->homeAdminService->updateOffer(
            $offer,
            $request->validated(),
            $request->file('image'),
        );

        return redirect()
            ->route('admin.home.index', ['tab' => 'offers'])
            ->with('success', 'Offer updated successfully.');
    }

    public function destroyOffer(HomeOffer $offer): RedirectResponse
    {
        $this->homeAdminService->deleteOffer($offer);

        return redirect()
            ->route('admin.home.index', ['tab' => 'offers'])
            ->with('success', 'Offer deleted successfully.');
    }

    /**
     * @return array{action_types: list<string>, categories: list<string>, platforms: list<string>, schedule_timezone: string}
     */
    private function formOptions(): array
    {
        return [
            'action_types' => HomeBanner::ACTION_TYPES,
            'categories' => HomeOffer::CATEGORIES,
            'platforms' => ['ios', 'android'],
            'schedule_timezone' => HomeContentSchedule::timezone(),
        ];
    }
}
