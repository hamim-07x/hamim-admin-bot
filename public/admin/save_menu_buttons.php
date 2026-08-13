<?php
require_once __DIR__ . '/../../config/bootstrap.php';
requireAdmin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/?page=menu_buttons');
    exit;
}
$keys = ['menu_btn_wallet', 'menu_btn_referrals', 'menu_btn_payout', 'menu_btn_earn'];
foreach ($keys as $k) {
    if (isset($_POST[$k])) setSetting($k, trim((string)$_POST[$k]));
}
$_SESSION['flash'] = 'Menu buttons saved.';
header('Location: /admin/?page=menu_buttons');
exit;
