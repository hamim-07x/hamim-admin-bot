<?php
/**
 * Scalable bot-admin broadcast.
 * - Preview → Confirm / Cancel / Add URL button
 * - copyMessage (keeps premium emoji, media, format)
 * - Background batches so 5k–200k users won't kill webhook
 */

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
        getDB()->exec(
            "CREATE TABLE IF NOT EXISTS broadcast_jobs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                admin_id BIGINT NOT NULL,
                admin_chat_id BIGINT NOT NULL,
                from_chat_id BIGINT NOT NULL,
                message_id INT NOT NULL,
                buttons_json TEXT NULL,
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
    $scheme = 'https';
    $secret = urlencode(broadcastWorkerSecret());
    return "{$scheme}://{$host}/broadcast_worker.php?job={$jobId}&key={$secret}";
}

/** Non-blocking trigger next batch */
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

function showBroadcastPanel(TelegramBot $bot, int $chatId, int $userId): void
{
    clearBotState($userId);
    $text  = ce('ce_menu_1') . " <b>Broadcast</b>\n\n";
    $text .= ce('ce_warn') . " Only listed bot admins can use this.\n";
    $text .= ce('ce_ref_rocket') . " Flow: content → preview → confirm.\n";
    $text .= ce('ce_ok') . " Supports text, photo, premium emoji, forward.";

    $idGo = btnEmojiId('ce_btn_agree', '5206607081334906820');
    $idBack = btnEmojiId('ce_btn_back', '5416041192905265756');
    $kb = TelegramBot::inlineKeyboard([
        [inlineBtn('Start Broadcast', 'bc_start', $idGo)],
        [inlineBtn('Cancel', 'bc_cancel', btnEmojiId('ce_btn_cancel', '5210952531676504517'))],
        [inlineBtn('Back', 'go_menu', $idBack)],
    ]);
    botSend($bot, $chatId, $userId, $text, '', ['reply_markup' => $kb]);
}

function askBroadcastContent(TelegramBot $bot, int $chatId, int $userId): void
{
    setBotState($userId, 'broadcast_wait', []);
    $text  = ce('ce_payout_ok') . " <b>Send broadcast content</b>\n\n";
    $text .= ce('ce_card') . " Send text / photo / premium emoji, or <b>forward</b> any message.\n";
    $text .= ce('ce_warn') . " Next you will see a preview before it goes live.";
    $kb = TelegramBot::inlineKeyboard([
        [inlineBtn('Cancel', 'bc_cancel', btnEmojiId('ce_btn_cancel', '5210952531676504517'))],
    ]);
    botSend($bot, $chatId, $userId, $text, '', ['reply_markup' => $kb]);
}

/** @param array<int,array{text:string,url:string}> $buttons */
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
    $idOk = btnEmojiId('ce_btn_agree', '5206607081334906820');
    $idNo = btnEmojiId('ce_btn_cancel', '5210952531676504517');
    $rows[] = [
        inlineBtn('Confirm Broadcast', 'bc_confirm', $idOk),
        inlineBtn('Cancel', 'bc_cancel', $idNo),
    ];
    $rows[] = [inlineBtn('➕ Add Button', 'bc_add_btn', '')];
    return TelegramBot::inlineKeyboard($rows);
}

function showBroadcastPreview(TelegramBot $bot, int $chatId, int $userId, int $fromChatId, int $messageId, array $buttons = []): void
{
    setBotState($userId, 'broadcast_preview', [
        'from_chat_id' => $fromChatId,
        'message_id'   => $messageId,
        'buttons'      => $buttons,
    ]);

    // Show same content to admin (premium emoji preserved via copy)
    $copied = $bot->copyMessage($chatId, $fromChatId, $messageId);
    if (!$copied || !($copied['ok'] ?? false)) {
        $bot->forwardMessage($chatId, $fromChatId, $messageId);
    }

    $text  = ce('ce_warn') . " <b>Preview above</b> — this is exactly what users will receive.\n\n";
    $text .= ce('ce_ok') . " Confirm to start, or Cancel.\n";
    $text .= ce('ce_card') . " Optional: Add Button (name + link).";
    $bot->sendMessage($chatId, $text, [
        'parse_mode' => 'HTML',
        'reply_markup' => broadcastPreviewMarkup($buttons),
    ]);
}

function startBroadcastJob(TelegramBot $bot, int $adminChatId, int $adminId, int $fromChatId, int $messageId, array $buttons = []): void
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

    $prog  = ce('ce_ref_rocket') . " <b>Broadcast queued</b>\n\n";
    $prog .= ce('ce_chart') . " Users: <b>0 / {$total}</b>\n";
    $prog .= ce('ce_ok') . " Sent: <b>0</b>\n";
    $prog .= ce('ce_no') . " Failed: <b>0</b>\n";
    $prog .= ce('ce_warn') . ' Running in background…';
    $res = $bot->sendMessage($adminChatId, $prog, ['parse_mode' => 'HTML']);
    $progMid = (int)($res['result']['message_id'] ?? 0);

    $stmt = $db->prepare(
        'INSERT INTO broadcast_jobs (admin_id, admin_chat_id, from_chat_id, message_id, buttons_json, status, offset_id, total, ok_count, fail_count, progress_mid)
         VALUES (?,?,?,?,?,?,0,?,0,0,?)'
    );
    $stmt->execute([
        $adminId,
        $adminChatId,
        $fromChatId,
        $messageId,
        $buttons ? json_encode($buttons, JSON_UNESCAPED_UNICODE) : null,
        'running',
        $total,
        $progMid > 0 ? $progMid : null,
    ]);
    $jobId = (int)$db->lastInsertId();

    // First tick immediately (small batch), rest via worker
    processBroadcastBatch($jobId);
    triggerBroadcastWorker($jobId);
}

/**
 * Process one batch. Safe to call many times. Returns true if job still running.
 */
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
    $bot = new TelegramBot($token);

    $fromChat = (int)$job['from_chat_id'];
    $msgId = (int)$job['message_id'];
    $offsetId = (int)$job['offset_id'];
    $ok = (int)$job['ok_count'];
    $fail = (int)$job['fail_count'];
    $total = (int)$job['total'];
    $adminChat = (int)$job['admin_chat_id'];
    $progMid = (int)($job['progress_mid'] ?? 0);

    $buttons = [];
    if (!empty($job['buttons_json'])) {
        $decoded = json_decode((string)$job['buttons_json'], true);
        if (is_array($decoded)) {
            $buttons = $decoded;
        }
    }
    $replyMarkup = null;
    if ($buttons) {
        $rows = [];
        foreach ($buttons as $b) {
            $t = trim((string)($b['text'] ?? ''));
            $u = trim((string)($b['url'] ?? ''));
            if ($t !== '' && preg_match('#^https?://#i', $u)) {
                $rows[] = [['text' => $t, 'url' => $u]];
            }
        }
        if ($rows) {
            $replyMarkup = json_encode(['inline_keyboard' => $rows], JSON_UNESCAPED_UNICODE);
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

    $lastId = $offsetId;
    foreach ($ids as $uid) {
        $uid = (int)$uid;
        $lastId = $uid;
        $sent = false;
        try {
            $params = [
                'chat_id'      => $uid,
                'from_chat_id' => $fromChat,
                'message_id'   => $msgId,
            ];
            if ($replyMarkup) {
                $params['reply_markup'] = $replyMarkup;
            }
            $r = $bot->request('copyMessage', $params);
            if ($r && ($r['ok'] ?? false)) {
                $sent = true;
            }
        } catch (Throwable $e) {
        }
        if (!$sent) {
            try {
                $r = $bot->forwardMessage($uid, $fromChat, $msgId);
                if ($r && ($r['ok'] ?? false)) {
                    $sent = true;
                }
            } catch (Throwable $e) {
            }
        }
        if ($sent) {
            $ok++;
        } else {
            $fail++;
        }
        usleep(30000); // ~30 msg/s max
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

    // More left?
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
        }
        return false;
    }

    return true;
}
