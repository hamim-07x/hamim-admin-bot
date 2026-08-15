<?php
/**
 * Complete withdrawal:
 * - Manual (Demo OFF + Auto OFF): admin approves → notify user + channel
 * - BSC + Demo OFF → real on-chain
 * - Demo ON or TON/GRAM → soft complete + same notifications
 */
require_once __DIR__ . '/BscTokenSender.php';
require_once __DIR__ . '/PaymentNetwork.php';

class PayoutService
{
    /**
     * @param array{notify_user?:bool,notify_channel?:bool,from_admin?:bool} $opts
     * @return array{ok:bool,tx?:string,error?:string,status?:string}
     */
    public static function completeWithdrawal(int $id, ?TelegramBot $bot = null, array $opts = []): array
    {
        $notifyUser = $opts['notify_user'] ?? true;
        $notifyChannel = $opts['notify_channel'] ?? true;

        $db = getDB();
        $stmt = $db->prepare('SELECT * FROM withdrawals WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $w = $stmt->fetch();
        if (!$w) {
            return ['ok' => false, 'error' => 'not found'];
        }
        if (($w['status'] ?? '') !== 'pending') {
            return ['ok' => false, 'error' => 'already processed'];
        }

        $amountRaw = (float)$w['amount'];
        $amountStr = rtrim(rtrim(sprintf('%.8f', $amountRaw), '0'), '.');
        if ($amountStr === '' || $amountStr === '.') {
            $amountStr = '0';
        }
        $address = trim((string)$w['address']);

        $demo = getSetting('demo_payment', '0') === '1';
        $net = activePaymentNetwork();

        $txHash = '';
        if ($net === 'bsc' && !$demo) {
            $sender = BscTokenSender::fromSettings();
            if (!$sender) {
                return ['ok' => false, 'error' => 'BSC: set hot wallet private key + contract in Payment Settings'];
            }
            $res = $sender->transfer($address, $amountStr);
            if ($res['ok'] ?? false) {
                $txHash = (string)($res['tx'] ?? '');
            } else {
                return ['ok' => false, 'error' => (string)($res['error'] ?? 'send failed')];
            }
        } else {
            if ($net === 'ton') {
                $txHash = strtoupper(bin2hex(random_bytes(16))) . bin2hex(random_bytes(16));
            } else {
                $txHash = '0x' . bin2hex(random_bytes(32));
            }
        }

        $status = 'paid';

        try {
            $cols = $db->query("SHOW COLUMNS FROM withdrawals LIKE 'tx_hash'")->fetch();
            if (!$cols) {
                $db->exec("ALTER TABLE withdrawals ADD COLUMN tx_hash VARCHAR(128) DEFAULT NULL");
            }
            $db->prepare('UPDATE withdrawals SET status=?, processed_at=NOW(), tx_hash=? WHERE id=?')
                ->execute([$status, $txHash !== '' ? $txHash : null, $id]);
        } catch (Throwable $e) {
            $db->prepare('UPDATE withdrawals SET status=?, processed_at=NOW() WHERE id=?')->execute([$status, $id]);
        }

        try {
            $db->prepare("UPDATE transactions SET status='completed' WHERE user_id=? AND type='withdraw' AND status='pending' AND note=?")
                ->execute([(int)$w['user_id'], $address]);
        } catch (Throwable $e) {
        }

        $token = trim((string)getSetting('bot_token', ''));
        if ($token !== '' && ($notifyUser || $notifyChannel)) {
            if (!$bot) {
                require_once __DIR__ . '/TelegramBot.php';
                require_once __DIR__ . '/helpers.php';
                $bot = new TelegramBot($token);
            }
            self::sendNotifications($bot, $w, $status, $txHash, $notifyUser, $notifyChannel);
        }

        return ['ok' => true, 'tx' => $txHash, 'status' => $status];
    }

    public static function payoutSuccessMarkup(): array
    {
        if (!function_exists('viewPaymentChannelMarkup')) {
            require_once __DIR__ . '/auto_withdraw_hook.php';
        }
        if (function_exists('viewPaymentChannelMarkup')) {
            return viewPaymentChannelMarkup();
        }
        return TelegramBot::inlineKeyboard([[
            ['text' => 'Back', 'callback_data' => 'go_menu'],
        ]]);
    }

    private static function sendNotifications(
        TelegramBot $bot,
        array $w,
        string $status,
        string $txHash,
        bool $notifyUser = true,
        bool $notifyChannel = true
    ): void {
        require_once __DIR__ . '/helpers.php';
        $userId = (int)$w['user_id'];
        $amountRaw = (float)$w['amount'];
        $amount = rtrim(rtrim(number_format($amountRaw, 6, '.', ''), '0'), '.');
        if ($amount === '') {
            $amount = '0';
        }
        $address = (string)$w['address'];
        $c = getSetting('currency_name', 'USDT');
        $s = getSetting('currency_symbol', '$');

        $successPhoto = '';
        if (getSetting('img_payout_success_on', '0') === '1') {
            $successPhoto = trim((string)getSetting('img_payout_success', ''));
        }

        $payChannelRaw = trim((string)getSetting('payment_channel', ''));
        if ($payChannelRaw === '') {
            $payChannelRaw = trim((string)getSetting('notify_channel', ''));
        }

        if ($notifyUser && getSetting('user_payout_alert', '1') === '1') {
            $html  = "🎟 <b>Payout successful</b>\n\n";
            $html .= "💵 Amount: <b>{$s}{$amount} {$c}</b>\n";
            $html .= "💳 Address:\n<code>" . htmlspecialchars($address) . "</code>\n";
            $html .= "🧾 Status: <b>COMPLETE</b> ✅";
            if ($txHash !== '') {
                $html .= "\n\n🔗 Transaction:\n<code>" . htmlspecialchars($txHash) . '</code>';
            }
            $extra = [
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true,
                'reply_markup' => self::payoutSuccessMarkup(),
            ];
            if ($successPhoto !== '') {
                $bot->sendPhoto($userId, $successPhoto, $html, $extra);
            } else {
                $bot->sendMessage($userId, $html, $extra);
            }
        }

        if ($notifyChannel && $payChannelRaw !== '') {
            $chat = $payChannelRaw;
            if (!str_starts_with($chat, '@') && !str_starts_with($chat, '-') && preg_match('/^\d+$/', $chat)) {
                $chat = (strlen($chat) < 14) ? '-100' . $chat : $chat;
            } elseif (!str_starts_with($chat, '@') && !str_starts_with($chat, '-')) {
                $chat = '@' . ltrim($chat, '@');
            }
            $lines = [
                '🎟 <b>New Payout successfully Paid</b>',
                '',
                '👤 User ID: <code>' . $userId . '</code>',
                '💵 Amount: <b>' . $s . $amount . ' ' . $c . '</b>',
                '💳 Address:',
                '<code>' . htmlspecialchars($address) . '</code>',
                '🧾 Status: <b>COMPLETE</b> ✅',
            ];
            if ($txHash !== '') {
                $lines[] = '';
                $lines[] = '🔗 Transaction:';
                $lines[] = '<code>' . htmlspecialchars($txHash) . '</code>';
            }
            $html = implode("\n", $lines);
            $extra = ['parse_mode' => 'HTML', 'disable_web_page_preview' => true];
            $botUser = ltrim((string)getSetting('bot_username', ''), '@');
            if ($botUser !== '') {
                $btnText = trim((string)getSetting('notify_btn_text', 'Start Bot')) ?: 'Start Bot';
                $btn = ['text' => $btnText, 'url' => 'https://t.me/' . $botUser . '?start=1'];
                $extra['reply_markup'] = ['inline_keyboard' => [[$btn]]];
            }
            if ($successPhoto !== '') {
                $bot->sendPhoto($chat, $successPhoto, $html, $extra);
            } else {
                $bot->sendMessage($chat, $html, $extra);
            }
        }
    }
}
