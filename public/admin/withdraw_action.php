<?php
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../bot/TelegramBot.php';
require_once __DIR__ . '/../../bot/helpers.php';
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
$s = getSetting('currency_symbol', '$');
$network = getSetting('network', 'BEP20');
$token = getSetting('bot_token', '');

// username for display
$uStmt = $db->prepare('SELECT username, first_name FROM users WHERE id = ?');
$uStmt->execute([$userId]);
$uRow = $uStmt->fetch() ?: [];
$displayName = trim((string)($uRow['first_name'] ?? ''));
if ($displayName === '') {
    $displayName = 'User';
}
$handle = !empty($uRow['username']) ? '@' . $uRow['username'] : (string)$userId;

if ($action === 'reject') {
    $db->prepare("UPDATE withdrawals SET status='rejected', processed_at=NOW() WHERE id=?")->execute([$id]);
    $db->prepare('UPDATE users SET balance = balance + ? WHERE id = ?')->execute([(float)$amount, $userId]);

    if ($token && getSetting('user_payout_alert', '1') === '1') {
        $bot = new TelegramBot($token);
        $msg = ce('ce_payout_no') . " <b>Payout rejected</b>\n\n";
        $msg .= ce('ce_balance') . " Amount: <b>{$s}{$amount} {$c}</b>\n";
        $msg .= 'Your balance has been refunded.';
        $bot->sendMessage($userId, $msg);
    }
    $_SESSION['flash'] = "Withdrawal #{$id} rejected (balance refunded).";
    header('Location: /admin/?page=withdrawals');
    exit;
}

// APPROVE
$mode = getSetting('withdraw_mode', 'manual');
$newStatus = ($mode === 'auto') ? 'paid' : 'approved';
$db->prepare('UPDATE withdrawals SET status=?, processed_at=NOW() WHERE id=?')->execute([$newStatus, $id]);

$bot = $token ? new TelegramBot($token) : null;
$channelOk = true;

// --- User notification (English) + optional success image ---
if ($bot && getSetting('user_payout_alert', '1') === '1') {
    $msg  = ce('ce_payout_ok') . " <b>Payout successful</b>\n\n";
    $msg .= ce('ce_balance') . " Amount: <b>{$s}{$amount} {$c}</b>\n";
    $msg .= ce('ce_card') . " Address:\n<code>" . htmlspecialchars($address) . "</code>\n";
    $msg .= ce('ce_network') . " Network: <b>" . htmlspecialchars($network) . "</b>\n";
    $msg .= ce('ce_receipt') . ' Status: <b>' . strtoupper($newStatus) . '</b>';

    $photo = '';
    if (getSetting('img_payout_success_on', '0') === '1') {
        $photo = trim((string)getSetting('img_payout_success', ''));
    }
    if ($photo !== '' && preg_match('#^https?://#i', $photo)) {
        $bot->sendPhoto($userId, $photo, $msg);
    } else {
        $bot->sendMessage($userId, $msg);
    }
}

// --- Payment channel notification (notify only — NOT join checklist) ---
$channel = trim((string)getSetting('payment_channel', ''));
if ($channel === '') {
    $channel = trim((string)getSetting('notify_channel', ''));
}
if ($bot && $channel !== '') {
    $chat = (str_starts_with($channel, '@') || str_starts_with($channel, '-'))
        ? $channel
        : '@' . ltrim($channel, '@');

    $chMsg  = ce('ce_payout_ok') . " <b>Payout " . strtoupper($newStatus) . "</b>\n\n";
    $chMsg .= ce('ce_ref_1') . " User: <b>" . htmlspecialchars($displayName) . "</b> (" . htmlspecialchars($handle) . ")\n";
    $chMsg .= "ID: <code>{$userId}</code>\n";
    $chMsg .= ce('ce_balance') . " Amount: <b>{$s}{$amount} {$c}</b>\n";
    $chMsg .= ce('ce_card') . " Address: <code>" . htmlspecialchars($address) . "</code>\n";
    $chMsg .= ce('ce_receipt') . " Withdrawal: <b>#{$id}</b>";

    // Inline Start Bot button (editable text + premium icon)
    $botUser = ltrim((string)getSetting('bot_username', ''), '@');
    $btnText = trim((string)getSetting('notify_btn_text', 'Start Bot'));
    if ($btnText === '') {
        $btnText = 'Start Bot';
    }
    $btnIcon = preg_replace('/\D+/', '', (string)getSetting('notify_btn_emoji_id', '5416041192905265756'));
    $rows = [];
    if ($botUser !== '') {
        $btn = [
            'text' => $btnText,
            'url'  => 'https://t.me/' . $botUser . '?start=1',
        ];
        if ($btnIcon !== '' && strlen($btnIcon) >= 8) {
            $btn['icon_custom_emoji_id'] = $btnIcon;
        }
        $rows[] = [$btn];
    }
    $extra = $rows ? ['reply_markup' => ['inline_keyboard' => $rows]] : [];

    $photo = '';
    if (getSetting('img_payout_success_on', '0') === '1') {
        $photo = trim((string)getSetting('img_payout_success', ''));
    }
    if ($photo !== '' && preg_match('#^https?://#i', $photo)) {
        $res = $bot->sendPhoto($chat, $photo, $chMsg, $extra);
    } else {
        $res = $bot->sendMessage($chat, $chMsg, $extra);
    }
    if (!$res || !($res['ok'] ?? false)) {
        $channelOk = false;
    }
}

if (!$channelOk) {
    $_SESSION['flash'] = "Withdrawal #{$id} marked {$newStatus}. Channel notify failed (make bot admin in payment channel).";
} else {
    $_SESSION['flash'] = "Withdrawal #{$id} marked {$newStatus}. Notifications sent.";
}
header('Location: /admin/?page=withdrawals');
exit;
