<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use App\Mail\SecurityAlertMail;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Mail;

$to = $argv[1] ?? null;
if (! is_string($to) || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Usage: php scripts/send-security-mail-preview.php you@example.com\n");
    exit(1);
}

echo 'mailer='.config('mail.default')."\n";
echo 'host='.config('mail.mailers.smtp.host')."\n";
echo 'from='.config('mail.from.address')."\n";
echo "to={$to}\n";

try {
    Mail::to($to)->send(new SecurityAlertMail(
        variant: SecurityAlertMail::VARIANT_LOGIN,
        recipientName: 'أحمد',
        mailLocale: 'ar',
        meta: [
            'device_name' => 'iPhone 15 Pro · iOS 18',
            'ip' => '41.252.88.14',
            'location' => 'طرابلس، ليبيا',
            'occurred_at' => '21 سبتمبر 2026 · 09:40 ص',
            'timezone_label' => 'بتوقيت طرابلس (GMT+2)',
            'cta_url' => rtrim((string) config('app.url'), '/').'/profile',
        ],
    ));
    echo "OK: SecurityAlertMail sent to {$to}\n";
} catch (Throwable $e) {
    echo 'FAIL: '.$e->getMessage()."\n";
    exit(1);
}
