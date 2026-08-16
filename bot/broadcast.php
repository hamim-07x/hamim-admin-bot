<?php
/**
 * Bot-admin broadcast to all users in DB.
 */

function isBotAdmin(int $userId): bool
{
    $raw = trim((string)getSetting('bot_admin_ids', ''));
    if ($raw === '') {
        return false;
    }
    foreach (preg_split('/[\s,;]+/', $raw) as $part) {
        $part = trim($part);
        if ($part !== '' && ctype_digit($part) && (int)$part === $userId) {
            return true;
        }
    }
    return false;
}

function showBroadcastPanel(TelegramBot $bot, int $chatId, int $userId): void
{
    $text  = ce('ce_menu_1') . " <b>Broadcast</b>\n\n";
    $text .= ce('ce_warn') . " Only bot admins can use this.\n";
    $text .= ce('ce_ref_rocket') . " Tap the button, then send or <b>forward</b> any message.\n";
    $text .= ce('ce_ok') . " It will be delivered to every user in the database.";

    $idGo = btnEmojiId('ce_btn_agree', '5206607081334906820');
    $idBack = btnEmojiId('ce_btn_back', '5416041192905265756');
    $kb = TelegramBot::inlineKeyboard([
        [inlineBtn('Start Broadcast', 'bc_start', $idGo)],
        [inlineBtn('Back', 'go_menu', $idBack)],
    ]);
    botSend($bot, $chatId, $userId, $text, '', ['reply_markup' => $kb]);
}

function askBroadcastContent(TelegramBot $bot, int $chatId, int $userId): void
{
    setBotState($userId, 'broadcast_wait');
    $text  = ce('ce_payout_ok') . " <b>Send broadcast content</b>\n\n";
    $text .= ce('ce_card') . " Send any text, photo, or <b>forward</b> a message now.\n";
    $text .= ce('ce_warn') . " Cancel: /cancel";
    $idCancel = btnEmojiId('ce_btn_cancel', '5210952531676504517');
    $kb = TelegramBot::inlineKeyboard([
        [inlineBtn('Cancel', 'bc_cancel', $idCancel)],
    ]);
    botSend($bot, $chatId, $userId, $text, '', ['reply_markup' => $kb]);
}

/**
 * Copy admin message to all users. Shows live progress to admin.
 */
function runBroadcast(TelegramBot $bot, int $adminChatId, int $adminId, int $fromChatId, int $messageId): void
{
    clearBotState($adminId);
    @set_time_limit(300);
    @ini_set('max_execution_time', '300');

    $db = getDB();
    // All registered users (skip blocked)
    try {
        $rows = $db->query('SELECT id FROM users WHERE COALESCE(is_blocked,0) = 0 ORDER BY id ASC')->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable $e) {
        $rows = $db->query('SELECT id FROM users ORDER BY id ASC')->fetchAll(PDO::FETCH_COLUMN);
    }

    $total = count($rows);
    if ($total === 0) {
        botSend($bot, $adminChatId, $adminId, ce('ce_payout_no') . ' No users in database.');
        return;
    }

    $ok = 0;
    $fail = 0;
    $done = 0;

    $prog  = ce('ce_ref_rocket') . " <b>Broadcasting…</b>\n\n";
    $prog .= ce('ce_chart') . " Progress: <b>0 / {$total}</b>\n";
    $prog .= ce('ce_ok') . " Sent: <b>0</b>\n";
    $prog .= ce('ce_no') . " Failed: <b>0</b>";
    $res = $bot->sendMessage($adminChatId, $prog, ['parse_mode' => 'HTML']);
    $progMid = (int)($res['result']['message_id'] ?? 0);

    $lastEdit = time();
    foreach ($rows as $uid) {
        $uid = (int)$uid;
        if ($uid <= 0) {
            continue;
        }
        $sent = false;
        try {
            $r = $bot->copyMessage($uid, $fromChatId, $messageId);
            if ($r && ($r['ok'] ?? false)) {
                $sent = true;
            }
        } catch (Throwable $e) {
        }
        if (!$sent) {
            try {
                $r = $bot->forwardMessage($uid, $fromChatId, $messageId);
                if ($r && ($r['ok'] ?? false)) {
                    $sent = true;
                }
            } catch (Throwable $e) {
            }
        }
        if ($sent) {
            $ok++;
        } else {
            $fail++;
        }
        $done++;

        // Live progress every 15 users or 2s
        if ($progMid > 0 && ($done % 15 === 0 || (time() - $lastEdit) >= 2 || $done === $total)) {
            $lastEdit = time();
            $p  = ce('ce_ref_rocket') . " <b>Broadcasting…</b>\n\n";
            $p .= ce('ce_chart') . " Progress: <b>{$done} / {$total}</b>\n";
            $p .= ce('ce_ok') . " Sent: <b>{$ok}</b>\n";
            $p .= ce('ce_no') . " Failed: <b>{$fail}</b>";
            try {
                $bot->editMessage($adminChatId, $progMid, $p, ['parse_mode' => 'HTML']);
            } catch (Throwable $e) {
            }
        }

        // ~25–30 msg/sec safe pacing
        if ($done % 25 === 0) {
            usleep(800000);
        } else {
            usleep(35000);
        }
    }

    $doneMsg  = ce('ce_payout_ok') . " <b>Broadcast complete</b>\n\n";
    $doneMsg .= ce('ce_chart') . " Total users: <b>{$total}</b>\n";
    $doneMsg .= ce('ce_ok') . " Delivered: <b>{$ok}</b>\n";
    $doneMsg .= ce('ce_no') . " Failed: <b>{$fail}</b>";
    if ($progMid > 0) {
        try {
            $bot->editMessage($adminChatId, $progMid, $doneMsg, ['parse_mode' => 'HTML']);
        } catch (Throwable $e) {
            $bot->sendMessage($adminChatId, $doneMsg, ['parse_mode' => 'HTML']);
        }
    } else {
        $bot->sendMessage($adminChatId, $doneMsg, ['parse_mode' => 'HTML']);
    }
}
