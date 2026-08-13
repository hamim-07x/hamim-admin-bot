<?php
require_once __DIR__ . '/../../config/bootstrap.php';
requireAdmin();
$token = getSetting('bot_token');
if (!$token) { $_SESSION['flash'] = 'Set bot token first.'; header('Location: /admin/?page=webhook'); exit; }
$action = $_POST['action'] ?? 'set';
$host = $_SERVER['HTTP_HOST'] ?? '';
$url = 'https://' . $host . '/webhook.php';
if ($action === 'delete') {
    $api = "https://api.telegram.org/bot{$token}/deleteWebhook";
    setSetting('webhook_enabled', '0');
} else {
    $api = "https://api.telegram.org/bot{$token}/setWebhook?url=" . urlencode($url);
    setSetting('webhook_enabled', '1');
}
$res = @file_get_contents($api);
$_SESSION['flash'] = 'Webhook API: ' . ($res ?: 'no response');
header('Location: /admin/?page=webhook');
exit;
