<?php
declare(strict_types=1);

$dbDirectory = dirname(__DIR__) . '/storage';
$dbFile = $dbDirectory . '/labtrace.db';

if (!is_dir($dbDirectory) && !mkdir($dbDirectory, 0775, true) && !is_dir($dbDirectory)) {
    throw new RuntimeException('Could not create the storage directory. Check folder permissions.');
}

try {
    $pdo = new PDO('sqlite:' . $dbFile, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA busy_timeout = 5000');

    // First run: create and seed the local database automatically.
    $initialized = (int)$pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='departments'")->fetchColumn() > 0;
    if (!$initialized) {
        $schema = file_get_contents(dirname(__DIR__) . '/database/schema.sql');
        $seed = file_get_contents(dirname(__DIR__) . '/database/seed.sql');
        if ($schema === false || $seed === false) {
            throw new RuntimeException('Could not read the database setup scripts.');
        }
        $pdo->exec($schema);
        $pdo->exec($seed);
    }
} catch (Throwable $e) {
    http_response_code(500);
    exit('Database setup failed. Make sure PHP has the PDO SQLite extension enabled. Details: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}
