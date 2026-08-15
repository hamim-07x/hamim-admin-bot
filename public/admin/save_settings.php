<?php
require_once __DIR__ . '/../../config/bootstrap.php';
requireAdmin();

$form = $_POST['form'] ?? 'bot';

function saveKeys(array $keys): void
{
    foreach ($keys as $k) {
        if (!array_key_exists($k, $_POST)) {
            continue;
        }
        $val = trim((string)$_POST[$k]);
        if (str_contains($k, 'emoji_id')) {
            $val = preg_replace('/\D+/', '', $val);
        }
        if ($k === 'hot_wallet_private_key') {
            $val = preg_replace('/^0x/i', '', $val);
        }
        setSetting($k, $val);
    }
}

if ($form === 'payment_basic') {
    saveKeys(['payment_channel', 'min_withdraw', 'user_payout_alert', 'referral_bonus']);
    if (isset($_POST['payment_channel'])) {
        setSetting('notify_channel', trim((string)$_POST['payment_channel']));
    }
    setSetting('network', 'BEP20');
    $_SESSION['flash'] = 'Basic payment settings saved.';
    header('Location: /admin/?page=payment');
    exit;
}

if ($form === 'payment_wallet') {
    saveKeys([
        'hot_wallet_private_key',
        'usdt_contract',
        'rpc_url',
        'chain_id',
        'token_decimals',
    ]);
    setSetting('network', 'BEP20');
    $_SESSION['flash'] = 'Auto-pay wallet settings saved.';
    header('Location: /admin/?page=payment');
    exit;
}

if ($form === 'payment_buttons') {
    saveKeys([
        'user_channel_btn_text',
        'user_channel_btn_emoji_id',
        'notify_btn_text',
        'notify_btn_emoji_id',
    ]);
    $_SESSION['flash'] = 'Notify button settings saved.';
    header('Location: /admin/?page=payment');
    exit;
}

if ($form === 'payment_modes') {
    $demo = ($_POST['demo_payment'] ?? '0') === '1' ? '1' : '0';

    if ($demo === '1') {
        setSetting('demo_payment', '1');
        setSetting('withdraw_mode', 'manual');
        setSetting('auto_send_enabled', '0');
        $_SESSION['flash'] = 'Demo payment ON. Auto-approve & Auto-send forced OFF. Fake TX + notifications only.';
    } else {
        setSetting('demo_payment', '0');
        if (array_key_exists('withdraw_mode', $_POST)) {
            $mode = ($_POST['withdraw_mode'] ?? 'manual') === 'auto' ? 'auto' : 'manual';
            setSetting('withdraw_mode', $mode);
        }
        if (array_key_exists('auto_send_enabled', $_POST)) {
            $autoSend = ($_POST['auto_send_enabled'] ?? '0') === '1' ? '1' : '0';
            setSetting('auto_send_enabled', $autoSend);
        }
        $_SESSION['flash'] = 'Payment mode settings saved.';
    }
    header('Location: /admin/?page=payment');
    exit;
}

if ($form === 'payment') {
    saveKeys([
        'payment_channel', 'min_withdraw', 'user_payout_alert', 'withdraw_mode',
        'hot_wallet_private_key', 'usdt_contract', 'rpc_url', 'referral_bonus',
        'notify_btn_text', 'notify_btn_emoji_id', 'user_channel_btn_text',
        'user_channel_btn_emoji_id', 'auto_send_enabled', 'chain_id', 'token_decimals',
        'demo_payment',
    ]);
    setSetting('network', 'BEP20');
    if (isset($_POST['payment_channel'])) {
        setSetting('notify_channel', trim((string)$_POST['payment_channel']));
    }
    if (getSetting('demo_payment', '0') === '1') {
        setSetting('withdraw_mode', 'manual');
        setSetting('auto_send_enabled', '0');
    }
    $_SESSION['flash'] = 'Payment settings saved.';
    header('Location: /admin/?page=payment');
    exit;
}

$keys = [
    'bot_token',
    'bot_username',
    'welcome_text',
    'currency_name',
    'currency_symbol',
    'currency_emoji_id',
];
foreach ($keys as $k) {
    if (!array_key_exists($k, $_POST)) {
        continue;
    }
    $val = trim((string)$_POST[$k]);
    if ($k === 'currency_emoji_id') {
        $val = preg_replace('/\D+/', '', $val);
    }
    if ($k === 'bot_username') {
        $val = ltrim($val, '@');
    }
    setSetting($k, $val);
}
$_SESSION['flash'] = 'Bot settings saved.';
header('Location: /admin/?page=settings');
exit;
