<?php
/**
 * One-time setup: creates all tables + default admin
 * Visit: https://YOUR-APP.up.railway.app/setup.php
 */
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/migrate.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $done = runMigrations();
    echo json_encode([
        'ok' => true,
        'message' => 'Database ready',
        'created' => $done,
        'admin' => 'admin / admin123',
        'next' => 'Open /admin/ and login',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
    ], JSON_PRETTY_PRINT);
}
