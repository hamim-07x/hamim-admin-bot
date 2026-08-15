<?php
/**
 * Optional TON/GRAM payout webhook.
 * Set ton_payout_url = https://YOUR-DOMAIN/ton_payout.php
 * For real sends, replace with a Node/Python TON signer that returns {"ok":true,"tx":"..."}.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/bootstrap.php';

$raw = file_get_contents('php://input') ?: '';
$data = json_decode($raw, true) ?: [];
$secret = (string)($data['secret'] ?? '');
$expect = trim((string)getSetting('ton_payout_secret', ''));
if ($expect !== '' && !hash_equals($expect, $secret)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'bad secret']);
    exit;
}

$to = trim((string)($data['to'] ?? ''));
$amount = trim((string)($data['amount'] ?? ''));
$mn = trim((string)($data['mnemonic'] ?? getSetting('ton_mnemonic', '')));

if ($to === '' || $amount === '') {
    echo json_encode(['ok' => false, 'error' => 'to and amount required']);
    exit;
}
if ($mn === '') {
    echo json_encode(['ok' => false, 'error' => 'mnemonic not set in admin']);
    exit;
}

http_response_code(501);
echo json_encode([
    'ok' => false,
    'error' => 'TON live sign not built into PHP bot. Use Demo mode, or host a Node/Python TON signer and set ton_payout_url to that service. Expected response: {"ok":true,"tx":"..."}',
]);
