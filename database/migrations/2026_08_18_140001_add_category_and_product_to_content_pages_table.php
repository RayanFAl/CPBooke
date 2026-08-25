<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_pages', function (Blueprint $table): void {
            $table->string('category', 32)->default('legal')->after('slug');
            $table->string('product', 32)->nullable()->after('category');
            $table->index(['category', 'product']);
        });
    }

    public function down(): void
    {
        Schema::table('content_pages', function (Blueprint $table): void {
            $table->dropIndex(['category', 'product']);
            $table->dropColumn(['category', 'product']);
        });
    }
};
