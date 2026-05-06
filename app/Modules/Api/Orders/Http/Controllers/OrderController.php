<?php

namespace App\Modules\Api\Orders\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Modules\Api\Orders\Http\Requests\CreateOrderRequest;
use App\Modules\Api\Orders\Services\OrderService;
use App\Modules\Api\Resources\OrderResource;
use App\Modules\Api\Support\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
    ) {
    }

    /**
     * Store a newly created booking order.
     */
    public function store(CreateOrderRequest $request): JsonResponse
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

    /**
     * List the authenticated customer's orders.
     */
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

    /**
     * Display the specified customer order.
     */
    public function show(Request $request, Order $order): JsonResponse
    {
        $this->authorize('view', $order);

        $order = $this->orderService->get($order);

        return ApiResponse::success(
            ['order' => OrderResource::make($order)->resolve($request)],
            'Order fetched successfully.',
        );
    }
}