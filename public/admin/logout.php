<?php
require_once __DIR__ . '/../../config/bootstrap.php';
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'] ?? '/', $p['domain'] ?? '', (bool)($p['secure'] ?? false), (bool)($p['httponly'] ?? true));
}
session_destroy();
if (function_exists('clearAdminAuthCookie')) {
    clearAdminAuthCookie();
}
header('Location: /admin/login.php');
exit;
