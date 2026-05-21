<?php

use App\Models\FinancialTransaction;
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
            $table->string('status', 30)
                ->default(FinancialTransaction::STATUS_EXECUTED)
                ->after('type');
            $table->string('performed_by_type', 60)
                ->nullable()
                ->after('currency');
            $table->unsignedBigInteger('performed_by_id')
                ->nullable()
                ->after('performed_by_type');
            $table->unsignedBigInteger('source_id')
                ->nullable()
                ->after('source');
            $table->text('reason')
                ->nullable()
                ->after('source_id');
            $table->json('metadata')
                ->nullable()
                ->after('reason');

            $table->index(['source', 'source_id']);
            $table->index(['performed_by_type', 'performed_by_id']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('financial_transactions', function (Blueprint $table): void {
            $table->dropIndex(['source', 'source_id']);
            $table->dropIndex(['performed_by_type', 'performed_by_id']);
            $table->dropIndex(['status', 'created_at']);

            $table->dropColumn([
                'status',
                'performed_by_type',
                'performed_by_id',
                'source_id',
                'reason',
                'metadata',
            ]);
        });
    }
};