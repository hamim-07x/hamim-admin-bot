<?php
/* Message helpers: photo + auto-delete + custom emoji — inline only UI */

require_once __DIR__ . '/premium_emojis.php';

function botSend(TelegramBot $bot, int $chatId, int $userId, string $text, string $imgKey = '', array $extra = []): void
{
    deleteAllBotMessages($bot, $chatId, $userId);

    $photo = '';
    if ($imgKey !== '' && getSetting($imgKey . '_on', '0') === '1') {
        $photo = trim((string)getSetting($imgKey, ''));
    }

    $extra['parse_mode'] = 'HTML';
    $extra['disable_web_page_preview'] = true;

    if (empty($extra['reply_markup'])) {
        $extra['reply_markup'] = TelegramBot::removeKeyboard();
    }

    $res = null;
    if ($photo !== '' && preg_match('#^https?://#i', $photo)) {
        $res = $bot->sendPhoto($chatId, $photo, $text, $extra);
    } else {
        $res = $bot->sendMessage($chatId, $text, $extra);
    }

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

function stripTgEmoji(string $html): string
{
    return preg_replace('/<tg-emoji\s+emoji-id="\d+">(.*?)<\/tg-emoji>/su', '$1', $html) ?? $html;
}

function deleteAllBotMessages(TelegramBot $bot, int $chatId, int $userId): void
{
    foreach (getBotMessageIds($userId) as $mid) {
        try {
            $bot->deleteMessage($chatId, (int)$mid);
        } catch (Throwable $e) {
        }
    }
    clearBotMessageIds($userId);
}

function ensureMsgColumns(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        $db = getDB();
        if (!$db->query("SHOW COLUMNS FROM users LIKE 'bot_msgs'")->fetch()) {
            $db->exec("ALTER TABLE users ADD COLUMN bot_msgs TEXT DEFAULT NULL");
        }
    } catch (Throwable $e) {
    }
}

function getBotMessageIds(int $userId): array
{
    ensureMsgColumns();
    $stmt = getDB()->prepare('SELECT bot_msgs FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    $d = json_decode((string)($row['bot_msgs'] ?? ''), true);
    return is_array($d) ? $d : [];
}

function pushBotMessageId(int $userId, int $messageId): void
{
    ensureMsgColumns();
    $ids = getBotMessageIds($userId);
    $ids[] = $messageId;
    if (count($ids) > 5) {
        $ids = array_slice($ids, -5);
    }
    getDB()->prepare('UPDATE users SET bot_msgs = ? WHERE id = ?')
        ->execute([json_encode($ids), $userId]);
}

function clearBotMessageIds(int $userId): void
{
    ensureMsgColumns();
    getDB()->prepare('UPDATE users SET bot_msgs = NULL WHERE id = ?')->execute([$userId]);
}

function currencyEmoji(): string
{
    $id = preg_replace('/\D+/', '', (string)getSetting('currency_emoji_id', ''));
    if ($id === '') {
        $id = '5201873447554145566'; // premium cash from catalog
    }
    return '<tg-emoji emoji-id="' . $id . '">💵</tg-emoji>';
}

function ce(string $key, string $fallbackEmoji = '⭐'): string
{
    if ($key === 'ce_balance' || $key === 'currency') {
        $cid = preg_replace('/\D+/', '', (string)getSetting('currency_emoji_id', ''));
        if ($cid !== '' && strlen($cid) >= 8) {
            return '<tg-emoji emoji-id="' . $cid . '">💵</tg-emoji>';
        }
    }

    static $defaults = null;
    if ($defaults === null) {
        $defaults = premiumCeDefaults();
    }

    static $emojiFallback = [
        'ce_welcome_1' => '👋', 'ce_welcome_2' => '💰', 'ce_welcome_3' => '🔗',
        'ce_welcome_4' => '💵', 'ce_welcome_5' => '⚠️', 'ce_welcome_6' => '✅',
        'ce_join_1' => '🏦', 'ce_join_2' => '💬', 'ce_join_ok' => '✅', 'ce_join_no' => '❌',
        'ce_retry' => '🔄', 'ce_menu_1' => '🏠', 'ce_wallet_1' => '💰', 'ce_balance' => '💵',
        'ce_ref_1' => '👥', 'ce_ref_2' => '🔗', 'ce_ref_rocket' => '🚀', 'ce_ref_gift' => '🎁',
        'ce_payout_1' => '💳', 'ce_payout_ok' => '✅', 'ce_payout_no' => '❌',
        'ce_card' => '💳', 'ce_network' => '🌐', 'ce_earn_1' => '🎯', 'ce_target' => '🎯',
        'ce_warn' => '⚠️', 'ce_ok' => '✅', 'ce_no' => '❌', 'ce_fire' => '🔥',
        'ce_chart' => '📊', 'ce_receipt' => '🧾',
        'ce_btn_wallet' => '👛', 'ce_btn_referrals' => '👥', 'ce_btn_payout' => '💳', 'ce_btn_earn' => '🎯',
        'ce_btn_back' => '⬅️', 'ce_btn_cancel' => '❌', 'ce_btn_agree' => '✅',
        'ce_btn_retry' => '🔄', 'ce_btn_channel' => '📣',
    ];

    $alias = [
        'ce_wallet_1' => 'ce_btn_wallet',
        'ce_ref_1'    => 'ce_btn_referrals',
        'ce_payout_1' => 'ce_btn_payout',
        'ce_earn_1'   => 'ce_btn_earn',
    ];
    $lookupKey = $alias[$key] ?? $key;

    $id = '';
    try {
        $id = preg_replace('/\D+/', '', (string)getSetting($lookupKey, ''));
        if ($id === '' && $lookupKey !== $key) {
            $id = preg_replace('/\D+/', '', (string)getSetting($key, ''));
        }
    } catch (Throwable $e) {
        $id = '';
    }
    if ($id === '' && isset($defaults[$lookupKey])) {
        $id = $defaults[$lookupKey];
    }
    if ($id === '' && isset($defaults[$key])) {
        $id = $defaults[$key];
    }

    $inner = $emojiFallback[$key] ?? ($emojiFallback[$lookupKey] ?? $fallbackEmoji);
    if ($id === '' || strlen($id) < 8) {
        return $inner;
    }
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
    return $missing;
}
