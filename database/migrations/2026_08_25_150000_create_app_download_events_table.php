<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('app_download_events')) {
            return;
        }

        Schema::create('app_download_events', function (Blueprint $table): void {
            $table->id();
            $table->string('event_type', 32)->index();
            $table->string('visitor_hash', 64)->index();
            $table->string('platform', 32)->nullable()->index();
            $table->string('version', 40)->nullable();
            $table->string('apk_filename', 191)->nullable();
            $table->string('locale', 8)->nullable();
            $table->timestamps();

            $table->index(['event_type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_download_events');
    }
};
