<?php
require_once __DIR__ . '/TelegramBot.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/screens_ui.php';
require_once __DIR__ . '/screens_user.php';
require_once __DIR__ . '/broadcast.php';

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

function tryDeleteUserMessage(TelegramBot $bot, array $message): void
{
    $chatId = $message['chat']['id'] ?? null;
    $mid = $message['message_id'] ?? null;
    if ($chatId === null || $mid === null) {
        return;
    }
    try {
        $bot->deleteMessage($chatId, (int)$mid);
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

    $isStart = str_starts_with($text, '/start');
    $stateNow = getBotState($userId);
    $bcStates = ['broadcast_wait', 'broadcast_preview', 'broadcast_btn_text', 'broadcast_btn_url'];
    if (!$isStart && !in_array($stateNow, $bcStates, true)) {
        tryDeleteUserMessage($bot, $message);
    }

    if (isUserBlocked($userId)) {
        botSend($bot, $chatId, $userId, 'You are blocked from using this bot.');
        return;
    }

    if ($text === '/broadcast' || str_starts_with($text, '/broadcast@')) {
        if (!isBotAdmin($userId)) {
            botSend($bot, $chatId, $userId, ce('ce_payout_no') . ' You are not a bot admin.');
            return;
        }
        showBroadcastPanel($bot, $chatId, $userId);
        return;
    }
    if ($text === '/cancel' || str_starts_with($text, '/cancel@')) {
        if (in_array(getBotState($userId), $bcStates, true)) {
            clearBotState($userId);
            botSend($bot, $chatId, $userId, ce('ce_ok') . ' Broadcast cancelled.');
            return;
        }
    }

    $state = getBotState($userId);
    if (in_array($state, $bcStates, true)) {
        if (!isBotAdmin($userId)) {
            clearBotState($userId);
            botSend($bot, $chatId, $userId, ce('ce_payout_no') . ' You are not a bot admin.');
            return;
        }

        if ($state === 'broadcast_wait') {
            $mid = (int)($message['message_id'] ?? 0);
            if ($mid <= 0) {
                botSend($bot, $chatId, $userId, ce('ce_warn') . ' Could not read message. Try again.');
                return;
            }
            $payload = prepareBroadcastPayload($message);
            showBroadcastPreview($bot, (int)$chatId, $userId, $payload, []);
            return;
        }

        if ($state === 'broadcast_btn_text') {
            $data = getBotStateData($userId);
            if ($text === '') {
                botSend($bot, $chatId, $userId, ce('ce_warn') . ' Send button text (e.g. Open Channel).');
                return;
            }
            $data['pending_btn_text'] = mb_substr($text, 0, 64);
            setBotState($userId, 'broadcast_btn_url', $data);
            botSend($bot, $chatId, $userId, ce('ce_card') . " Now send the <b>URL</b> for this button (https://…)", '', [
                'reply_markup' => TelegramBot::inlineKeyboard([
                    [inlineBtn('Cancel', 'bc_cancel', btnEmojiId('ce_btn_cancel', '5019523782004441717'))],
                ]),
            ]);
            return;
        }

        if ($state === 'broadcast_btn_url') {
            $data = getBotStateData($userId);
            $url = trim($text);
            if (!preg_match('#^https?://#i', $url)) {
                botSend($bot, $chatId, $userId, ce('ce_warn') . ' Invalid URL. Send full link starting with https://');
                return;
            }
            $buttons = $data['buttons'] ?? [];
            if (!is_array($buttons)) {
                $buttons = [];
            }
            $buttons[] = [
                'text' => (string)($data['pending_btn_text'] ?? 'Open'),
                'url'  => $url,
            ];
            $buttons = array_slice($buttons, 0, 5);
            unset($data['pending_btn_text'], $data['buttons']);
            showBroadcastPreview($bot, (int)$chatId, $userId, $data, $buttons);
            return;
        }

        botSend($bot, $chatId, $userId, ce('ce_warn') . ' Use Confirm / Cancel / Add Button below.');
        return;
    }

    if ($isStart) {
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
        tryGrantReferralBonus($bot, $userId);
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
    tryGrantReferralBonus($bot, $userId);

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
        $idCancel = btnEmojiId('ce_btn_cancel', '5019523782004441717');
        $idBack = btnEmojiId('ce_btn_back', '5854967531793550989');
        $idOk = btnEmojiId('ce_btn_agree', '5021905410089550576');
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

    if (str_starts_with($data, 'bc_')) {
        if (!isBotAdmin($userId)) {
            $bot->answerCallback($cbId, 'Not a bot admin', true);
            return;
        }
        if ($data === 'bc_start') {
            $bot->answerCallback($cbId, 'Send content');
            askBroadcastContent($bot, $chatId, $userId);
            return;
        }
        if ($data === 'bc_cancel') {
            $bot->answerCallback($cbId, 'Cancelled');
            clearBotState($userId);
            showBroadcastPanel($bot, $chatId, $userId);
            return;
        }
        if ($data === 'bc_confirm') {
            $st = getBotStateData($userId);
            $okPayload = !empty($st['html']) || !empty($st['photo_file_id']) || ((int)($st['message_id'] ?? 0) > 0);
            if (!$okPayload) {
                $bot->answerCallback($cbId, 'No message', true);
                clearBotState($userId);
                return;
            }
            $bot->answerCallback($cbId, 'Starting…');
            startBroadcastJob($bot, (int)$chatId, $userId, $st);
            return;
        }
        if ($data === 'bc_add_btn') {
            $st = getBotStateData($userId);
            if (getBotState($userId) !== 'broadcast_preview') {
                $bot->answerCallback($cbId, 'Send content first', true);
                return;
            }
            $bot->answerCallback($cbId, 'Button text');
            setBotState($userId, 'broadcast_btn_text', $st);
            botSend($bot, $chatId, $userId, ce('ce_card') . " Send <b>button name</b> (e.g. Join Channel)", '', [
                'reply_markup' => TelegramBot::inlineKeyboard([
                    [inlineBtn('Cancel', 'bc_cancel', btnEmojiId('ce_btn_cancel', '5019523782004441717'))],
                ]),
            ]);
            return;
        }
        $bot->answerCallback($cbId);
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
            tryGrantReferralBonus($bot, $userId);
            $bot->answerCallback($cbId, 'All channels joined!');
            showMainMenu($bot, $chatId, $userId);
        } else {
            $bot->answerCallback($cbId, 'Join remaining channels first', true);
            showJoinFailed($bot, $chatId, $userId, $missing);
        }
        return;
    }

    if ($data === 'go_menu' || $data === 'nav_menu' || $data === 'wd_cancel') {
        $bot->answerCallback($cbId, $data === 'wd_cancel' ? 'Cancelled' : '');
        clearBotState($userId);
        showMainMenu($bot, $chatId, $userId);
        return;
    }

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
    tryGrantReferralBonus($bot, $userId);
    return true;
}
