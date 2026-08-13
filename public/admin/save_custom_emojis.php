<?php
require_once __DIR__ . '/../../config/bootstrap.php';
requireAdmin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /admin/?page=emojis'); exit; }
$keys = ['ce_welcome_1','ce_welcome_2','ce_welcome_3','ce_welcome_4','ce_welcome_5','ce_welcome_6','ce_join_1','ce_join_2','ce_menu_1','ce_wallet_1','ce_balance','ce_ref_1','ce_ref_2','ce_payout_1','ce_earn_1'];
foreach ($keys as $k) {
  if (!array_key_exists($k, $_POST)) continue;
  setSetting($k, preg_replace('/\D+/', '', trim((string)$_POST[$k])));
}
$_SESSION['flash'] = 'Custom emoji IDs saved.';
header('Location: /admin/?page=emojis');
exit;
