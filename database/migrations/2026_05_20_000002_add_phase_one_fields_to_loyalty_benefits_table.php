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
        Schema::table('loyalty_benefits', function (Blueprint $table): void {
            $table->json('applies_to_services')->nullable()->after('configuration');
            $table->decimal('minimum_order_amount', 12, 2)->nullable()->after('applies_to_services');
            $table->decimal('maximum_discount_amount', 12, 2)->nullable()->after('minimum_order_amount');
            $table->unsignedSmallInteger('priority')->default(0)->after('maximum_discount_amount')->index();
            $table->boolean('stackable')->default(false)->after('priority')->index();
            $table->timestamp('effective_from')->nullable()->after('stackable')->index();
            $table->timestamp('effective_to')->nullable()->after('effective_from')->index();
            $table->boolean('finance_sensitive')->default(false)->after('effective_to')->index();
            $table->foreignId('created_by_user_id')->nullable()->after('finance_sensitive')->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->after('created_by_user_id')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loyalty_benefits', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('updated_by_user_id');
            $table->dropConstrainedForeignId('created_by_user_id');
            $table->dropColumn([
                'applies_to_services',
                'minimum_order_amount',
                'maximum_discount_amount',
                'priority',
                'stackable',
                'effective_from',
                'effective_to',
                'finance_sensitive',
            ]);
        });
    }
};