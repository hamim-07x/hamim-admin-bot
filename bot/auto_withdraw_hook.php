<?php
/** Called after a pending withdrawal is created. */
function tryAutoCompleteWithdrawal(TelegramBot $bot, int $chatId, int $userId, int $wdId, float $amount, string $address): bool
{
    if (getSetting('withdraw_mode', 'manual') !== 'auto' || $wdId <= 0) {
        return false;
    }

    $c = getSetting('currency_name', 'USDT');
    $amtShow = rtrim(rtrim(number_format($amount, 8, '.', ''), '0'), '.');
    if ($amtShow === '') {
        $amtShow = '0';
    }

    // Processing / loading message first (10–20 sec expectation)
    $processing  = ce('ce_warn') . " <b>Payout processing…</b>\n\n";
    $processing .= ce('ce_balance') . " Amount: <b>{$amtShow} {$c}</b>\n";
    $processing .= ce('ce_card') . " Address:\n<code>" . htmlspecialchars($address) . "</code>\n\n";
    $processing .= ce('ce_receipt') . " Status: <b>PROCESSING</b>\n";
    $processing .= ce('ce_network') . " Usually completes in <b>10–20 seconds</b>.\n";
    $processing .= ce('ce_welcome_6') . ' Please wait — do not resubmit.';
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
        $text  = ce('ce_payout_no') . " <b>Auto-pay could not complete</b>\n\n";
        $text .= ce('ce_balance') . " Amount: <b>{$amtShow} {$c}</b>\n";
        $text .= ce('ce_card') . " Address:\n<code>" . htmlspecialchars($address) . "</code>\n";
        $text .= ce('ce_warn') . ' ' . htmlspecialchars($result['error'] ?? 'pending admin review');
        $text .= "\n" . ce('ce_receipt') . ' Request stays pending if send failed.';
    }
    botSend($bot, $chatId, $userId, $text, 'img_payout', ['reply_markup' => backInline()]);
    return true;
}
