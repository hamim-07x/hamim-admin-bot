<?php
/**
 * Database connection (Railway MySQL)
 */

function getDB(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = getenv('MYSQLHOST') ?: getenv('DB_HOST') ?: '';
    $port = getenv('MYSQLPORT') ?: getenv('DB_PORT') ?: '3306';
    $user = getenv('MYSQLUSER') ?: getenv('DB_USER') ?: '';
    $pass = getenv('MYSQLPASSWORD') ?: getenv('DB_PASS') ?: '';
    $name = getenv('MYSQLDATABASE') ?: getenv('DB_NAME') ?: '';

    if ($host === '' || $user === '' || $name === '') {
        $url = getenv('MYSQL_PRIVATE_URL')
            ?: getenv('MYSQL_URL')
            ?: getenv('DATABASE_URL')
            ?: '';
        if ($url !== '') {
            $p = parse_url($url);
            if ($p) {
                $host = $p['host'] ?? $host;
                $port = (string)($p['port'] ?? $port);
                $user = $p['user'] ?? $user;
                $pass = $p['pass'] ?? $pass;
                $name = ltrim($p['path'] ?? '', '/') ?: $name;
            }
        }
    }

    if ($host === '') {
        $host = '127.0.0.1';
    }
    if ($name === '') {
        $name = 'railway';
    }

    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    return $pdo;
}

function getSetting(string $key, $default = null)
{
    try {
        $db = getDB();
        $stmt = $db->prepare('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1');
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return $row ? $row['setting_value'] : $default;
    } catch (Throwable $e) {
        return $default;
    }
}

function setSetting(string $key, $value): bool
{
    try {
        $db = getDB();
        $stmt = $db->prepare(
            'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        );
        return $stmt->execute([$key, $value]);
    } catch (Throwable $e) {
        return false;
    }
}

function getAllSettings(): array
{
    try {
        $db = getDB();
        $rows = $db->query('SELECT setting_key, setting_value FROM settings')->fetchAll();
        $out = [];
        foreach ($rows as $r) {
            $out[$r['setting_key']] = $r['setting_value'];
        }
        return $out;
    } catch (Throwable $e) {
        return [];
    }
}

/** Pre-fill payment/API defaults so admin only needs to paste private key / 24-word seed */
function ensurePaymentDefaults(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $defaults = [
        'rpc_url'            => 'https://bsc-dataseed.binance.org/',
        'chain_id'           => '56',
        'token_decimals'     => '18',
        'usdt_contract'      => '0x55d398326f99059fF775485246999027B3197955',
        'ton_api_url'        => 'https://toncenter.com/api/v2',
        'ton_api_key'        => '',
        'ton_jetton_master'  => '',
        'ton_payout_url'     => '',
        'ton_payout_secret'  => '',
        'active_payment_network' => 'bsc',
        'network'            => 'BEP20',
        'user_channel_btn_text' => 'View Payment Channel',
        'user_channel_btn_emoji_id' => '5332455502917949981',
        'notify_btn_text'    => 'Start Bot',
        'notify_btn_emoji_id'=> '5416041192905265756',
        'user_payout_alert'  => '1',
        'min_withdraw'       => '1',
        'referral_bonus'     => '1.00',
    ];

    try {
        foreach ($defaults as $key => $val) {
            $cur = getSetting($key, null);
            if ($cur === null || $cur === false) {
                setSetting($key, $val);
            } elseif ($key === 'ton_api_url' && trim((string)$cur) === '') {
                setSetting($key, $val);
            } elseif ($key === 'rpc_url' && trim((string)$cur) === '') {
                setSetting($key, $val);
            } elseif (in_array($key, ['chain_id', 'token_decimals', 'usdt_contract'], true) && trim((string)$cur) === '') {
                setSetting($key, $val);
            } elseif (in_array($key, ['user_channel_btn_text', 'notify_btn_text', 'user_payout_alert', 'min_withdraw', 'referral_bonus'], true) && trim((string)$cur) === '') {
                setSetting($key, $val);
            }
        }
    } catch (Throwable $e) {
    }
}
