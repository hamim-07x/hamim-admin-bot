<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', getenv('APP_DEBUG') === '1' ? '1' : '0');
ini_set('session.save_path', '/tmp');
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_httponly', '1');

require_once __DIR__ . '/database.php';

if (session_status() === PHP_SESSION_NONE) {
    // Railway terminates TLS; app usually sees HTTP unless X-Forwarded-Proto is set
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
        || (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on');

    // Always treat production Railway as secure if Host looks like railway / custom domain over public URL
    $host = (string)($_SERVER['HTTP_HOST'] ?? '');
    if (!$https && $host !== '' && (str_contains($host, 'railway.app') || str_contains($host, 'up.railway.app'))) {
        $https = true;
    }

    session_name('HAMIMADMINSESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

date_default_timezone_set('Asia/Dhaka');

function jsonResponse(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function adminAuthSecret(): string
{
    $s = getenv('ADMIN_SESSION_SECRET') ?: getenv('BOT_TOKEN') ?: 'hamim-admin-local-secret';
    return hash('sha256', (string)$s);
}

/** Set a short-lived signed cookie so login survives if PHP session file is lost between requests */
function setAdminAuthCookie(int $adminId, string $username): void
{
    $exp = time() + 86400 * 7;
    $payload = $adminId . '|' . $username . '|' . $exp;
    $sig = hash_hmac('sha256', $payload, adminAuthSecret());
    $val = base64_encode($payload . '|' . $sig);

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');
    $host = (string)($_SERVER['HTTP_HOST'] ?? '');
    if (!$https && (str_contains($host, 'railway.app') || str_contains($host, 'up.railway.app'))) {
        $https = true;
    }

    setcookie('hamim_admin_auth', $val, [
        'expires'  => $exp,
        'path'     => '/',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function clearAdminAuthCookie(): void
{
    setcookie('hamim_admin_auth', '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'secure'   => false,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function readAdminAuthCookie(): ?array
{
    $raw = $_COOKIE['hamim_admin_auth'] ?? '';
    if ($raw === '') {
        return null;
    }
    $decoded = base64_decode($raw, true);
    if ($decoded === false) {
        return null;
    }
    $parts = explode('|', $decoded);
    if (count($parts) !== 4) {
        return null;
    }
    [$adminId, $username, $exp, $sig] = $parts;
    $payload = $adminId . '|' . $username . '|' . $exp;
    $expect = hash_hmac('sha256', $payload, adminAuthSecret());
    if (!hash_equals($expect, $sig)) {
        return null;
    }
    if ((int)$exp < time()) {
        return null;
    }
    return ['id' => (int)$adminId, 'user' => $username];
}

function restoreAdminSessionFromCookie(): void
{
    if (!empty($_SESSION['admin_id'])) {
        return;
    }
    $c = readAdminAuthCookie();
    if ($c) {
        $_SESSION['admin_id'] = $c['id'];
        $_SESSION['admin_user'] = $c['user'];
    }
}

function requireAdmin(): void
{
    restoreAdminSessionFromCookie();
    if (empty($_SESSION['admin_id'])) {
        header('Location: /admin/login.php');
        exit;
    }
}

function isAdminLoggedIn(): bool
{
    restoreAdminSessionFromCookie();
    return !empty($_SESSION['admin_id']);
}
