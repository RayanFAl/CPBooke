<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$host = (string) config('mail.mailers.smtp.host', 'mail.booke.ly');
$port = (int) config('mail.mailers.smtp.port', 587);
$user = (string) config('mail.mailers.smtp.username', '');
$passSet = filled(config('mail.mailers.smtp.password'));

echo "host={$host}:{$port}".PHP_EOL;
echo 'username='.($user !== '' ? $user : '(empty)').PHP_EOL;
echo 'password_set='.($passSet ? 'yes' : 'no').PHP_EOL;

$errno = 0;
$errstr = '';
$socket = @stream_socket_client(
    "tcp://{$host}:{$port}",
    $errno,
    $errstr,
    15,
    STREAM_CLIENT_CONNECT,
);

if ($socket === false) {
    echo 'tcp=FAIL'.PHP_EOL;
    echo "tcp_error={$errstr} ({$errno})".PHP_EOL;
    echo 'verdict=Cannot reach SMTP from this machine (network/firewall). Password was not tested.'.PHP_EOL;
    exit(1);
}

fclose($socket);
echo 'tcp=OK'.PHP_EOL;

if (! $passSet) {
    echo 'verdict=Port open but MAIL_PASSWORD empty in .env — set password then retry.'.PHP_EOL;
    exit(2);
}

try {
    Illuminate\Support\Facades\Mail::raw('CPBooke SMTP probe '.date('c'), static function ($message): void {
        $message->to($message->from[0]['address'] ?? config('mail.from.address'))
            ->subject('CPBooke SMTP probe');
    });
    echo 'send=OK'.PHP_EOL;
    echo 'verdict=SMTP send succeeded from this environment.'.PHP_EOL;
    exit(0);
} catch (Throwable $e) {
    echo 'send=FAIL'.PHP_EOL;
    echo 'send_error='.$e->getMessage().PHP_EOL;
    echo 'verdict=TCP works but SMTP auth/send failed (check user/password/TLS).'.PHP_EOL;
    exit(3);
}
