<?php

namespace App\Modules\Partners\Http\Controllers;

use App\Models\Order;
use App\Modules\Api\Support\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class PartnerOrderController
{
    public function show(Order $order): JsonResponse
    {
        return ApiResponse::success([
            'order' => [
                'id' => $order->id,
                'booking_reference' => $order->booking_reference,
                'external_booking_id' => $order->external_booking_id,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'service_type' => $order->service_type,
                'currency' => $order->currency,
                'total_amount' => $order->total_amount,
                'final_amount' => $order->final_amount,
                'customer_id' => $order->customer_id,
                'provider_id' => $order->provider_id,
                'provider_name' => $order->provider_name,
                'source' => $order->source,
                'created_at' => optional($order->created_at)?->toIso8601String(),
                'updated_at' => optional($order->updated_at)?->toIso8601String(),
            ],
        ]);
    }
}
