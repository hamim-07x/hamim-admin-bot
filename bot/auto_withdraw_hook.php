<?php
/** Called after a pending withdrawal is created. Auto-approve or Demo mode. */
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

    $processing  = ce('ce_payout_ok') . " <b>Withdraw request successful</b>\n\n";
    $processing .= ce('ce_receipt') . " Your request is <b>processing</b>.\n";
    $processing .= ce('ce_warn') . " It may take <b>5–10 seconds</b>.\n\n";
    $processing .= ce('ce_card') . " Address:\n<code>" . htmlspecialchars($address) . '</code>';
    botSend($bot, $chatId, $userId, $processing, 'img_payout', ['reply_markup' => backInline()]);

    require_once __DIR__ . '/PayoutService.php';
    $result = PayoutService::completeWithdrawal($wdId, $bot);

    if (!empty($result['ok'])) {
        $text  = ce('ce_payout_ok') . " <b>Payout successful</b>\n\n";
        $text .= ce('ce_balance') . " Amount: <b>{$amtShow} {$c}</b>\n";
        $text .= ce('ce_card') . " Address:\n<code>" . htmlspecialchars($address) . "</code>\n";
        $text .= ce('ce_receipt') . ' Status: <b>APPROVED</b>';
        if (!empty($result['tx'])) {
            $text .= "\n" . ce('ce_ref_2') . " Transaction:\n<code>" . htmlspecialchars($result['tx']) . '</code>';
        }
    } else {
        $text  = ce('ce_payout_no') . " <b>Could not complete</b>\n\n";
        $text .= ce('ce_warn') . ' ' . htmlspecialchars($result['error'] ?? 'pending review');
    }
    botSend($bot, $chatId, $userId, $text, 'img_payout', ['reply_markup' => backInline()]);
    return true;
}
