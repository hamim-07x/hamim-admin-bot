<?php
/**
 * One-time setup: creates all tables + default admin
 * Visit: https://YOUR-APP.up.railway.app/setup.php
 * Also repairs admin password to admin / admin123 if broken.
 */
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/migrate.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $done = runMigrations();

    // Always ensure admin/admin123 works after setup
    $adminHash = '$2y$10$R.8RkWvI7k58MVrnttm3/O40peeTROQnPv4C0eT5/31DlU2loOqQe';
    $db = getDB();
    $row = $db->query("SELECT id, password_hash FROM admins WHERE username = 'admin' LIMIT 1")->fetch();
    if (!$row) {
        $stmt = $db->prepare('INSERT INTO admins (username, password_hash) VALUES (?, ?)');
        $stmt->execute(['admin', $adminHash]);
        $done[] = 'force_seed_admin';
    } elseif (!password_verify('admin123', (string)$row['password_hash'])) {
        $stmt = $db->prepare('UPDATE admins SET password_hash = ? WHERE id = ?');
        $stmt->execute([$adminHash, $row['id']]);
        $done[] = 'force_repair_admin';
    }

    echo json_encode([
        'ok' => true,
        'message' => 'Database ready',
        'created' => $done,
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
