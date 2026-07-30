<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('password_reset_otps', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('otp_hash');
            $table->timestamp('otp_expires_at');
            $table->unsignedTinyInteger('otp_attempts')->default(0);
            $table->timestamp('last_sent_at')->nullable();
            $table->string('reset_token_hash')->nullable()->unique();
            $table->timestamp('reset_token_expires_at')->nullable();
            $table->timestamp('otp_verified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_otps');
    }
};
