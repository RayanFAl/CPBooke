<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$s = app(App\Modules\Settings\Services\SystemSettingsService::class);

echo 'mailer='.config('mail.default').PHP_EOL;
echo 'host='.config('mail.mailers.smtp.host').PHP_EOL;
echo 'port='.config('mail.mailers.smtp.port').PHP_EOL;
echo 'scheme='.var_export(config('mail.mailers.smtp.scheme'), true).PHP_EOL;
echo 'user='.(config('mail.mailers.smtp.username') ?: '(empty)').PHP_EOL;
echo 'pass_set='.(filled(config('mail.mailers.smtp.password')) ? 'yes' : 'no').PHP_EOL;
echo 'db_mailer='.($s->current()->mail_mailer ?: '(empty)').PHP_EOL;
echo 'db_host='.($s->current()->mail_host ?: '(empty)').PHP_EOL;
echo 'delivery_configured='.($s->isMailDeliveryConfigured() ? 'yes' : 'no').PHP_EOL;
