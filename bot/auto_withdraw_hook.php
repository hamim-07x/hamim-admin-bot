<?php
/** Called after a pending withdrawal is created. */
function tryAutoCompleteWithdrawal(TelegramBot $bot, int $chatId, int $userId, int $wdId, float $amount, string $address): bool
{
    if (getSetting('withdraw_mode', 'manual') !== 'auto' || $wdId <= 0) {
        return false;
    }
    require_once __DIR__ . '/PayoutService.php';
    $result = PayoutService::completeWithdrawal($wdId, $bot);
    $c = getSetting('currency_name', 'USDT');
    if (!empty($result['ok'])) {
        $text  = ce('ce_payout_ok') . " <b>Payout successful</b>\n\n";
        $text .= ce('ce_balance') . " Amount: <b>{$amount} {$c}</b>\n";
        $text .= ce('ce_card') . " Address: <code>" . htmlspecialchars($address) . "</code>\n";
        $text .= ce('ce_receipt') . ' Status: <b>APPROVED</b>';
        if (!empty($result['tx'])) {
            $text .= "\n" . ce('ce_ref_2') . " Transaction:\n<code>" . htmlspecialchars($result['tx']) . '</code>';
        }
    } else {
        $text  = ce('ce_payout_ok') . " <b>Payout request submitted</b>\n\n";
        $text .= ce('ce_balance') . " Amount: <b>{$amount} {$c}</b>\n";
        $text .= ce('ce_card') . " Address: <code>" . htmlspecialchars($address) . "</code>\n";
        $text .= ce('ce_warn') . ' Auto-pay: ' . htmlspecialchars($result['error'] ?? 'pending review');
    }
    botSend($bot, $chatId, $userId, $text, 'img_payout', ['reply_markup' => backInline()]);
    return true;
}
