<?php

namespace App\Modules\Admin\Loyalty\Services;

use App\Models\AuditLog;
use App\Models\LoyaltyBenefit;
use App\Models\LoyaltyCompanyRate;
use App\Models\LoyaltyTier;
use App\Models\Order;
use App\Modules\Audit\Services\AuditRecorder;
use App\Modules\Loyalty\Services\LoyaltyAirlinesCatalogService;
use App\Support\Rbac\RbacAuditLogger;
use App\Support\Rbac\RbacAuthorizer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LoyaltyCompanyRateAdminService
{
    public function __construct(
        private readonly LoyaltyAirlinesCatalogService $airlinesCatalog,
        private readonly RbacAuthorizer $rbacAuthorizer,
        private readonly RbacAuditLogger $rbacAuditLogger,
        private readonly AuditRecorder $auditRecorder,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function matrix(bool $refreshAirlines = false): array
    {
        $actor = $this->rbacAuthorizer->authorize('loyalty.view', allowSystem: true);
        $this->rbacAuditLogger->log('loyalty.company_rates.viewed', 'loyalty.view', $actor, 'loyalty_company_rates');

        if (! Schema::hasTable('loyalty_company_rates')) {
            return [
                'tiers' => [],
                'companies' => [],
                'rates' => [],
                'airlines' => [
                    'count' => 0,
                    'source' => null,
                    'error' => 'Loyalty company rates table is not ready.',
                ],
            ];
        }

        $tiers = LoyaltyTier::query()
            ->where('is_active', true)
            ->where('level', '>', 0)
            ->with(['benefits' => fn ($query) => $query
                ->where('benefit_type', LoyaltyBenefit::TYPE_DISCOUNT)
                ->where('value_type', LoyaltyBenefit::VALUE_TYPE_PERCENTAGE)
                ->where('is_active', true)
                ->orderByDesc('is_highlighted')
                ->orderBy('display_order')])
            ->orderBy('level')
            ->get();

        if ($refreshAirlines) {
            $this->airlinesCatalog->forgetCache();
        }

        $airlinesResult = $this->airlinesCatalog->fetch();
        $this->ensureCatalogRows($tiers, $airlinesResult['airlines']);

        $rates = LoyaltyCompanyRate::query()
            ->whereIn('tier_id', $tiers->pluck('id'))
            ->orderBy('sort_order')
            ->orderBy('company_name')
            ->get();

        $companies = $this->buildCompanies($airlinesResult['airlines'], $rates);

        return [
            'tiers' => $tiers->map(fn (LoyaltyTier $tier): array => [
                'id' => $tier->id,
                'level' => $tier->level,
                'code' => $tier->code,
                'name' => $tier->name,
                'default_discount_percentage' => $this->defaultDiscountForTier($tier),
            ])->values()->all(),
            'companies' => $companies,
            'rates' => $rates->map(fn (LoyaltyCompanyRate $rate): array => [
                'id' => $rate->id,
                'tier_id' => $rate->tier_id,
                'service_type' => $rate->service_type,
                'company_key' => $rate->company_key,
                'company_name' => $rate->company_name,
                'discount_percentage' => $rate->discount_percentage !== null
                    ? number_format((float) $rate->discount_percentage, 2, '.', '')
                    : '',
                'is_active' => (bool) $rate->is_active,
            ])->values()->all(),
            'airlines' => [
                'count' => count($airlinesResult['airlines']),
                'source' => $airlinesResult['source'],
                'error' => $airlinesResult['error'],
            ],
        ];
    }

    /**
     * @param  array<int, array{tier_id: int, service_type: string, company_key: string, company_name?: string|null, discount_percentage?: float|int|string|null, is_active?: bool}>  $rows
     * @return array<string, mixed>
     */
    public function syncRates(array $rows): array
    {
        $actor = $this->rbacAuthorizer->authorize('loyalty.manage-benefits', allowSystem: true);

        if (! Schema::hasTable('loyalty_company_rates')) {
            return $this->matrix();
        }

        $beforeCount = LoyaltyCompanyRate::query()->count();

        DB::transaction(function () use ($rows): void {
            foreach ($rows as $row) {
                $serviceType = strtolower(trim((string) ($row['service_type'] ?? '')));
                $companyKey = $serviceType === Order::SERVICE_TYPE_FLIGHT
                    ? LoyaltyCompanyRate::normalizeCompanyKey((string) ($row['company_key'] ?? ''))
                    : strtolower(trim((string) ($row['company_key'] ?? '')));

                if ($companyKey === null || $companyKey === '' || ! in_array($serviceType, Order::serviceTypes(), true)) {
                    continue;
                }

                $tierId = (int) ($row['tier_id'] ?? 0);

                if ($tierId <= 0) {
                    continue;
                }

                $percentage = $row['discount_percentage'] ?? null;
                $normalizedPercentage = $percentage === null || $percentage === ''
                    ? null
                    : max(0, min(100, round((float) $percentage, 2)));

                LoyaltyCompanyRate::query()->updateOrCreate(
                    [
                        'tier_id' => $tierId,
                        'service_type' => $serviceType,
                        'company_key' => $companyKey,
                    ],
                    [
                        'company_name' => isset($row['company_name']) && is_string($row['company_name'])
                            ? trim($row['company_name'])
                            : null,
                        'discount_percentage' => $normalizedPercentage,
                        'is_active' => array_key_exists('is_active', $row)
                            ? (bool) $row['is_active']
                            : true,
                        'sort_order' => $this->sortOrderFor($serviceType, $companyKey),
                    ],
                );
            }
        });

        $after = $this->matrix();

        $this->auditRecorder->success(
            AuditLog::MODULE_LOYALTY,
            'loyalty.company_rates.synced',
            'Loyalty company discount rates updated',
            'loyalty_company_rates',
            null,
            $actor,
            ['count' => $beforeCount],
            ['count' => count($after['rates'] ?? [])],
            ['rows_submitted' => count($rows)],
        );

        $this->rbacAuditLogger->log('loyalty.company_rates.synced', 'loyalty.manage-benefits', $actor, 'loyalty_company_rates');

        return $after;
    }

    /**
     * @param  Collection<int, LoyaltyTier>  $tiers
     * @param  array<int, array{code: string, name: string}>  $airlines
     */
    private function ensureCatalogRows($tiers, array $airlines): void
    {
        if ($tiers->isEmpty()) {
            return;
        }

        $now = now();
        $payload = [];

        foreach ($tiers as $tier) {
            $default = $this->defaultDiscountForTier($tier);

            foreach (LoyaltyCompanyRate::catalogCompanies() as $company) {
                $payload[] = [
                    'tier_id' => $tier->id,
                    'service_type' => $company['service_type'],
                    'company_key' => $company['company_key'],
                    'company_name' => $company['company_name'],
                    'discount_percentage' => $default,
                    'is_active' => true,
                    'sort_order' => $company['sort_order'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach ($airlines as $index => $airline) {
                $payload[] = [
                    'tier_id' => $tier->id,
                    'service_type' => Order::SERVICE_TYPE_FLIGHT,
                    'company_key' => $airline['code'],
                    'company_name' => $airline['name'],
                    'discount_percentage' => $default,
                    'is_active' => true,
                    'sort_order' => $index,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if ($payload === []) {
            return;
        }

        // Insert missing rows only — never overwrite admin-edited percentages.
        LoyaltyCompanyRate::query()->insertOrIgnore($payload);

        // Keep airline display names fresh when the API returns updated labels.
        foreach ($airlines as $airline) {
            LoyaltyCompanyRate::query()
                ->where('service_type', Order::SERVICE_TYPE_FLIGHT)
                ->where('company_key', $airline['code'])
                ->where(function ($query) use ($airline): void {
                    $query->whereNull('company_name')
                        ->orWhere('company_name', '')
                        ->orWhere('company_name', $airline['code']);
                })
                ->update(['company_name' => $airline['name']]);
        }
    }

    /**
     * @param  array<int, array{code: string, name: string}>  $airlines
     * @param  Collection<int, LoyaltyCompanyRate>  $rates
     * @return array<int, array{service_type: string, company_key: string, company_name: string, sort_order: int}>
     */
    private function buildCompanies(array $airlines, $rates): array
    {
        $companies = [];

        foreach ($airlines as $index => $airline) {
            $companies[$airline['code']] = [
                'service_type' => Order::SERVICE_TYPE_FLIGHT,
                'company_key' => $airline['code'],
                'company_name' => $airline['name'],
                'logo_url' => $airline['logo_url'] ?? null,
                'sort_order' => $index,
            ];
        }

        // Include flight rates that may exist locally even if API is down.
        foreach ($rates->where('service_type', Order::SERVICE_TYPE_FLIGHT) as $rate) {
            if (! isset($companies[$rate->company_key])) {
                $companies[$rate->company_key] = [
                    'service_type' => Order::SERVICE_TYPE_FLIGHT,
                    'company_key' => $rate->company_key,
                    'company_name' => $rate->company_name ?: $rate->company_key,
                    'logo_url' => null,
                    'sort_order' => (int) $rate->sort_order,
                ];
            } elseif (empty($companies[$rate->company_key]['logo_url'])) {
                $companies[$rate->company_key]['logo_url'] = null;
            }
        }

        foreach (LoyaltyCompanyRate::catalogCompanies() as $company) {
            $companies[$company['company_key']] = array_merge($company, [
                'logo_url' => null,
            ]);
        }

        return collect($companies)
            ->sortBy([
                fn (array $company) => $company['service_type'] === Order::SERVICE_TYPE_FLIGHT ? 0 : 1,
                fn (array $company) => $company['sort_order'],
                fn (array $company) => $company['company_name'],
            ])
            ->values()
            ->all();
    }

    private function defaultDiscountForTier(LoyaltyTier $tier): ?string
    {
        $benefit = $tier->benefits->first();

        if ($benefit === null || $benefit->value === null) {
            return null;
        }

        return number_format((float) $benefit->value, 2, '.', '');
    }

    private function sortOrderFor(string $serviceType, string $companyKey): int
    {
        foreach (LoyaltyCompanyRate::catalogCompanies() as $company) {
            if ($company['company_key'] === $companyKey) {
                return $company['sort_order'];
            }
        }

        return $serviceType === Order::SERVICE_TYPE_FLIGHT ? 0 : 999;
    }
}
