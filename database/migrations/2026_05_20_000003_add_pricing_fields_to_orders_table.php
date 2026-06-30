<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->decimal('base_amount', 12, 2)->nullable()->after('total_amount');
            $table->decimal('discount_total', 12, 2)->nullable()->after('base_amount');
            $table->decimal('final_amount', 12, 2)->nullable()->after('discount_total');
            $table->string('pricing_version', 120)->nullable()->after('final_amount')->index();
            $table->json('pricing_snapshot_json')->nullable()->after('pricing_version');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn([
                'base_amount',
                'discount_total',
                'final_amount',
                'pricing_version',
                'pricing_snapshot_json',
            ]);
        });
    }
};