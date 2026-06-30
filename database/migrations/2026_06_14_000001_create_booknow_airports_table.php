<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('booknow_airports')) {
            return;
        }

        Schema::create('booknow_airports', function (Blueprint $table) {
            $table->id();
            $table->string('iata_code', 10)->nullable()->index();
            $table->string('icao_code', 10)->nullable()->index();
            $table->string('name_en');
            $table->string('name_ar')->nullable();
            $table->string('name_fr')->nullable();
            $table->string('city_en')->nullable();
            $table->string('city_ar')->nullable();
            $table->string('city_fr')->nullable();
            $table->string('country_iso2', 2)->nullable()->index();
            $table->string('country_name_en')->nullable();
            $table->string('country_name_ar')->nullable();
            $table->string('country_name_fr')->nullable();
            $table->string('type', 50)->nullable();
            $table->string('scheduled_service', 3)->nullable();
            $table->decimal('latitude_deg', 10, 7)->nullable();
            $table->decimal('longitude_deg', 10, 7)->nullable();
            $table->string('translation_status')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booknow_airports');
    }
};
