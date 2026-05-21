<?php

namespace App\Modules\Notifications\Events;

use App\Models\FinancialTransaction;
use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RefundIssued
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Order $order,
        public readonly FinancialTransaction $transaction,
    ) {
    }
}