<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('loyalty_company_rates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tier_id')->constrained('loyalty_tiers')->cascadeOnDelete();
            $table->string('service_type', 40);
            $table->string('company_key', 80);
            $table->string('company_name', 160)->nullable();
            $table->decimal('discount_percentage', 5, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tier_id', 'service_type', 'company_key'], 'loyalty_company_rates_unique');
            $table->index(['service_type', 'company_key'], 'loyalty_company_rates_service_company_idx');
            $table->index(['tier_id', 'is_active'], 'loyalty_company_rates_tier_active_idx');
        });

        $this->seedCatalogDefaults();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loyalty_company_rates');
    }

    private function seedCatalogDefaults(): void
    {
        if (! Schema::hasTable('loyalty_tiers') || ! Schema::hasTable('loyalty_benefits')) {
            return;
        }

        $tiers = DB::table('loyalty_tiers')
            ->where('is_active', true)
            ->where('level', '>', 0)
            ->orderBy('level')
            ->get(['id', 'level']);

        if ($tiers->isEmpty()) {
            return;
        }

        $now = now();
        $catalog = [
            ['service_type' => 'hotel', 'company_key' => 'hotel', 'company_name' => 'Hotels', 'sort_order' => 1000],
            ['service_type' => 'esim', 'company_key' => 'esim', 'company_name' => 'eSIM', 'sort_order' => 1001],
            ['service_type' => 'insurance', 'company_key' => 'insurance', 'company_name' => 'Insurance', 'sort_order' => 1002],
        ];

        $rows = [];

        foreach ($tiers as $tier) {
            $defaultPercentage = DB::table('loyalty_benefits')
                ->where('tier_id', $tier->id)
                ->where('benefit_type', 'discount')
                ->where('value_type', 'percentage')
                ->where('is_active', true)
                ->orderByDesc('is_highlighted')
                ->orderBy('display_order')
                ->value('value');

            foreach ($catalog as $company) {
                $rows[] = [
                    'tier_id' => $tier->id,
                    'service_type' => $company['service_type'],
                    'company_key' => $company['company_key'],
                    'company_name' => $company['company_name'],
                    'discount_percentage' => $defaultPercentage !== null
                        ? number_format((float) $defaultPercentage, 2, '.', '')
                        : null,
                    'is_active' => true,
                    'sort_order' => $company['sort_order'],
                    'metadata' => json_encode(['seeded' => true]),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if ($rows !== []) {
            DB::table('loyalty_company_rates')->insert($rows);
        }
    }
};
