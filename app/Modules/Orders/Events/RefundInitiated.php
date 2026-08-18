<?php

namespace App\Modules\Orders\Events;

use App\Models\FinancialTransaction;
use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RefundInitiated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Order $order,
        public readonly FinancialTransaction $transaction,
    ) {}
}
