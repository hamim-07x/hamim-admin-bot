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

function stripReplyKeyboard(TelegramBot $bot, int $chatId): void
{
    try {
        $bot->sendMessage($chatId, 'ᅠ', [
            'reply_markup' => TelegramBot::removeKeyboard(),
            'disable_notification' => true,
        ]);
    } catch (Throwable $e) {
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
        stripReplyKeyboard($bot, (int)$chatId);

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
        $idCancel = btnEmojiId('ce_btn_cancel', '5210952531676504517');
        $idBack = btnEmojiId('ce_btn_back', '5416041192905265756');
        $idOk = btnEmojiId('ce_btn_agree', '5206607081334906820');
        botSend($bot, $chatId, $userId, 'Please tap Confirm (MAX) or Cancel.', '', [
            'reply_markup' => TelegramBot::inlineKeyboard([
                [
                    inlineBtn('Confirm (MAX)', 'wd_confirm', $idOk),
                    inlineBtn('Cancel', 'wd_cancel', $idCancel),
                ],
                [inlineBtn('Back', 'wd_back_address', $idBack)],
            ]),
        ]);
        return;
    }

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

    // Home
    if ($data === 'go_menu' || $data === 'nav_menu' || $data === 'wd_cancel') {
        $bot->answerCallback($cbId, $data === 'wd_cancel' ? 'Cancelled' : '');
        clearBotState($userId);
        showMainMenu($bot, $chatId, $userId);
        return;
    }

    // One-step back in withdraw flow
    if ($data === 'wd_back_start') {
        $bot->answerCallback($cbId);
        if (!gateOk($bot, $chatId, $userId, $cb['from'])) {
            return;
        }
        startWithdrawFlow($bot, $chatId, $userId);
        return;
    }
    if ($data === 'wd_back_address') {
        $bot->answerCallback($cbId);
        if (!gateOk($bot, $chatId, $userId, $cb['from'])) {
            return;
        }
        // keep address state cleared; re-ask address
        askWithdrawAddress($bot, $chatId, $userId);
        return;
    }

    if ($data === 'nav_wallet') {
        $bot->answerCallback($cbId);
        if (!gateOk($bot, $chatId, $userId, $cb['from'])) {
            return;
        }
        showWallet($bot, $chatId, $userId);
        return;
    }
    if ($data === 'nav_referrals') {
        $bot->answerCallback($cbId);
        if (!gateOk($bot, $chatId, $userId, $cb['from'])) {
            return;
        }
        showReferrals($bot, $chatId, $userId);
        return;
    }
    if ($data === 'nav_payout') {
        $bot->answerCallback($cbId);
        if (!gateOk($bot, $chatId, $userId, $cb['from'])) {
            return;
        }
        startWithdrawFlow($bot, $chatId, $userId);
        return;
    }
    if ($data === 'nav_earn') {
        $bot->answerCallback($cbId);
        if (!gateOk($bot, $chatId, $userId, $cb['from'])) {
            return;
        }
        showEarnMore($bot, $chatId, $userId);
        return;
    }

    if ($data === 'wd_continue') {
        $bot->answerCallback($cbId);
        if (!gateOk($bot, $chatId, $userId, $cb['from'])) {
            return;
        }
        askWithdrawAddress($bot, $chatId, $userId);
        return;
    }

    if ($data === 'wd_confirm' || $data === 'wd_max') {
        $bot->answerCallback($cbId);
        if (!gateOk($bot, $chatId, $userId, $cb['from'])) {
            return;
        }
        processWithdraw($bot, $chatId, $userId);
        return;
    }

    $bot->answerCallback($cbId);
}

function gateOk(TelegramBot $bot, int $chatId, int $userId, array $from): bool
{
    if (!userHasAgreed($userId)) {
        showWelcomeAgree($bot, $chatId, $from);
        return false;
    }
    $missing = getMissingChannels($bot, $userId);
    if ($missing) {
        markUserJoined($userId, false);
        showJoinFailed($bot, $chatId, $userId, $missing);
        return false;
    }
    return true;
}
