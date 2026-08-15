<?php
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../bot/TelegramBot.php';
require_once __DIR__ . '/../../bot/helpers.php';
require_once __DIR__ . '/../../bot/PayoutService.php';
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
$amountRaw = (float)$w['amount'];
$amount = rtrim(rtrim(number_format($amountRaw, 6, '.', ''), '0'), '.');
if ($amount === '') {
    $amount = '0';
}
$c = getSetting('currency_name', 'USDT');
$s = getSetting('currency_symbol', '$');
$token = trim((string)getSetting('bot_token', ''));

if ($action === 'reject') {
    $db->prepare("UPDATE withdrawals SET status='rejected', processed_at=NOW() WHERE id=?")->execute([$id]);
    $db->prepare('UPDATE users SET balance = balance + ? WHERE id = ?')->execute([$amountRaw, $userId]);

    if ($token !== '' && getSetting('user_payout_alert', '1') === '1') {
        $bot = new TelegramBot($token);
        $msg = ce('ce_payout_no') . " <b>Payout rejected</b>\n\n";
        $msg .= ce('ce_balance') . " Amount: <b>{$s}{$amount} {$c}</b>\n";
        $msg .= 'Your balance has been refunded.';
        $bot->sendMessage($userId, $msg, ['parse_mode' => 'HTML']);
    }
    $_SESSION['flash'] = "Withdrawal #{$id} rejected (balance refunded).";
    header('Location: /admin/?page=withdrawals');
    exit;
}

$result = PayoutService::completeWithdrawal($id, null, [
    'notify_user' => true,
    'notify_channel' => true,
    'from_admin' => true,
]);
if (!empty($result['ok'])) {
    $_SESSION['flash'] = "Withdrawal #{$id} PAID on-chain. Status: " . ($result['status'] ?? 'paid');
    if (!empty($result['tx'])) {
        $_SESSION['flash'] .= ' | TX: ' . $result['tx'];
    }
} else {
    $_SESSION['flash'] = "Withdrawal #{$id} FAILED (not marked paid): " . ($result['error'] ?? 'unknown')
        . ' — Fix wallet settings / gas / balance then Approve again.';
}
header('Location: /admin/?page=withdrawals');
exit;
