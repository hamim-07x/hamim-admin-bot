<?php
function showWelcomeAgree(TelegramBot $bot, int $chatId, array $from): void
{
    $userId = (int)$from['id'];
    $name = trim(($from['first_name'] ?? '') . ' ' . ($from['last_name'] ?? ''));
    if ($name === '') $name = $from['username'] ?? 'User';
    $welcome = getSetting('welcome_text', 'Welcome to Ton Grid');
    $text  = ce('ce_welcome_1') . ' <b>' . htmlspecialchars($name) . '!</b> ' . htmlspecialchars($welcome) . "\n\n";
    $text .= ce('ce_welcome_2') . " Complete tasks and earn rewards.\n";
    $text .= ce('ce_welcome_3') . " Invite friends to earn referral bonuses.\n";
    $text .= ce('ce_welcome_4') . " The More You Engage, The More You Earn.\n\n";
    $text .= ce('ce_welcome_5') . " By continuing, you must agree to our Terms & Conditions.\n\n";
    $text .= ce('ce_welcome_6') . ' Click <b>I Agree & Continue</b> below.';
    $kb = TelegramBot::inlineKeyboard([[['text' => 'I Agree & Continue', 'callback_data' => 'agree_continue']]]);
    botSend($bot, $chatId, $userId, $text, 'img_welcome', ['reply_markup' => $kb], false);
}

function showJoinScreen(TelegramBot $bot, int $chatId, int $userId, array $missing = []): void
{
    if (!$missing) $missing = getMissingChannels($bot, $userId);
    if (!$missing) { markUserJoined($userId, true); showMainMenu($bot, $chatId, $userId); return; }
    $welcome = getSetting('welcome_text', 'Welcome to Ton Grid');
    $text  = ce('ce_join_1') . ' <b>' . htmlspecialchars($welcome) . "</b>\n\n";
    $text .= ce('ce_join_2') . " <b>Join the channels below to continue.</b>\n";
    $text .= ce('ce_warn') . " Only channels you have not joined are shown.\n";
    $text .= ce('ce_no') . " Without joining, the bot will not work.\n";
    $rows = []; $pair = [];
    foreach ($missing as $ch) {
        $link = trim((string)($ch['invite_link'] ?? ''));
        if ($link === '') $link = 'https://t.me/' . ltrim($ch['username'], '@');
        $pair[] = ['text' => ($ch['title'] ?: $ch['username']), 'url' => $link];
        if (count($pair) === 2) { $rows[] = $pair; $pair = []; }
    }
    if ($pair) $rows[] = $pair;
    $rows[] = [['text' => 'Proceed', 'callback_data' => 'check_join']];
    botSend($bot, $chatId, $userId, $text, 'img_join', ['reply_markup' => TelegramBot::inlineKeyboard($rows)], false);
}

function showJoinFailed(TelegramBot $bot, int $chatId, int $userId, array $missing = []): void
{
    if (!$missing) $missing = getMissingChannels($bot, $userId);
    $text  = ce('ce_join_no') . " <b>You must join these channels first</b>\n\n";
    $text .= ce('ce_warn') . " To use the bot you must stay joined.\n";
    $text .= ce('ce_join_2') . " Only missing channels are listed:\n\n";
    foreach ($missing as $ch) {
        $text .= ce('ce_join_1') . ' @' . ltrim($ch['username'], '@') . "\n";
    }
    $text .= "\n" . ce('ce_retry') . " Join them, then tap <b>Retry</b>.";
    $rows = []; $pair = [];
    foreach ($missing as $ch) {
        $link = trim((string)($ch['invite_link'] ?? ''));
        if ($link === '') $link = 'https://t.me/' . ltrim($ch['username'], '@');
        $pair[] = ['text' => ($ch['title'] ?: $ch['username']), 'url' => $link];
        if (count($pair) === 2) { $rows[] = $pair; $pair = []; }
    }
    if ($pair) $rows[] = $pair;
    $rows[] = [['text' => 'Retry', 'callback_data' => 'retry_join']];
    botSend($bot, $chatId, $userId, $text, 'img_join', ['reply_markup' => TelegramBot::inlineKeyboard($rows)], false);
}

function menuLabels(): array
{
    $c = getSetting('currency_name', 'USDT');
    return [
        'wallet' => getSetting('menu_btn_wallet', "{$c} Wallet"),
        'referrals' => getSetting('menu_btn_referrals', 'Referrals'),
        'payout' => getSetting('menu_btn_payout', "{$c} Payout"),
        'earn' => getSetting('menu_btn_earn', 'EARN MORE'),
    ];
}

function backKeyboard(): array
{
    // Reply keyboard always includes main menu + Back text handled via go_menu callback alternative:
    // Use reply keyboard only so main buttons never disappear.
    return TelegramBot::mainMenuKeyboardFromLabels(menuLabels());
}

function showMainMenu(TelegramBot $bot, int $chatId, int $userId): void
{
    $text  = ce('ce_menu_1') . " <b>Main Menu</b>\n\n";
    $text .= ce('ce_welcome_6') . ' Choose an option from the menu below:';
    botSend($bot, $chatId, $userId, $text, 'img_menu', [
        'reply_markup' => TelegramBot::mainMenuKeyboardFromLabels(menuLabels()),
    ], true);
}

function showWallet(TelegramBot $bot, int $chatId, int $userId): void
{
    $stmt = getDB()->prepare('SELECT balance FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $bal = number_format((float)($stmt->fetch()['balance'] ?? 0), 2);
    $c = getSetting('currency_name', 'USDT');
    $s = getSetting('currency_symbol', '$');
    $text  = ce('ce_wallet_1') . " <b>{$c} Wallet</b>\n\n";
    $text .= "━━━━━━━━━━━━━━\n";
    $text .= ce('ce_balance') . " Balance: <b>{$s}{$bal}</b>\n";
    $text .= "━━━━━━━━━━━━━━\n\n";
    $text .= ce('ce_menu_1') . ' Use menu buttons below anytime.';
    botSend($bot, $chatId, $userId, $text, 'img_wallet', [
        'reply_markup' => TelegramBot::mainMenuKeyboardFromLabels(menuLabels()),
    ], true);
}

function showReferrals(TelegramBot $bot, int $chatId, int $userId): void
{
    $stmt = getDB()->prepare('SELECT referral_code FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $code = $stmt->fetch()['referral_code'] ?? (string)$userId;
    $botUser = ltrim(getSetting('bot_username', ''), '@');
    $link = $botUser ? "https://t.me/{$botUser}?start={$code}" : $code;
    $stmt2 = getDB()->prepare('SELECT COUNT(*) AS c FROM users WHERE referred_by = ?');
    $stmt2->execute([$userId]);
    $count = (int)$stmt2->fetch()['c'];
    $bonus = getSetting('referral_bonus', '1.00');
    $c = getSetting('currency_name', 'USDT');
    $s = getSetting('currency_symbol', '$');
    $text  = ce('ce_ref_1') . " <b>Refer & Earn Program</b>\n\n";
    $text .= ce('ce_ref_rocket') . " Invite friends and earn!\n";
    $text .= ce('ce_ref_gift') . " You receive <b>{$s}{$bonus} {$c}</b> per valid join.\n\n";
    $text .= ce('ce_ref_2') . " <b>Your link:</b>\n<code>" . htmlspecialchars($link) . "</code>\n\n";
    $text .= ce('ce_chart') . " Total referrals: <b>{$count}</b>";
    botSend($bot, $chatId, $userId, $text, 'img_referrals', [
        'reply_markup' => TelegramBot::mainMenuKeyboardFromLabels(menuLabels()),
    ], true);
}

function startWithdrawFlow(TelegramBot $bot, int $chatId, int $userId): void
{
    $stmt = getDB()->prepare('SELECT balance FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $bal = (float)($stmt->fetch()['balance'] ?? 0);
    $min = (float)getSetting('min_withdraw', '1');
    $c = getSetting('currency_name', 'USDT');
    $s = getSetting('currency_symbol', '$');
    if ($bal < $min) {
        $text  = ce('ce_payout_1') . " <b>{$c} Payout</b>\n\n";
        $text .= ce('ce_payout_no') . " Insufficient balance.\n";
        $text .= ce('ce_balance') . " Balance: <b>{$s}" . number_format($bal, 2) . "</b>\n";
        $text .= ce('ce_warn') . " Minimum: <b>{$s}" . number_format($min, 2) . "</b>";
        botSend($bot, $chatId, $userId, $text, 'img_payout', [
            'reply_markup' => TelegramBot::mainMenuKeyboardFromLabels(menuLabels()),
        ], true);
        return;
    }
    $text  = ce('ce_payout_1') . " <b>{$c} Payout</b>\n\n";
    $text .= ce('ce_balance') . " Balance: <b>{$s}" . number_format($bal, 2) . "</b>\n";
    $text .= ce('ce_warn') . " Minimum: <b>{$s}" . number_format($min, 2) . "</b>\n";
    $text .= ce('ce_network') . " Network: <b>BEP-20 (BSC)</b>\n\n";
    $text .= ce('ce_card') . ' Continue to enter wallet address?';
    $kb = TelegramBot::inlineKeyboard([
        [
            ['text' => 'Payout', 'callback_data' => 'wd_continue'],
            ['text' => 'Cancel', 'callback_data' => 'wd_cancel'],
        ],
    ]);
    botSend($bot, $chatId, $userId, $text, 'img_payout', ['reply_markup' => $kb], false);
    // Re-assert main keyboard so it never disappears after inline message
    $bot->sendMessage($chatId, ce('ce_menu_1') . ' Menu is always available below.', [
        'reply_markup' => TelegramBot::mainMenuKeyboardFromLabels(menuLabels()),
        'parse_mode' => 'HTML',
    ]);
}

function handleWithdrawAddress(TelegramBot $bot, int $chatId, int $userId, string $address): void
{
    $address = trim($address);
    if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $address)) {
        botSend($bot, $chatId, $userId, ce('ce_payout_no') . ' Invalid BSC address. Send 0x... or Cancel.', 'img_payout', [
            'reply_markup' => TelegramBot::mainMenuKeyboardFromLabels(menuLabels()),
        ], true);
        return;
    }
    $stmt = getDB()->prepare('SELECT balance FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $bal = (float)($stmt->fetch()['balance'] ?? 0);
    $c = getSetting('currency_name', 'USDT');
    $s = getSetting('currency_symbol', '$');
    setBotState($userId, 'withdraw_confirm', ['address' => $address, 'amount' => $bal]);
    $text  = ce('ce_payout_1') . " <b>Confirm Payout</b>\n\n";
    $text .= ce('ce_balance') . " Amount: <b>{$s}" . number_format($bal, 2) . " {$c}</b> (MAX)\n";
    $text .= ce('ce_card') . " Address:\n<code>" . htmlspecialchars($address) . "</code>\n\n";
    $text .= ce('ce_network') . " Network: <b>BEP-20 (BSC)</b>";
    $kb = TelegramBot::inlineKeyboard([[
        ['text' => 'Confirm (MAX)', 'callback_data' => 'wd_confirm'],
        ['text' => 'Cancel', 'callback_data' => 'wd_cancel'],
    ]]);
    botSend($bot, $chatId, $userId, $text, 'img_payout', ['reply_markup' => $kb], false);
    $bot->sendMessage($chatId, ce('ce_menu_1') . ' Menu is always available below.', [
        'reply_markup' => TelegramBot::mainMenuKeyboardFromLabels(menuLabels()),
        'parse_mode' => 'HTML',
    ]);
}

function processWithdraw(TelegramBot $bot, int $chatId, int $userId): void
{
    $data = getBotStateData($userId);
    clearBotState($userId);
    $address = $data['address'] ?? '';
    if (!$address || !preg_match('/^0x[a-fA-F0-9]{40}$/', $address)) {
        botSend($bot, $chatId, $userId, ce('ce_payout_no') . ' Session expired.', '', [
            'reply_markup' => TelegramBot::mainMenuKeyboardFromLabels(menuLabels()),
        ], true);
        showMainMenu($bot, $chatId, $userId);
        return;
    }
    $db = getDB();
    $db->beginTransaction();
    try {
        $stmt = $db->prepare('SELECT balance FROM users WHERE id = ? FOR UPDATE');
        $stmt->execute([$userId]);
        $bal = (float)($stmt->fetch()['balance'] ?? 0);
        $min = (float)getSetting('min_withdraw', '1');
        if ($bal < $min) {
            $db->rollBack();
            botSend($bot, $chatId, $userId, ce('ce_payout_no') . ' Insufficient balance.', '', [
                'reply_markup' => TelegramBot::mainMenuKeyboardFromLabels(menuLabels()),
            ], true);
            showMainMenu($bot, $chatId, $userId);
            return;
        }
        $amount = $bal;
        $db->prepare('UPDATE users SET balance = balance - ? WHERE id = ?')->execute([$amount, $userId]);
        $db->prepare('INSERT INTO withdrawals (user_id, amount, address, status) VALUES (?,?,?,?)')
            ->execute([$userId, $amount, $address, 'pending']);
        $db->prepare('INSERT INTO transactions (user_id, type, amount, status, note) VALUES (?,?,?,?,?)')
            ->execute([$userId, 'withdraw', $amount, 'pending', $address]);
        $db->commit();
        $c = getSetting('currency_name', 'USDT');
        $text  = ce('ce_payout_ok') . " <b>Payout request submitted</b>\n\n";
        $text .= ce('ce_balance') . " Amount: <b>{$amount} {$c}</b>\n";
        $text .= ce('ce_card') . " Address: <code>" . htmlspecialchars($address) . "</code>\n";
        $text .= ce('ce_receipt') . ' Status: <b>Pending admin approval</b>';
        botSend($bot, $chatId, $userId, $text, 'img_payout', [
            'reply_markup' => TelegramBot::mainMenuKeyboardFromLabels(menuLabels()),
        ], true);
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        botSend($bot, $chatId, $userId, ce('ce_payout_no') . ' Error processing payout.', '', [
            'reply_markup' => TelegramBot::mainMenuKeyboardFromLabels(menuLabels()),
        ], true);
        showMainMenu($bot, $chatId, $userId);
    }
}

function showEarnMore(TelegramBot $bot, int $chatId, int $userId): void
{
    $text  = ce('ce_earn_1') . " <b>EARN MORE</b>\n\n";
    $text .= ce('ce_target') . " Tasks coming soon.\n";
    $text .= ce('ce_fire') . ' Stay tuned!';
    botSend($bot, $chatId, $userId, $text, 'img_earn', [
        'reply_markup' => TelegramBot::mainMenuKeyboardFromLabels(menuLabels()),
    ], true);
}
