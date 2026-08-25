<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Api\DTO\SyncBooknowOrderDTO;
use App\Modules\Api\Orders\Services\BooknowOrderSyncService;

require __DIR__.'/../../vendor/autoload.php';

if ($argc < 4) {
    fwrite(STDERR, "Usage: php run_booknow_sync_process.php <db_path> <user_id> <payload_base64>\n");
    exit(1);
}

$dbPath = (string) $argv[1];
$userId = (int) $argv[2];
$payload = json_decode(base64_decode((string) $argv[3], true) ?: '', true);

if (! is_array($payload)) {
    fwrite(STDERR, "Invalid payload.\n");
    exit(2);
}

putenv('APP_ENV=testing');
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE='.$dbPath);
$_ENV['APP_ENV'] = 'testing';
$_ENV['DB_CONNECTION'] = 'sqlite';
$_ENV['DB_DATABASE'] = $dbPath;
$_SERVER['APP_ENV'] = 'testing';
$_SERVER['DB_CONNECTION'] = 'sqlite';
$_SERVER['DB_DATABASE'] = $dbPath;

$app = require __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    /** @var User $user */
    $user = User::query()->findOrFail($userId);
    /** @var BooknowOrderSyncService $service */
    $service = app(BooknowOrderSyncService::class);

    $result = $service->upsert($user, SyncBooknowOrderDTO::fromArray($payload));
    echo json_encode([
        'ok' => true,
        'order_id' => $result['order']->id,
        'created' => $result['created'],
    ], JSON_THROW_ON_ERROR);
    exit(0);
} catch (Throwable $exception) {
    echo json_encode([
        'ok' => false,
        'error' => $exception::class,
        'message' => $exception->getMessage(),
    ], JSON_THROW_ON_ERROR);
    exit(0);
}
