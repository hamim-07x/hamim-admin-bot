<?php
/**
 * Telegram Bot API helper
 */

class TelegramBot
{
    private string $token;
    private string $api;

    public function __construct(string $token)
    {
        $this->token = $token;
        $this->api = 'https://api.telegram.org/bot' . $token . '/';
    }

    public function request(string $method, array $params = []): ?array
    {
        if (isset($params['reply_markup']) && is_array($params['reply_markup'])) {
            $params['reply_markup'] = json_encode($params['reply_markup'], JSON_UNESCAPED_UNICODE);
        }

        $url = $this->api . $method;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $params,
            CURLOPT_TIMEOUT        => 30,
        ]);
        $res = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        if ($res === false) {
            error_log('[TG] curl error: ' . $err);
            return null;
        }
        $data = json_decode($res, true);
        if (!($data['ok'] ?? false)) {
            error_log('[TG] API ' . $method . ': ' . ($data['description'] ?? $res));
        }
        return $data;
    }

    public function sendMessage(int|string $chatId, string $text, array $extra = []): ?array
    {
        return $this->request('sendMessage', array_merge([
            'chat_id'    => $chatId,
            'text'       => $text,
            'parse_mode' => 'HTML',
        ], $extra));
    }

    public function sendPhoto(int|string $chatId, string $photoUrl, string $caption = '', array $extra = []): ?array
    {
        $params = array_merge([
            'chat_id'    => $chatId,
            'photo'      => $photoUrl,
            'parse_mode' => 'HTML',
        ], $extra);
        if ($caption !== '') {
            $params['caption'] = $caption;
        }
        return $this->request('sendPhoto', $params);
    }

    public function deleteMessage(int|string $chatId, int $messageId): ?array
    {
        return $this->request('deleteMessage', [
            'chat_id'    => $chatId,
            'message_id' => $messageId,
        ]);
    }

    public function editMessage(int|string $chatId, int $messageId, string $text, array $extra = []): ?array
    {
        return $this->request('editMessageText', array_merge([
            'chat_id'    => $chatId,
            'message_id' => $messageId,
            'text'       => $text,
            'parse_mode' => 'HTML',
        ], $extra));
    }

    public function answerCallback(string $callbackId, string $text = '', bool $showAlert = false): ?array
    {
        return $this->request('answerCallbackQuery', [
            'callback_query_id' => $callbackId,
            'text'              => $text,
            'show_alert'        => $showAlert ? 'true' : 'false',
        ]);
    }

    public function getChatMember(string $chatId, int $userId): ?array
    {
        return $this->request('getChatMember', [
            'chat_id' => $chatId,
            'user_id' => $userId,
        ]);
    }

    public function isUserJoined(string $channelUsername, int $userId): bool
    {
        $chat = str_starts_with($channelUsername, '@')
            ? $channelUsername
            : '@' . ltrim($channelUsername, '@');
        $res = $this->getChatMember($chat, $userId);
        if (!$res || !($res['ok'] ?? false)) {
            return false;
        }
        $status = $res['result']['status'] ?? '';
        return in_array($status, ['member', 'administrator', 'creator'], true);
    }

    public static function inlineKeyboard(array $rows): array
    {
        return ['inline_keyboard' => $rows];
    }

    public static function replyKeyboard(array $rows, bool $resize = true): array
    {
        return [
            'keyboard'          => $rows,
            'resize_keyboard'   => $resize,
            'one_time_keyboard' => false,
        ];
    }

    public static function removeKeyboard(): array
    {
        return ['remove_keyboard' => true];
    }

    public static function mainMenuKeyboard(string $currency = 'USDT'): array
    {
        return self::mainMenuKeyboardFromLabels([
            'wallet'    => "{$currency} Wallet",
            'referrals' => 'Referrals',
            'payout'    => "{$currency} Payout",
            'earn'      => 'EARN MORE',
        ]);
    }

    public static function mainMenuKeyboardFromLabels(array $labels): array
    {
        return self::replyKeyboard([
            [$labels['wallet'] ?? 'Wallet', $labels['referrals'] ?? 'Referrals'],
            [$labels['payout'] ?? 'Payout', $labels['earn'] ?? 'EARN MORE'],
        ]);
    }
}
