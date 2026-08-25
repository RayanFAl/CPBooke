<?php

namespace App\Modules\Admin\Airports\Http\Controllers;

use App\Jobs\ImportBooknowAirportsJob;
use App\Models\Airport;
use App\Models\FeaturedAirport;
use App\Modules\Admin\Airports\Http\Requests\UpdateFeaturedAirportsRequest;
use App\Modules\Admin\Airports\Queries\AirportIndexQuery;
use App\Modules\Admin\Airports\Services\FeaturedAirportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AirportController
{
    public function __construct(
        private readonly FeaturedAirportService $featuredAirportService,
        private readonly AirportIndexQuery $airportIndexQuery,
    ) {
    }

    public function index(Request $request): Response
    {
        $listing = $this->airportIndexQuery->paginate($request);

        return Inertia::render('admin/airports/pages/Index', [
            'airports' => $listing['airports'],
            'filters' => $listing['filters'],
            'country_options' => $listing['country_options'],
            'type_options' => $listing['type_options'],
            'per_page_options' => $listing['per_page_options'],
            'usesFullSchema' => $listing['usesFullSchema'],
            'importStatus' => $listing['usesFullSchema'] ? $this->importStatus() : null,
            'featuredAirports' => $listing['usesFullSchema']
                ? $this->featuredAirportService->listWithDetails()
                : collect(),
            'featuredAirportKeys' => $listing['usesFullSchema']
                ? $this->featuredAirportService->featuredKeys()->all()
                : [],
            'maxFeaturedAirports' => FeaturedAirport::MAX_COUNT,
        ]);
    }

    public function searchFeaturedCandidates(Request $request): JsonResponse
    {
        if (! Schema::hasTable('booknow_airports')) {
            return response()->json([
                'results' => [],
            ]);
        }

        $results = $this->featuredAirportService->searchCandidates(
            $request->string('q')->toString(),
            min(max($request->integer('limit', 10), 1), 20),
        );

        return response()->json([
            'results' => $results,
        ]);
    }

    public function updateFeatured(UpdateFeaturedAirportsRequest $request): RedirectResponse
    {
        if (! Schema::hasTable('booknow_airports')) {
            return redirect()
                ->route('admin.airports.index')
                ->with('error', 'Featured airports are not available for the current schema.');
        }

        $this->featuredAirportService->sync($request->input('airports', []));

        return redirect()
            ->route('admin.airports.index')
            ->with('success', 'تم تحديث المطارات المفضلة بنجاح.');
    }

    public function toggleFeatured(string $airport): RedirectResponse
    {
        if (! Schema::hasTable('booknow_airports')) {
            return back()->with('error', 'Featured airports are not available for the current schema.');
        }

        $isFeatured = $this->featuredAirportService->toggle($airport);

        return back()->with(
            'success',
            $isFeatured ? 'تمت إضافة المطار إلى أفضل المواقع.' : 'تمت إزالة المطار من أفضل المواقع.',
        );
    }

    public function import(Request $request): RedirectResponse
    {
        if (! Schema::hasTable('booknow_airports')) {
            return redirect()
                ->route('admin.airports.index')
                ->with('error', 'Airport import is not available for the current schema.');
        }

        $currentStatus = $this->importStatus()['status'] ?? null;

        if (in_array($currentStatus, ['queued', 'processing'], true)) {
            return redirect()
                ->route('admin.airports.index')
                ->with('error', 'An airport import is already running.');
        }

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:51200'],
            'fresh' => ['sometimes', 'boolean'],
        ]);

        $storedPath = $request->file('file')->store('imports/airports');

        Cache::put('booknow_airports_import_status', [
            'status' => 'queued',
            'message' => 'Import queued.',
            'queued_at' => now()->toIso8601String(),
            'user_id' => $request->user()?->id,
        ], now()->addDay());

        ImportBooknowAirportsJob::dispatch(
            Storage::path($storedPath),
            $request->boolean('fresh'),
            (int) $request->user()->id,
        )->afterResponse();

        return redirect()
            ->route('admin.airports.index')
            ->with('success', 'تم رفع الملف وبدء استيراد المطارات.');
    }

    public function create(): Response
    {
        return Inertia::render('admin/airports/pages/Create', [
            'usesFullSchema' => Schema::hasTable('booknow_airports'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (Schema::hasTable('booknow_airports')) {
            $data = $this->validateBooknowAirport($request);

            DB::table('booknow_airports')->insert($this->normalizeBooknowPayload($data));

            return redirect()->route('admin.airports.index')->with('success', 'تمت إضافة المطار بنجاح');
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:10',
            'city' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
        ]);

        Airport::create($data);

        return redirect()->route('admin.airports.index')->with('success', 'تمت إضافة المطار بنجاح');
    }

    public function edit(string $airport): Response
    {
        if (Schema::hasTable('booknow_airports')) {
            $record = $this->findBooknowAirportByKey($airport);

            if (! $record) {
                abort(404);
            }

            return Inertia::render('admin/airports/pages/Edit', [
                'airport' => $this->formatBooknowAirport($record, $airport),
                'usesFullSchema' => true,
                'isFeatured' => $this->featuredAirportService->isFeatured($airport),
                'featuredOrder' => $this->featuredAirportService->featuredOrder($airport),
                'featuredCount' => FeaturedAirport::query()->count(),
                'maxFeaturedAirports' => FeaturedAirport::MAX_COUNT,
            ]);
        }

        $legacyAirport = Airport::findOrFail($airport);

        return Inertia::render('admin/airports/pages/Edit', [
            'airport' => [
                'id' => $legacyAirport->id,
                'airport_key' => (string) $legacyAirport->id,
                'name' => $legacyAirport->name,
                'code' => $legacyAirport->code,
                'city' => $legacyAirport->city,
                'country' => $legacyAirport->country,
            ],
            'usesFullSchema' => false,
            'isFeatured' => false,
            'featuredOrder' => null,
            'featuredCount' => 0,
            'maxFeaturedAirports' => FeaturedAirport::MAX_COUNT,
        ]);
    }

    public function update(Request $request, string $airport): RedirectResponse
    {
        if (Schema::hasTable('booknow_airports')) {
            $data = $this->validateBooknowAirport($request, $airport);
            $query = $this->booknowAirportQueryFromKey($airport);

            if (! $query || $query->count() === 0) {
                abort(404);
            }

            $query->update($this->normalizeBooknowPayload($data));

            return redirect()->route('admin.airports.index')->with('success', 'تم تعديل بيانات المطار بنجاح');
        }

        $legacyAirport = Airport::findOrFail($airport);
        $legacyAirport->update($request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:10',
            'city' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
        ]));

        return redirect()->route('admin.airports.index')->with('success', 'تم تعديل بيانات المطار بنجاح');
    }

    public function destroy(string $airport): RedirectResponse
    {
        if (Schema::hasTable('booknow_airports')) {
            $query = $this->booknowAirportQueryFromKey($airport);

            if (! $query || $query->count() === 0) {
                abort(404);
            }

            $query->delete();

            $this->featuredAirportService->remove($airport);

            return redirect()->route('admin.airports.index')->with('success', 'تم حذف المطار بنجاح');
        }

        $legacyAirport = Airport::findOrFail($airport);
        $legacyAirport->delete();

        return redirect()->route('admin.airports.index')->with('success', 'تم حذف المطار بنجاح');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function importStatus(): ?array
    {
        $status = Cache::get('booknow_airports_import_status');

        return is_array($status) ? $status : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function validateBooknowAirport(Request $request, ?string $currentAirportKey = null): array
    {
        return $request->validate([
            'iata_code' => ['nullable', 'string', 'max:10'],
            'icao_code' => ['nullable', 'string', 'max:10'],
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'name_fr' => ['nullable', 'string', 'max:255'],
            'city_en' => ['nullable', 'string', 'max:255'],
            'city_ar' => ['nullable', 'string', 'max:255'],
            'city_fr' => ['nullable', 'string', 'max:255'],
            'country_iso2' => ['nullable', 'string', 'size:2'],
            'country_name_en' => ['nullable', 'string', 'max:255'],
            'country_name_ar' => ['nullable', 'string', 'max:255'],
            'country_name_fr' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:50'],
            'scheduled_service' => ['nullable', Rule::in(['yes', 'no', ''])],
            'latitude_deg' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude_deg' => ['nullable', 'numeric', 'between:-180,180'],
            'translation_status' => ['nullable', 'string', 'max:255'],
        ], [], [
            'name_en' => 'name (English)',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeBooknowPayload(array $data): array
    {
        return [
            'iata_code' => $this->nullableString($data['iata_code'] ?? null),
            'icao_code' => $this->nullableString($data['icao_code'] ?? null),
            'name_en' => $data['name_en'],
            'name_ar' => $this->nullableString($data['name_ar'] ?? null),
            'name_fr' => $this->nullableString($data['name_fr'] ?? null),
            'city_en' => $this->nullableString($data['city_en'] ?? null),
            'city_ar' => $this->nullableString($data['city_ar'] ?? null),
            'city_fr' => $this->nullableString($data['city_fr'] ?? null),
            'country_iso2' => $this->nullableString($data['country_iso2'] ?? null),
            'country_name_en' => $this->nullableString($data['country_name_en'] ?? null),
            'country_name_ar' => $this->nullableString($data['country_name_ar'] ?? null),
            'country_name_fr' => $this->nullableString($data['country_name_fr'] ?? null),
            'type' => $this->nullableString($data['type'] ?? null),
            'scheduled_service' => $this->nullableString($data['scheduled_service'] ?? null),
            'latitude_deg' => $this->nullableDecimal($data['latitude_deg'] ?? null),
            'longitude_deg' => $this->nullableDecimal($data['longitude_deg'] ?? null),
            'translation_status' => $this->nullableString($data['translation_status'] ?? null),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatBooknowAirport(object $record, string $airportKey): array
    {
        return [
            'airport_key' => $airportKey,
            'iata_code' => $record->iata_code,
            'icao_code' => $record->icao_code,
            'name_en' => $record->name_en,
            'name_ar' => $record->name_ar,
            'name_fr' => $record->name_fr,
            'city_en' => $record->city_en,
            'city_ar' => $record->city_ar,
            'city_fr' => $record->city_fr,
            'country_iso2' => $record->country_iso2,
            'country_name_en' => $record->country_name_en,
            'country_name_ar' => $record->country_name_ar,
            'country_name_fr' => $record->country_name_fr,
            'type' => $record->type,
            'scheduled_service' => $record->scheduled_service,
            'latitude_deg' => $record->latitude_deg,
            'longitude_deg' => $record->longitude_deg,
            'translation_status' => $record->translation_status,
        ];
    }

    private function findBooknowAirportByKey(string $key): ?object
    {
        return $this->booknowAirportQueryFromKey($key)?->first();
    }

    private function booknowAirportQueryFromKey(string $key)
    {
        $parts = explode(':', $key, 2);

        if (count($parts) !== 2) {
            return null;
        }

        [$type, $value] = $parts;

        if ($value === '') {
            return null;
        }

        if ($type === 'IATA') {
            return DB::table('booknow_airports')->where('iata_code', $value);
        }

        if ($type === 'ICAO') {
            return DB::table('booknow_airports')->where('icao_code', $value);
        }

        return null;
    }

    private function nullableString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function nullableDecimal(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }
}
