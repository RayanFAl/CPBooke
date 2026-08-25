<?php

namespace App\Modules\Admin\Airports\Queries;

use App\Models\Airport;
use App\Support\Airports\AirportSearchScope;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AirportIndexQuery
{
    public const PER_PAGE_OPTIONS = [20, 50, 100];

    public const DEFAULT_PER_PAGE = 20;

    public function usesFullSchema(): bool
    {
        return Schema::hasTable('booknow_airports');
    }

    /**
     * @return array{
     *     airports: LengthAwarePaginator,
     *     filters: array<string, mixed>,
     *     country_options: array<int, array{value: string, label: string}>,
     *     type_options: array<int, string>,
     *     per_page_options: array<int, int>,
     *     usesFullSchema: bool
     * }
     */
    public function paginate(Request $request): array
    {
        $fullSchema = $this->usesFullSchema();
        $search = $this->nullableString($request->input('search'));
        $country = $this->nullableString($request->input('country'));
        $type = $this->nullableString($request->input('type'));
        $perPage = $this->resolvePerPage($request);

        $airports = $fullSchema
            ? $this->paginateFullSchema($search, $country, $type, $perPage)
            : $this->paginateLegacy($search, $country, $perPage);

        return [
            'airports' => $airports,
            'filters' => [
                'search' => $search,
                'country' => $country,
                'type' => $type,
                'per_page' => $perPage,
            ],
            'country_options' => $this->countryOptions($fullSchema),
            'type_options' => $fullSchema ? $this->typeOptions() : [],
            'per_page_options' => self::PER_PAGE_OPTIONS,
            'usesFullSchema' => $fullSchema,
        ];
    }

    public function resolvePerPage(Request $request): int
    {
        $perPage = $request->integer('per_page', self::DEFAULT_PER_PAGE);

        return in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : self::DEFAULT_PER_PAGE;
    }

    /**
     * @return LengthAwarePaginator<int, object>
     */
    private function paginateFullSchema(
        ?string $search,
        ?string $country,
        ?string $type,
        int $perPage,
    ): LengthAwarePaginator {
        $codeExpr = $this->airportKeyExpression();

        $query = DB::table('booknow_airports')
            ->select([
                DB::raw("$codeExpr as id"),
                DB::raw("$codeExpr as airport_key"),
                'iata_code',
                'icao_code',
                'name_en',
                'city_en',
                'country_name_en',
                'country_iso2',
                'type',
            ]);

        if ($search) {
            $query->where(function ($q) use ($search): void {
                AirportSearchScope::apply($q, $search);
                $q->orWhere('translation_status', 'like', "%{$search}%");
            });
        }

        if ($country) {
            $query->where('country_iso2', $country);
        }

        if ($type) {
            $query->where('type', $type);
        }

        $query->orderBy('booknow_airports.name_en')->orderBy('booknow_airports.id');

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * @return LengthAwarePaginator<int, Airport>
     */
    private function paginateLegacy(
        ?string $search,
        ?string $country,
        int $perPage,
    ): LengthAwarePaginator {
        $query = Airport::query();

        if ($search) {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")
                    ->orWhere('country', 'like', "%{$search}%");
            });
        }

        if ($country) {
            $query->where('country', $country);
        }

        $query->orderBy('name')->orderBy('id');

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function countryOptions(bool $fullSchema): array
    {
        if ($fullSchema) {
            return DB::table('booknow_airports')
                ->selectRaw('country_iso2, MIN(country_name_en) as country_name_en')
                ->whereNotNull('country_iso2')
                ->where('country_iso2', '!=', '')
                ->groupBy('country_iso2')
                ->orderBy('country_name_en')
                ->get()
                ->map(function (object $row): array {
                    $iso = (string) $row->country_iso2;
                    $name = trim((string) ($row->country_name_en ?? ''));

                    return [
                        'value' => $iso,
                        'label' => $name !== '' ? "{$name} ({$iso})" : $iso,
                    ];
                })
                ->all();
        }

        return Airport::query()
            ->whereNotNull('country')
            ->where('country', '!=', '')
            ->distinct()
            ->orderBy('country')
            ->pluck('country')
            ->map(fn (string $country): array => [
                'value' => $country,
                'label' => $country,
            ])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function typeOptions(): array
    {
        return DB::table('booknow_airports')
            ->whereNotNull('type')
            ->where('type', '!=', '')
            ->distinct()
            ->orderBy('type')
            ->pluck('type')
            ->values()
            ->all();
    }

    private function airportKeyExpression(): string
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return "CASE WHEN NULLIF(iata_code, '') IS NOT NULL THEN 'IATA:' || iata_code WHEN NULLIF(icao_code, '') IS NOT NULL THEN 'ICAO:' || icao_code ELSE NULL END";
        }

        return "CASE WHEN NULLIF(iata_code, '') IS NOT NULL THEN CONCAT('IATA:', iata_code) WHEN NULLIF(icao_code, '') IS NOT NULL THEN CONCAT('ICAO:', icao_code) ELSE NULL END";
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
