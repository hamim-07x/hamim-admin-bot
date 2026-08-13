<?php
require_once __DIR__ . '/../../config/bootstrap.php';
requireAdmin();
$action = $_POST['action'] ?? '';
$userId = (int)($_POST['user_id'] ?? 0);
$amount = (float)($_POST['amount'] ?? 0);
if ($userId <= 0) { $_SESSION['flash'] = 'Invalid user'; header('Location: /admin/?page=users'); exit; }
$db = getDB();
$stmt = $db->prepare('SELECT id, balance FROM users WHERE id = ?');
$stmt->execute([$userId]);
if (!$stmt->fetch()) { $_SESSION['flash'] = 'User not found'; header('Location: /admin/?page=users'); exit; }
try {
    if ($action === 'add_balance' && $amount > 0) {
        $db->prepare('UPDATE users SET balance = balance + ? WHERE id = ?')->execute([$amount, $userId]);
        $db->prepare('INSERT INTO transactions (user_id, type, amount, status, note) VALUES (?,?,?,?,?)')
            ->execute([$userId, 'admin_add', $amount, 'completed', 'Admin add']);
        $_SESSION['flash'] = "Added {$amount}";
    } elseif ($action === 'cut_balance' && $amount > 0) {
        $db->prepare('UPDATE users SET balance = GREATEST(0, balance - ?) WHERE id = ?')->execute([$amount, $userId]);
        $db->prepare('INSERT INTO transactions (user_id, type, amount, status, note) VALUES (?,?,?,?,?)')
            ->execute([$userId, 'admin_cut', $amount, 'completed', 'Admin cut']);
        $_SESSION['flash'] = "Cut {$amount}";
    } elseif ($action === 'set_balance' && $amount >= 0) {
        $db->prepare('UPDATE users SET balance = ? WHERE id = ?')->execute([$amount, $userId]);
        $_SESSION['flash'] = "Set balance {$amount}";
    } elseif ($action === 'block') {
        $db->prepare('UPDATE users SET is_blocked = 1 WHERE id = ?')->execute([$userId]);
        $_SESSION['flash'] = 'Blocked';
    } elseif ($action === 'unblock') {
        $db->prepare('UPDATE users SET is_blocked = 0 WHERE id = ?')->execute([$userId]);
        $_SESSION['flash'] = 'Unblocked';
    } else {
        $_SESSION['flash'] = 'Unknown action';
    }
} catch (Throwable $e) {
    $_SESSION['flash'] = 'Error: ' . $e->getMessage();
}
header('Location: /admin/?page=users');
exit;
