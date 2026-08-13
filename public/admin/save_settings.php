<?php
require_once __DIR__ . '/../../config/bootstrap.php';
requireAdmin();
$keys = ['bot_token','bot_username','support_username','welcome_text','currency_name','currency_symbol','min_withdraw','withdraw_mode','referral_bonus','payment_channel','notify_channel','hot_wallet_private_key','network','webhook_enabled'];
foreach ($keys as $k) {
    if (array_key_exists($k, $_POST)) setSetting($k, trim((string)$_POST[$k]));
}
if (isset($_POST['delete_token'])) setSetting('bot_token', '');
$_SESSION['flash'] = 'Settings saved.';
header('Location: /admin/?page=settings');
exit;
