<?php

namespace App\Modules\Admin\Dashboard\Services;

use App\Models\AiTravelAssistantLog;
use App\Models\AppDownloadEvent;
use App\Models\TravelSearchIntent;
use App\Models\User;
use App\Models\UserNotificationDevice;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\PersonalAccessToken;
use Throwable;

class AppPulseService
{
    /**
     * @param  array<string, mixed>|null  $release
     */
    public function recordPageView(Request $request, ?array $release = null): void
    {
        $this->record($request, AppDownloadEvent::TYPE_PAGE_VIEW, $release);
    }

    /**
     * @param  array<string, mixed>|null  $release
     */
    public function recordApkDownload(Request $request, ?array $release = null): void
    {
        $this->record($request, AppDownloadEvent::TYPE_APK_DOWNLOAD, $release);
    }

    /**
     * @param  Collection<int, array{date: string, label: string}>  $labels
     * @return array<string, mixed>
     */
    public function dashboardPayload(Carbon $rangeStart, Collection $labels): array
    {
        $emptyTrend = $labels->map(fn (array $point): array => [
            'label' => $point['label'],
            'value' => 0,
        ])->all();

        $apkDownloads = 0;
        $apkDownloadsThisWeek = 0;
        $uniqueDownloaders = 0;
        $uniqueDownloadersThisWeek = 0;
        $pageViews = 0;
        $pageViewsThisWeek = 0;
        $uniquePageVisitorsThisWeek = 0;
        $activeDevices = 0;
        $newDevicesThisWeek = 0;
        $searchers = 0;
        $searchersThisWeek = 0;
        $searchRoutes = 0;
        $searchesThisWeek = 0;
        $viewedPriceThisWeek = 0;
        $__BookNowD__FromSearchThisWeek = 0;
        $aiSearchesThisWeek = 0;
        $activeSessionsThisWeek = 0;
        $newCustomersThisWeek = 0;

        $downloadsTrend = $emptyTrend;
        $searchesTrend = $emptyTrend;
        $signupsTrend = $emptyTrend;
        $devicesTrend = $emptyTrend;
        $platformMix = [];
        $topRoutes = [];

        if (Schema::hasTable('app_download_events')) {
            $apkDownloads = AppDownloadEvent::query()
                ->where('event_type', AppDownloadEvent::TYPE_APK_DOWNLOAD)
                ->count();
            $apkDownloadsThisWeek = AppDownloadEvent::query()
                ->where('event_type', AppDownloadEvent::TYPE_APK_DOWNLOAD)
                ->where('created_at', '>=', $rangeStart)
                ->count();
            $uniqueDownloaders = (int) AppDownloadEvent::query()
                ->where('event_type', AppDownloadEvent::TYPE_APK_DOWNLOAD)
                ->selectRaw('COUNT(DISTINCT visitor_hash) as aggregate')
                ->value('aggregate');
            $uniqueDownloadersThisWeek = (int) AppDownloadEvent::query()
                ->where('event_type', AppDownloadEvent::TYPE_APK_DOWNLOAD)
                ->where('created_at', '>=', $rangeStart)
                ->selectRaw('COUNT(DISTINCT visitor_hash) as aggregate')
                ->value('aggregate');
            $pageViews = AppDownloadEvent::query()
                ->where('event_type', AppDownloadEvent::TYPE_PAGE_VIEW)
                ->count();
            $pageViewsThisWeek = AppDownloadEvent::query()
                ->where('event_type', AppDownloadEvent::TYPE_PAGE_VIEW)
                ->where('created_at', '>=', $rangeStart)
                ->count();
            $uniquePageVisitorsThisWeek = (int) AppDownloadEvent::query()
                ->where('event_type', AppDownloadEvent::TYPE_PAGE_VIEW)
                ->where('created_at', '>=', $rangeStart)
                ->selectRaw('COUNT(DISTINCT visitor_hash) as aggregate')
                ->value('aggregate');

            $dailyDownloads = AppDownloadEvent::query()
                ->selectRaw('DATE(created_at) as day, COUNT(*) as aggregate')
                ->where('event_type', AppDownloadEvent::TYPE_APK_DOWNLOAD)
                ->where('created_at', '>=', $rangeStart)
                ->groupBy('day')
                ->pluck('aggregate', 'day');

            $downloadsTrend = $labels->map(fn (array $point): array => [
                'label' => $point['label'],
                'value' => (int) ($dailyDownloads[$point['date']] ?? 0),
            ])->all();
        }

        if (Schema::hasTable('user_notification_devices')) {
            $activeDevices = UserNotificationDevice::query()
                ->where('is_active', true)
                ->count();
            $newDevicesThisWeek = UserNotificationDevice::query()
                ->where('created_at', '>=', $rangeStart)
                ->count();

            $platformMix = UserNotificationDevice::query()
                ->select('platform', DB::raw('COUNT(*) as aggregate'))
                ->where('is_active', true)
                ->groupBy('platform')
                ->orderByDesc('aggregate')
                ->get()
                ->map(fn (object $row): array => [
                    'label' => $this->platformLabel((string) $row->platform),
                    'value' => (int) $row->aggregate,
                ])
                ->all();

            $dailyDevices = UserNotificationDevice::query()
                ->selectRaw('DATE(created_at) as day, COUNT(*) as aggregate')
                ->where('created_at', '>=', $rangeStart)
                ->groupBy('day')
                ->pluck('aggregate', 'day');

            $devicesTrend = $labels->map(fn (array $point): array => [
                'label' => $point['label'],
                'value' => (int) ($dailyDevices[$point['date']] ?? 0),
            ])->all();
        }

        if (Schema::hasTable('travel_search_intents')) {
            $searchers = (int) TravelSearchIntent::query()
                ->selectRaw('COUNT(DISTINCT user_id) as aggregate')
                ->value('aggregate');
            $searchersThisWeek = (int) TravelSearchIntent::query()
                ->where('last_searched_at', '>=', $rangeStart)
                ->selectRaw('COUNT(DISTINCT user_id) as aggregate')
                ->value('aggregate');
            $searchRoutes = TravelSearchIntent::query()->count();
            $searchesThisWeek = TravelSearchIntent::query()
                ->where('last_searched_at', '>=', $rangeStart)
                ->count();

            $viewedPriceThisWeek = (int) TravelSearchIntent::query()
                ->where('last_searched_at', '>=', $rangeStart)
                ->where(function ($query): void {
                    $query->whereNotNull('last_seen_price');

                    if (Schema::hasColumn('travel_search_intents', 'results_viewed_at')) {
                        $query->orWhereNotNull('results_viewed_at');
                    }
                })
                ->selectRaw('COUNT(DISTINCT user_id) as aggregate')
                ->value('aggregate');

            $__BookNowD__FromSearchThisWeek = (int) TravelSearchIntent::query()
                ->where('converted_at', '>=', $rangeStart)
                ->selectRaw('COUNT(DISTINCT user_id) as aggregate')
                ->value('aggregate');

            $dailySearches = TravelSearchIntent::query()
                ->selectRaw('DATE(last_searched_at) as day, COUNT(*) as aggregate')
                ->where('last_searched_at', '>=', $rangeStart)
                ->groupBy('day')
                ->pluck('aggregate', 'day');

            $searchesTrend = $labels->map(fn (array $point): array => [
                'label' => $point['label'],
                'value' => (int) ($dailySearches[$point['date']] ?? 0),
            ])->all();

            $topRoutes = TravelSearchIntent::query()
                ->select('origin', 'destination', DB::raw('COUNT(*) as aggregate'))
                ->groupBy('origin', 'destination')
                ->orderByDesc('aggregate')
                ->limit(5)
                ->get()
                ->map(fn (object $row): array => [
                    'label' => strtoupper((string) $row->origin).' → '.strtoupper((string) $row->destination),
                    'value' => (int) $row->aggregate,
                ])
                ->all();
        }

        if (Schema::hasTable('ai_travel_assistant_logs')) {
            $aiSearchesThisWeek = AiTravelAssistantLog::query()
                ->where('created_at', '>=', $rangeStart)
                ->count();
        }

        if (Schema::hasTable('personal_access_tokens')) {
            $activeSessionsThisWeek = PersonalAccessToken::query()
                ->where(function ($query): void {
                    $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->where(function ($query) use ($rangeStart): void {
                    $query->where('last_used_at', '>=', $rangeStart)
                        ->orWhere(function ($inner) use ($rangeStart): void {
                            $inner->whereNull('last_used_at')
                                ->where('created_at', '>=', $rangeStart);
                        });
                })
                ->count();
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'account_type')) {
            $newCustomersThisWeek = User::query()
                ->where('account_type', User::ACCOUNT_TYPE_CUSTOMER)
                ->where('created_at', '>=', $rangeStart)
                ->count();

            $dailySignups = User::query()
                ->selectRaw('DATE(created_at) as day, COUNT(*) as aggregate')
                ->where('account_type', User::ACCOUNT_TYPE_CUSTOMER)
                ->where('created_at', '>=', $rangeStart)
                ->groupBy('day')
                ->pluck('aggregate', 'day');

            $signupsTrend = $labels->map(fn (array $point): array => [
                'label' => $point['label'],
                'value' => (int) ($dailySignups[$point['date']] ?? 0),
            ])->all();
        }

        return [
            'cards' => [
                [
                    'key' => 'apk_downloads',
                    'label' => 'APK downloads',
                    'value' => $apkDownloads,
                    'delta' => $apkDownloadsThisWeek,
                    'delta_label' => 'this week',
                    'helper' => 'From the public BookNow download page, not the Play Store.',
                    'accent' => 'violet',
                ],
                [
                    'key' => 'downloaders',
                    'label' => 'People who downloaded',
                    'value' => $uniqueDownloaders,
                    'delta' => $uniqueDownloadersThisWeek,
                    'delta_label' => 'unique this week',
                    'helper' => 'Unique visitors who actually downloaded the APK.',
                    'accent' => 'sky',
                ],
                [
                    'key' => 'installed_devices',
                    'label' => 'App installs we can see',
                    'value' => $activeDevices,
                    'delta' => $newDevicesThisWeek,
                    'delta_label' => 'new devices this week',
                    'helper' => 'Phones that opened the app and registered for notifications.',
                    'accent' => 'emerald',
                ],
                [
                    'key' => 'searchers',
                    'label' => 'People who searched',
                    'value' => $searchers,
                    'delta' => $searchersThisWeek,
                    'delta_label' => 'searched this week',
                    'helper' => 'Customers who searched flights inside the app.',
                    'accent' => 'amber',
                ],
            ],
            'spotlights' => [
                [
                    'label' => 'Download page views',
                    'value' => $pageViewsThisWeek,
                    'helper' => 'unique visitors this week',
                    'helper_value' => $uniquePageVisitorsThisWeek,
                ],
                [
                    'label' => 'Flight searches',
                    'value' => $searchesThisWeek,
                    'helper' => 'unique routes recorded in total',
                    'helper_value' => $searchRoutes,
                ],
                [
                    'label' => 'AI travel searches',
                    'value' => $aiSearchesThisWeek,
                    'helper' => 'Assistant queries in the last 7 days.',
                    'helper_value' => null,
                ],
                [
                    'label' => 'Active app sessions',
                    'value' => $activeSessionsThisWeek,
                    'helper' => 'new customers this week',
                    'helper_value' => $newCustomersThisWeek,
                ],
            ],
            'charts' => [
                'downloads_trend' => $downloadsTrend,
                'searches_trend' => $searchesTrend,
                'signups_trend' => $signupsTrend,
                'devices_trend' => $devicesTrend,
                'platform_mix' => $platformMix,
                'top_routes' => $topRoutes,
            ],
            'totals' => [
                'page_views' => $pageViews,
                'page_views_this_week' => $pageViewsThisWeek,
                'apk_downloads' => $apkDownloads,
                'apk_downloads_this_week' => $apkDownloadsThisWeek,
            ],
            'conversion' => [
                'searched' => $searchersThisWeek,
                'viewed_price' => $viewedPriceThisWeek,
                '__BookNowD__' => $__BookNowD__FromSearchThisWeek,
                'search_to_price_rate' => $searchersThisWeek > 0
                    ? round(($viewedPriceThisWeek / $searchersThisWeek) * 100, 1)
                    : 0,
                'search_to_book_rate' => $searchersThisWeek > 0
                    ? round(($__BookNowD__FromSearchThisWeek / $searchersThisWeek) * 100, 1)
                    : 0,
                'price_to_book_rate' => $viewedPriceThisWeek > 0
                    ? round(($__BookNowD__FromSearchThisWeek / $viewedPriceThisWeek) * 100, 1)
                    : 0,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $release
     */
    private function record(Request $request, string $eventType, ?array $release): void
    {
        if (! Schema::hasTable('app_download_events') || $this->isAutomatedClient($request)) {
            return;
        }

        try {
            AppDownloadEvent::query()->create([
                'event_type' => $eventType,
                'visitor_hash' => $this->visitorHash($request),
                'platform' => $this->detectPlatform($request),
                'version' => is_array($release) ? (string) ($release['version'] ?? '') : null,
                'apk_filename' => is_array($release) ? (string) ($release['apk_filename'] ?? '') : null,
                'locale' => strtolower(substr((string) $request->query('locale', $request->getPreferredLanguage(['ar', 'en']) ?: 'en'), 0, 8)),
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function visitorHash(Request $request): string
    {
        return hash('sha256', (string) $request->ip().'|'.(string) config('app.key'));
    }

    private function detectPlatform(Request $request): string
    {
        $agent = strtolower((string) $request->userAgent());

        if (str_contains($agent, 'android')) {
            return 'android';
        }

        if (str_contains($agent, 'iphone') || str_contains($agent, 'ipad') || str_contains($agent, 'ios')) {
            return 'ios';
        }

        return 'web';
    }

    private function isAutomatedClient(Request $request): bool
    {
        $agent = strtolower((string) $request->userAgent());

        if ($agent === '') {
            return false;
        }

        return str_contains($agent, 'bot')
            || str_contains($agent, 'crawl')
            || str_contains($agent, 'spider')
            || str_contains($agent, 'slurp');
    }

    private function platformLabel(string $platform): string
    {
        $normalized = strtolower(trim($platform));

        return match ($normalized) {
            'android' => 'Android',
            'ios', 'iphone', 'ipad' => 'iOS',
            'web' => 'Web',
            '' => 'Unknown',
            default => ucfirst($normalized),
        };
    }
}
