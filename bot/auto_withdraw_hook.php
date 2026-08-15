<?php
/**
 * Auto / Demo: processing (few seconds) → success + View Channel + Back
 */
function viewPaymentChannelMarkup(): array
{
    $rows = [];
    $payChannelRaw = trim((string)getSetting('payment_channel', ''));
    if ($payChannelRaw === '') {
        $payChannelRaw = trim((string)getSetting('notify_channel', ''));
    }
    if ($payChannelRaw !== '' && !preg_match('/^-?\d+$/', $payChannelRaw)) {
        $channelLink = 'https://t.me/' . ltrim($payChannelRaw, '@');
        $viewText = trim((string)getSetting('user_channel_btn_text', 'View Payment Channel')) ?: 'View Payment Channel';
        $viewIcon = preg_replace('/\D+/', '', (string)getSetting('user_channel_btn_emoji_id', '5332455502917949981'));
        $btn = ['text' => $viewText, 'url' => $channelLink];
        if (strlen($viewIcon) >= 8) {
            $btn['icon_custom_emoji_id'] = $viewIcon;
        }
        $rows[] = [$btn];
    }
    $idBack = function_exists('btnEmojiId')
        ? btnEmojiId('ce_btn_back', '5416041192905265756')
        : '5416041192905265756';
    $backBtn = ['text' => 'Back', 'callback_data' => 'go_menu'];
    if (strlen($idBack) >= 8) {
        $backBtn['icon_custom_emoji_id'] = $idBack;
    }
    $rows[] = [$backBtn];
    return TelegramBot::inlineKeyboard($rows);
}

function tryAutoCompleteWithdrawal(TelegramBot $bot, int $chatId, int $userId, int $wdId, float $amount, string $address): bool
{
    $demo = getSetting('demo_payment', '0') === '1';
    $auto = getSetting('withdraw_mode', 'manual') === 'auto';
    if ((!$demo && !$auto) || $wdId <= 0) {
        return false;
    }

    $c = getSetting('currency_name', 'USDT');
    $amtShow = rtrim(rtrim(number_format($amount, 8, '.', ''), '0'), '.');
    if ($amtShow === '') {
        $amtShow = '0';
    }

    $waitSec = random_int(5, 10);
    $text  = "🎟 <b>Payout request submitted</b>\n\n";
    $text .= "💵 Amount: <b>{$amtShow} {$c}</b>\n";
    $text .= "💳 Address:\n<code>" . htmlspecialchars($address) . "</code>\n";
    $text .= "🧾 Status: <b>Processing</b>\n";
    $text .= '⏳ Processing will take a <b>few seconds</b>. Please wait…';
    botSend($bot, $chatId, $userId, $text, 'img_payout', [
        'reply_markup' => TelegramBot::removeKeyboard(),
    ]);

    sleep($waitSec);

    require_once __DIR__ . '/PayoutService.php';
    $result = PayoutService::completeWithdrawal($wdId, $bot, [
        'notify_user' => false,
        'notify_channel' => true,
    ]);

    if (!empty($result['ok'])) {
        $ok  = "🎟 <b>Payout successful</b>\n\n";
        $ok .= "💵 Amount: <b>{$amtShow} {$c}</b>\n";
        $ok .= "💳 Address:\n<code>" . htmlspecialchars($address) . "</code>\n";
        $ok .= '🧾 Status: <b>COMPLETE</b> ✅';
        if (!empty($result['tx'])) {
            $ok .= "\n\n🔗 Transaction:\n<code>" . htmlspecialchars($result['tx']) . '</code>';
        }
        botSend($bot, $chatId, $userId, $ok, 'img_payout', [
            'reply_markup' => viewPaymentChannelMarkup(),
        ]);
    } else {
        $err  = "❌ <b>Could not complete</b>\n\n";
        $err .= '⚠️ ' . htmlspecialchars($result['error'] ?? 'pending review');
        botSend($bot, $chatId, $userId, $err, 'img_payout', [
            'reply_markup' => viewPaymentChannelMarkup(),
        ]);
    }
    return true;
}
