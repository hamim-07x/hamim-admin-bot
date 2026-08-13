<?php
require_once __DIR__ . '/../../config/bootstrap.php';
requireAdmin();
$id = (int)($_GET['id'] ?? 0);
if ($id > 0) {
    getDB()->prepare('DELETE FROM channels WHERE id = ?')->execute([$id]);
    $_SESSION['flash'] = 'Channel deleted.';
}
header('Location: /admin/?page=channels');
exit;
