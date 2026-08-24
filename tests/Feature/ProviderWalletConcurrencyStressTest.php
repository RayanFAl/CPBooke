<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Provider;
use App\Models\ProviderWallet;
use App\Models\ProviderWalletTransaction;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ProviderWalletConcurrencyStressTest extends TestCase
{
    public function test_process_level_concurrent_sync_requests_enforce_wallet_capacity(): void
    {
        $dbPath = $this->prepareSharedSqliteDatabase();

        try {
            $customer = User::factory()->create([
                'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
                'is_admin' => false,
            ]);

            $provider = Provider::query()->create([
                'name' => 'BookNow',
                'key' => Provider::KEY_BOOKNOW,
                'status' => Provider::STATUS_ACTIVE,
                'credit_limit' => 0,
                'default_currency' => 'LYD',
                'integration_status' => Provider::INTEGRATION_LIVE,
                'commission_rate' => 0,
            ]);

            ProviderWallet::query()->create([
                'provider_id' => $provider->id,
                'currency' => 'LYD',
                'environment' => 'production',
                'balance' => 500,
                'allow_negative' => false,
                'is_active' => true,
            ]);

            $payloads = [
                $this->payload('stress-a', 400),
                $this->payload('stress-b', 400),
                $this->payload('stress-c', 200),
            ];

            $processes = [];
            foreach ($payloads as $payload) {
                $encoded = base64_encode((string) json_encode($payload, JSON_THROW_ON_ERROR));
                $process = new Process([
                    PHP_BINARY,
                    base_path('tests/Support/run_booknow_sync_process.php'),
                    $dbPath,
                    (string) $customer->id,
                    $encoded,
                ], base_path());
                $process->start();
                $processes[] = $process;
            }

            foreach ($processes as $process) {
                $process->wait();
                $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
            }

            $results = array_map(
                static fn (Process $process): array => json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR),
                $processes,
            );

            $successes = collect($results)->where('ok', true)->count();
            $failures = collect($results)->where('ok', false)->count();

            $this->assertSame(1, $successes);
            $this->assertSame(2, $failures);

            $wallet = ProviderWallet::query()
                ->where('provider_id', $provider->id)
                ->where('currency', 'LYD')
                ->firstOrFail();

            $this->assertSame('100.00', (string) $wallet->balance);

            $debits = ProviderWalletTransaction::query()
                ->where('provider_wallet_id', $wallet->id)
                ->where('type', ProviderWalletTransaction::TYPE_DEBIT)
                ->orderBy('id')
                ->get();

            $this->assertCount(1, $debits);
            $this->assertSame('400.00', (string) $debits->first()->amount);
            $this->assertSame('100.00', (string) $debits->first()->balance_after);

            $this->assertSame(1, Order::query()->count());
        } finally {
            @unlink($dbPath);
        }
    }

    private function prepareSharedSqliteDatabase(): string
    {
        $dbPath = base_path('database/testing-concurrency-'.uniqid().'.sqlite');
        touch($dbPath);

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', $dbPath);
        config()->set('database.connections.sqlite.foreign_key_constraints', true);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Artisan::call('migrate:fresh', [
            '--database' => 'sqlite',
            '--force' => true,
        ]);

        return $dbPath;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(string $bookingId, float $grandTotal): array
    {
        return [
            'source' => 'mobile_app',
            'product_type' => 'flight',
            'status' => 'confirmed',
            'currency' => 'LYD',
            'grand_total' => $grandTotal,
            'provider_booking' => [
                'booking_id' => $bookingId,
                'order_number' => strtoupper($bookingId),
                'pnr' => strtoupper(substr($bookingId, 0, 6)),
                'provider_id' => 12,
                'provider_name' => 'Buraq Air',
                'search_uuid' => 'search-uuid-'.$bookingId,
            ],
            'contact' => [
                'first_name' => 'RAYAN',
                'last_name' => 'FATHI',
                'email' => 'a.rayan@median.ly',
                'phone' => '+218943215277',
            ],
            'passengers' => [
                [
                    'type' => 'adult',
                    'title' => 'Mr',
                    'first_name' => 'RAYAN',
                    'last_name' => 'FATHI',
                    'dob' => '1998-05-10',
                    'gender' => 'M',
                    'nationality' => 'LY',
                    'passport_number' => 'AB1234567',
                    'passport_expiry' => '2030-01-01',
                    'passport_issue_country' => 'LY',
                ],
            ],
            'items' => [
                [
                    'type' => 'flight',
                    'product_type' => 'ticket',
                    'product_subtype' => 'oneway',
                    'provider_reference' => strtoupper(substr($bookingId, 0, 6)),
                    'total' => $grandTotal,
                    'currency' => 'LYD',
                    'item_details' => [
                        'pnr' => strtoupper(substr($bookingId, 0, 6)),
                        'airline_code' => 'BM',
                        'airline_name' => 'Buraq Air',
                        'segments' => [
                            [
                                'flight_number' => 'BM0400',
                                'departure_airport' => 'MJI',
                                'arrival_airport' => 'TUN',
                                'departure_time' => '2026-06-20 10:25:00',
                                'arrival_time' => '2026-06-20 10:35:00',
                                'duration' => 10,
                                'cabin_type' => 'Y',
                                'class' => 'S',
                            ],
                        ],
                        'passengers' => [],
                    ],
                ],
            ],
            'payment' => [
                'status' => 'paid',
                'method' => 'card',
                'method_code' => 1,
                'amount' => $grandTotal,
                'currency' => 'LYD',
                'transaction_id' => 'txn_'.$bookingId,
                'paid_at' => '2026-06-07T10:22:50Z',
            ],
            'metadata' => [
                'app_version' => '1.0.0',
                'platform' => 'android',
            ],
            'booking_flight_data' => [
                'departure_airport' => 'MJI',
                'arrival_airport' => 'TUN',
                'departure_time' => '2026-06-20 10:25:00',
                'segments' => [],
            ],
            'base_amount' => $grandTotal,
            'commission_amount' => 0,
        ];
    }
}
