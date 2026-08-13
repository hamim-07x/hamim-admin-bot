<?php
/**
 * All UI via INLINE buttons only.
 * Premium emoji on buttons via icon_custom_emoji_id (Telegram Bot API).
 * Text labels editable from Admin → Menu Buttons + emoji IDs.
 */

function menuLabels(): array
{
    $c = getSetting('currency_name', 'USDT');
    return [
        'wallet'    => getSetting('menu_btn_wallet', $c . ' Wallet'),
        'referrals' => getSetting('menu_btn_referrals', 'Referrals'),
        'payout'    => getSetting('menu_btn_payout', $c . ' Payout'),
        'earn'      => getSetting('menu_btn_earn', 'EARN MORE'),
    ];
}

/** Digits-only custom emoji id from settings or default pack */
function btnEmojiId(string $settingKey, string $defaultId): string
{
    try {
        $id = preg_replace('/\D+/', '', (string)getSetting($settingKey, ''));
    } catch (Throwable $e) {
        $id = '';
    }
    if ($id === '' || strlen($id) < 8) {
        $id = $defaultId;
    }
    return $id;
}

/** Strip leading unicode emoji from label (icon is separate) */
function cleanBtnLabel(string $label): string
{
    $label = trim($label);
    $label = preg_replace('/^[\p{So}\p{Sk}\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}\s]+/u', '', $label);
    $label = trim($label);
    return $label !== '' ? $label : 'Button';
}

/** Inline button with optional premium icon */
function inlineBtn(string $text, string $callbackData, string $iconEmojiId = ''): array
{
    $btn = [
        'text'          => $text,
        'callback_data' => $callbackData,
    ];
    if ($iconEmojiId !== '' && strlen($iconEmojiId) >= 8) {
        $btn['icon_custom_emoji_id'] = $iconEmojiId;
    }
    return $btn;
}

function inlineUrlBtn(string $text, string $url, string $iconEmojiId = ''): array
{
    $btn = [
        'text' => $text,
        'url'  => $url,
    ];
    if ($iconEmojiId !== '' && strlen($iconEmojiId) >= 8) {
        $btn['icon_custom_emoji_id'] = $iconEmojiId;
    }
    return $btn;
}

function mainMenuInline(): array
{
    $l = menuLabels();
    $w = cleanBtnLabel($l['wallet']);
    $r = cleanBtnLabel($l['referrals']);
    $p = cleanBtnLabel($l['payout']);
    $e = cleanBtnLabel($l['earn']);

    // Defaults from FinanceEmoji / NewsEmoji packs
    $idW = btnEmojiId('ce_btn_wallet', '5287231198098117669');    // 💰
    $idR = btnEmojiId('ce_btn_referrals', '5332724926216428039'); // 📇
    $idP = btnEmojiId('ce_btn_payout', '5445355530111437729');    // 📤
    $idE = btnEmojiId('ce_btn_earn', '5310278924616356636');      // 🎯

    return TelegramBot::inlineKeyboard([
        [
            inlineBtn($w, 'nav_wallet', $idW),
            inlineBtn($r, 'nav_referrals', $idR),
        ],
        [
            inlineBtn($p, 'nav_payout', $idP),
            inlineBtn($e, 'nav_earn', $idE),
        ],
    ]);
}

function backInline(): array
{
    // ⬅️ / back style id from News pack if available — use retry/home style
    $idBack = btnEmojiId('ce_btn_back', '5416041192905265756'); // 🏠 home as back-to-menu
    return TelegramBot::inlineKeyboard([
        [inlineBtn('Back', 'go_menu', $idBack)],
    ]);
}

function showWelcomeAgree(TelegramBot $bot, int $chatId, array $from): void
{
    $userId = (int)$from['id'];
    $name = trim(($from['first_name'] ?? '') . ' ' . ($from['last_name'] ?? ''));
    if ($name === '') {
        $name = $from['username'] ?? 'User';
    }
    $welcome = getSetting('welcome_text', 'Welcome to Ton Grid');
    $text  = ce('ce_welcome_1') . ' <b>' . htmlspecialchars($name) . '!</b> ' . htmlspecialchars($welcome) . "\n\n";
    $text .= ce('ce_welcome_2') . " Complete tasks and earn rewards.\n";
    $text .= ce('ce_welcome_3') . " Invite friends to earn referral bonuses.\n";
    $text .= ce('ce_welcome_4') . " The More You Engage, The More You Earn.\n\n";
    $text .= ce('ce_welcome_5') . " By continuing, you must agree to our Terms & Conditions.\n\n";
    $text .= ce('ce_welcome_6') . ' Click <b>I Agree & Continue</b> below.';

    $idOk = btnEmojiId('ce_btn_agree', '5206607081334906820'); // ✔️
    $kb = TelegramBot::inlineKeyboard([
        [inlineBtn('I Agree & Continue', 'agree_continue', $idOk)],
    ]);
    botSend($bot, $chatId, $userId, $text, 'img_welcome', ['reply_markup' => $kb]);
}

function showJoinScreen(TelegramBot $bot, int $chatId, int $userId, array $missing = []): void
{
    if (!$missing) {
        $missing = getMissingChannels($bot, $userId);
    }
    if (!$missing) {
        markUserJoined($userId, true);
        showMainMenu($bot, $chatId, $userId);
        return;
    }
    $welcome = getSetting('welcome_text', 'Welcome to Ton Grid');
    $text  = ce('ce_join_1') . ' <b>' . htmlspecialchars($welcome) . "</b>\n\n";
    $text .= ce('ce_join_2') . " <b>Join the channels below to continue.</b>\n";
    $text .= ce('ce_warn') . " Only channels you have not joined are shown.\n";
    $text .= ce('ce_no') . " Without joining, the bot will not work.\n";

    $idCh = btnEmojiId('ce_btn_channel', '5332455502917949981'); // 🏦
    $idGo = btnEmojiId('ce_btn_agree', '5206607081334906820');
    $rows = [];
    $pair = [];
    foreach ($missing as $ch) {
        $link = trim((string)($ch['invite_link'] ?? ''));
        if ($link === '') {
            $link = 'https://t.me/' . ltrim($ch['username'], '@');
        }
        $pair[] = inlineUrlBtn(($ch['title'] ?: $ch['username']), $link, $idCh);
        if (count($pair) === 2) {
            $rows[] = $pair;
            $pair = [];
        }
    }
    if ($pair) {
        $rows[] = $pair;
    }
    $rows[] = [inlineBtn('Proceed', 'check_join', $idGo)];
    botSend($bot, $chatId, $userId, $text, 'img_join', ['reply_markup' => TelegramBot::inlineKeyboard($rows)]);
}

function showJoinFailed(TelegramBot $bot, int $chatId, int $userId, array $missing = []): void
{
    if (!$missing) {
        $missing = getMissingChannels($bot, $userId);
    }
    $text  = ce('ce_join_no') . " <b>You must join these channels first</b>\n\n";
    $text .= ce('ce_warn') . " To use the bot you must stay joined.\n";
    $text .= ce('ce_join_2') . " Only missing channels are listed:\n\n";
    foreach ($missing as $ch) {
        $text .= ce('ce_join_1') . ' @' . ltrim($ch['username'], '@') . "\n";
    }
    $text .= "\n" . ce('ce_retry') . " Join them, then tap <b>Retry</b>.";

    $idCh = btnEmojiId('ce_btn_channel', '5332455502917949981');
    $idRetry = btnEmojiId('ce_btn_retry', '5375338737028841420'); // 🔄
    $rows = [];
    $pair = [];
    foreach ($missing as $ch) {
        $link = trim((string)($ch['invite_link'] ?? ''));
        if ($link === '') {
            $link = 'https://t.me/' . ltrim($ch['username'], '@');
        }
        $pair[] = inlineUrlBtn(($ch['title'] ?: $ch['username']), $link, $idCh);
        if (count($pair) === 2) {
            $rows[] = $pair;
            $pair = [];
        }
    }
    if ($pair) {
        $rows[] = $pair;
    }
    $rows[] = [inlineBtn('Retry', 'retry_join', $idRetry)];
    botSend($bot, $chatId, $userId, $text, 'img_join', ['reply_markup' => TelegramBot::inlineKeyboard($rows)]);
}

function showMainMenu(TelegramBot $bot, int $chatId, int $userId): void
{
    $text  = ce('ce_menu_1') . " <b>Main Menu</b>\n\n";
    $text .= ce('ce_welcome_6') . ' Choose an option below:';
    botSend($bot, $chatId, $userId, $text, 'img_menu', ['reply_markup' => mainMenuInline()]);
}

function showWallet(TelegramBot $bot, int $chatId, int $userId): void
{
    $stmt = getDB()->prepare('SELECT balance FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $bal = number_format((float)($stmt->fetch()['balance'] ?? 0), 2);
    $c = getSetting('currency_name', 'USDT');
    $s = getSetting('currency_symbol', '$');
    $text  = ce('ce_btn_wallet') . " <b>{$c} Wallet</b>\n\n";
    $text .= "━━━━━━━━━━━━━━\n";
    $text .= ce('ce_balance') . " Balance: <b>{$s}{$bal}</b>\n";
    $text .= "━━━━━━━━━━━━━━";
    botSend($bot, $chatId, $userId, $text, 'img_wallet', ['reply_markup' => backInline()]);
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
    $text  = ce('ce_btn_referrals') . " <b>Refer & Earn Program</b>\n\n";
    $text .= ce('ce_ref_rocket') . " Invite friends and earn!\n";
    $text .= ce('ce_ref_gift') . " You receive <b>{$s}{$bonus} {$c}</b> per valid join.\n\n";
    $text .= ce('ce_ref_2') . " <b>Your link:</b>\n<code>" . htmlspecialchars($link) . "</code>\n\n";
    $text .= ce('ce_chart') . " Total referrals: <b>{$count}</b>";
    botSend($bot, $chatId, $userId, $text, 'img_referrals', ['reply_markup' => backInline()]);
}

function startWithdrawFlow(TelegramBot $bot, int $chatId, int $userId): void
{
    $stmt = getDB()->prepare('SELECT balance FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $bal = (float)($stmt->fetch()['balance'] ?? 0);
    $min = (float)getSetting('min_withdraw', '1');
    $c = getSetting('currency_name', 'USDT');
    $s = getSetting('currency_symbol', '$');

    $idPayout = btnEmojiId('ce_btn_payout', '5445355530111437729');
    $idCancel = btnEmojiId('ce_btn_cancel', '5210952531676504517'); // ❌
    $idBack = btnEmojiId('ce_btn_back', '5416041192905265756');

    if ($bal < $min) {
        $text  = ce('ce_btn_payout') . " <b>{$c} Payout</b>\n\n";
        $text .= ce('ce_payout_no') . " Insufficient balance.\n";
        $text .= ce('ce_balance') . " Balance: <b>{$s}" . number_format($bal, 2) . "</b>\n";
        $text .= ce('ce_warn') . " Minimum: <b>{$s}" . number_format($min, 2) . "</b>";
        botSend($bot, $chatId, $userId, $text, 'img_payout', ['reply_markup' => backInline()]);
        return;
    }
    $text  = ce('ce_btn_payout') . " <b>{$c} Payout</b>\n\n";
    $text .= ce('ce_balance') . " Balance: <b>{$s}" . number_format($bal, 2) . "</b>\n";
    $text .= ce('ce_warn') . " Minimum: <b>{$s}" . number_format($min, 2) . "</b>\n";
    $text .= ce('ce_network') . " Network: <b>BEP-20 (BSC)</b>\n\n";
    $text .= ce('ce_card') . ' Continue to enter wallet address?';
    $kb = TelegramBot::inlineKeyboard([
        [
            inlineBtn('Payout', 'wd_continue', $idPayout),
            inlineBtn('Cancel', 'wd_cancel', $idCancel),
        ],
        [inlineBtn('Back', 'go_menu', $idBack)],
    ]);
    botSend($bot, $chatId, $userId, $text, 'img_payout', ['reply_markup' => $kb]);
}

function askWithdrawAddress(TelegramBot $bot, int $chatId, int $userId): void
{
    setBotState($userId, 'withdraw_address');
    $c = getSetting('currency_name', 'USDT');
    $text  = ce('ce_btn_payout') . " <b>{$c} Payout</b>\n\n";
    $text .= ce('ce_card') . " Send your <b>BEP-20 (BSC)</b> wallet address:\n\n";
    $text .= ce('ce_warn') . " Example: <code>0x...</code>";
    $idCancel = btnEmojiId('ce_btn_cancel', '5210952531676504517');
    $idBack = btnEmojiId('ce_btn_back', '5416041192905265756');
    $kb = TelegramBot::inlineKeyboard([
        [
            inlineBtn('Cancel', 'wd_cancel', $idCancel),
            inlineBtn('Back', 'go_menu', $idBack),
        ],
    ]);
    botSend($bot, $chatId, $userId, $text, 'img_payout', ['reply_markup' => $kb]);
}

function handleWithdrawAddress(TelegramBot $bot, int $chatId, int $userId, string $address): void
{
    $address = trim($address);
    $idCancel = btnEmojiId('ce_btn_cancel', '5210952531676504517');
    $idBack = btnEmojiId('ce_btn_back', '5416041192905265756');
    $idOk = btnEmojiId('ce_btn_agree', '5206607081334906820');

    if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $address)) {
        $text = ce('ce_payout_no') . ' Invalid BSC address. Send <code>0x...</code> or Cancel.';
        $kb = TelegramBot::inlineKeyboard([
            [
                inlineBtn('Cancel', 'wd_cancel', $idCancel),
                inlineBtn('Back', 'go_menu', $idBack),
            ],
        ]);
        botSend($bot, $chatId, $userId, $text, 'img_payout', ['reply_markup' => $kb]);
        return;
    }
    $stmt = getDB()->prepare('SELECT balance FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $bal = (float)($stmt->fetch()['balance'] ?? 0);
    $c = getSetting('currency_name', 'USDT');
    $s = getSetting('currency_symbol', '$');
    setBotState($userId, 'withdraw_confirm', ['address' => $address, 'amount' => $bal]);
    $text  = ce('ce_btn_payout') . " <b>Confirm Payout</b>\n\n";
    $text .= ce('ce_balance') . " Amount: <b>{$s}" . number_format($bal, 2) . " {$c}</b> (MAX)\n";
    $text .= ce('ce_card') . " Address:\n<code>" . htmlspecialchars($address) . "</code>\n\n";
    $text .= ce('ce_network') . " Network: <b>BEP-20 (BSC)</b>";
    $kb = TelegramBot::inlineKeyboard([
        [
            inlineBtn('Confirm (MAX)', 'wd_confirm', $idOk),
            inlineBtn('Cancel', 'wd_cancel', $idCancel),
        ],
        [inlineBtn('Back', 'go_menu', $idBack)],
    ]);
    botSend($bot, $chatId, $userId, $text, 'img_payout', ['reply_markup' => $kb]);
}

function processWithdraw(TelegramBot $bot, int $chatId, int $userId): void
{
    $data = getBotStateData($userId);
    clearBotState($userId);
    $address = $data['address'] ?? '';
    if (!$address || !preg_match('/^0x[a-fA-F0-9]{40}$/', $address)) {
        botSend($bot, $chatId, $userId, ce('ce_payout_no') . ' Session expired.', '', ['reply_markup' => backInline()]);
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
            botSend($bot, $chatId, $userId, ce('ce_payout_no') . ' Insufficient balance.', '', ['reply_markup' => backInline()]);
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
        botSend($bot, $chatId, $userId, $text, 'img_payout', ['reply_markup' => backInline()]);
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        botSend($bot, $chatId, $userId, ce('ce_payout_no') . ' Error processing payout.', '', ['reply_markup' => backInline()]);
    }
}

function showEarnMore(TelegramBot $bot, int $chatId, int $userId): void
{
    $text  = ce('ce_btn_earn') . " <b>EARN MORE</b>\n\n";
    $text .= ce('ce_target') . " Tasks coming soon.\n";
    $text .= ce('ce_fire') . ' Stay tuned!';
    botSend($bot, $chatId, $userId, $text, 'img_earn', ['reply_markup' => backInline()]);
}
