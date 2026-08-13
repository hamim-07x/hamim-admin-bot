<?php
/**
 * Bot handlers — no slash-commands menu; message screens + optional photo URL
 * Auto-deletes previous bot messages to keep chat clean.
 */

require_once __DIR__ . '/TelegramBot.php';
require_once __DIR__ . '/helpers.php';

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
        botSend($bot, $chatId, $userId, "You are blocked from using this bot.");
        return;
    }

    if (str_starts_with($text, '/start')) {
        $parts = explode(' ', $text, 2);
        if (!empty($parts[1])) {
            applyReferral($userId, $parts[1]);
        }
        clearBotState($userId);
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

    if (str_starts_with($text, '/')) {
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
        botSend($bot, $chatId, $userId, 'Please tap <b>Confirm (MAX)</b> or <b>Cancel</b>.');
        return;
    }

    $labels = menuLabels();
    if ($text === $labels['wallet'] || str_contains($text, 'Wallet')) {
        showWallet($bot, $chatId, $userId);
    } elseif ($text === $labels['referrals'] || str_contains($text, 'Referral')) {
        showReferrals($bot, $chatId, $userId);
    } elseif ($text === $labels['payout'] || str_contains($text, 'Payout')) {
        startWithdrawFlow($bot, $chatId, $userId);
    } elseif ($text === $labels['earn'] || str_contains($text, 'EARN') || str_contains($text, 'Earn')) {
        showEarnMore($bot, $chatId, $userId);
    } else {
        showMainMenu($bot, $chatId, $userId);
    }
}

function handleCallback(TelegramBot $bot, array $cb): void
{
    $chatId = $cb['message']['chat']['id'];
    $msgId  = $cb['message']['message_id'];
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
        $missing = getMissingChannels($bot, $userId);
        showJoinScreen($bot, $chatId, $userId, $missing);
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

    if ($data === 'show_join') {
        $bot->answerCallback($cbId);
        showJoinScreen($bot, $chatId, $userId, getMissingChannels($bot, $userId));
        return;
    }

    if ($data === 'go_menu') {
        $bot->answerCallback($cbId);
        clearBotState($userId);
        showMainMenu($bot, $chatId, $userId);
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
        setBotState($userId, 'withdraw_address');
        $c = getSetting('currency_name', 'USDT');
        $text = "<b>{$c} Payout</b>\n\nSend your <b>BEP-20 (BSC)</b> wallet address:\n\nExample: <code>0x...</code>\n\nOr tap Cancel";
        $kb = TelegramBot::inlineKeyboard([
            [['text' => 'Cancel', 'callback_data' => 'wd_cancel']],
            [['text' => 'Back', 'callback_data' => 'go_menu']],
        ]);
        botSend($bot, $chatId, $userId, $text, 'img_payout', ['reply_markup' => $kb]);
        return;
    }
    if ($data === 'wd_confirm' || $data === 'wd_max') {
        $bot->answerCallback($cbId);
        processWithdraw($bot, $chatId, $userId);
        return;
    }

    if (!userHasAgreed($userId)) {
        $bot->answerCallback($cbId, 'Please agree first', true);
        showWelcomeAgree($bot, $chatId, $cb['from']);
        return;
    }
    $missing = getMissingChannels($bot, $userId);
    if ($missing) {
        markUserJoined($userId, false);
        $bot->answerCallback($cbId, 'Join channels first', true);
        showJoinFailed($bot, $chatId, $userId, $missing);
        return;
    }

    $bot->answerCallback($cbId);
}
