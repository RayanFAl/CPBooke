<?php

use App\Models\NotificationTemplate;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $template = NotificationTemplate::query()->where('code', 'EXCHANGE_RATE_UPDATED')->first();

        if (! $template) {
            return;
        }

        $translations = is_array($template->translations) ? $template->translations : [];
        $translations['ar'] = array_merge(
            is_array($translations['ar'] ?? null) ? $translations['ar'] : [],
            [
                'subject' => 'تم تحديث أسعار الصرف',
                'body' => 'تم تحديث أسعار الشراء والبيع. افتح التطبيق لعرض آخر الأسعار.',
                'name' => 'تم تحديث سعر الصرف',
            ],
        );

        $template->forceFill([
            'subject' => 'Exchange rates updated',
            'body' => 'Buy and sell rates were updated. Open the app to view the latest prices.',
            'translations' => $translations,
            'variables' => ['currency_codes', 'deep_link'],
        ])->save();
    }

    public function down(): void
    {
        //
    }
};
