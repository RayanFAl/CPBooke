<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'customer_id',
    'provider_name',
    'external_booking_id',
    'booking_reference',
    'status',
    'payment_status',
    'service_type',
    'details',
    'currency',
    'total_amount',
    'base_amount',
    'discount_total',
    'final_amount',
    'pricing_version',
    'pricing_snapshot_json',
    'internal_notes',
    'request_payload',
    'response_payload',
    'error_message',
])]
class Order extends Model
{
    public const DEFAULT_CURRENCY = 'LYD';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING_PAYMENT = 'pending_payment';

    public const STATUS_PAID = 'paid';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_FAILED = 'failed';

    public const STATUS_REFUNDED = 'refunded';

    public const PAYMENT_STATUS_UNPAID = 'unpaid';

    public const PAYMENT_STATUS_PAID = 'paid';

    public const PAYMENT_STATUS_PARTIALLY_REFUNDED = 'partially_refunded';

    public const PAYMENT_STATUS_REFUNDED = 'refunded';

    public const SERVICE_TYPE_FLIGHT = 'flight';

    public const SERVICE_TYPE_HOTEL = 'hotel';

    public const SERVICE_TYPE_INSURANCE = 'insurance';

    /**
     * Get the attribute casts.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'details' => 'array',
            'request_payload' => 'array',
            'response_payload' => 'array',
            'total_amount' => 'decimal:2',
            'base_amount' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'final_amount' => 'decimal:2',
            'pricing_snapshot_json' => 'array',
        ];
    }

    /**
     * Get the customer who owns the order.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /**
     * Get the tracked history entries for the order.
     */
    public function histories(): HasMany
    {
        return $this->hasMany(OrderHistory::class)
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    /**
     * Get the financial transactions linked to the order.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(FinancialTransaction::class)
            ->latest('id');
    }

    /**
     * Get the support tickets linked to the order.
     */
    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class)
            ->latest('id');
    }

    /**
     * Get the supported statuses.
     *
     * @return array<int, string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_DRAFT,
            self::STATUS_PENDING_PAYMENT,
            self::STATUS_PAID,
            self::STATUS_PROCESSING,
            self::STATUS_CONFIRMED,
            self::STATUS_COMPLETED,
            self::STATUS_CANCELLED,
            self::STATUS_FAILED,
            self::STATUS_REFUNDED,
        ];
    }

    /**
     * Get the supported payment statuses.
     *
     * @return array<int, string>
     */
    public static function paymentStatuses(): array
    {
        return [
            self::PAYMENT_STATUS_UNPAID,
            self::PAYMENT_STATUS_PAID,
            self::PAYMENT_STATUS_PARTIALLY_REFUNDED,
            self::PAYMENT_STATUS_REFUNDED,
        ];
    }

    /**
     * Get the supported order service types.
     *
     * @return array<int, string>
     */
    public static function serviceTypes(): array
    {
        return [
            self::SERVICE_TYPE_FLIGHT,
            self::SERVICE_TYPE_HOTEL,
            self::SERVICE_TYPE_INSURANCE,
        ];
    }

    /**
     * Get the allowed status transitions for the given order state.
     *
     * @return array<int, string>
     */
    public static function allowedTransitionsFor(string $status): array
    {
        return match ($status) {
            self::STATUS_DRAFT => [self::STATUS_PENDING_PAYMENT],
            self::STATUS_PENDING_PAYMENT => [self::STATUS_PAID],
            self::STATUS_PAID => [self::STATUS_PROCESSING],
            self::STATUS_PROCESSING => [self::STATUS_CONFIRMED, self::STATUS_FAILED],
            self::STATUS_CONFIRMED => [self::STATUS_COMPLETED, self::STATUS_CANCELLED, self::STATUS_REFUNDED],
            default => [],
        };
    }

    /**
     * Get the transitions available from the order's current status.
     *
     * @return array<int, string>
     */
    public function availableStatusTransitions(): array
    {
        return self::allowedTransitionsFor($this->status);
    }

    /**
     * Determine whether the order may move to the supplied status.
     */
    public function canTransitionTo(string $status): bool
    {
        return in_array($status, $this->availableStatusTransitions(), true);
    }

    /**
     * Get the statuses that can appear as admin transition targets.
     *
     * @return array<int, string>
     */
    public static function adminUpdatableStatuses(): array
    {
        return [
            self::STATUS_PENDING_PAYMENT,
            self::STATUS_PAID,
            self::STATUS_PROCESSING,
            self::STATUS_CONFIRMED,
            self::STATUS_COMPLETED,
            self::STATUS_CANCELLED,
            self::STATUS_FAILED,
            self::STATUS_REFUNDED,
        ];
    }

    /**
     * Determine whether the order may move to the supplied payment status.
     */
    public function canUpdatePaymentStatusTo(string $paymentStatus): bool
    {
        return in_array($paymentStatus, self::paymentStatuses(), true);
    }

    public function getRefundedAmount(): float
    {
        $transactions = $this->financialTransactionsForCalculations();

        $refunds = (float) $transactions
            ->where('type', FinancialTransaction::TYPE_REFUND)
            ->sum('amount');

        $reversals = (float) $transactions
            ->where('type', FinancialTransaction::TYPE_REVERSAL)
            ->sum('amount');

        return max($refunds - $reversals, 0.0);
    }

    public function getCompensationAmount(): float
    {
        return (float) $this->financialTransactionsForCalculations()
            ->where('type', FinancialTransaction::TYPE_COMPENSATION)
            ->sum('amount');
    }

    public function getNetPaidAmount(): float
    {
        $payments = (float) $this->financialTransactionsForCalculations()
            ->where('type', FinancialTransaction::TYPE_PAYMENT)
            ->sum('amount');

        return max($payments - $this->getRefundedAmount(), 0.0);
    }

    public function getRemainingCollectibleAmount(): float
    {
        return max(
            (float) $this->total_amount - $this->getNetPaidAmount() - $this->getCompensationAmount(),
            0.0,
        );
    }

    public function derivePaymentStatus(): string
    {
        $netPaidAmount = $this->getNetPaidAmount();
        $refundedAmount = $this->getRefundedAmount();
        $totalAmount = (float) $this->total_amount;

        if ($totalAmount <= 0 || $netPaidAmount <= 0.0) {
            return $refundedAmount > 0.0
                ? self::PAYMENT_STATUS_REFUNDED
                : self::PAYMENT_STATUS_UNPAID;
        }

        if ($refundedAmount > 0.0 && $netPaidAmount < $totalAmount) {
            return self::PAYMENT_STATUS_PARTIALLY_REFUNDED;
        }

        if ($refundedAmount > 0.0 && $netPaidAmount >= $totalAmount) {
            return self::PAYMENT_STATUS_PAID;
        }

        return self::PAYMENT_STATUS_PAID;
    }

    private function financialTransactionsForCalculations(): Collection
    {
        if ($this->relationLoaded('transactions')) {
            return $this->transactions;
        }

        return $this->transactions()->get();
    }
}