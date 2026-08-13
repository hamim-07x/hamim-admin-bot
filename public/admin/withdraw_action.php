<?php
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../bot/TelegramBot.php';
requireAdmin();

$id = (int)($_POST['id'] ?? 0);
$action = $_POST['action'] ?? '';
if ($id <= 0 || !in_array($action, ['approve', 'reject'], true)) {
    $_SESSION['flash'] = 'Invalid request';
    header('Location: /admin/?page=withdrawals');
    exit;
}

$db = getDB();
$stmt = $db->prepare('SELECT * FROM withdrawals WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$w = $stmt->fetch();
if (!$w) {
    $_SESSION['flash'] = 'Withdrawal not found';
    header('Location: /admin/?page=withdrawals');
    exit;
}
if (($w['status'] ?? '') !== 'pending') {
    $_SESSION['flash'] = 'Already processed';
    header('Location: /admin/?page=withdrawals');
    exit;
}

$userId = (int)$w['user_id'];
$amount = (string)$w['amount'];
$address = (string)$w['address'];
$c = getSetting('currency_name', 'USDT');
$token = getSetting('bot_token', '');

if ($action === 'reject') {
    $db->prepare("UPDATE withdrawals SET status='rejected', processed_at=NOW() WHERE id=?")->execute([$id]);
    // refund balance
    $db->prepare('UPDATE users SET balance = balance + ? WHERE id = ?')->execute([(float)$amount, $userId]);
    $db->prepare("UPDATE transactions SET status='rejected' WHERE user_id=? AND type='withdraw' AND status='pending' ORDER BY id DESC LIMIT 1")
        ->execute([$userId]);

    if ($token && getSetting('user_payout_alert', '1') === '1') {
        $bot = new TelegramBot($token);
        $bot->sendMessage($userId, "❌ <b>Payout rejected</b>\n\nAmount: <b>{$amount} {$c}</b>\nYour balance was refunded.");
    }
    $_SESSION['flash'] = "Withdrawal #{$id} rejected (balance refunded).";
    header('Location: /admin/?page=withdrawals');
    exit;
}

// APPROVE
$mode = getSetting('withdraw_mode', 'manual');
$newStatus = ($mode === 'auto') ? 'paid' : 'approved';
$db->prepare('UPDATE withdrawals SET status=?, processed_at=NOW() WHERE id=?')->execute([$newStatus, $id]);
$db->prepare("UPDATE transactions SET status='completed' WHERE user_id=? AND type='withdraw' AND status='pending' ORDER BY id DESC LIMIT 1")
    ->execute([$userId]);

// Notify user
if ($token && getSetting('user_payout_alert', '1') === '1') {
    $bot = new TelegramBot($token);
    $msg = "✅ <b>Payout successful</b>\n\n";
    $msg .= "Amount: <b>{$amount} {$c}</b>\n";
    $msg .= "Address: <code>" . htmlspecialchars($address) . "</code>\n";
    $msg .= "Network: <b>" . htmlspecialchars(getSetting('network', 'BEP20')) . "</b>\n";
    $msg .= "Status: <b>" . strtoupper($newStatus) . "</b>";
    $bot->sendMessage($userId, $msg);
}

// Notify payment channel
$channel = trim((string)getSetting('payment_channel', ''));
if ($channel === '') {
    $channel = trim((string)getSetting('notify_channel', ''));
}
if ($token && $channel !== '') {
    $bot = $bot ?? new TelegramBot($token);
    $chat = str_starts_with($channel, '@') || str_starts_with($channel, '-') ? $channel : '@' . ltrim($channel, '@');
    $chMsg = "💸 <b>Payout {$newStatus}</b>\n\n";
    $chMsg .= "User: <code>{$userId}</code>\n";
    $chMsg .= "Amount: <b>{$amount} {$c}</b>\n";
    $chMsg .= "Address: <code>" . htmlspecialchars($address) . "</code>\n";
    $chMsg .= "ID: #{$id}";
    $res = $bot->sendMessage($chat, $chMsg);
    if (!$res || !($res['ok'] ?? false)) {
        $_SESSION['flash'] = "Withdrawal #{$id} {$newStatus}, but channel notify failed (is bot admin in channel?).";
        header('Location: /admin/?page=withdrawals');
        exit;
    }
}

$_SESSION['flash'] = "Withdrawal #{$id} marked {$newStatus}. User notified.";
header('Location: /admin/?page=withdrawals');
exit;
