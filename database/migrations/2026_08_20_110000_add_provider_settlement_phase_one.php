<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settlements', function (Blueprint $table): void {
            $table->timestamp('approved_at')->nullable()->after('compared_at');
            $table->foreignId('approved_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            $table->timestamp('reopened_at')->nullable()->after('closed_at');
            $table->foreignId('reopened_by')->nullable()->after('reopened_at')->constrained('users')->nullOnDelete();
            $table->text('reopen_reason')->nullable()->after('reopened_by');
            $table->json('close_history')->nullable()->after('reopen_reason');
        });

        Schema::table('settlement_items', function (Blueprint $table): void {
            $table->string('expected_cost_source', 30)->default('order')->after('supplier_cost');
            $table->string('resolution_type', 30)->nullable()->after('status');
            $table->string('resolution_reason', 50)->nullable()->after('resolution_type');
            $table->decimal('resolution_amount', 14, 2)->nullable()->after('resolution_reason');
            $table->foreignId('pending_approval_id')->nullable()->after('resolved_at')->constrained('approvals')->nullOnDelete();
            $table->foreignId('financial_transaction_id')->nullable()->after('pending_approval_id')->constrained('financial_transactions')->nullOnDelete();
        });

        Schema::create('settlement_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('settlement_id')->constrained('settlements')->cascadeOnDelete();
            $table->string('kind', 30)->index();
            $table->string('disk', 30)->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 120)->nullable();
            $table->unsignedInteger('size')->default(0);
            $table->string('source', 30)->default('upload');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['settlement_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settlement_attachments');

        Schema::table('settlement_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('financial_transaction_id');
            $table->dropConstrainedForeignId('pending_approval_id');
            $table->dropColumn([
                'expected_cost_source',
                'resolution_type',
                'resolution_reason',
                'resolution_amount',
            ]);
        });

        Schema::table('settlements', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropConstrainedForeignId('reopened_by');
            $table->dropColumn([
                'approved_at',
                'reopened_at',
                'reopen_reason',
                'close_history',
            ]);
        });
    }
};
