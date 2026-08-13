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
$token = trim((string)getSetting('bot_token', ''));

$uStmt = $db->prepare('SELECT username, first_name FROM users WHERE id = ?');
$uStmt->execute([$userId]);
$uRow = $uStmt->fetch() ?: [];
$displayName = trim((string)($uRow['first_name'] ?? ''));
if ($displayName === '') {
    $displayName = 'User';
}
$handle = !empty($uRow['username']) ? '@' . $uRow['username'] : (string)$userId;

/** Normalize channel chat id for Telegram API */
function normalizeChannelChat(string $channel): string
{
    $channel = trim($channel);
    if ($channel === '') {
        return '';
    }
    // already @username or -100... id
    if (str_starts_with($channel, '@') || str_starts_with($channel, '-')) {
        return $channel;
    }
    // pure numeric public channel id often needs -100 prefix for supergroups
    if (preg_match('/^\d+$/', $channel)) {
        // if looks like short id, try -100 prefix (common for channels)
        if (strlen($channel) < 14) {
            return '-100' . $channel;
        }
        return $channel;
    }
    // username without @
    return '@' . ltrim($channel, '@');
}

/** Send HTML message/photo; if custom emoji fails, retry plain */
function notifySend(TelegramBot $bot, string|int $chatId, string $html, string $photoUrl = '', array $extra = []): bool
{
    $extra['parse_mode'] = 'HTML';
    $extra['disable_web_page_preview'] = true;

    $res = null;
    if ($photoUrl !== '' && preg_match('#^https?://#i', $photoUrl)) {
        $res = $bot->sendPhoto($chatId, $photoUrl, $html, $extra);
    } else {
        $res = $bot->sendMessage($chatId, $html, $extra);
    }
    if ($res && ($res['ok'] ?? false)) {
        return true;
    }

    // Retry without <tg-emoji> (channels often reject unknown custom emoji)
    $plain = preg_replace('/<tg-emoji\s+emoji-id="\d+">(.*?)<\/tg-emoji>/su', '$1', $html) ?? $html;
    if ($photoUrl !== '' && preg_match('#^https?://#i', $photoUrl)) {
        $res = $bot->sendPhoto($chatId, $photoUrl, $plain, $extra);
    } else {
        $res = $bot->sendMessage($chatId, $plain, $extra);
    }
    return (bool)($res && ($res['ok'] ?? false));
}

if ($action === 'reject') {
    $db->prepare("UPDATE withdrawals SET status='rejected', processed_at=NOW() WHERE id=?")->execute([$id]);
    $db->prepare('UPDATE users SET balance = balance + ? WHERE id = ?')->execute([(float)$amount, $userId]);

    if ($token !== '' && getSetting('user_payout_alert', '1') === '1') {
        $bot = new TelegramBot($token);
        $msg = "❌ <b>Payout rejected</b>\n\n";
        $msg .= "💵 Amount: <b>{$s}{$amount} {$c}</b>\n";
        $msg .= 'Your balance has been refunded.';
        notifySend($bot, $userId, $msg);
    }
    $_SESSION['flash'] = "Withdrawal #{$id} rejected (balance refunded).";
    header('Location: /admin/?page=withdrawals');
    exit;
}

// APPROVE
$mode = getSetting('withdraw_mode', 'manual');
$newStatus = ($mode === 'auto') ? 'paid' : 'approved';
$db->prepare('UPDATE withdrawals SET status=?, processed_at=NOW() WHERE id=?')->execute([$newStatus, $id]);

if ($token === '') {
    $_SESSION['flash'] = "Withdrawal #{$id} marked {$newStatus}, but bot token is empty — no notifications.";
    header('Location: /admin/?page=withdrawals');
    exit;
}

$bot = new TelegramBot($token);
$userOk = true;
$channelOk = true;
$notes = [];

$successPhoto = '';
if (getSetting('img_payout_success_on', '0') === '1') {
    $successPhoto = trim((string)getSetting('img_payout_success', ''));
}

// ========== 1) USER private chat ONLY (never the payment channel) ==========
if (getSetting('user_payout_alert', '1') === '1') {
    // Prefer custom emoji for private chat (works better than in channels)
    $msg  = ce('ce_payout_ok') . " <b>Payout successful</b>\n\n";
    $msg .= ce('ce_balance') . " Amount: <b>{$s}{$amount} {$c}</b>\n";
    $msg .= ce('ce_card') . " Address:\n<code>" . htmlspecialchars($address) . "</code>\n";
    $msg .= ce('ce_network') . " Network: <b>" . htmlspecialchars($network) . "</b>\n";
    $msg .= ce('ce_receipt') . ' Status: <b>' . strtoupper($newStatus) . '</b>';

    $userOk = notifySend($bot, $userId, $msg, $successPhoto);
    if (!$userOk) {
        $notes[] = 'user DM failed';
    }
}

// ========== 2) PAYMENT CHANNEL only (notify) ==========
$channel = trim((string)getSetting('payment_channel', ''));
if ($channel === '') {
    $channel = trim((string)getSetting('notify_channel', ''));
}
$chat = normalizeChannelChat($channel);

if ($chat !== '') {
    // Channel posts: use unicode emoji first (custom emoji often blocked in channels)
    // Keep structure same as user message style
    $chMsg  = "✅ <b>Payout " . strtoupper($newStatus) . "</b>\n\n";
    $chMsg .= "👤 User: <b>" . htmlspecialchars($displayName) . "</b> (" . htmlspecialchars($handle) . ")\n";
    $chMsg .= "🆔 ID: <code>{$userId}</code>\n";
    $chMsg .= "💵 Amount: <b>{$s}{$amount} {$c}</b>\n";
    $chMsg .= "💳 Address: <code>" . htmlspecialchars($address) . "</code>\n";
    $chMsg .= "🧾 Withdrawal: <b>#{$id}</b>";

    // Optional: also try premium version if admin wants (second attempt path inside notifySend)
    $chMsgPremium  = ce('ce_payout_ok') . " <b>Payout " . strtoupper($newStatus) . "</b>\n\n";
    $chMsgPremium .= ce('ce_ref_1') . " User: <b>" . htmlspecialchars($displayName) . "</b> (" . htmlspecialchars($handle) . ")\n";
    $chMsgPremium .= "ID: <code>{$userId}</code>\n";
    $chMsgPremium .= ce('ce_balance') . " Amount: <b>{$s}{$amount} {$c}</b>\n";
    $chMsgPremium .= ce('ce_card') . " Address: <code>" . htmlspecialchars($address) . "</code>\n";
    $chMsgPremium .= ce('ce_receipt') . " Withdrawal: <b>#{$id}</b>";

    // Start Bot inline button (premium icon is more reliable than text custom emoji)
    $botUser = ltrim((string)getSetting('bot_username', ''), '@');
    $btnText = trim((string)getSetting('notify_btn_text', 'Start Bot'));
    if ($btnText === '') {
        $btnText = 'Start Bot';
    }
    $btnIcon = preg_replace('/\D+/', '', (string)getSetting('notify_btn_emoji_id', '5416041192905265756'));
    $extra = [];
    if ($botUser !== '') {
        $btn = [
            'text' => $btnText,
            'url'  => 'https://t.me/' . $botUser . '?start=1',
        ];
        if ($btnIcon !== '' && strlen($btnIcon) >= 8) {
            $btn['icon_custom_emoji_id'] = $btnIcon;
        }
        $extra['reply_markup'] = ['inline_keyboard' => [[$btn]]];
    }

    // Try premium text first, notifySend falls back to plain automatically
    $channelOk = notifySend($bot, $chat, $chMsgPremium, $successPhoto, $extra);
    if (!$channelOk) {
        // last resort without photo / without icon
        $channelOk = notifySend($bot, $chat, $chMsg, '', $extra);
    }
    if (!$channelOk) {
        $notes[] = 'channel notify failed (bot must be admin; check @username)';
    }
}

$flash = "Withdrawal #{$id} marked {$newStatus}.";
if ($userOk && (empty($chat) || $channelOk)) {
    $flash .= ' Notifications sent.';
} elseif ($notes) {
    $flash .= ' Issues: ' . implode('; ', $notes);
}
$_SESSION['flash'] = $flash;
header('Location: /admin/?page=withdrawals');
exit;
