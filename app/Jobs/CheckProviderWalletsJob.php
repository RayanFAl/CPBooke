<?php

namespace App\Jobs;

use App\Models\ProviderWallet;
use App\Modules\Monitoring\Services\ApplicationEventRecorder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CheckProviderWalletsJob implements ShouldQueue
{
    use Queueable;

    public function handle(ApplicationEventRecorder $recorder): void
    {
        $critical = (float) config('provider_health.alerts.wallet_critical_balance', 500);

        $wallets = ProviderWallet::query()
            ->with('provider:id,name,key')
            ->where('is_active', true)
            ->get()
            ->filter(function (ProviderWallet $wallet) use ($critical): bool {
                return $wallet->isLowBalance() || (float) $wallet->balance < $critical;
            });

        foreach ($wallets as $wallet) {
            $recorder->record(
                'system',
                'critical',
                'Wallet alert: '.($wallet->provider?->name ?? 'provider').' balance '.$wallet->balance.' '.$wallet->currency,
                'wallet_check',
                [
                    'wallet_id' => $wallet->id,
                    'provider_id' => $wallet->provider_id,
                    'balance' => $wallet->balance,
                ],
            );
        }
    }
}
