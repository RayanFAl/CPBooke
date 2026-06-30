<?php

namespace App\Modules\Api\Orders\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Modules\Api\Orders\Http\Requests\CreateOrderRequest;
use App\Modules\Api\Orders\Http\Requests\SyncBooknowOrderRequest;
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

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Order::class);

        $orders = $this->orderService->paginateForCustomer($request->user());

        return ApiResponse::success(
            [
                'orders' => OrderResource::collection($orders->items())->resolve($request),
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
        $syncRequest = SyncBooknowOrderRequest::createFrom($request);
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
