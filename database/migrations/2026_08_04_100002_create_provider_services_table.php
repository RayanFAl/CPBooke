<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_services', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('provider_id')->constrained('providers')->cascadeOnDelete();
            $table->string('service', 40);
            $table->boolean('enabled')->default(false);
            $table->json('configuration')->nullable();
            $table->timestamps();

            $table->unique(['provider_id', 'service']);
            $table->index(['provider_id', 'enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_services');
    }
};
