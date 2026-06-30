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
        Schema::create('saved_passengers', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['ADT', 'CHD', 'INF'])->default('ADT');
            $table->string('title', 20)->nullable();
            $table->string('first_name', 120);
            $table->string('last_name', 120);
            $table->date('date_of_birth');
            $table->enum('gender', ['M', 'F']);
            $table->string('nationality', 3);
            $table->string('country_of_residence', 3)->nullable();
            $table->enum('document_type', ['passport', 'national_id'])->default('passport');
            $table->text('passport_number');
            $table->string('passport_number_hash', 64)->nullable();
            $table->string('passport_issue_country', 3)->nullable();
            $table->date('passport_issue_date')->nullable();
            $table->date('passport_expiry');
            $table->text('email')->nullable();
            $table->text('phone')->nullable();
            $table->string('phone_hash', 64)->nullable();
            $table->string('seat_preference', 30)->nullable();
            $table->string('meal_preference', 30)->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'deleted_at']);
            $table->index(['user_id', 'first_name']);
            $table->index(['user_id', 'last_name']);
            $table->index(['user_id', 'passport_number_hash']);
            $table->index(['user_id', 'phone_hash']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('saved_passengers');
    }
};
