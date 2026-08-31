<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$to = $argv[1] ?? 'test@example.com';

try {
    Illuminate\Support\Facades\Mail::raw('Booke SMTP connectivity test', function ($message) use ($to) {
        $message->to($to)->subject('Booke SMTP test');
    });
    echo "OK: message handed to mailer for {$to}\n";
} catch (Throwable $e) {
    echo 'FAIL: '.$e->getMessage()."\n";
    exit(1);
}
