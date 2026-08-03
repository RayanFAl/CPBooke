<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignId('provider_id')->nullable()->after('customer_id')->constrained('providers')->nullOnDelete();
            $table->decimal('selling_price', 12, 2)->nullable()->after('tax_amount');
            $table->decimal('supplier_cost', 12, 2)->nullable()->after('selling_price');
            $table->decimal('commission_amount', 12, 2)->nullable()->after('supplier_cost');
            $table->decimal('markup_amount', 12, 2)->nullable()->after('commission_amount');
            $table->decimal('profit_amount', 12, 2)->nullable()->after('markup_amount');
            $table->decimal('margin_percent', 8, 2)->nullable()->after('profit_amount');

            $table->index('provider_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('provider_id');
            $table->dropColumn([
                'selling_price',
                'supplier_cost',
                'commission_amount',
                'markup_amount',
                'profit_amount',
                'margin_percent',
            ]);
        });
    }
};
