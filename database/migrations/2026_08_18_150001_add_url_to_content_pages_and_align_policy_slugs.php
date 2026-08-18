<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_pages', function (Blueprint $table): void {
            $table->string('url', 2048)->nullable()->after('body_ar');
            $table->unique('product');
        });

        $this->renameSlug('flights-policy', 'flight-policy');
        $this->renameSlug('hotels-policy', 'hotel-policy');
    }

    public function down(): void
    {
        $this->renameSlug('flight-policy', 'flights-policy');
        $this->renameSlug('hotel-policy', 'hotels-policy');

        Schema::table('content_pages', function (Blueprint $table): void {
            $table->dropUnique(['product']);
            $table->dropColumn('url');
        });
    }

    private function renameSlug(string $from, string $to): void
    {
        DB::table('content_pages')
            ->where('slug', $from)
            ->update(['slug' => $to]);
    }
};
