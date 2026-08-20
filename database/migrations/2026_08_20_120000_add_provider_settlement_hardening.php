<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settlement_invoice_imports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('settlement_id')->constrained('settlements')->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->foreignId('attachment_id')->nullable()->constrained('settlement_attachments')->nullOnDelete();
            $table->string('original_name')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('uploaded_at');
            $table->unsignedInteger('row_count')->default(0);
            $table->unsignedInteger('matched_count')->default(0);
            $table->unsignedInteger('extra_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->json('errors')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();

            $table->unique(['settlement_id', 'sequence']);
            $table->index(['settlement_id', 'is_active']);
        });

        Schema::table('settlements', function (Blueprint $table): void {
            $table->foreignId('current_invoice_import_id')->nullable()->after('close_history')->constrained('settlement_invoice_imports')->nullOnDelete();
            $table->json('close_snapshot')->nullable()->after('current_invoice_import_id');
            $table->unsignedInteger('resolved_count')->default(0)->after('matched_count');
            $table->decimal('adjustment_total', 14, 2)->default(0)->after('difference');
        });

        Schema::table('settlement_items', function (Blueprint $table): void {
            $table->foreignId('invoice_import_id')->nullable()->after('financial_transaction_id')->constrained('settlement_invoice_imports')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('settlement_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('invoice_import_id');
        });

        Schema::table('settlements', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('current_invoice_import_id');
            $table->dropColumn([
                'close_snapshot',
                'resolved_count',
                'adjustment_total',
            ]);
        });

        Schema::dropIfExists('settlement_invoice_imports');
    }
};
