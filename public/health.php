<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/bootstrap.php';
try {
    $db = getDB();
    $db->query('SELECT 1');
    echo json_encode(['ok' => true, 'db' => 'ok']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
