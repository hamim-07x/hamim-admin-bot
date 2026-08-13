<?php
/**
 * Setup + repair admin password
 * Visit once: /setup.php
 */
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/migrate.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $done = runMigrations();
    $db = getDB();

    // Always re-hash admin123 at runtime (never trust static broken hashes)
    $hash = password_hash('admin123', PASSWORD_DEFAULT);
    $row = $db->query("SELECT id FROM admins WHERE username = 'admin' LIMIT 1")->fetch();
    if ($row) {
        $db->prepare('UPDATE admins SET password_hash = ? WHERE id = ?')->execute([$hash, $row['id']]);
        $done[] = 'admin_password_reset';
    } else {
        $db->prepare('INSERT INTO admins (username, password_hash) VALUES (?, ?)')->execute(['admin', $hash]);
        $done[] = 'admin_created';
    }

    // Verify it works
    $check = $db->query("SELECT password_hash FROM admins WHERE username = 'admin' LIMIT 1")->fetch();
    $verifyOk = $check && password_verify('admin123', (string)$check['password_hash']);

    echo json_encode([
        'ok' => true,
        'message' => 'Database ready',
        'created' => $done,
        'password_verify_admin123' => $verifyOk ? 'OK' : 'FAIL',
        'admin' => 'admin / admin123',
        'next' => 'Open /admin/login.php and login',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
    ], JSON_PRETTY_PRINT);
}
