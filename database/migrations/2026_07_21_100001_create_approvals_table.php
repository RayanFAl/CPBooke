<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approvals', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 50)->index();
            $table->string('entity_type', 50)->index();
            $table->unsignedBigInteger('entity_id')->index();
            $table->string('status', 30)->default('pending')->index();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->json('payload');
            $table->json('execution_result')->nullable();
            $table->text('execution_error')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();

            $table->index(['entity_type', 'entity_id', 'status']);
            $table->index(['type', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approvals');
    }
};
