<?php
/* Message helpers: photo + auto-delete + custom emoji */

function botSend(TelegramBot $bot, int $chatId, int $userId, string $text, string $imgKey = '', array $extra = [], bool $keepReplyKeyboard = true): void
{
    // Keep only last 1 previous bot message, then send new = max 2 total after send
    deleteOldBotMessages($bot, $chatId, $userId, 1);

    $photo = '';
    if ($imgKey !== '' && getSetting($imgKey . '_on', '0') === '1') {
        $photo = trim((string)getSetting($imgKey, ''));
    }

    $extra['parse_mode'] = 'HTML';
    $extra['disable_web_page_preview'] = true;

    // Always keep main reply keyboard visible (unless caller already set reply_markup keyboard
    // or explicitly disabled — e.g. withdraw address input can still keep it)
    if ($keepReplyKeyboard && empty($extra['reply_markup'])) {
        $extra['reply_markup'] = TelegramBot::mainMenuKeyboardFromLabels(menuLabelsSafe());
    } elseif ($keepReplyKeyboard && isset($extra['reply_markup']['inline_keyboard'])) {
        // Telegram allows only one reply_markup; prefer inline for action buttons,
        // but re-send reply keyboard in a follow-up is heavy — instead attach reply keyboard
        // by converting: keep inline as-is (reply keyboard from earlier messages usually stays).
        // Force re-show reply keyboard by merging is NOT supported in one message.
        // So we send reply keyboard only when no inline; when inline exists keyboard stays from before.
    }

    $res = null;
    if ($photo !== '' && preg_match('#^https?://#i', $photo)) {
        $res = $bot->sendPhoto($chatId, $photo, $text, $extra);
    } else {
        $res = $bot->sendMessage($chatId, $text, $extra);
    }

    // Retry without tg-emoji if API rejects custom emoji
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
        // After send, if more than 2 stored ids, delete oldest
        pruneBotMessages($bot, $chatId, $userId, 2);
    }
}

function menuLabelsSafe(): array
{
    if (function_exists('menuLabels')) {
        return menuLabels();
    }
    $c = getSetting('currency_name', 'USDT');
    return [
        'wallet' => getSetting('menu_btn_wallet', $c . ' Wallet'),
        'referrals' => getSetting('menu_btn_referrals', 'Referrals'),
        'payout' => getSetting('menu_btn_payout', $c . ' Payout'),
        'earn' => getSetting('menu_btn_earn', 'EARN MORE'),
    ];
}

function stripTgEmoji(string $html): string
{
    return preg_replace('/<tg-emoji\s+emoji-id="\d+">(.*?)<\/tg-emoji>/su', '$1', $html) ?? $html;
}

function deleteOldBotMessages(TelegramBot $bot, int $chatId, int $userId, int $keep = 0): void
{
    $ids = getBotMessageIds($userId);
    if ($keep > 0 && count($ids) > $keep) {
        $toDelete = array_slice($ids, 0, count($ids) - $keep);
        $remain = array_slice($ids, -$keep);
    } else {
        $toDelete = $ids;
        $remain = [];
    }
    foreach ($toDelete as $mid) {
        try { $bot->deleteMessage($chatId, (int)$mid); } catch (Throwable $e) {}
    }
    if ($remain) {
        getDB()->prepare('UPDATE users SET bot_msgs = ? WHERE id = ?')->execute([json_encode(array_values($remain)), $userId]);
    } else {
        clearBotMessageIds($userId);
    }
}

function pruneBotMessages(TelegramBot $bot, int $chatId, int $userId, int $max = 2): void
{
    $ids = getBotMessageIds($userId);
    if (count($ids) <= $max) return;
    $toDelete = array_slice($ids, 0, count($ids) - $max);
    $remain = array_slice($ids, -$max);
    foreach ($toDelete as $mid) {
        try { $bot->deleteMessage($chatId, (int)$mid); } catch (Throwable $e) {}
    }
    getDB()->prepare('UPDATE users SET bot_msgs = ? WHERE id = ?')->execute([json_encode(array_values($remain)), $userId]);
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
    $row = $stmt->fetch();
    $d = json_decode((string)($row['bot_msgs'] ?? ''), true);
    return is_array($d) ? $d : [];
}

function pushBotMessageId(int $userId, int $messageId): void
{
    ensureMsgColumns();
    $ids = getBotMessageIds($userId);
    $ids[] = $messageId;
    getDB()->prepare('UPDATE users SET bot_msgs = ? WHERE id = ?')->execute([json_encode($ids), $userId]);
}

function clearBotMessageIds(int $userId): void
{
    ensureMsgColumns();
    getDB()->prepare('UPDATE users SET bot_msgs = NULL WHERE id = ?')->execute([$userId]);
}

/**
 * Custom premium emoji via Telegram HTML.
 * IDs from FinanceEmoji + NewsEmoji packs provided by owner.
 *
 * REQUIREMENT: Bot must be owned by a Telegram Premium account,
 * otherwise Telegram only shows the fallback emoji inside the tag.
 */
function ce(string $key, string $fallbackEmoji = '⭐'): string
{
    // Defaults from HTML_FinanceEmoji + HTML_NewsEmoji
    static $defaults = [
        // Welcome / general
        'ce_welcome_1'  => '5438496463044752972', // ⭐ News
        'ce_welcome_2'  => '5287231198098117669', // 💰 Finance
        'ce_welcome_3'  => '5271604874419647061', // 🔗 News
        'ce_welcome_4'  => '5409048419211682843', // 💵 News
        'ce_welcome_5'  => '5447644880824181073', // ⚠️ News
        'ce_welcome_6'  => '5206607081334906820', // ✔️ News
        // Join
        'ce_join_1'     => '5332455502917949981', // 🏦 Finance
        'ce_join_2'     => '5303138782004924588', // 💬 Finance
        'ce_join_ok'    => '5206607081334906820', // ✔️
        'ce_join_no'    => '5210952531676504517', // ❌
        'ce_retry'      => '5375338737028841420', // 🔄 News
        // Menu
        'ce_menu_1'     => '5416041192905265756', // 🏠 News
        // Wallet / money
        'ce_wallet_1'   => '5287231198098117669', // 💰
        'ce_balance'    => '5197434882321567830', // 💵 Finance
        // Referrals
        'ce_ref_1'      => '5332724926216428039', // 📇 Finance
        'ce_ref_2'      => '5271604874419647061', // 🔗
        'ce_ref_rocket' => '5195033767969839232', // 🚀 Finance
        'ce_ref_gift'   => '5461151367559141950', // 🎉 News
        // Payout
        'ce_payout_1'   => '5445355530111437729', // 📤 Finance
        'ce_payout_ok'  => '5206607081334906820', // ✔️
        'ce_payout_no'  => '5210952531676504517', // ❌
        'ce_card'       => '5445353829304387411', // 💳 Finance
        'ce_network'    => '5224450179368767019', // 🌎 Finance
        // Earn
        'ce_earn_1'     => '5310278924616356636', // 🎯 Finance
        'ce_target'     => '5310278924616356636', // 🎯
        'ce_warn'       => '5447644880824181073', // ⚠️
        'ce_ok'         => '5206607081334906820', // ✔️
        'ce_no'         => '5210952531676504517', // ❌
        'ce_fire'       => '5424972470023104089', // 🔥 News
        'ce_chart'      => '5197503331215361533', // 📈 Finance
        'ce_receipt'    => '5444856076954520455', // 🧾 Finance
        'ce_star'       => '5267500801240092311', // ⭐ Finance
        'ce_bell'       => '5458603043203327669', // 🔔 News
        'ce_lock'       => '5296369303661067030', // 🔒 News
        'ce_new'        => '5382357040008021292', // 🆕 News
        'ce_diamond'    => '5427168083074628963', // 💎 News
        'ce_crown'      => '5217822164362739968', // 👑 News
        'ce_in'         => '5443127283898405358', // 📥 Finance
        'ce_out'        => '5445355530111437729', // 📤 Finance
        'ce_look'       => '5210956306952758910', // 👀 News
        'ce_zap'        => '5456140674028019486', // ⚡️ News
        'ce_pin'        => '5397782960512444700', // 📌 News
        'ce_shield'     => '5251203410396458957', // 🛡 News
        'ce_calendar'   => '5274055917766202507', // 🗓 Finance
        'ce_briefcase'  => '5445221832074483553', // 💼 Finance
    ];

    static $emojiFallback = [
        'ce_welcome_1'  => '⭐',
        'ce_welcome_2'  => '💰',
        'ce_welcome_3'  => '🔗',
        'ce_welcome_4'  => '💵',
        'ce_welcome_5'  => '⚠️',
        'ce_welcome_6'  => '✅',
        'ce_join_1'     => '🏦',
        'ce_join_2'     => '💬',
        'ce_join_ok'    => '✅',
        'ce_join_no'    => '❌',
        'ce_retry'      => '🔄',
        'ce_menu_1'     => '🏠',
        'ce_wallet_1'   => '💰',
        'ce_balance'    => '💵',
        'ce_ref_1'      => '📇',
        'ce_ref_2'      => '🔗',
        'ce_ref_rocket' => '🚀',
        'ce_ref_gift'   => '🎉',
        'ce_payout_1'   => '📤',
        'ce_payout_ok'  => '✅',
        'ce_payout_no'  => '❌',
        'ce_card'       => '💳',
        'ce_network'    => '🌐',
        'ce_earn_1'     => '🎯',
        'ce_target'     => '🎯',
        'ce_warn'       => '⚠️',
        'ce_ok'         => '✅',
        'ce_no'         => '❌',
        'ce_fire'       => '🔥',
        'ce_chart'      => '📈',
        'ce_receipt'    => '🧾',
        'ce_star'       => '⭐',
        'ce_bell'       => '🔔',
        'ce_lock'       => '🔒',
        'ce_new'        => '🆕',
        'ce_diamond'    => '💎',
        'ce_crown'      => '👑',
        'ce_in'         => '📥',
        'ce_out'        => '📤',
        'ce_look'       => '👀',
        'ce_zap'        => '⚡️',
        'ce_pin'        => '📌',
        'ce_shield'     => '🛡',
        'ce_calendar'   => '🗓',
        'ce_briefcase'  => '💼',
    ];

    $id = '';
    try {
        $raw = trim((string)getSetting($key, ''));
        $id = preg_replace('/\D+/', '', $raw);
    } catch (Throwable $e) {
        $id = '';
    }
    if ($id === '' && isset($defaults[$key])) {
        $id = $defaults[$key];
    }

    $inner = $emojiFallback[$key] ?? $fallbackEmoji;
    if ($id === '' || strlen($id) < 8) {
        return $inner;
    }

    // Official Bot API HTML custom emoji
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
