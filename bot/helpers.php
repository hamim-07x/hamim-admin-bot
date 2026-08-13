<?php
/* Message helpers: photo + auto-delete + custom emoji */

function botSend(TelegramBot $bot, int $chatId, int $userId, string $text, string $imgKey = '', array $extra = []): void
{
    deleteOldBotMessages($bot, $chatId, $userId);
    $photo = '';
    if ($imgKey !== '' && getSetting($imgKey . '_on', '0') === '1') {
        $photo = trim((string)getSetting($imgKey, ''));
    }

    // Always force HTML so <tg-emoji> works
    $extra['parse_mode'] = 'HTML';

    $res = null;
    if ($photo !== '' && preg_match('#^https?://#i', $photo)) {
        $res = $bot->sendPhoto($chatId, $photo, $text, $extra);
    } else {
        $res = $bot->sendMessage($chatId, $text, $extra);
    }

    // If Telegram rejects custom emoji / HTML, retry once without tg-emoji tags
    if (!$res || !($res['ok'] ?? false)) {
        $plain = stripTgEmoji($text);
        if ($plain !== $text) {
            if ($photo !== '' && preg_match('#^https?://#i', $photo)) {
                $res = $bot->sendPhoto($chatId, $photo, $plain, $extra);
            } else {
                $res = $bot->sendMessage($chatId, $plain, $extra);
            }
        }
    }

    $mid = $res['result']['message_id'] ?? null;
    if ($mid) {
        pushBotMessageId($userId, (int)$mid);
    }
}

/** Remove <tg-emoji> wrappers, keep inner fallback character */
function stripTgEmoji(string $html): string
{
    return preg_replace('/<tg-emoji\s+emoji-id="\d+">(.*?)<\/tg-emoji>/su', '$1', $html) ?? $html;
}

function deleteOldBotMessages(TelegramBot $bot, int $chatId, int $userId): void
{
    foreach (getBotMessageIds($userId) as $mid) {
        try { $bot->deleteMessage($chatId, (int)$mid); } catch (Throwable $e) {}
    }
    clearBotMessageIds($userId);
}

function ensureMsgColumns(): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        $db = getDB();
        if (!$db->query("SHOW COLUMNS FROM users LIKE 'bot_msgs'")->fetch()) {
            $db->exec("ALTER TABLE users ADD COLUMN bot_msgs TEXT DEFAULT NULL");
        }
    } catch (Throwable $e) {}
}

function getBotMessageIds(int $userId): array
{
    ensureMsgColumns();
    $stmt = getDB()->prepare('SELECT bot_msgs FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $d = json_decode((string)($stmt->fetch()['bot_msgs'] ?? ''), true);
    return is_array($d) ? $d : [];
}

function pushBotMessageId(int $userId, int $messageId): void
{
    ensureMsgColumns();
    $ids = getBotMessageIds($userId);
    $ids[] = $messageId;
    if (count($ids) > 3) $ids = array_slice($ids, -3);
    getDB()->prepare('UPDATE users SET bot_msgs = ? WHERE id = ?')->execute([json_encode($ids), $userId]);
}

function clearBotMessageIds(int $userId): void
{
    ensureMsgColumns();
    getDB()->prepare('UPDATE users SET bot_msgs = NULL WHERE id = ?')->execute([$userId]);
}

/**
 * Custom emoji HTML tag for Telegram.
 * Inner text MUST be a real emoji character (Telegram requirement) — used as fallback on old clients.
 *
 * IMPORTANT: Bot must be owned by a Telegram Premium account for custom emoji to render.
 */
function ce(string $key, string $fallbackEmoji = '⭐'): string
{
    static $defaults = [
        'ce_welcome_1'  => '5458904472598095631',
        'ce_welcome_2'  => '6001538474795078519',
        'ce_welcome_3'  => '6269103435413459285',
        'ce_welcome_4'  => '5386757680679377085',
        'ce_welcome_5'  => '6059724471223194869',
        'ce_welcome_6'  => '6222198028854367391',
        'ce_join_1'     => '5332455502917949981',
        'ce_join_2'     => '5303138782004924588',
        'ce_join_ok'    => '5206607081334906820',
        'ce_join_no'    => '5210952531676504517',
        'ce_retry'      => '5382194935057372936',
        'ce_menu_1'     => '5267500801240092311',
        'ce_wallet_1'   => '5287231198098117669',
        'ce_balance'    => '5197434882321567830',
        'ce_ref_1'      => '5332724926216428039',
        'ce_ref_2'      => '6269103435413459285',
        'ce_ref_rocket' => '5195033767969839232',
        'ce_ref_gift'   => '6001538474795078519',
        'ce_payout_1'   => '5445355530111437729',
        'ce_payout_ok'  => '5206607081334906820',
        'ce_payout_no'  => '5210952531676504517',
        'ce_card'       => '5445353829304387411',
        'ce_network'    => '5224450179368767019',
        'ce_earn_1'     => '6001538474795078519',
        'ce_target'     => '5310278924616356636',
        'ce_warn'       => '6059724471223194869',
        'ce_ok'         => '5206607081334906820',
        'ce_no'         => '5210952531676504517',
        'ce_fire'       => '5267102644886853973',
        'ce_chart'      => '5197503331215361533',
        'ce_receipt'    => '5444856076954520455',
    ];

    // Prefer real emoji as visual fallback (letters break some Telegram clients)
    static $emojiFallback = [
        'ce_welcome_1'  => '👋',
        'ce_welcome_2'  => '🎁',
        'ce_welcome_3'  => '🔗',
        'ce_welcome_4'  => '💰',
        'ce_welcome_5'  => '⚠️',
        'ce_welcome_6'  => '✅',
        'ce_join_1'     => '📢',
        'ce_join_2'     => '📋',
        'ce_join_ok'    => '✅',
        'ce_join_no'    => '❌',
        'ce_retry'      => '🔄',
        'ce_menu_1'     => '🏠',
        'ce_wallet_1'   => '💼',
        'ce_balance'    => '💵',
        'ce_ref_1'      => '👥',
        'ce_ref_2'      => '🔗',
        'ce_ref_rocket' => '🚀',
        'ce_ref_gift'   => '🎁',
        'ce_payout_1'   => '⬆️',
        'ce_payout_ok'  => '✅',
        'ce_payout_no'  => '❌',
        'ce_card'       => '💳',
        'ce_network'    => '🌐',
        'ce_earn_1'     => '🎁',
        'ce_target'     => '🎯',
        'ce_warn'       => '⚠️',
        'ce_ok'         => '✅',
        'ce_no'         => '❌',
        'ce_fire'       => '🔥',
        'ce_chart'      => '📊',
        'ce_receipt'    => '🧾',
    ];

    $id = '';
    try {
        $id = preg_replace('/\D+/', '', (string)getSetting($key, ''));
    } catch (Throwable $e) {
        $id = '';
    }
    if ($id === '' && isset($defaults[$key])) {
        $id = $defaults[$key];
    }

    $inner = $emojiFallback[$key] ?? $fallbackEmoji;
    // If caller passed a letter, still prefer mapped emoji
    if (isset($emojiFallback[$key]) && mb_strlen($fallbackEmoji) === 1 && preg_match('/[A-Za-z]/', $fallbackEmoji)) {
        $inner = $emojiFallback[$key];
    }

    if ($id === '' || strlen($id) < 5) {
        return $inner;
    }

    // Official HTML custom-emoji format
    return '<tg-emoji emoji-id="' . $id . '">' . $inner . '</tg-emoji>';
}

function getMissingChannels(TelegramBot $bot, int $userId): array
{
    $db = getDB();
    $channels = $db->query(
        'SELECT * FROM channels WHERE is_active = 1 AND is_required = 1 ORDER BY sort_order ASC, id ASC'
    )->fetchAll();
    $missing = [];
    foreach ($channels as $ch) {
        if (!$bot->isUserJoined($ch['username'], $userId)) {
            $missing[] = $ch;
        }
    }
    $pay = getSetting('payment_channel');
    if ($pay && !$bot->isUserJoined($pay, $userId)) {
        $missing[] = [
            'title' => 'PAYMENT CHANNEL',
            'username' => ltrim($pay, '@'),
            'invite_link' => 'https://t.me/' . ltrim($pay, '@'),
        ];
    }
    return $missing;
}
