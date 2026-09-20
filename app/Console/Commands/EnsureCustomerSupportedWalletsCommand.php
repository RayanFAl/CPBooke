<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Modules\CustomerWallets\Services\CustomerWalletService;
use Illuminate\Console\Command;

class EnsureCustomerSupportedWalletsCommand extends Command
{
    protected $signature = 'customer-wallets:ensure-supported {--chunk=200 : Customers per chunk}';

    protected $description = 'Backfill LYD/USD/EUR wallets for active customers (admin/ops only)';

    public function handle(CustomerWalletService $walletService): int
    {
        $chunk = max(1, (int) $this->option('chunk'));
        $createdOrChecked = 0;

        User::query()
            ->where('account_type', User::ACCOUNT_TYPE_CUSTOMER)
            ->where('is_active', true)
            ->orderBy('id')
            ->chunkById($chunk, function ($customers) use ($walletService, &$createdOrChecked): void {
                foreach ($customers as $customer) {
                    $walletService->ensureSupportedWallets($customer);
                    $createdOrChecked++;
                }
            });

        $this->info("Ensured supported wallets for {$createdOrChecked} customers.");

        return self::SUCCESS;
    }
}
