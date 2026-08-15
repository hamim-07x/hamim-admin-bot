<?php
/**
 * Auto-approve or Demo: same "request submitted" style message,
 * real wait 5–10 seconds, then complete once (no duplicate user spam).
 */
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
    botSend($bot, $chatId, $userId, $text, 'img_payout', ['reply_markup' => backInline()]);

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
        botSend($bot, $chatId, $userId, $ok, 'img_payout', ['reply_markup' => backInline()]);
    } else {
        $err  = ce('ce_payout_no') . " <b>Could not complete</b>\n\n";
        $err .= ce('ce_warn') . ' ' . htmlspecialchars($result['error'] ?? 'pending review');
        botSend($bot, $chatId, $userId, $err, 'img_payout', ['reply_markup' => backInline()]);
    }
    return true;
}
