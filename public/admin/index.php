<?php
require_once __DIR__ . '/../../config/bootstrap.php';
requireAdmin();
$settings = getAllSettings();
$page = $_GET['page'] ?? 'dashboard';
$nav = [
  'dashboard' => ['Dashboard', '📊'],
  'settings' => ['Bot Settings', '⚙️'],
  'payment' => ['Payment Settings', '💳'],
  'channels' => ['Channels', '📢'],
  'menu_buttons' => ['Menu Buttons', '🔘'],
  'messages' => ['Message Images', '🖼️'],
  'users' => ['Users', '👥'],
  'withdrawals' => ['Withdrawals', '💸'],
  'webhook' => ['Webhook', '🔗'],
];
$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);
$db = getDB();
$userCount = (int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn();
$wdPending = (int)$db->query("SELECT COUNT(*) FROM withdrawals WHERE status='pending'")->fetchColumn();
$wdPaid = (int)$db->query("SELECT COUNT(*) FROM withdrawals WHERE status IN ('paid','approved')")->fetchColumn();
$wdRejected = (int)$db->query("SELECT COUNT(*) FROM withdrawals WHERE status='rejected'")->fetchColumn();
$totalBalance = (float)$db->query('SELECT COALESCE(SUM(balance),0) FROM users')->fetchColumn();
$usersToday = (int)$db->query("SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetchColumn();
$chCount = (int)$db->query('SELECT COUNT(*) FROM channels WHERE is_active=1')->fetchColumn();
$refCount = 0;
try { $refCount = (int)$db->query("SELECT COUNT(*) FROM transactions WHERE type='referral' AND status='completed'")->fetchColumn(); } catch (Throwable $e) {}
$editId = (int)($_GET['edit'] ?? 0);
$editCh = null;
if ($page === 'channels' && $editId > 0) {
    $st = $db->prepare('SELECT * FROM channels WHERE id = ?');
    $st->execute([$editId]);
    $editCh = $st->fetch() ?: null;
}
$q = trim((string)($_GET['q'] ?? ''));
$adminName = htmlspecialchars((string)($_SESSION['admin_user'] ?? 'admin'));
$s = getSetting('currency_symbol', '$');
$cName = getSetting('currency_name', 'USDT');
$demoOn = ($settings['demo_payment'] ?? '0') === '1';
?><!DOCTYPE html>
<html lang="en"><head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>HAMIM Admin · <?= htmlspecialchars($nav[$page][0] ?? 'Panel') ?></title>
<link rel="stylesheet" href="/admin/admin.css">
</head><body>
<div class="overlay" id="navOverlay" onclick="closeNav()"></div>
<div class="layout">
<aside class="sidebar" id="sidebar">
  <div class="brand"><div class="brand-logo">H</div><div><h2>HAMIM Admin</h2><span><?= $adminName ?></span></div></div>
  <?php foreach ($nav as $k => $meta): ?>
    <a href="/admin/?page=<?= urlencode($k) ?>" class="<?= $page === $k ? 'active' : '' ?>"><span class="nav-ico"><?= $meta[1] ?></span> <?= htmlspecialchars($meta[0]) ?></a>
  <?php endforeach; ?>
  <a href="/admin/logout.php" class="nav-logout"><span class="nav-ico">🚪</span> Logout</a>
</aside>
<main class="main">
  <div class="topbar">
    <button type="button" class="menu-toggle" onclick="openNav()" aria-label="Menu">☰</button>
    <strong style="font-size:.95rem"><?= htmlspecialchars($nav[$page][0] ?? 'Admin') ?></strong>
    <span style="width:44px"></span>
  </div>
  <?php if ($flash): ?><div class="flash"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
<?php if ($page === 'dashboard'): ?>
  <div class="page-title">Dashboard</div>
  <div class="stats-grid">
    <div class="stat-card"><div class="label">Total Users</div><div class="value"><?= number_format($userCount) ?></div><div class="sub">All registered</div></div>
    <div class="stat-card"><div class="label">Joined 24h</div><div class="value"><?= number_format($usersToday) ?></div><div class="sub">Last 24 hours</div></div>
    <div class="stat-card"><div class="label">Total Balance</div><div class="value"><?= htmlspecialchars($s) . number_format($totalBalance, 2) ?></div><div class="sub">All users · <?= htmlspecialchars($cName) ?></div></div>
    <div class="stat-card"><div class="label">Pending WD</div><div class="value"><?= number_format($wdPending) ?></div><div class="sub">Awaiting approval</div></div>
    <div class="stat-card"><div class="label">Paid / Approved</div><div class="value"><?= number_format($wdPaid) ?></div><div class="sub">Completed withdrawals</div></div>
    <div class="stat-card"><div class="label">Rejected WD</div><div class="value"><?= number_format($wdRejected) ?></div><div class="sub">Refunded to users</div></div>
    <div class="stat-card"><div class="label">Channels</div><div class="value"><?= number_format($chCount) ?></div><div class="sub">Join checklist</div></div>
    <div class="stat-card"><div class="label">Referral Payouts</div><div class="value"><?= number_format($refCount) ?></div><div class="sub">Bonus credited</div></div>
  </div>
  <div class="card" style="margin-top:1rem"><p class="hint">Webhook: <b><?= ($settings['webhook_enabled'] ?? '0') === '1' ? 'ON' : 'OFF' ?></b> · Demo: <b><?= $demoOn ? 'ON' : 'OFF' ?></b> · Auto-pay: <b><?= ($settings['withdraw_mode'] ?? '') === 'auto' ? 'ON' : 'OFF' ?></b> · Net: <b><?= htmlspecialchars(strtoupper($settings['active_payment_network'] ?? 'bsc')) ?></b> · Currency: <b><?= htmlspecialchars($settings['currency_name'] ?? 'USDT') ?></b></p></div>
<?php elseif ($page === 'settings'): ?>
  <div class="page-title">Bot Settings</div>
  <div class="card"><form method="post" action="/admin/save_settings.php">
    <input type="hidden" name="form" value="bot">
    <label>Bot API Token</label><input name="bot_token" value="<?= htmlspecialchars($settings['bot_token'] ?? '') ?>" autocomplete="off">
    <label>Bot Username</label><input name="bot_username" value="<?= htmlspecialchars($settings['bot_username'] ?? '') ?>">
    <label>Welcome Message</label><textarea name="welcome_text" rows="3"><?= htmlspecialchars($settings['welcome_text'] ?? '') ?></textarea>
    <label>Currency Name</label><input name="currency_name" value="<?= htmlspecialchars($settings['currency_name'] ?? 'USDT') ?>">
    <label>Currency Symbol</label><input name="currency_symbol" value="<?= htmlspecialchars($settings['currency_symbol'] ?? '$') ?>">
    <label>Currency Emoji ID</label><input name="currency_emoji_id" value="<?= htmlspecialchars($settings['currency_emoji_id'] ?? '') ?>">
    <button type="submit" class="btn-full">Save Bot Settings</button>
  </form></div>
<?php elseif ($page === 'payment'): ?>
  <div class="page-title">Payment Settings</div>
  <div class="card">
    <p class="hint"><b>1 · Basic</b></p>
    <form method="post" action="/admin/save_settings.php">
      <input type="hidden" name="form" value="payment_basic">
      <label>Payment / Notify Channel</label>
      <input name="payment_channel" value="<?= htmlspecialchars($settings['payment_channel'] ?? '') ?>" placeholder="@mychannel">
      <label>Min Withdraw</label>
      <input name="min_withdraw" value="<?= htmlspecialchars($settings['min_withdraw'] ?? '1') ?>">
      <label>Referral Bonus</label>
      <input name="referral_bonus" value="<?= htmlspecialchars($settings['referral_bonus'] ?? '1.00') ?>">
      <label>User Alert on Payout</label>
      <select name="user_payout_alert">
        <option value="1" <?= ($settings['user_payout_alert'] ?? '1') === '1' ? 'selected' : '' ?>>ON</option>
        <option value="0" <?= ($settings['user_payout_alert'] ?? '1') === '0' ? 'selected' : '' ?>>OFF</option>
      </select>
      <button type="submit" class="btn-full">Save Basic Settings</button>
    </form>
  </div>
  <?php require __DIR__ . '/payment_wallets_partial.php'; ?>
  <div class="card">
    <p class="hint"><b>3 · Notify Buttons</b></p>
    <form method="post" action="/admin/save_settings.php">
      <input type="hidden" name="form" value="payment_buttons">
      <label>User button text</label>
      <input name="user_channel_btn_text" value="<?= htmlspecialchars($settings['user_channel_btn_text'] ?? 'View Payment Channel') ?>">
      <label>User button premium emoji ID</label>
      <input name="user_channel_btn_emoji_id" value="<?= htmlspecialchars($settings['user_channel_btn_emoji_id'] ?? '') ?>">
      <label>Channel button text</label>
      <input name="notify_btn_text" value="<?= htmlspecialchars($settings['notify_btn_text'] ?? 'Start Bot') ?>">
      <label>Channel button premium emoji ID</label>
      <input name="notify_btn_emoji_id" value="<?= htmlspecialchars($settings['notify_btn_emoji_id'] ?? '') ?>">
      <button type="submit" class="btn-full">Save Button Settings</button>
    </form>
  </div>
  <div class="card">
    <p class="hint"><b>4 · Modes</b></p>
    <form method="post" action="/admin/save_settings.php">
      <input type="hidden" name="form" value="payment_modes">
      <input type="hidden" name="demo_payment" value="<?= $demoOn ? '1' : '0' ?>">
      <label>Auto-Approve Withdrawals</label>
      <select name="withdraw_mode" <?= $demoOn ? 'disabled' : '' ?>>
        <option value="manual" <?= ($settings['withdraw_mode'] ?? 'manual') === 'manual' ? 'selected' : '' ?>>OFF — admin must Approve</option>
        <option value="auto" <?= ($settings['withdraw_mode'] ?? '') === 'auto' ? 'selected' : '' ?>>ON — auto complete</option>
      </select>
      <?php if ($demoOn): ?><input type="hidden" name="withdraw_mode" value="manual"><?php endif; ?>
      <label>Auto-Send On-Chain</label>
      <select name="auto_send_enabled" <?= $demoOn ? 'disabled' : '' ?>>
        <option value="0" <?= ($settings['auto_send_enabled'] ?? '0') === '0' ? 'selected' : '' ?>>OFF</option>
        <option value="1" <?= ($settings['auto_send_enabled'] ?? '0') === '1' ? 'selected' : '' ?>>ON — real on-chain (active network)</option>
      </select>
      <?php if ($demoOn): ?><input type="hidden" name="auto_send_enabled" value="0"><?php endif; ?>
      <p class="hint" style="margin-top:.5rem">Locked while Demo Payment is ON.</p>
      <button type="submit" class="btn-full" <?= $demoOn ? 'disabled' : '' ?>>Save Mode Settings</button>
    </form>
  </div>
  <div class="card">
    <p class="hint"><b>5 · Demo Payment</b> — notifications + fake TX, <b>no real blockchain send</b></p>
    <form method="post" action="/admin/save_settings.php">
      <input type="hidden" name="form" value="payment_modes">
      <label>Demo Payment</label>
      <select name="demo_payment">
        <option value="0" <?= !$demoOn ? 'selected' : '' ?>>OFF</option>
        <option value="1" <?= $demoOn ? 'selected' : '' ?>>ON — simulate payouts</option>
      </select>
      <p class="hint" style="margin-top:.5rem">When ON: Auto-Approve & Auto-Send forced OFF.</p>
      <button type="submit" class="btn-full">Save Demo Setting</button>
    </form>
  </div>
<?php elseif ($page === 'channels'): ?>
  <div class="page-title">Channels</div>
  <div class="card">
    <form method="post" action="/admin/save_channel.php">
      <?php if ($editCh): ?><input type="hidden" name="id" value="<?= (int)$editCh['id'] ?>"><p class="hint">Editing #<?= (int)$editCh['id'] ?> — <a href="/admin/?page=channels">Cancel</a></p><?php endif; ?>
      <label>Title</label><input name="title" required value="<?= htmlspecialchars($editCh['title'] ?? '') ?>">
      <label>Username</label><input name="username" required value="<?= htmlspecialchars($editCh['username'] ?? '') ?>">
      <label>Invite Link</label><input name="invite_link" required value="<?= htmlspecialchars($editCh['invite_link'] ?? '') ?>">
      <button type="submit" class="btn-full"><?= $editCh ? 'Update' : 'Add Channel' ?></button>
    </form>
  </div>
  <div class="card"><div class="table-wrap"><table class="table">
    <tr><th>Title</th><th>Username</th><th>Link</th><th></th></tr>
    <?php foreach ($db->query('SELECT * FROM channels ORDER BY id DESC')->fetchAll() as $ch): ?>
    <tr>
      <td><?= htmlspecialchars($ch['title']) ?></td>
      <td>@<?= htmlspecialchars($ch['username']) ?></td>
      <td><code class="addr"><?= htmlspecialchars($ch['invite_link'] ?? '') ?></code></td>
      <td class="inline-form">
        <a class="btn btn-ok" style="padding:.45rem .7rem;text-decoration:none" href="/admin/?page=channels&edit=<?= (int)$ch['id'] ?>">Edit</a>
        <a class="btn btn-bad" style="padding:.45rem .7rem;text-decoration:none;color:#fff" href="/admin/delete_channel.php?id=<?= (int)$ch['id'] ?>" onclick="return confirm('Delete?')">Delete</a>
      </td>
    </tr>
    <?php endforeach; ?>
  </table></div></div>
<?php elseif ($page === 'menu_buttons'): ?>
  <div class="page-title">Menu Buttons</div>
  <div class="card"><form method="post" action="/admin/save_menu_buttons.php">
    <label>Wallet text</label><input name="menu_btn_wallet" value="<?= htmlspecialchars($settings['menu_btn_wallet'] ?? 'USDT Wallet') ?>">
    <label>Wallet emoji ID</label><input name="ce_btn_wallet" value="<?= htmlspecialchars($settings['ce_btn_wallet'] ?? '') ?>">
    <label>Referrals text</label><input name="menu_btn_referrals" value="<?= htmlspecialchars($settings['menu_btn_referrals'] ?? 'Referrals') ?>">
    <label>Referrals emoji ID</label><input name="ce_btn_referrals" value="<?= htmlspecialchars($settings['ce_btn_referrals'] ?? '') ?>">
    <label>Payout text</label><input name="menu_btn_payout" value="<?= htmlspecialchars($settings['menu_btn_payout'] ?? 'USDT Payout') ?>">
    <label>Payout emoji ID</label><input name="ce_btn_payout" value="<?= htmlspecialchars($settings['ce_btn_payout'] ?? '') ?>">
    <label>Earn text</label><input name="menu_btn_earn" value="<?= htmlspecialchars($settings['menu_btn_earn'] ?? 'EARN MORE') ?>">
    <label>Earn emoji ID</label><input name="ce_btn_earn" value="<?= htmlspecialchars($settings['ce_btn_earn'] ?? '') ?>">
    <label>Back emoji ID</label><input name="ce_btn_back" value="<?= htmlspecialchars($settings['ce_btn_back'] ?? '') ?>">
    <label>Cancel emoji ID</label><input name="ce_btn_cancel" value="<?= htmlspecialchars($settings['ce_btn_cancel'] ?? '') ?>">
    <label>OK emoji ID</label><input name="ce_btn_agree" value="<?= htmlspecialchars($settings['ce_btn_agree'] ?? '') ?>">
    <label>Retry emoji ID</label><input name="ce_btn_retry" value="<?= htmlspecialchars($settings['ce_btn_retry'] ?? '') ?>">
    <label>Channel emoji ID</label><input name="ce_btn_channel" value="<?= htmlspecialchars($settings['ce_btn_channel'] ?? '') ?>">
    <button type="submit" class="btn-full">Save</button>
  </form></div>
<?php elseif ($page === 'messages'): ?>
  <div class="page-title">Message Images</div>
  <div class="card"><form method="post" action="/admin/save_message_images.php">
    <?php
    $imgSlots = ['img_welcome'=>'Welcome','img_join'=>'Join','img_menu'=>'Menu','img_wallet'=>'Wallet','img_referrals'=>'Referrals','img_payout'=>'Payout','img_earn'=>'Earn','img_payout_success'=>'Payout Success','img_referral_bonus'=>'Referral Bonus Notify'];
    foreach ($imgSlots as $k => $lab):
    ?>
      <label><?= htmlspecialchars($lab) ?> URL</label>
      <input name="<?= $k ?>" value="<?= htmlspecialchars($settings[$k] ?? '') ?>">
      <label class="check-row"><input type="checkbox" name="<?= $k ?>_on" value="1" <?= ($settings[$k . '_on'] ?? '0') === '1' ? 'checked' : '' ?>> Enable <?= htmlspecialchars($lab) ?></label>
    <?php endforeach; ?>
    <button type="submit" class="btn-full">Save Images</button>
  </form></div>
<?php elseif ($page === 'users'): ?>
  <div class="page-title">Users</div>
  <div class="card"><form method="get" action="/admin/" class="search-bar">
    <input type="hidden" name="page" value="users">
    <label>Search User ID or Username</label>
    <div class="inline-form" style="margin-top:.35rem">
      <input name="q" value="<?= htmlspecialchars($q) ?>" placeholder="User ID or @username" style="flex:1;min-width:180px">
      <button type="submit">Search</button>
      <?php if ($q !== ''): ?><a class="btn btn-ghost" style="text-decoration:none;padding:.6rem 1rem" href="/admin/?page=users">Clear</a><?php endif; ?>
    </div>
  </form></div>
  <div class="card"><div class="table-wrap"><table class="table">
    <tr><th>#</th><th>User</th><th>Balance</th><th>Referrals</th><th>Status</th><th>Set Balance</th><th>Block</th></tr>
    <?php
    if ($q !== '') {
      if (preg_match('/^\d+$/', $q)) {
        $st = $db->prepare('SELECT id, username, first_name, last_name, balance, is_blocked, created_at FROM users WHERE id = ? LIMIT 1');
        $st->execute([(int)$q]); $rows = $st->fetchAll();
      } else {
        $like = '%' . ltrim($q, '@') . '%';
        $st = $db->prepare('SELECT id, username, first_name, last_name, balance, is_blocked, created_at FROM users WHERE username LIKE ? OR first_name LIKE ? OR last_name LIKE ? ORDER BY created_at DESC LIMIT 200');
        $st->execute([$like, $like, $like]); $rows = $st->fetchAll();
      }
    } else {
      $rows = $db->query('SELECT id, username, first_name, last_name, balance, is_blocked, created_at FROM users ORDER BY created_at DESC LIMIT 300')->fetchAll();
    }
    $serialMap = [];
    try {
      $allOrdered = $db->query('SELECT id FROM users ORDER BY created_at ASC, id ASC')->fetchAll(PDO::FETCH_COLUMN);
      $i = 1; foreach ($allOrdered as $uid) { $serialMap[(int)$uid] = $i++; }
    } catch (Throwable $e) {}
    $refStmt = $db->prepare('SELECT COUNT(*) FROM users WHERE referred_by = ?');
    foreach ($rows as $u):
      $uname = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')) ?: 'User';
      $handle = $u['username'] ? '@' . $u['username'] : '—';
      $serial = $serialMap[(int)$u['id']] ?? '—';
      $refStmt->execute([(int)$u['id']]); $refs = (int)$refStmt->fetchColumn();
      $balRaw = (float)$u['balance'];
      if (abs($balRaw) < 0.00000001) { $balShow = '0'; }
      else {
        $balShow = rtrim(rtrim(number_format($balRaw, 8, '.', ''), '0'), '.');
        if ($balShow === '' || $balShow === '-') { $balShow = '0'; }
      }
    ?>
    <tr>
      <td><b><?= (int)$serial ?></b></td>
      <td><div class="user-name"><?= htmlspecialchars($uname) ?></div><div class="user-meta"><?= htmlspecialchars($handle) ?> · ID <?= (int)$u['id'] ?></div></td>
      <td><b><?= htmlspecialchars($balShow) ?></b></td>
      <td><b><?= $refs ?></b></td>
      <td><?= (int)$u['is_blocked'] ? '<span class="badge bad">Blocked</span>' : '<span class="badge ok">Active</span>' ?></td>
      <td><form method="post" action="/admin/user_action.php" class="inline-form">
        <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
        <input name="amount" type="number" step="any" min="0" class="amt" value="<?= htmlspecialchars($balShow) ?>">
        <button name="action" value="set_balance">Set</button>
      </form></td>
      <td><form method="post" action="/admin/user_action.php" class="inline-form">
        <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
        <button name="action" value="<?= (int)$u['is_blocked'] ? 'unblock' : 'block' ?>" class="<?= (int)$u['is_blocked'] ? 'btn-ok' : 'btn-bad' ?>"><?= (int)$u['is_blocked'] ? 'Unblock' : 'Block' ?></button>
      </form></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="7" style="color:var(--muted);text-align:center;padding:1.5rem">No users found</td></tr><?php endif; ?>
  </table></div></div>
<?php elseif ($page === 'withdrawals'): ?>
  <div class="page-title">Withdrawals</div>
  <div class="card"><div class="table-wrap"><table class="table">
    <tr><th>#</th><th>User ID</th><th>Amount</th><th>Address</th><th>Status</th><th>Date</th><th>Action</th></tr>
    <?php foreach ($db->query('SELECT * FROM withdrawals ORDER BY id DESC LIMIT 100')->fetchAll() as $w): ?>
    <tr>
      <td><b>#<?= (int)$w['id'] ?></b></td>
      <td><?= (int)$w['user_id'] ?></td>
      <td><b><?= htmlspecialchars((string)$w['amount']) ?></b></td>
      <td><code class="addr"><?= htmlspecialchars($w['address']) ?></code></td>
      <td><?= htmlspecialchars($w['status']) ?></td>
      <td><?= htmlspecialchars($w['created_at']) ?></td>
      <td><?php if (($w['status'] ?? '') === 'pending'): ?>
        <form method="post" action="/admin/withdraw_action.php" class="inline-form">
          <input type="hidden" name="id" value="<?= (int)$w['id'] ?>">
          <button name="action" value="approve" class="btn-ok">Approve</button>
          <button name="action" value="reject" class="btn-bad">Reject</button>
        </form>
      <?php else: ?>—<?php endif; ?></td>
    </tr>
    <?php endforeach; ?>
  </table></div></div>
<?php elseif ($page === 'webhook'): ?>
  <div class="page-title">Webhook</div>
  <div class="card">
    <p>Status: <b><?= ($settings['webhook_enabled'] ?? '0') === '1' ? 'ON' : 'OFF' ?></b></p>
    <p class="hint" style="margin-top:.75rem">https://<?= htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'app.up.railway.app') ?>/webhook.php</p>
    <form method="post" action="/admin/set_webhook.php">
      <button name="action" value="set" class="btn-full">Set Webhook + Enable</button>
      <button name="action" value="delete" class="btn-full btn-ghost" style="margin-top:.6rem">Delete Webhook</button>
    </form>
  </div>
  <div class="card">
    <p class="hint"><b>Bot admin Telegram IDs</b> — can use <code>/broadcast</code> in the bot</p>
    <form method="post" action="/admin/save_settings.php">
      <input type="hidden" name="form" value="bot_admins">
      <label>Admin User IDs (comma separated)</label>
      <input name="bot_admin_ids" value="<?= htmlspecialchars($settings['bot_admin_ids'] ?? '') ?>" placeholder="6301340957, 123456789">
      <p class="hint" style="margin-top:.5rem">Only these Telegram user IDs can open broadcast. Others get “not a bot admin”.</p>
      <button type="submit" class="btn-full">Save Bot Admin IDs</button>
    </form>
  </div>
<?php endif; ?>
</main></div>
<script>
function openNav(){document.getElementById('sidebar').classList.add('open');document.getElementById('navOverlay').classList.add('show');}
function closeNav(){document.getElementById('sidebar').classList.remove('open');document.getElementById('navOverlay').classList.remove('show');}
</script>
</body></html>
