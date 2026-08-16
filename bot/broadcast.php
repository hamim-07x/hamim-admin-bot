<?php
/**
 * Scalable bot-admin broadcast with premium emoji upgrade.
 */
require_once __DIR__ . '/premium_emojis.php';

function isBotAdmin(int $userId): bool
{
    $raw = trim((string)getSetting('bot_admin_ids', ''));
    if ($raw === '') {
        return false;
    }
    foreach (preg_split('/[\s,;]+/', $raw) as $part) {
        $part = trim($part);
        if ($part !== '' && ctype_digit($part) && (int)$part === $userId) {
            return true;
        }
    }
    return false;
}

function ensureBroadcastTable(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        $db = getDB();
        $db->exec(
            "CREATE TABLE IF NOT EXISTS broadcast_jobs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                admin_id BIGINT NOT NULL,
                admin_chat_id BIGINT NOT NULL,
                from_chat_id BIGINT NOT NULL,
                message_id INT NOT NULL DEFAULT 0,
                buttons_json TEXT NULL,
                content_json TEXT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'pending',
                offset_id BIGINT NOT NULL DEFAULT 0,
                total INT NOT NULL DEFAULT 0,
                ok_count INT NOT NULL DEFAULT 0,
                fail_count INT NOT NULL DEFAULT 0,
                progress_mid INT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX (status),
                INDEX (offset_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
        try {
            if (!$db->query("SHOW COLUMNS FROM broadcast_jobs LIKE 'content_json'")->fetch()) {
                $db->exec('ALTER TABLE broadcast_jobs ADD COLUMN content_json TEXT NULL');
            }
        } catch (Throwable $e) {
        }
    } catch (Throwable $e) {
        error_log('[BC] ensure table: ' . $e->getMessage());
    }
}

function broadcastWorkerSecret(): string
{
    $s = getenv('BROADCAST_SECRET') ?: getenv('ADMIN_SESSION_SECRET') ?: getenv('BOT_TOKEN') ?: 'hamim-bc';
    return hash('sha256', (string)$s . '|broadcast');
}

function broadcastWorkerUrl(int $jobId): string
{
    $host = (string)($_SERVER['HTTP_HOST'] ?? '');
    if ($host === '') {
        $host = parse_url((string)getSetting('app_url', ''), PHP_URL_HOST) ?: '';
    }
    $secret = urlencode(broadcastWorkerSecret());
    return 'https://' . $host . '/broadcast_worker.php?job=' . $jobId . '&key=' . $secret;
}

function triggerBroadcastWorker(int $jobId): void
{
    $url = broadcastWorkerUrl($jobId);
    try {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT_MS => 800,
            CURLOPT_CONNECTTIMEOUT_MS => 500,
            CURLOPT_NOSIGNAL => 1,
        ]);
        @curl_exec($ch);
        @curl_close($ch);
    } catch (Throwable $e) {
    }
}

function prepareBroadcastPayload(array $message): array
{
    $chatId = (int)($message['chat']['id'] ?? 0);
    $mid = (int)($message['message_id'] ?? 0);
    $text = (string)($message['text'] ?? '');
    $caption = (string)($message['caption'] ?? '');

    $complex = !empty($message['video']) || !empty($message['document'])
        || !empty($message['animation']) || !empty($message['sticker'])
        || !empty($message['audio']) || !empty($message['voice'])
        || !empty($message['video_note']);

    if ($text !== '' && empty($message['photo']) && !$complex) {
        $safe = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $html = upgradeTextEmojisToPremium($safe);
        return [
            'mode'         => 'html',
            'html'         => $html,
            'from_chat_id' => $chatId,
            'message_id'   => $mid,
        ];
    }

    if (!empty($message['photo']) && is_array($message['photo']) && !$complex) {
        $photos = $message['photo'];
        $best = $photos[count($photos) - 1] ?? [];
        $fileId = (string)($best['file_id'] ?? '');
        $capHtml = '';
        if ($caption !== '') {
            $capHtml = upgradeTextEmojisToPremium(htmlspecialchars($caption, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        }
        return [
            'mode'          => 'photo',
            'photo_file_id' => $fileId,
            'html'          => $capHtml,
            'from_chat_id'  => $chatId,
            'message_id'    => $mid,
        ];
    }

    return [
        'mode'         => 'copy',
        'from_chat_id' => $chatId,
        'message_id'   => $mid,
    ];
}

function showBroadcastPanel(TelegramBot $bot, int $chatId, int $userId): void
{
    clearBotState($userId);
    $text  = ce('ce_menu_1') . " <b>Broadcast</b>\n\n";
    $text .= ce('ce_warn') . " Only listed bot admins can use this.\n";
    $text .= ce('ce_ref_rocket') . " Flow: content → preview → confirm.\n";
    $text .= ce('ce_ok') . " Plain emojis auto-upgrade to premium from your packs.";

    $idGo = btnEmojiId('ce_btn_agree', '5021905410089550576');
    $idBack = btnEmojiId('ce_btn_back', '5854967531793550989');
    $idNo = btnEmojiId('ce_btn_cancel', '5019523782004441717');
    $kb = TelegramBot::inlineKeyboard([
        [inlineBtn('Start Broadcast', 'bc_start', $idGo)],
        [inlineBtn('Cancel', 'bc_cancel', $idNo)],
        [inlineBtn('Back', 'go_menu', $idBack)],
    ]);
    botSend($bot, $chatId, $userId, $text, '', ['reply_markup' => $kb]);
}

function askBroadcastContent(TelegramBot $bot, int $chatId, int $userId): void
{
    setBotState($userId, 'broadcast_wait', []);
    $text  = ce('ce_payout_ok') . " <b>Send broadcast content</b>\n\n";
    $text .= ce('ce_card') . " Text / photo / forward — anything works.\n";
    $text .= premiumTag('spark') . " Normal emojis → premium automatically.\n";
    $text .= ce('ce_warn') . " You will preview before send.";
    $kb = TelegramBot::inlineKeyboard([
        [inlineBtn('Cancel', 'bc_cancel', btnEmojiId('ce_btn_cancel', '5019523782004441717'))],
    ]);
    botSend($bot, $chatId, $userId, $text, '', ['reply_markup' => $kb]);
}

function broadcastPreviewMarkup(array $buttons = []): array
{
    $rows = [];
    foreach ($buttons as $b) {
        $t = trim((string)($b['text'] ?? ''));
        $u = trim((string)($b['url'] ?? ''));
        if ($t !== '' && $u !== '') {
            $rows[] = [['text' => $t, 'url' => $u]];
        }
    }
    $idOk = btnEmojiId('ce_btn_agree', '5021905410089550576');
    $idNo = btnEmojiId('ce_btn_cancel', '5019523782004441717');
    $rows[] = [
        inlineBtn('Confirm Broadcast', 'bc_confirm', $idOk),
        inlineBtn('Cancel', 'bc_cancel', $idNo),
    ];
    $rows[] = [inlineBtn('➕ Add Button', 'bc_add_btn', '')];
    return TelegramBot::inlineKeyboard($rows);
}

function showBroadcastPreview(TelegramBot $bot, int $chatId, int $userId, array $payload, array $buttons = []): void
{
    $payload['buttons'] = $buttons;
    setBotState($userId, 'broadcast_preview', $payload);

    $mode = $payload['mode'] ?? 'copy';
    if ($mode === 'html' && !empty($payload['html'])) {
        $bot->sendMessage($chatId, (string)$payload['html'], [
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ]);
    } elseif ($mode === 'photo' && !empty($payload['photo_file_id'])) {
        $bot->sendPhoto($chatId, (string)$payload['photo_file_id'], (string)($payload['html'] ?? ''), [
            'parse_mode' => 'HTML',
        ]);
    } else {
        $from = (int)($payload['from_chat_id'] ?? $chatId);
        $mid = (int)($payload['message_id'] ?? 0);
        $copied = $bot->copyMessage($chatId, $from, $mid);
        if (!$copied || !($copied['ok'] ?? false)) {
            $bot->forwardMessage($chatId, $from, $mid);
        }
    }

    $text  = ce('ce_warn') . " <b>Preview above</b> — users will get this.\n\n";
    $text .= ce('ce_ok') . " Confirm to start · Cancel to abort.\n";
    $text .= ce('ce_card') . " Optional: Add Button (name + https link).";
    $bot->sendMessage($chatId, $text, [
        'parse_mode' => 'HTML',
        'reply_markup' => broadcastPreviewMarkup($buttons),
    ]);
}

function startBroadcastJob(TelegramBot $bot, int $adminChatId, int $adminId, array $payload): void
{
    ensureBroadcastTable();
    clearBotState($adminId);

    $db = getDB();
    try {
        $total = (int)$db->query('SELECT COUNT(*) FROM users WHERE COALESCE(is_blocked,0)=0')->fetchColumn();
    } catch (Throwable $e) {
        $total = (int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn();
    }

    if ($total <= 0) {
        botSend($bot, $adminChatId, $adminId, ce('ce_payout_no') . ' No users in database.');
        return;
    }

    $buttons = is_array($payload['buttons'] ?? null) ? $payload['buttons'] : [];
    unset($payload['buttons']);

    $prog  = ce('ce_ref_rocket') . " <b>Broadcast queued</b>\n\n";
    $prog .= ce('ce_chart') . " Users: <b>0 / {$total}</b>\n";
    $prog .= ce('ce_ok') . " Sent: <b>0</b>\n";
    $prog .= ce('ce_no') . " Failed: <b>0</b>\n";
    $prog .= ce('ce_warn') . ' Running in background…';
    $res = $bot->sendMessage($adminChatId, $prog, ['parse_mode' => 'HTML']);
    $progMid = (int)($res['result']['message_id'] ?? 0);

    $stmt = $db->prepare(
        'INSERT INTO broadcast_jobs (admin_id, admin_chat_id, from_chat_id, message_id, buttons_json, content_json, status, offset_id, total, ok_count, fail_count, progress_mid)
         VALUES (?,?,?,?,?,?,\'running\',0,?,0,0,?)'
    );
    $stmt->execute([
        $adminId,
        $adminChatId,
        (int)($payload['from_chat_id'] ?? 0),
        (int)($payload['message_id'] ?? 0),
        $buttons ? json_encode($buttons, JSON_UNESCAPED_UNICODE) : null,
        json_encode($payload, JSON_UNESCAPED_UNICODE),
        $total,
        $progMid > 0 ? $progMid : null,
    ]);
    $jobId = (int)$db->lastInsertId();

    processBroadcastBatch($jobId);
    triggerBroadcastWorker($jobId);
}

function processBroadcastBatch(int $jobId, int $batchSize = 40): bool
{
    ensureBroadcastTable();
    $db = getDB();

    $stmt = $db->prepare('SELECT * FROM broadcast_jobs WHERE id = ? LIMIT 1');
    $stmt->execute([$jobId]);
    $job = $stmt->fetch();
    if (!$job || ($job['status'] ?? '') !== 'running') {
        return false;
    }

    $token = trim((string)getSetting('bot_token', ''));
    if ($token === '') {
        $db->prepare("UPDATE broadcast_jobs SET status='failed' WHERE id=?")->execute([$jobId]);
        return false;
    }
    require_once __DIR__ . '/TelegramBot.php';
    require_once __DIR__ . '/helpers.php';
    $bot = new TelegramBot($token);

    $content = json_decode((string)($job['content_json'] ?? ''), true);
    if (!is_array($content)) {
        $content = [
            'mode' => 'copy',
            'from_chat_id' => (int)$job['from_chat_id'],
            'message_id' => (int)$job['message_id'],
        ];
    }
    $mode = $content['mode'] ?? 'copy';

    $offsetId = (int)$job['offset_id'];
    $ok = (int)$job['ok_count'];
    $fail = (int)$job['fail_count'];
    $total = (int)$job['total'];
    $adminChat = (int)$job['admin_chat_id'];
    $progMid = (int)($job['progress_mid'] ?? 0);

    $replyMarkup = null;
    if (!empty($job['buttons_json'])) {
        $decoded = json_decode((string)$job['buttons_json'], true);
        if (is_array($decoded)) {
            $rows = [];
            foreach ($decoded as $b) {
                $t = trim((string)($b['text'] ?? ''));
                $u = trim((string)($b['url'] ?? ''));
                if ($t !== '' && preg_match('#^https?://#i', $u)) {
                    $rows[] = [['text' => $t, 'url' => $u]];
                }
            }
            if ($rows) {
                $replyMarkup = ['inline_keyboard' => $rows];
            }
        }
    }

    try {
        $q = $db->prepare(
            'SELECT id FROM users WHERE id > ? AND COALESCE(is_blocked,0)=0 ORDER BY id ASC LIMIT ' . (int)$batchSize
        );
        $q->execute([$offsetId]);
        $ids = $q->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable $e) {
        $q = $db->prepare('SELECT id FROM users WHERE id > ? ORDER BY id ASC LIMIT ' . (int)$batchSize);
        $q->execute([$offsetId]);
        $ids = $q->fetchAll(PDO::FETCH_COLUMN);
    }

    if (!$ids) {
        return finishBroadcastJob($bot, $db, $jobId, $adminChat, $progMid, $total, $ok, $fail);
    }

    $lastId = $offsetId;
    foreach ($ids as $uid) {
        $uid = (int)$uid;
        $lastId = $uid;
        $sent = false;
        try {
            if ($mode === 'html' && !empty($content['html'])) {
                $extra = ['parse_mode' => 'HTML', 'disable_web_page_preview' => true];
                if ($replyMarkup) {
                    $extra['reply_markup'] = $replyMarkup;
                }
                $r = $bot->sendMessage($uid, (string)$content['html'], $extra);
                $sent = $r && ($r['ok'] ?? false);
                if (!$sent) {
                    $plain = stripTgEmoji((string)$content['html']);
                    $r = $bot->sendMessage($uid, $plain, $extra);
                    $sent = $r && ($r['ok'] ?? false);
                }
            } elseif ($mode === 'photo' && !empty($content['photo_file_id'])) {
                $extra = ['parse_mode' => 'HTML'];
                if ($replyMarkup) {
                    $extra['reply_markup'] = $replyMarkup;
                }
                $r = $bot->sendPhoto($uid, (string)$content['photo_file_id'], (string)($content['html'] ?? ''), $extra);
                $sent = $r && ($r['ok'] ?? false);
            } else {
                $params = [
                    'chat_id' => $uid,
                    'from_chat_id' => (int)($content['from_chat_id'] ?? $job['from_chat_id']),
                    'message_id' => (int)($content['message_id'] ?? $job['message_id']),
                ];
                if ($replyMarkup) {
                    $params['reply_markup'] = json_encode($replyMarkup, JSON_UNESCAPED_UNICODE);
                }
                $r = $bot->request('copyMessage', $params);
                $sent = $r && ($r['ok'] ?? false);
                if (!$sent) {
                    $r = $bot->forwardMessage($uid, (int)$params['from_chat_id'], (int)$params['message_id']);
                    $sent = $r && ($r['ok'] ?? false);
                }
            }
        } catch (Throwable $e) {
        }
        if ($sent) {
            $ok++;
        } else {
            $fail++;
        }
        usleep(30000);
    }

    $doneCount = $ok + $fail;
    $db->prepare('UPDATE broadcast_jobs SET offset_id=?, ok_count=?, fail_count=?, updated_at=NOW() WHERE id=?')
        ->execute([$lastId, $ok, $fail, $jobId]);

    if ($progMid > 0) {
        $p  = ce('ce_ref_rocket') . " <b>Broadcasting…</b>\n\n";
        $p .= ce('ce_chart') . " Progress: <b>{$doneCount} / {$total}</b>\n";
        $p .= ce('ce_ok') . " Sent: <b>{$ok}</b>\n";
        $p .= ce('ce_no') . " Failed: <b>{$fail}</b>";
        try {
            $bot->editMessage($adminChat, $progMid, $p, ['parse_mode' => 'HTML']);
        } catch (Throwable $e) {
        }
    }

    try {
        $more = $db->prepare('SELECT id FROM users WHERE id > ? AND COALESCE(is_blocked,0)=0 ORDER BY id ASC LIMIT 1');
        $more->execute([$lastId]);
        $hasMore = (bool)$more->fetch();
    } catch (Throwable $e) {
        $more = $db->prepare('SELECT id FROM users WHERE id > ? ORDER BY id ASC LIMIT 1');
        $more->execute([$lastId]);
        $hasMore = (bool)$more->fetch();
    }

    if (!$hasMore) {
        return finishBroadcastJob($bot, $db, $jobId, $adminChat, $progMid, $total, $ok, $fail);
    }
    return true;
}

function finishBroadcastJob(TelegramBot $bot, $db, int $jobId, int $adminChat, int $progMid, int $total, int $ok, int $fail): bool
{
    $db->prepare("UPDATE broadcast_jobs SET status='done', updated_at=NOW() WHERE id=?")->execute([$jobId]);
    $done  = ce('ce_payout_ok') . " <b>Broadcast complete</b>\n\n";
    $done .= ce('ce_chart') . " Total: <b>{$total}</b>\n";
    $done .= ce('ce_ok') . " Delivered: <b>{$ok}</b>\n";
    $done .= ce('ce_no') . " Failed: <b>{$fail}</b>";
    if ($progMid > 0) {
        try {
            $bot->editMessage($adminChat, $progMid, $done, ['parse_mode' => 'HTML']);
        } catch (Throwable $e) {
            $bot->sendMessage($adminChat, $done, ['parse_mode' => 'HTML']);
        }
    } else {
        $bot->sendMessage($adminChat, $done, ['parse_mode' => 'HTML']);
    }
    return false;
}
