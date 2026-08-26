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
        Schema::create('linked_accounts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('linked_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('linked_account_request_id')
                ->nullable()
                ->constrained('linked_account_requests')
                ->nullOnDelete();
            $table->string('relationship_type', 30);
            $table->string('nickname', 120)->nullable();
            $table->boolean('can_request_payment')->default(false);
            $table->boolean('can_receive_payment_requests')->default(true);
            $table->boolean('auto_approve')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'linked_user_id']);
            $table->index(['user_id', 'is_active']);
            $table->index(['linked_user_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('linked_accounts');
    }
};
