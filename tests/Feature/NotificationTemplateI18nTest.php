<?php

namespace Tests\Feature;

use App\Models\NotificationTemplate;
use App\Models\User;
use App\Modules\Notifications\Services\NotificationLocaleResolver;
use App\Modules\Notifications\Services\NotificationTemplateSyncService;
use App\Modules\Notifications\Support\NotificationLocales;
use App\Modules\Notifications\Support\NotificationTemplateStaffLabels;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTemplateI18nTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_seeds_arabic_translations_for_catalog_templates(): void
    {
        $result = app(NotificationTemplateSyncService::class)->syncMissing();

        $this->assertGreaterThan(0, $result['created'] + $result['existing']);

        $template = NotificationTemplate::query()->where('code', 'PAYMENT_SUCCEEDED')->firstOrFail();

        $this->assertSame('تم الدفع بنجاح', data_get($template->translations, 'ar.subject'));
        $this->assertSame('تم الدفع بنجاح', data_get($template->translations, 'ar.name'));
        $this->assertSame('Payment successful', $template->name);
        $this->assertTrue($template->hasLocale(NotificationLocales::AR));
    }

    public function test_template_localizes_copy_for_arabic_users(): void
    {
        $template = NotificationTemplate::query()->create([
            'code' => 'TEST_LOCALE',
            'name' => 'Test',
            'category' => 'general',
            'subject' => 'Hello {user_name}',
            'body' => 'English body for {user_name}',
            'translations' => [
                'ar' => [
                    'subject' => 'مرحباً {user_name}',
                    'body' => 'نص عربي لـ {user_name}',
                ],
            ],
            'channels' => ['in_app'],
            'variables' => ['user_name'],
            'is_active' => true,
        ]);

        $this->assertSame('مرحباً {user_name}', $template->localizedSubject(NotificationLocales::AR));
        $this->assertSame('نص عربي لـ {user_name}', $template->localizedBody(NotificationLocales::AR));
    }

    public function test_staff_labels_are_arabic_and_plain_language(): void
    {
        $this->assertSame('Airline cancelled the flight', NotificationTemplateStaffLabels::english('FLIGHT_CANCELLED'));
        $this->assertSame('شركة الطيران ألغت الرحلة', NotificationTemplateStaffLabels::arabic('FLIGHT_CANCELLED'));
        $this->assertSame('Wallet topped up', NotificationTemplateStaffLabels::english('WALLET_TOPUP_SUCCESS'));
        $this->assertSame('تم شحن المحفظة', NotificationTemplateStaffLabels::arabic('WALLET_TOPUP_SUCCESS'));
    }

    public function test_user_preferred_locale_defaults_to_arabic(): void
    {
        $user = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
        ]);

        $this->assertSame('ar', app(NotificationLocaleResolver::class)->forUser($user));
    }

    public function test_sync_refreshes_outdated_exchange_rate_arabic_copy(): void
    {
        NotificationTemplate::query()->create([
            'code' => 'EXCHANGE_RATE_UPDATED',
            'name' => 'Exchange Rate Updated',
            'category' => 'general',
            'subject' => 'Exchange Rate Updated',
            'body' => "{currency_code} rates changed:\nBuy: {old_buy} → {new_buy} LYD\nSell: {old_sell} → {new_sell} LYD",
            'translations' => [
                'ar' => [
                    'subject' => 'تم تحديث سعر الصرف',
                    'body' => "تغيرت أسعار {currency_code}:\nشراء: {old_buy} ← {new_buy} د.ل\nبيع: {old_sell} ← {new_sell} د.ل",
                ],
            ],
            'channels' => ['push', 'in_app'],
            'variables' => ['currency_code', 'old_buy', 'new_buy', 'old_sell', 'new_sell'],
            'is_active' => true,
        ]);

        app(NotificationTemplateSyncService::class)->syncMissing();

        $template = NotificationTemplate::query()->where('code', 'EXCHANGE_RATE_UPDATED')->firstOrFail();

        $this->assertSame(
            'Buy and sell rates were updated. Open the app to view the latest prices.',
            (string) $template->body,
        );
        $this->assertSame(
            'تم تحديث أسعار الشراء والبيع. افتح التطبيق لعرض آخر الأسعار.',
            (string) data_get($template->translations, 'ar.body'),
        );
        $this->assertSame(
            'تم تحديث أسعار الصرف',
            $template->localizedSubject(NotificationLocales::AR),
        );
    }
}
