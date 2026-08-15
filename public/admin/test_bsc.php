<?php
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../bot/BscTokenSender.php';
require_once __DIR__ . '/../../bot/PaymentNetwork.php';
requireAdmin();

header('Content-Type: text/html; charset=utf-8');
$out = [];
$out[] = 'Network active: ' . activePaymentNetwork();
$out[] = 'Demo: ' . getSetting('demo_payment', '0');
$sender = BscTokenSender::fromSettings();
if (!$sender) {
    $out[] = 'FAIL: BSC not configured (need private key + contract)';
} else {
    try {
        $from = $sender->getFromAddress();
        $out[] = 'Hot wallet address: ' . $from;
        $out[] = 'Contract: ' . getSetting('usdt_contract', '');
        $out[] = 'Decimals: ' . getSetting('token_decimals', '18');
        $out[] = 'Chain ID: ' . getSetting('chain_id', '56');
        $out[] = 'RPC: ' . getSetting('rpc_url', '');
        $out[] = 'OK: key loads and address derives. If Approve fails, check TOKEN + BNB on this address.';
        $out[] = 'BscScan: https://bscscan.com/address/' . $from;
    } catch (Throwable $e) {
        $out[] = 'FAIL: ' . $e->getMessage();
    }
}
echo '<pre style="font:14px monospace;padding:1rem;background:#111;color:#0f0">';
echo htmlspecialchars(implode("\n", $out));
echo '</pre><p><a href="/admin/?page=payment">← Payment Settings</a></p>';
