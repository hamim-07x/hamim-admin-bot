<?php
require_once __DIR__ . '/TelegramBot.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/screens_ui.php';
require_once __DIR__ . '/screens_user.php';

function handleUpdate(array $update): void
{
    $token = getSetting('bot_token');
    if (!$token) {
        return;
    }
    $bot = new TelegramBot($token);
    if (isset($update['message'])) {
        handleMessage($bot, $update['message']);
    } elseif (isset($update['callback_query'])) {
        handleCallback($bot, $update['callback_query']);
    }
}

function handleMessage(TelegramBot $bot, array $message): void
{
    $chatId = $message['chat']['id'];
    $userId = (int)$message['from']['id'];
    $text   = trim($message['text'] ?? '');
    $from   = $message['from'];
    ensureUser($from);

    if (isUserBlocked($userId)) {
        botSend($bot, $chatId, $userId, 'You are blocked from using this bot.');
        return;
    }

    if (str_starts_with($text, '/start')) {
        $parts = explode(' ', $text, 2);
        if (!empty($parts[1])) {
            applyReferral($userId, $parts[1]);
        }
        clearBotState($userId);
        // remove any old reply keyboard leftover
        if (!userHasAgreed($userId)) {
            showWelcomeAgree($bot, $chatId, $from);
            return;
        }
        $missing = getMissingChannels($bot, $userId);
        if ($missing) {
            markUserJoined($userId, false);
            showJoinScreen($bot, $chatId, $userId, $missing);
            return;
        }
        markUserJoined($userId, true);
        showMainMenu($bot, $chatId, $userId);
        return;
    }

    if (!userHasAgreed($userId)) {
        showWelcomeAgree($bot, $chatId, $from);
        return;
    }

    $missing = getMissingChannels($bot, $userId);
    if ($missing) {
        markUserJoined($userId, false);
        clearBotState($userId);
        showJoinFailed($bot, $chatId, $userId, $missing);
        return;
    }
    markUserJoined($userId, true);

    $state = getBotState($userId);
    if ($state === 'withdraw_address') {
        if (isCancelText($text)) {
            clearBotState($userId);
            showMainMenu($bot, $chatId, $userId);
            return;
        }
        handleWithdrawAddress($bot, $chatId, $userId, $text);
        return;
    }
    if ($state === 'withdraw_confirm') {
        if (isCancelText($text)) {
            clearBotState($userId);
            showMainMenu($bot, $chatId, $userId);
            return;
        }
        if (isConfirmText($text)) {
            processWithdraw($bot, $chatId, $userId);
            return;
        }
        botSend($bot, $chatId, $userId, 'Please tap Confirm (MAX) or Cancel.', '', [
            'reply_markup' => TelegramBot::inlineKeyboard([
                [
                    ['text' => '✅ Confirm (MAX)', 'callback_data' => 'wd_confirm'],
                    ['text' => '❌ Cancel', 'callback_data' => 'wd_cancel'],
                ],
                [['text' => '⬅️ Back', 'callback_data' => 'go_menu']],
            ]),
        ]);
        return;
    }

    // Any other text → main menu (inline)
    showMainMenu($bot, $chatId, $userId);
}

function handleCallback(TelegramBot $bot, array $cb): void
{
    $chatId = $cb['message']['chat']['id'] ?? 0;
    $userId = (int)$cb['from']['id'];
    $data   = $cb['data'] ?? '';
    $cbId   = $cb['id'];
    ensureUser($cb['from']);

    if (isUserBlocked($userId) && $data !== 'agree_continue') {
        $bot->answerCallback($cbId, 'You are blocked', true);
        return;
    }

    if ($data === 'agree_continue') {
        markUserAgreed($userId);
        $bot->answerCallback($cbId, 'Agreed');
        showJoinScreen($bot, $chatId, $userId, getMissingChannels($bot, $userId));
        return;
    }

    if ($data === 'check_join' || $data === 'retry_join') {
        $missing = getMissingChannels($bot, $userId);
        if (!$missing) {
            markUserJoined($userId, true);
            $bot->answerCallback($cbId, 'All channels joined!');
            showMainMenu($bot, $chatId, $userId);
        } else {
            $bot->answerCallback($cbId, 'Join remaining channels first', true);
            showJoinFailed($bot, $chatId, $userId, $missing);
        }
        return;
    }

    if ($data === 'go_menu' || $data === 'nav_menu') {
        $bot->answerCallback($cbId);
        clearBotState($userId);
        showMainMenu($bot, $chatId, $userId);
        return;
    }

    // Main menu navigation (inline)
    if ($data === 'nav_wallet') {
        $bot->answerCallback($cbId);
        if (!gateUser($bot, $cbId, $chatId, $userId, $cb['from'])) {
            return;
        }
        showWallet($bot, $chatId, $userId);
        return;
    }
    if ($data === 'nav_referrals') {
        $bot->answerCallback($cbId);
        if (!gateUser($bot, $cbId, $chatId, $userId, $cb['from'])) {
            return;
        }
        showReferrals($bot, $chatId, $userId);
        return;
    }
    if ($data === 'nav_payout') {
        $bot->answerCallback($cbId);
        if (!gateUser($bot, $cbId, $chatId, $userId, $cb['from'])) {
            return;
        }
        startWithdrawFlow($bot, $chatId, $userId);
        return;
    }
    if ($data === 'nav_earn') {
        $bot->answerCallback($cbId);
        if (!gateUser($bot, $cbId, $chatId, $userId, $cb['from'])) {
            return;
        }
        showEarnMore($bot, $chatId, $userId);
        return;
    }

    if ($data === 'wd_cancel') {
        $bot->answerCallback($cbId, 'Cancelled');
        clearBotState($userId);
        showMainMenu($bot, $chatId, $userId);
        return;
    }

    if ($data === 'wd_continue') {
        $bot->answerCallback($cbId);
        if (!gateUser($bot, $cbId, $chatId, $userId, $cb['from'])) {
            return;
        }
        askWithdrawAddress($bot, $chatId, $userId);
        return;
    }

    if ($data === 'wd_confirm' || $data === 'wd_max') {
        $bot->answerCallback($cbId);
        if (!gateUser($bot, $cbId, $chatId, $userId, $cb['from'])) {
            return;
        }
        processWithdraw($bot, $chatId, $userId);
        return;
    }

    $bot->answerCallback($cbId);
}

/** Agree + channel gate for callbacks. Returns false if blocked by gate (already answered). */
function gateUser(TelegramBot $bot, string $cbId, int $chatId, int $userId, array $from): bool
{
    if (!userHasAgreed($userId)) {
        $bot->answerCallback($cbId, 'Please agree first', true);
        showWelcomeAgree($bot, $chatId, $from);
        return false;
    }
    $missing = getMissingChannels($bot, $userId);
    if ($missing) {
        markUserJoined($userId, false);
        $bot->answerCallback($cbId, 'Join channels first', true);
        showJoinFailed($bot, $chatId, $userId, $missing);
        return false;
    }
    return true;
}
