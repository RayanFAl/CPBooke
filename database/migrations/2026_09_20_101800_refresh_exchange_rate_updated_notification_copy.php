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
                'subject' => 'تم تحديث سعر الصرف',
                'body' => "تغيرت أسعار {currency_code}:\nشراء: {old_buy} ← {new_buy} د.ل\nبيع: {old_sell} ← {new_sell} د.ل",
                'name' => 'تم تحديث سعر الصرف',
            ],
        );

        $template->forceFill([
            'subject' => 'Exchange Rate Updated',
            'body' => "{currency_code} rates changed:\nBuy: {old_buy} → {new_buy} LYD\nSell: {old_sell} → {new_sell} LYD",
            'translations' => $translations,
            'variables' => [
                'currency_code',
                'old_buy',
                'new_buy',
                'old_sell',
                'new_sell',
                'old_rate',
                'new_rate',
                'deep_link',
            ],
        ])->save();
    }

    public function down(): void
    {
        //
    }
};
