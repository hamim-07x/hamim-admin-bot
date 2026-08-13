<?php
/**
 * Telegram Webhook endpoint
 * Set webhook URL: https://YOUR-DOMAIN.up.railway.app/webhook.php
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../bot/handlers.php';

// Hard stop if admin turned webhook OFF or token missing
$token = getSetting('bot_token');
$enabled = getSetting('webhook_enabled', '0') === '1';
if (!$token || !$enabled) {
    http_response_code(200);
    echo 'DISABLED';
    exit;
}

$input = file_get_contents('php://input');
$update = json_decode($input, true);

if (!$update) {
    http_response_code(400);
    echo 'Invalid';
    exit;
}

try {
    handleUpdate($update);
} catch (Throwable $e) {
    error_log('[BOT] ' . $e->getMessage());
}

http_response_code(200);
echo 'OK';
