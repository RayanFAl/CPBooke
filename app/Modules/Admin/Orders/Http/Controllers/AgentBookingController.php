<?php

namespace App\Modules\Admin\Orders\Http\Controllers;

use App\Models\Order;
use App\Models\User;
use App\Modules\Admin\Home\Services\AdminHotelLookupService;
use App\Modules\Admin\Orders\Http\Requests\StoreAgentBookingRequest;
use App\Modules\Admin\Orders\Services\AgentBookingService;
use App\Modules\Settings\Services\SystemSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AgentBookingController
{
    public function __construct(
        private readonly AgentBookingService $agentBookingService,
        private readonly AdminHotelLookupService $hotelLookupService,
        private readonly SystemSettingsService $systemSettingsService,
    ) {
    }

    public function create(Request $request): Response
    {
        Gate::authorize('orders.agent-book');

        $preselectedCustomerId = $request->integer('customer_id') ?: null;
        $preselectedCustomer = null;

        if ($preselectedCustomerId) {
            $customer = User::query()->find($preselectedCustomerId);

            if ($customer?->isCustomerAccount()) {
                $preselectedCustomer = $this->customerPayload($customer);
            }
        }

        $platform = $this->systemSettingsService->platformPayload();

        return Inertia::render('admin/orders/pages/AgentBooking', [
            'preselected_customer' => $preselectedCustomer,
            'customers' => $this->listCustomers(),
            'service_types' => collect(Order::serviceTypes())
                ->map(fn (string $type): array => [
                    'name' => $type,
                    'label' => ucfirst($type),
                ])
                ->values()
                ->all(),
            'default_currency' => strtoupper((string) ($platform['default_currency'] ?? Order::DEFAULT_CURRENCY)),
            'hotel_lookup_configured' => $this->hotelLookupService->isConfigured(),
        ]);
    }

    public function store(StoreAgentBookingRequest $request): RedirectResponse
    {
        $customer = User::query()->findOrFail($request->integer('customer_id'));

        $validated = $request->validated();
        $order = $this->agentBookingService->bookForCustomer(
            $customer,
            $request->user(),
            $validated,
        );

        $message = ($validated['service_type'] ?? null) === Order::SERVICE_TYPE_HOTEL
            ? 'Hotel booked on the live provider API and synced into Booke.'
            : 'Assisted booking created successfully.';

        return redirect()
            ->route('admin.orders.show', $order)
            ->with('success', $message);
    }

    public function customers(Request $request): JsonResponse
    {
        Gate::authorize('orders.agent-book');

        $search = trim((string) $request->query('q', ''));

        return response()->json([
            'success' => true,
            'data' => [
                'customers' => $this->listCustomers($search !== '' ? $search : null),
            ],
        ]);
    }

    public function hotelStatus(): JsonResponse
    {
        Gate::authorize('orders.agent-book');

        return response()->json([
            'success' => true,
            'data' => [
                'configured' => $this->hotelLookupService->isConfigured(),
            ],
        ]);
    }

    public function hotelCities(Request $request): JsonResponse
    {
        Gate::authorize('orders.agent-book');

        $query = trim((string) $request->query('q', ''));

        if (mb_strlen($query) < 2) {
            return response()->json([
                'success' => true,
                'data' => ['cities' => []],
            ]);
        }

        $result = $this->hotelLookupService->autocomplete($query);

        return response()->json([
            'success' => (bool) ($result['ok'] ?? false),
            'message' => $result['error'] ?? 'Cities fetched.',
            'data' => [
                'cities' => $result['cities'] ?? [],
            ],
        ], ($result['ok'] ?? false) ? 200 : 422);
    }

    public function hotelSearch(Request $request): JsonResponse
    {
        Gate::authorize('orders.agent-book');

        $validated = $request->validate([
            'city' => ['required', 'string', 'max:160'],
            'city_id' => ['required', 'string', 'max:80'],
            'check_in' => ['required', 'date'],
            'check_out' => ['required', 'date', 'after:check_in'],
            'adults' => ['nullable', 'integer', 'min:1', 'max:8'],
        ]);

        $result = $this->hotelLookupService->search($validated);

        return response()->json([
            'success' => (bool) ($result['ok'] ?? false),
            'message' => $result['error'] ?? 'Hotels fetched.',
            'data' => [
                'search_uuid' => $result['search_uuid'] ?? null,
                'hotels' => $result['hotels'] ?? [],
            ],
        ], ($result['ok'] ?? false) ? 200 : 422);
    }

    public function hotelOffers(Request $request): JsonResponse
    {
        Gate::authorize('orders.agent-book');

        $validated = $request->validate([
            'hotel_id' => ['required', 'string', 'max:80'],
            'city_id' => ['required', 'string', 'max:80'],
            'city' => ['nullable', 'string', 'max:160'],
            'source' => ['nullable', 'string', 'max:80'],
            'check_in' => ['nullable', 'date'],
            'check_out' => ['nullable', 'date', 'after:check_in'],
        ]);

        $result = $this->hotelLookupService->offers(
            $validated['hotel_id'],
            $validated['city_id'],
            $validated['source'] ?? null,
            isset($validated['check_in']) ? (string) $validated['check_in'] : null,
            isset($validated['check_out']) ? (string) $validated['check_out'] : null,
            $validated['city'] ?? null,
        );

        return response()->json([
            'success' => (bool) ($result['ok'] ?? false),
            'message' => $result['error'] ?? 'Offers fetched.',
            'data' => [
                'offers' => $result['offers'] ?? [],
            ],
        ], ($result['ok'] ?? false) ? 200 : 422);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listCustomers(?string $search = null, int $limit = 40): array
    {
        $query = User::query()
            ->where('account_type', User::ACCOUNT_TYPE_CUSTOMER);

        $search = $search !== null ? trim($search) : '';

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('email', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%')
                    ->orWhere('full_name', 'like', '%'.$search.'%')
                    ->orWhere('name', 'like', '%'.$search.'%');
            });
        }

        return $query
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (User $user): array => $this->customerPayload($user))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function customerPayload(User $customer): array
    {
        return [
            'id' => $customer->id,
            'name' => $customer->full_name ?: $customer->name,
            'email' => $customer->email,
            'phone' => $customer->phone,
        ];
    }
}
