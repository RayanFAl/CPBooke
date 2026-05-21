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
        Schema::table('financial_transactions', function (Blueprint $table): void {
            $table->string('debit_account', 80)->nullable()->after('source');
            $table->string('credit_account', 80)->nullable()->after('debit_account');
            $table->string('reference_type', 40)->nullable()->after('credit_account');
            $table->unsignedBigInteger('reference_id')->nullable()->after('reference_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('financial_transactions', function (Blueprint $table): void {
            $table->dropColumn([
                'debit_account',
                'credit_account',
                'reference_type',
                'reference_id',
            ]);
        });
    }
};