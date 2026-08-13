<?php
require_once __DIR__ . '/../../config/bootstrap.php';
requireAdmin();

$form = $_POST['form'] ?? 'bot';

if ($form === 'payment') {
    $keys = [
        'payment_channel',
        'min_withdraw',
        'user_payout_alert',
        'withdraw_mode',
        'hot_wallet_private_key',
        'usdt_contract',
        'network',
        'rpc_url',
        'referral_bonus',
    ];
    foreach ($keys as $k) {
        if (array_key_exists($k, $_POST)) {
            setSetting($k, trim((string)$_POST[$k]));
        }
    }
    // keep notify_channel in sync with payment_channel for older code paths
    if (isset($_POST['payment_channel'])) {
        setSetting('notify_channel', trim((string)$_POST['payment_channel']));
    }
    $_SESSION['flash'] = 'Payment settings saved.';
    header('Location: /admin/?page=payment');
    exit;
}

// Bot settings (default)
$keys = [
    'bot_token',
    'bot_username',
    'welcome_text',
    'currency_name',
    'currency_symbol',
    'currency_emoji_id',
];
foreach ($keys as $k) {
    if (array_key_exists($k, $_POST)) {
        $val = trim((string)$_POST[$k]);
        if ($k === 'currency_emoji_id') {
            $val = preg_replace('/\D+/', '', $val);
        }
        if ($k === 'bot_username') {
            $val = ltrim($val, '@');
        }
        setSetting($k, $val);
    }
}
$_SESSION['flash'] = 'Bot settings saved.';
header('Location: /admin/?page=settings');
exit;
