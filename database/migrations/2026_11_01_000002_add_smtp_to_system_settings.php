<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('system_settings')) {
            return;
        }

        Schema::table('system_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('system_settings', 'mail_mailer')) {
                $table->string('mail_mailer', 32)->nullable()->after('email_from_name');
            }
            if (! Schema::hasColumn('system_settings', 'mail_host')) {
                $table->string('mail_host', 255)->nullable()->after('mail_mailer');
            }
            if (! Schema::hasColumn('system_settings', 'mail_port')) {
                $table->unsignedSmallInteger('mail_port')->nullable()->after('mail_host');
            }
            if (! Schema::hasColumn('system_settings', 'mail_encryption')) {
                $table->string('mail_encryption', 16)->nullable()->after('mail_port');
            }
            if (! Schema::hasColumn('system_settings', 'mail_username')) {
                $table->string('mail_username', 255)->nullable()->after('mail_encryption');
            }
            if (! Schema::hasColumn('system_settings', 'mail_password')) {
                $table->text('mail_password')->nullable()->after('mail_username');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('system_settings')) {
            return;
        }

        Schema::table('system_settings', function (Blueprint $table): void {
            foreach (['mail_mailer', 'mail_host', 'mail_port', 'mail_encryption', 'mail_username', 'mail_password'] as $column) {
                if (Schema::hasColumn('system_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
