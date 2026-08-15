<?php
/**
 * Complete a pending withdrawal: optional on-chain BEP-20 send + status + notify.
 * Demo mode: looks real (fake tx hash + notifications) but never sends on-chain.
 */
require_once __DIR__ . '/BscTokenSender.php';

class PayoutService
{
    /** @return array{ok:bool,tx?:string,error?:string,status?:string} */
    public static function completeWithdrawal(int $id, ?TelegramBot $bot = null): array
    {
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
        $address = (string)$w['address'];

        $demo = getSetting('demo_payment', '0') === '1';
        $autoSend = !$demo && getSetting('auto_send_enabled', '0') === '1';

        $txHash = '';
        if ($autoSend) {
            $sender = BscTokenSender::fromSettings();
            if ($sender) {
                $res = $sender->transfer($address, (string)$amountRaw);
                if ($res['ok'] ?? false) {
                    $txHash = (string)($res['tx'] ?? '');
                } else {
                    return ['ok' => false, 'error' => (string)($res['error'] ?? 'send failed')];
                }
            } else {
                return ['ok' => false, 'error' => 'Wallet/RPC not configured'];
            }
        } else {
            $txHash = '0x' . bin2hex(random_bytes(32));
        }

        $status = 'approved';
        if ($demo || getSetting('withdraw_mode', 'manual') === 'auto' || ($autoSend && $txHash !== '')) {
            $status = 'paid';
        }

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

        $token = trim((string)getSetting('bot_token', ''));
        if ($token !== '') {
            if (!$bot) {
                require_once __DIR__ . '/TelegramBot.php';
                require_once __DIR__ . '/helpers.php';
                $bot = new TelegramBot($token);
            }
            self::sendNotifications($bot, $w, $status, $txHash);
        }

        return ['ok' => true, 'tx' => $txHash, 'status' => $status];
    }

    private static function sendNotifications(TelegramBot $bot, array $w, string $status, string $txHash): void
    {
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

        if (getSetting('user_payout_alert', '1') === '1') {
            $html  = ce('ce_payout_ok') . " <b>Payout successful</b>\n\n";
            $html .= ce('ce_balance') . " Amount: <b>{$s}{$amount} {$c}</b>\n";
            $html .= ce('ce_card') . " Address:\n<code>" . htmlspecialchars($address) . "</code>\n";
            $html .= ce('ce_receipt') . " Status: <b>APPROVED</b>\n";
            if ($txHash !== '') {
                $html .= ce('ce_ref_2') . " Transaction:\n<code>" . htmlspecialchars($txHash) . '</code>';
            }
            $extra = ['parse_mode' => 'HTML', 'disable_web_page_preview' => true];
            $ch = trim($payChannelRaw);
            $channelLink = '';
            if ($ch !== '' && !preg_match('/^-?\d+$/', $ch)) {
                $channelLink = 'https://t.me/' . ltrim($ch, '@');
            }
            if ($channelLink !== '') {
                $viewText = trim((string)getSetting('user_channel_btn_text', 'View Payment Channel')) ?: 'View Payment Channel';
                $viewIcon = preg_replace('/\D+/', '', (string)getSetting('user_channel_btn_emoji_id', '5332455502917949981'));
                $btn = ['text' => $viewText, 'url' => $channelLink];
                if (strlen($viewIcon) >= 8) {
                    $btn['icon_custom_emoji_id'] = $viewIcon;
                }
                $extra['reply_markup'] = ['inline_keyboard' => [[$btn]]];
            }
            if ($successPhoto !== '') {
                $bot->sendPhoto($userId, $successPhoto, $html, $extra);
            } else {
                $bot->sendMessage($userId, $html, $extra);
            }
        }

        if ($payChannelRaw !== '') {
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
                '🧾 Status: <b>COMPLETE</b>',
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
                $btnIcon = preg_replace('/\D+/', '', (string)getSetting('notify_btn_emoji_id', '5416041192905265756'));
                $btn = ['text' => $btnText, 'url' => 'https://t.me/' . $botUser . '?start=1'];
                if (strlen($btnIcon) >= 8) {
                    $btn['icon_custom_emoji_id'] = $btnIcon;
                }
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
