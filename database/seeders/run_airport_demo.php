<?php

/**
 * PHP 8.2-compatible loader for local demo airports.
 * Used when `php artisan db:seed` is unavailable.
 */

$root = dirname(__DIR__, 2);
$envPath = $root.DIRECTORY_SEPARATOR.'.env';
$dataPath = __DIR__.DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR.'airports-demo.php';

if (! is_file($envPath) || ! is_file($dataPath)) {
    fwrite(STDERR, "Missing .env or airports-demo.php\n");
    exit(1);
}

$env = [];

foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
    if (! str_contains($line, '=') || str_starts_with(ltrim($line), '#')) {
        continue;
    }

    [$key, $value] = explode('=', $line, 2);
    $env[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
}

$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
    $env['DB_HOST'] ?? '127.0.0.1',
    $env['DB_PORT'] ?? '3306',
    $env['DB_DATABASE'] ?? 'cpbooke',
);

$pdo = new PDO($dsn, $env['DB_USERNAME'] ?? 'root', $env['DB_PASSWORD'] ?? '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$tableCheck = $pdo->query("SHOW TABLES LIKE 'booknow_airports'")->fetchColumn();

if (! $tableCheck) {
    fwrite(STDERR, "booknow_airports table does not exist. Run migrations first.\n");
    exit(1);
}

$airports = require $dataPath;
$insert = $pdo->prepare(
    'INSERT INTO booknow_airports (
        iata_code, icao_code, name_en, name_ar, name_fr, city_en, city_ar, city_fr,
        country_iso2, country_name_en, country_name_ar, country_name_fr, type, scheduled_service, translation_status
    ) VALUES (
        :iata_code, :icao_code, :name_en, :name_ar, :name_fr, :city_en, :city_ar, :city_fr,
        :country_iso2, :country_name_en, :country_name_ar, :country_name_fr, :type, :scheduled_service, :translation_status
    )',
);
$findByIata = $pdo->prepare('SELECT id FROM booknow_airports WHERE iata_code = :iata_code LIMIT 1');
$findByIcao = $pdo->prepare('SELECT id FROM booknow_airports WHERE icao_code = :icao_code LIMIT 1');
$update = $pdo->prepare(
    'UPDATE booknow_airports SET
        iata_code = :iata_code, icao_code = :icao_code, name_en = :name_en, name_ar = :name_ar, name_fr = :name_fr,
        city_en = :city_en, city_ar = :city_ar, city_fr = :city_fr, country_iso2 = :country_iso2,
        country_name_en = :country_name_en, country_name_ar = :country_name_ar, country_name_fr = :country_name_fr,
        type = :type, scheduled_service = :scheduled_service, translation_status = :translation_status
     WHERE id = :id',
);

$imported = 0;
$updated = 0;

$pdo->beginTransaction();

foreach ($airports as $airport) {
    $payload = [
        ':iata_code' => $airport['iata_code'] ?? null,
        ':icao_code' => $airport['icao_code'] ?? null,
        ':name_en' => $airport['name_en'],
        ':name_ar' => $airport['name_ar'] ?? null,
        ':name_fr' => $airport['name_fr'] ?? null,
        ':city_en' => $airport['city_en'] ?? null,
        ':city_ar' => $airport['city_ar'] ?? null,
        ':city_fr' => $airport['city_fr'] ?? null,
        ':country_iso2' => $airport['country_iso2'] ?? null,
        ':country_name_en' => $airport['country_name_en'] ?? null,
        ':country_name_ar' => $airport['country_name_ar'] ?? null,
        ':country_name_fr' => $airport['country_name_fr'] ?? null,
        ':type' => $airport['type'] ?? null,
        ':scheduled_service' => $airport['scheduled_service'] ?? null,
        ':translation_status' => 'complete',
    ];

    $existingId = null;

    if (! empty($airport['iata_code'])) {
        $findByIata->execute([':iata_code' => $airport['iata_code']]);
        $existingId = $findByIata->fetchColumn() ?: null;
    } elseif (! empty($airport['icao_code'])) {
        $findByIcao->execute([':icao_code' => $airport['icao_code']]);
        $existingId = $findByIcao->fetchColumn() ?: null;
    }

    if ($existingId) {
        $payload[':id'] = $existingId;
        $update->execute($payload);
        $updated++;
        continue;
    }

    $insert->execute($payload);
    $imported++;
}

$pdo->commit();

$count = (int) $pdo->query('SELECT COUNT(*) FROM booknow_airports')->fetchColumn();
$countries = (int) $pdo->query('SELECT COUNT(DISTINCT country_iso2) FROM booknow_airports WHERE country_iso2 IS NOT NULL AND country_iso2 <> ""')->fetchColumn();

echo "Inserted: {$imported}\nUpdated: {$updated}\nTotal airports: {$count}\nCountries: {$countries}\n";
