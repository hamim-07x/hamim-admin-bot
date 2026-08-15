<?php
/**
 * Auto-approve or Demo:
 * processing → main menu while waiting → success with View Payment Channel only (no Back)
 */
function viewPaymentChannelMarkup(): array
{
    $payChannelRaw = trim((string)getSetting('payment_channel', ''));
    if ($payChannelRaw === '') {
        $payChannelRaw = trim((string)getSetting('notify_channel', ''));
    }
    $channelLink = '';
    if ($payChannelRaw !== '' && !preg_match('/^-?\d+$/', $payChannelRaw)) {
        $channelLink = 'https://t.me/' . ltrim($payChannelRaw, '@');
    }
    if ($channelLink === '') {
        return backInline();
    }
    $viewText = trim((string)getSetting('user_channel_btn_text', 'View Payment Channel')) ?: 'View Payment Channel';
    $viewIcon = preg_replace('/\D+/', '', (string)getSetting('user_channel_btn_emoji_id', '5332455502917949981'));
    $btn = ['text' => $viewText, 'url' => $channelLink];
    if (strlen($viewIcon) >= 8) {
        $btn['icon_custom_emoji_id'] = $viewIcon;
    }
    return TelegramBot::inlineKeyboard([[$btn]]);
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
    $text  = ce('ce_payout_ok') . " <b>Payout request submitted</b>\n\n";
    $text .= ce('ce_balance') . " Amount: <b>{$amtShow} {$c}</b>\n";
    $text .= ce('ce_card') . " Address:\n<code>" . htmlspecialchars($address) . "</code>\n";
    $text .= ce('ce_receipt') . " Status: <b>Processing</b>\n";
    $text .= ce('ce_warn') . " Auto-complete in about <b>{$waitSec} seconds</b>. Please wait.";
    botSend($bot, $chatId, $userId, $text, 'img_payout', ['reply_markup' => TelegramBot::removeKeyboard()]);

    showMainMenu($bot, $chatId, $userId);

    sleep($waitSec);

    require_once __DIR__ . '/PayoutService.php';
    $result = PayoutService::completeWithdrawal($wdId, $bot, [
        'notify_user' => false,
        'notify_channel' => true,
    ]);

    if (!empty($result['ok'])) {
        $ok  = ce('ce_payout_ok') . " <b>Payout successful</b>\n\n";
        $ok .= ce('ce_balance') . " Amount: <b>{$amtShow} {$c}</b>\n";
        $ok .= ce('ce_card') . " Address:\n<code>" . htmlspecialchars($address) . "</code>\n";
        $ok .= ce('ce_receipt') . ' Status: <b>APPROVED</b>';
        if (!empty($result['tx'])) {
            $ok .= "\n" . ce('ce_ref_2') . " Transaction:\n<code>" . htmlspecialchars($result['tx']) . '</code>';
        }
        botSend($bot, $chatId, $userId, $ok, 'img_payout', ['reply_markup' => viewPaymentChannelMarkup()]);
    } else {
        $err  = ce('ce_payout_no') . " <b>Could not complete</b>\n\n";
        $err .= ce('ce_warn') . ' ' . htmlspecialchars($result['error'] ?? 'pending review');
        botSend($bot, $chatId, $userId, $err, 'img_payout', ['reply_markup' => backInline()]);
    }
    return true;
}
