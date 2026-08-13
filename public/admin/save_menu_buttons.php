<?php
require_once __DIR__ . '/../../config/bootstrap.php';
requireAdmin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/?page=menu_buttons');
    exit;
}

$keys = [
    'menu_btn_wallet',
    'menu_btn_referrals',
    'menu_btn_payout',
    'menu_btn_earn',
    'ce_btn_wallet',
    'ce_btn_referrals',
    'ce_btn_payout',
    'ce_btn_earn',
];

foreach ($keys as $k) {
    if (!isset($_POST[$k])) {
        continue;
    }
    $val = trim((string)$_POST[$k]);
    // Emoji fields: digits only
    if (str_starts_with($k, 'ce_btn_')) {
        $val = preg_replace('/\D+/', '', $val);
    }
    setSetting($k, $val);
}

$_SESSION['flash'] = 'Menu buttons + custom emojis saved.';
header('Location: /admin/?page=menu_buttons');
exit;
