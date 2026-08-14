<?php
require_once __DIR__ . '/../../config/bootstrap.php';
requireAdmin();
$slots = [
    'img_welcome',
    'img_join',
    'img_menu',
    'img_wallet',
    'img_referrals',
    'img_payout',
    'img_earn',
    'img_payout_success',
    'img_referral_bonus',
];
foreach ($slots as $k) {
    if (isset($_POST[$k])) {
        setSetting($k, trim((string)$_POST[$k]));
    }
    setSetting($k . '_on', isset($_POST[$k . '_on']) ? '1' : '0');
}
$_SESSION['flash'] = 'Message images saved.';
header('Location: /admin/?page=messages');
exit;
