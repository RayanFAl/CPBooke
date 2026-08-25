<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotel_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('hotel_id', 80)->index();
            $table->string('booking_reference', 80)->nullable()->index();
            $table->unsignedTinyInteger('overall_rating');
            $table->json('categories')->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->index(['hotel_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_reviews');
    }
};
