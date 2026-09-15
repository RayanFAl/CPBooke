<?php

use App\Models\LoyaltyBenefit;
use App\Models\Order;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ensure loyalty discounts apply to flight, hotel, insurance, and esim.
     */
    public function up(): void
    {
        if (! Schema::hasTable('loyalty_benefits')) {
            return;
        }

        $services = Order::serviceTypes();
        $now = now();

        DB::table('loyalty_benefits')
            ->where('benefit_type', LoyaltyBenefit::TYPE_DISCOUNT)
            ->where('is_active', true)
            ->orderBy('id')
            ->each(function (object $benefit) use ($services, $now): void {
                $configuration = json_decode((string) ($benefit->configuration ?? '[]'), true);
                if (! is_array($configuration)) {
                    $configuration = [];
                }

                $configuration['applies_to'] = $services;

                $payload = [
                    'configuration' => json_encode($configuration),
                    'updated_at' => $now,
                ];

                if (Schema::hasColumn('loyalty_benefits', 'applies_to_services')) {
                    $payload['applies_to_services'] = json_encode($services);
                }

                DB::table('loyalty_benefits')
                    ->where('id', $benefit->id)
                    ->update($payload);
            });
    }

    public function down(): void
    {
        // Data-only migration; no structural rollback.
    }
};
