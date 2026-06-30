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
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->string('provider_name');
            $table->string('external_booking_id')->nullable();
            $table->string('booking_reference')->nullable()->index();
            $table->enum('status', [
                'draft',
                'pending_payment',
                'paid',
                'processing',
                'confirmed',
                'ticketed',
                'completed',
                'cancelled',
                'failed',
                'refunded',
            ])->default('draft')->index();
            $table->string('currency', 3);
            $table->decimal('total_amount', 12, 2);
            $table->json('request_payload');
            $table->json('response_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};