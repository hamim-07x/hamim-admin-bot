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
$amountRaw = (float)$w['amount'];
$amount = rtrim(rtrim(number_format($amountRaw, 6, '.', ''), '0'), '.');
if ($amount === '') {
    $amount = '0';
}
$address = (string)$w['address'];
$c = getSetting('currency_name', 'USDT');
$s = getSetting('currency_symbol', '$');
$network = getSetting('network', 'BEP20');
$token = trim((string)getSetting('bot_token', ''));

function normalizeChannelChat(string $channel): string
{
    $channel = trim($channel);
    if ($channel === '') {
        return '';
    }
    if (str_starts_with($channel, '@') || str_starts_with($channel, '-')) {
        return $channel;
    }
    if (preg_match('/^\d+$/', $channel)) {
        if (strlen($channel) < 14) {
            return '-100' . $channel;
        }
        return $channel;
    }
    return '@' . ltrim($channel, '@');
}

function channelPublicLink(string $channel): string
{
    $channel = trim($channel);
    if ($channel === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $channel)) {
        return $channel;
    }
    if (preg_match('/^-?\d+$/', $channel)) {
        return '';
    }
    return 'https://t.me/' . ltrim($channel, '@');
}

function emojiIdFor(string $key, string $default): string
{
    $id = '';
    try {
        $id = preg_replace('/\D+/', '', (string)getSetting($key, ''));
    } catch (Throwable $e) {
    }
    if ($id === '' || strlen($id) < 8) {
        $id = $default;
    }
    return $id;
}

function notifySendHtml(TelegramBot $bot, string|int $chatId, string $html, string $photoUrl = '', array $extra = []): bool
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
    $plain = preg_replace('/<tg-emoji\s+emoji-id="\d+">(.*?)<\/tg-emoji>/su', '$1', $html) ?? $html;
    if ($photoUrl !== '' && preg_match('#^https?://#i', $photoUrl)) {
        $res = $bot->sendPhoto($chatId, $photoUrl, $plain, $extra);
    } else {
        $res = $bot->sendMessage($chatId, $plain, $extra);
    }
    return (bool)($res && ($res['ok'] ?? false));
}

/**
 * Channel notify with native custom_emoji entities (same as private chat).
 * No username, no tx hash — User ID + Withdrawal # + Amount + Address + Network + Status Paid.
 */
function notifyChannelRich(TelegramBot $bot, string $chat, array $ctx, string $photoUrl, array $extra): bool
{
    $idOk   = emojiIdFor('ce_payout_ok', '5206607081334906820');
    $idBal  = emojiIdFor('currency_emoji_id', emojiIdFor('ce_balance', '5197434882321567830'));
    $idCard = emojiIdFor('ce_card', '5445353829304387411');
    $idNet  = emojiIdFor('ce_network', '5224450179368767019');
    $idRec  = emojiIdFor('ce_receipt', '5444856076954520455');
    $idUser = emojiIdFor('ce_ref_1', '5332724926216428039');

    $parts = [
        ['e' => $idOk, 'f' => '✅'],
        ['t' => ' '],
        ['b' => true, 't' => 'Payout successful'],
        ['t' => "\n\n"],
        ['e' => $idUser, 'f' => '👤'],
        ['t' => ' User ID: '],
        ['code' => true, 't' => (string)$ctx['userId']],
        ['t' => "\n"],
        ['e' => $idRec, 'f' => '🧾'],
        ['t' => ' Withdrawal: '],
        ['b' => true, 't' => '#' . $ctx['id']],
        ['t' => "\n"],
        ['e' => $idBal, 'f' => '💵'],
        ['t' => ' Amount: '],
        ['b' => true, 't' => $ctx['s'] . $ctx['amount'] . ' ' . $ctx['c']],
        ['t' => "\n"],
        ['e' => $idCard, 'f' => '💳'],
        ['t' => " Address:\n"],
        ['code' => true, 't' => $ctx['address']],
        ['t' => "\n"],
        ['e' => $idNet, 'f' => '🌐'],
        ['t' => ' Network: '],
        ['b' => true, 't' => $ctx['network']],
        ['t' => "\n"],
        ['e' => $idOk, 'f' => '✅'],
        ['t' => ' Status: '],
        ['b' => true, 't' => 'SUCCESSFULLY PAID'],
        ['t' => "\n\n"],
        ['e' => $idOk, 'f' => '✅'],
        ['t' => ' '],
        ['b' => true, 't' => 'Your withdrawal has been processed successfully.'],
    ];

    $res = $bot->sendRich($chat, $parts, $extra, $photoUrl);
    if ($res && ($res['ok'] ?? false)) {
        return true;
    }

    $html  = ce('ce_payout_ok') . " <b>Payout successful</b>\n\n";
    $html .= ce('ce_ref_1') . ' User ID: <code>' . $ctx['userId'] . "</code>\n";
    $html .= ce('ce_receipt') . ' Withdrawal: <b>#' . $ctx['id'] . "</b>\n";
    $html .= ce('ce_balance') . ' Amount: <b>' . $ctx['s'] . $ctx['amount'] . ' ' . $ctx['c'] . "</b>\n";
    $html .= ce('ce_card') . " Address:\n<code>" . htmlspecialchars($ctx['address']) . "</code>\n";
    $html .= ce('ce_network') . ' Network: <b>' . htmlspecialchars($ctx['network']) . "</b>\n";
    $html .= ce('ce_payout_ok') . " Status: <b>SUCCESSFULLY PAID</b>\n\n";
    $html .= ce('ce_payout_ok') . ' <b>Your withdrawal has been processed successfully.</b>';

    return notifySendHtml($bot, $chat, $html, $photoUrl, $extra);
}

if ($action === 'reject') {
    $db->prepare("UPDATE withdrawals SET status='rejected', processed_at=NOW() WHERE id=?")->execute([$id]);
    $db->prepare('UPDATE users SET balance = balance + ? WHERE id = ?')->execute([$amountRaw, $userId]);

    if ($token !== '' && getSetting('user_payout_alert', '1') === '1') {
        $bot = new TelegramBot($token);
        $msg = ce('ce_payout_no') . " <b>Payout rejected</b>\n\n";
        $msg .= ce('ce_balance') . " Amount: <b>{$s}{$amount} {$c}</b>\n";
        $msg .= 'Your balance has been refunded.';
        notifySendHtml($bot, $userId, $msg);
    }
    $_SESSION['flash'] = "Withdrawal #{$id} rejected (balance refunded).";
    header('Location: /admin/?page=withdrawals');
    exit;
}

$mode = getSetting('withdraw_mode', 'manual');
$newStatus = ($mode === 'auto') ? 'paid' : 'approved';
$db->prepare('UPDATE withdrawals SET status=?, processed_at=NOW() WHERE id=?')->execute([$newStatus, $id]);

if ($token === '') {
    $_SESSION['flash'] = "Withdrawal #{$id} marked {$newStatus}, but bot token is empty.";
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

$payChannelRaw = trim((string)getSetting('payment_channel', ''));
if ($payChannelRaw === '') {
    $payChannelRaw = trim((string)getSetting('notify_channel', ''));
}
$chat = normalizeChannelChat($payChannelRaw);
$channelLink = channelPublicLink($payChannelRaw);

$coreMsg  = ce('ce_payout_ok') . " <b>Payout successful</b>\n\n";
$coreMsg .= ce('ce_balance') . " Amount: <b>{$s}{$amount} {$c}</b>\n";
$coreMsg .= ce('ce_card') . " Address:\n<code>" . htmlspecialchars($address) . "</code>\n";
$coreMsg .= ce('ce_network') . " Network: <b>" . htmlspecialchars($network) . "</b>\n";
$coreMsg .= ce('ce_receipt') . " Status: <b>SUCCESSFULLY PAID</b>";

if (getSetting('user_payout_alert', '1') === '1') {
    $userExtra = [];
    if ($channelLink !== '') {
        $viewText = trim((string)getSetting('user_channel_btn_text', 'View Payment Channel'));
        if ($viewText === '') {
            $viewText = 'View Payment Channel';
        }
        $viewIcon = preg_replace('/\D+/', '', (string)getSetting('user_channel_btn_emoji_id', '5332455502917949981'));
        $btn = ['text' => $viewText, 'url' => $channelLink];
        if ($viewIcon !== '' && strlen($viewIcon) >= 8) {
            $btn['icon_custom_emoji_id'] = $viewIcon;
        }
        $userExtra['reply_markup'] = ['inline_keyboard' => [[$btn]]];
    }
    $userOk = notifySendHtml($bot, $userId, $coreMsg, $successPhoto, $userExtra);
    if (!$userOk) {
        $notes[] = 'user DM failed';
    }
}

if ($chat !== '') {
    $botUser = ltrim((string)getSetting('bot_username', ''), '@');
    $btnText = trim((string)getSetting('notify_btn_text', 'Start Bot'));
    if ($btnText === '') {
        $btnText = 'Start Bot';
    }
    $btnIcon = preg_replace('/\D+/', '', (string)getSetting('notify_btn_emoji_id', '5416041192905265756'));
    $extra = ['disable_web_page_preview' => true];
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

    $ctx = [
        'userId'  => $userId,
        's'       => $s,
        'amount'  => $amount,
        'c'       => $c,
        'address' => $address,
        'network' => $network,
        'id'      => $id,
    ];

    $channelOk = notifyChannelRich($bot, $chat, $ctx, $successPhoto, $extra);
    if (!$channelOk) {
        $notes[] = 'channel notify failed (bot admin? @username?)';
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
