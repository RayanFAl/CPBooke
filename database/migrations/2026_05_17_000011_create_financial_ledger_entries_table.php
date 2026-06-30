<?php

use App\Models\FinancialLedgerEntry;
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
        Schema::create('financial_ledger_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('financial_transaction_id')->constrained('financial_transactions')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('entry_type', 10)->index();
            $table->string('account_code', 80)->index();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->index();
            $table->string('reference_type', 40)->nullable()->index();
            $table->unsignedBigInteger('reference_id')->nullable()->index();
            $table->timestamp('posted_at')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['financial_transaction_id', 'entry_type'], 'fin_tx_entry_type_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('financial_ledger_entries');
    }
};