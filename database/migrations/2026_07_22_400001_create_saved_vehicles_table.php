<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_vehicles', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('label', 120)->nullable();
            $table->boolean('is_default')->default(false);
            $table->string('beneficiary_name', 180);
            $table->text('beneficiary_phone');
            $table->string('beneficiary_phone_hash', 64)->nullable();
            $table->text('email')->nullable();
            $table->unsignedBigInteger('vehicle_type_id')->nullable();
            $table->unsignedBigInteger('vehicle_color_id')->nullable();
            $table->unsignedBigInteger('vehicle_licensing_authority_id')->nullable();
            $table->unsignedSmallInteger('vehicle_manufacture_year');
            $table->string('vehicle_chassis_number', 80);
            $table->string('vehicle_chassis_number_hash', 64);
            $table->string('vehicle_plate_number', 40);
            $table->string('vehicle_plate_number_hash', 64)->nullable();
            $table->decimal('payload', 10, 2)->nullable();
            $table->unsignedBigInteger('document_type_id')->nullable();
            $table->string('vehicle_nationality', 3)->nullable();
            $table->string('address', 255)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'deleted_at']);
            $table->index(['user_id', 'type']);
            $table->index(['user_id', 'vehicle_chassis_number_hash']);
            $table->index(['user_id', 'vehicle_plate_number_hash']);
            $table->index(['user_id', 'beneficiary_phone_hash']);
            $table->index(['user_id', 'beneficiary_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_vehicles');
    }
};
