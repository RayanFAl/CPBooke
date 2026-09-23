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
            if (! Schema::hasColumn('system_settings', 'noreply_email')) {
                $table->string('noreply_email')->nullable()->after('support_phone');
            }
            if (! Schema::hasColumn('system_settings', 'info_email')) {
                $table->string('info_email')->nullable()->after('noreply_email');
            }
            if (! Schema::hasColumn('system_settings', 'feedback_email')) {
                $table->string('feedback_email')->nullable()->after('info_email');
            }
        });

        $row = DB::table('system_settings')->orderBy('id')->first();
        if ($row === null) {
            return;
        }

        $updates = [];

        if (blank($row->support_email ?? null)) {
            $updates['support_email'] = config('mail.addresses.support');
        }
        if (blank($row->noreply_email ?? null) && Schema::hasColumn('system_settings', 'noreply_email')) {
            $updates['noreply_email'] = config('mail.addresses.noreply');
        }
        if (blank($row->info_email ?? null) && Schema::hasColumn('system_settings', 'info_email')) {
            $updates['info_email'] = config('mail.addresses.info');
        }
        if (blank($row->feedback_email ?? null) && Schema::hasColumn('system_settings', 'feedback_email')) {
            $updates['feedback_email'] = config('mail.addresses.feedback');
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
            foreach (['feedback_email', 'info_email', 'noreply_email'] as $column) {
                if (Schema::hasColumn('system_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
