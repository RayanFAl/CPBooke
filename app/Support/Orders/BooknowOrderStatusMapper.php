<?php

namespace App\Support\Orders;

use App\Models\Order;

class BooknowOrderStatusMapper
{
    public static function toInternal(string $providerStatus): string
    {
        return match (strtolower(trim($providerStatus))) {
            'draft' => Order::STATUS_DRAFT,
            'pending', 'pending_payment', 'awaiting_payment' => Order::STATUS_PENDING_PAYMENT,
            'payment_failed', 'failed' => Order::STATUS_FAILED,
            'expired' => Order::STATUS_FAILED,
            'paid' => Order::STATUS_PAID,
            'processing' => Order::STATUS_PROCESSING,
            'confirmed' => Order::STATUS_CONFIRMED,
            'ticketed' => Order::STATUS_TICKETED,
            'completed' => Order::STATUS_COMPLETED,
            'voided', 'cancelled', 'canceled' => Order::STATUS_CANCELLED,
            'refunded', 'refund' => Order::STATUS_REFUNDED,
            default => Order::STATUS_PENDING_PAYMENT,
        };
    }

    public static function toProvider(string $internalStatus): string
    {
        return match ($internalStatus) {
            Order::STATUS_DRAFT => 'draft',
            Order::STATUS_PENDING_PAYMENT => 'pending',
            Order::STATUS_PAID => 'paid',
            Order::STATUS_PROCESSING => 'processing',
            Order::STATUS_CONFIRMED => 'confirmed',
            Order::STATUS_TICKETED => 'ticketed',
            Order::STATUS_COMPLETED => 'completed',
            Order::STATUS_CANCELLED => 'cancelled',
            Order::STATUS_FAILED => 'payment_failed',
            Order::STATUS_REFUNDED => 'refunded',
            default => 'pending',
        };
    }
}
