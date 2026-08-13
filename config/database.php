<?php
/**
 * Database connection (Railway MySQL)
 * Supports:
 * - MYSQLHOST, MYSQLPORT, MYSQLUSER, MYSQLPASSWORD, MYSQLDATABASE
 * - or full URL: MYSQL_PRIVATE_URL / MYSQL_URL / DATABASE_URL
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
