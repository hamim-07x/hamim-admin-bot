<?php
function ensureUser(array $from): void
{
    $db = getDB();
    $id = (int)$from['id'];
    $stmt = $db->prepare('SELECT id FROM users WHERE id = ?');
    $stmt->execute([$id]);
    if ($stmt->fetch()) {
        $db->prepare('UPDATE users SET username=?, first_name=?, last_name=?, updated_at=NOW() WHERE id=?')
            ->execute([$from['username'] ?? null, $from['first_name'] ?? null, $from['last_name'] ?? null, $id]);
        return;
    }
    $code = substr(md5((string)$id . 'tg' . microtime(true)), 0, 10);
    $db->prepare('INSERT INTO users (id, username, first_name, last_name, referral_code) VALUES (?,?,?,?,?)')
        ->execute([$id, $from['username'] ?? null, $from['first_name'] ?? null, $from['last_name'] ?? null, $code]);
}

function applyReferral(int $userId, string $refCode): void
{
    $db = getDB();
    $stmt = $db->prepare('SELECT id FROM users WHERE referral_code = ? AND id != ? LIMIT 1');
    $stmt->execute([$refCode, $userId]);
    $ref = $stmt->fetch();
    if (!$ref && ctype_digit($refCode)) {
        $stmt = $db->prepare('SELECT id FROM users WHERE id = ? AND id != ? LIMIT 1');
        $stmt->execute([(int)$refCode, $userId]);
        $ref = $stmt->fetch();
    }
    if (!$ref) return;
    $check = $db->prepare('SELECT referred_by FROM users WHERE id = ?');
    $check->execute([$userId]);
    $u = $check->fetch();
    if ($u && $u['referred_by']) return;
    $db->prepare('UPDATE users SET referred_by = ? WHERE id = ?')->execute([$ref['id'], $userId]);
}

function isUserBlocked(int $userId): bool
{
    try {
        $stmt = getDB()->prepare('SELECT is_blocked FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        return $row && (int)$row['is_blocked'] === 1;
    } catch (Throwable $e) { return false; }
}

function userHasAgreed(int $userId): bool
{
    try {
        $stmt = getDB()->prepare('SELECT has_agreed FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        return $row && (int)$row['has_agreed'] === 1;
    } catch (Throwable $e) { return false; }
}

function markUserAgreed(int $userId): void
{
    try {
        getDB()->prepare('UPDATE users SET has_agreed = 1 WHERE id = ?')->execute([$userId]);
    } catch (Throwable $e) {
        try {
            getDB()->exec("ALTER TABLE users ADD COLUMN has_agreed TINYINT(1) DEFAULT 0");
            getDB()->prepare('UPDATE users SET has_agreed = 1 WHERE id = ?')->execute([$userId]);
        } catch (Throwable $e2) {}
    }
}

function markUserJoined(int $userId, bool $joined = true): void
{
    getDB()->prepare('UPDATE users SET is_joined = ? WHERE id = ?')->execute([$joined ? 1 : 0, $userId]);
}

function ensureBotStateColumns(): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        $db = getDB();
        if (!$db->query("SHOW COLUMNS FROM users LIKE 'bot_state'")->fetch()) {
            $db->exec("ALTER TABLE users ADD COLUMN bot_state VARCHAR(64) DEFAULT NULL");
            $db->exec("ALTER TABLE users ADD COLUMN bot_state_data TEXT DEFAULT NULL");
        }
    } catch (Throwable $e) {}
}

function setBotState(int $userId, ?string $state, array $data = []): void
{
    ensureBotStateColumns();
    getDB()->prepare('UPDATE users SET bot_state = ?, bot_state_data = ? WHERE id = ?')
        ->execute([$state, $data ? json_encode($data) : null, $userId]);
}

function getBotState(int $userId): ?string
{
    ensureBotStateColumns();
    $stmt = getDB()->prepare('SELECT bot_state FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $v = $stmt->fetch()['bot_state'] ?? null;
    return $v ?: null;
}

function getBotStateData(int $userId): array
{
    ensureBotStateColumns();
    $stmt = getDB()->prepare('SELECT bot_state_data FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $d = json_decode((string)($stmt->fetch()['bot_state_data'] ?? ''), true);
    return is_array($d) ? $d : [];
}

function clearBotState(int $userId): void
{
    setBotState($userId, null, []);
}

function isCancelText(string $t): bool
{
    $t = mb_strtolower($t);
    return str_contains($t, 'cancel') || str_contains($t, 'বাতিল');
}

function isConfirmText(string $t): bool
{
    $t = mb_strtolower($t);
    return str_contains($t, 'confirm') || str_contains($t, 'max') || str_contains($t, 'yes');
}
