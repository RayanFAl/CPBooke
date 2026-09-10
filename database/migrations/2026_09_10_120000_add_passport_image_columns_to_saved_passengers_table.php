<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('saved_passengers')) {
            return;
        }

        Schema::table('saved_passengers', function (Blueprint $table): void {
            if (! Schema::hasColumn('saved_passengers', 'passport_image_disk')) {
                $table->string('passport_image_disk', 32)->nullable()->after('is_default');
            }

            if (! Schema::hasColumn('saved_passengers', 'passport_image_path')) {
                $table->string('passport_image_path', 512)->nullable()->after('passport_image_disk');
            }

            if (! Schema::hasColumn('saved_passengers', 'passport_image_mime')) {
                $table->string('passport_image_mime', 64)->nullable()->after('passport_image_path');
            }

            if (! Schema::hasColumn('saved_passengers', 'passport_image_size')) {
                $table->unsignedInteger('passport_image_size')->nullable()->after('passport_image_mime');
            }

            if (! Schema::hasColumn('saved_passengers', 'passport_image_uploaded_at')) {
                $table->timestamp('passport_image_uploaded_at')->nullable()->after('passport_image_size');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('saved_passengers')) {
            return;
        }

        Schema::table('saved_passengers', function (Blueprint $table): void {
            foreach ([
                'passport_image_disk',
                'passport_image_path',
                'passport_image_mime',
                'passport_image_size',
                'passport_image_uploaded_at',
            ] as $column) {
                if (Schema::hasColumn('saved_passengers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
