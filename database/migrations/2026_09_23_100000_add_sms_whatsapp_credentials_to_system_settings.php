<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('system_settings')) {
            return;
        }

        Schema::table('system_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('system_settings', 'sms_endpoint')) {
                $table->string('sms_endpoint')->nullable()->after('sms_sender_name');
            }
            if (! Schema::hasColumn('system_settings', 'sms_token')) {
                $table->text('sms_token')->nullable()->after('sms_endpoint');
            }
            if (! Schema::hasColumn('system_settings', 'whatsapp_endpoint')) {
                $table->string('whatsapp_endpoint')->nullable()->after('whatsapp_sender_name');
            }
            if (! Schema::hasColumn('system_settings', 'whatsapp_token')) {
                $table->text('whatsapp_token')->nullable()->after('whatsapp_endpoint');
            }
        });

        $row = DB::table('system_settings')->orderBy('id')->first();
        if ($row === null) {
            return;
        }

        $updates = [];

        if (blank($row->sms_endpoint ?? null)) {
            $endpoint = trim((string) config('services.notifications.sms_endpoint', ''));
            if ($endpoint !== '') {
                $updates['sms_endpoint'] = $endpoint;
            }
        }
        if (blank($row->sms_token ?? null)) {
            $token = trim((string) config('services.notifications.sms_token', ''));
            if ($token !== '') {
                $updates['sms_token'] = $token;
            }
        }
        if (blank($row->whatsapp_endpoint ?? null)) {
            $endpoint = trim((string) config('services.notifications.whatsapp_endpoint', ''));
            if ($endpoint !== '') {
                $updates['whatsapp_endpoint'] = $endpoint;
            }
        }
        if (blank($row->whatsapp_token ?? null)) {
            $token = trim((string) config('services.notifications.whatsapp_token', ''));
            if ($token !== '') {
                $updates['whatsapp_token'] = $token;
            }
        }

        if ($updates !== []) {
            DB::table('system_settings')->where('id', $row->id)->update($updates);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('system_settings')) {
            return;
        }

        Schema::table('system_settings', function (Blueprint $table): void {
            foreach (['whatsapp_token', 'whatsapp_endpoint', 'sms_token', 'sms_endpoint'] as $column) {
                if (Schema::hasColumn('system_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
