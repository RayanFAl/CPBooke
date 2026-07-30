<?php

namespace App\Modules\Api\Orders\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Modules\Api\Orders\Http\Requests\CreateOrderRequest;
use App\Modules\Api\Orders\Http\Requests\SyncBooknowOrderRequest;
use App\Modules\Api\Orders\Http\Requests\SyncBundleOrderRequest;
use App\Modules\Api\Orders\Http\Requests\SyncEsimOrderRequest;
use App\Modules\Api\Orders\Http\Requests\SyncHotelOrderRequest;
use App\Modules\Api\Orders\Http\Requests\SyncInsuranceOrderRequest;
use App\Modules\Api\Orders\Services\BooknowOrderSyncService;
use App\Modules\Api\Orders\Services\OrderService;
use App\Modules\Api\Resources\BooknowOrderResource;
use App\Modules\Api\Resources\OrderResource;
use App\Modules\Api\Support\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
        private readonly BooknowOrderSyncService $booknowOrderSyncService,
    ) {
    }

    /**
     * Legacy manual order creation (non BookNow sync).
     */
    public function store(Request $request): JsonResponse
    {
        $legacyRequest = CreateOrderRequest::createFrom($request);
        $legacyRequest->setContainer(app());
        $legacyRequest->setRedirector(app('redirect'));
        $legacyRequest->validateResolved();

        return $this->storeLegacyOrder($legacyRequest);
    }

    /**
     * Sync a flight booking from BookNow into CPBooke (idempotent by booking_id).
     */
    public function syncFlight(Request $request): JsonResponse
    {
        return $this->syncBooknowOrder($request);
    }

    /**
     * Sync an eSIM purchase into CPBooke (idempotent by provider_booking.booking_id).
     */
    public function syncEsim(Request $request): JsonResponse
    {
        return $this->syncTypedBooknowOrder($request, SyncEsimOrderRequest::class);
    }

    /**
     * Sync an insurance purchase into CPBooke (idempotent by provider_booking.booking_id).
     */
    public function syncInsurance(Request $request): JsonResponse
    {
        return $this->syncTypedBooknowOrder($request, SyncInsuranceOrderRequest::class);
    }

    /**
     * Sync a hotel booking into CPBooke (idempotent by provider_booking.booking_id).
     */
    public function syncHotel(Request $request): JsonResponse
    {
        return $this->syncTypedBooknowOrder($request, SyncHotelOrderRequest::class);
    }

    /**
     * Sync a unified flight + add-ons (eSIM / insurance) order (idempotent by flight booking_id).
     */
    public function syncBundle(Request $request): JsonResponse
    {
        return $this->syncTypedBooknowOrder($request, SyncBundleOrderRequest::class);
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Order::class);

        $productType = $request->query('product_type');
        $productType = is_string($productType) && $productType !== '' ? $productType : null;

        $orders = $this->orderService->paginateForCustomer($request->user(), productType: $productType);

        $resolved = collect($orders->items())->map(function (Order $order) use ($request): array {
            if ($order->external_booking_id) {
                return BooknowOrderResource::make($order)->resolve($request);
            }

            return OrderResource::make($order)->resolve($request);
        })->all();

        return ApiResponse::success(
            [
                'orders' => $resolved,
            ],
            'Orders fetched successfully.',
            $this->orderService->paginationMeta($orders),
        );
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        $this->authorize('view', $order);

        $order = $this->orderService->get($order);

        if ($order->external_booking_id) {
            return ApiResponse::success(
                BooknowOrderResource::make($order)->resolve($request),
                'Order fetched successfully.',
            );
        }

        return ApiResponse::success(
            ['order' => OrderResource::make($order)->resolve($request)],
            'Order fetched successfully.',
        );
    }

    private function storeLegacyOrder(CreateOrderRequest $request): JsonResponse
    {
        $this->authorize('create', Order::class);

        $order = $this->orderService->createForCustomer($request->user(), $request->toDto());

        return ApiResponse::success(
            ['order' => OrderResource::make($order)->resolve($request)],
            'Order created successfully.',
            [],
            201,
        );
    }

    private function syncBooknowOrder(Request $request): JsonResponse
    {
        return $this->syncTypedBooknowOrder($request, SyncBooknowOrderRequest::class);
    }

    /**
     * @param  class-string<SyncBooknowOrderRequest|SyncEsimOrderRequest|SyncInsuranceOrderRequest|SyncHotelOrderRequest|SyncBundleOrderRequest>  $requestClass
     */
    private function syncTypedBooknowOrder(Request $request, string $requestClass): JsonResponse
    {
        /** @var SyncBooknowOrderRequest|SyncEsimOrderRequest|SyncInsuranceOrderRequest|SyncHotelOrderRequest|SyncBundleOrderRequest $syncRequest */
        $syncRequest = $requestClass::createFrom($request);
        $syncRequest->setContainer(app());
        $syncRequest->setRedirector(app('redirect'));
        $syncRequest->validateResolved();

        $this->authorize('create', Order::class);

        $result = $this->booknowOrderSyncService->upsert(
            $syncRequest->user(),
            $syncRequest->toDto(),
        );

        return ApiResponse::success(
            BooknowOrderResource::make($result['order'])->resolve($request),
            'Order saved successfully.',
            [
                'created' => $result['created'],
                'idempotent' => ! $result['created'],
            ],
            $result['created'] ? 201 : 200,
        );
    }
}
