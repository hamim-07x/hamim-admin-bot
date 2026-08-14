<?php
require_once __DIR__ . '/../../config/bootstrap.php';
requireAdmin();
$settings = getAllSettings();
$page = $_GET['page'] ?? 'dashboard';
$nav = [
  'dashboard'    => ['Dashboard', '📊'],
  'settings'     => ['Bot Settings', '⚙️'],
  'payment'      => ['Payment Settings', '💳'],
  'channels'     => ['Channels', '📢'],
  'menu_buttons' => ['Menu Buttons', '🔘'],
  'messages'     => ['Message Images', '🖼️'],
  'users'        => ['Users', '👥'],
  'withdrawals'  => ['Withdrawals', '💸'],
  'webhook'      => ['Webhook', '🔗'],
];
$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);
$db = getDB();
$userCount = (int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn();
$wdPending = (int)$db->query("SELECT COUNT(*) FROM withdrawals WHERE status='pending'")->fetchColumn();
$chCount = (int)$db->query('SELECT COUNT(*) FROM channels WHERE is_active=1')->fetchColumn();
$refCount = 0;
try {
  $refCount = (int)$db->query("SELECT COUNT(*) FROM transactions WHERE type='referral' AND status='completed'")->fetchColumn();
} catch (Throwable $e) {}
$editId = (int)($_GET['edit'] ?? 0);
$editCh = null;
if ($page === 'channels' && $editId > 0) {
    $st = $db->prepare('SELECT * FROM channels WHERE id = ?');
    $st->execute([$editId]);
    $editCh = $st->fetch() ?: null;
}
$adminName = htmlspecialchars((string)($_SESSION['admin_user'] ?? 'admin'));
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>HAMIM Admin · <?= htmlspecialchars($nav[$page][0] ?? 'Panel') ?></title>
<link rel="stylesheet" href="/admin/admin.css">
</head>
<body>
<div class="overlay" id="navOverlay" onclick="closeNav()"></div>
<div class="layout">
<aside class="sidebar" id="sidebar">
  <div class="brand">
    <div class="brand-logo">H</div>
    <div>
      <h2>HAMIM Admin</h2>
      <span><?= $adminName ?></span>
    </div>
  </div>
  <?php foreach ($nav as $k => $meta): ?>
    <a href="/admin/?page=<?= urlencode($k) ?>" class="<?= $page === $k ? 'active' : '' ?>">
      <span class="nav-ico"><?= $meta[1] ?></span>
      <?= htmlspecialchars($meta[0]) ?>
    </a>
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
    <div class="stat-card"><div class="label">Users</div><div class="value"><?= $userCount ?></div><div class="sub">Total registered</div></div>
    <div class="stat-card"><div class="label">Channels</div><div class="value"><?= $chCount ?></div><div class="sub">Active join checklist</div></div>
    <div class="stat-card"><div class="label">Pending WD</div><div class="value"><?= $wdPending ?></div><div class="sub">Awaiting approval</div></div>
    <div class="stat-card"><div class="label">Referral payouts</div><div class="value"><?= $refCount ?></div><div class="sub">Completed bonuses</div></div>
  </div>
  <div class="card" style="margin-top:1rem">
    <p class="hint">Webhook: <b><?= ($settings['webhook_enabled'] ?? '0') === '1' ? 'ON' : 'OFF' ?></b>
      · Auto-pay: <b><?= ($settings['withdraw_mode'] ?? '') === 'auto' ? 'ON' : 'OFF' ?></b>
      · Currency: <b><?= htmlspecialchars($settings['currency_name'] ?? 'USDT') ?></b>
      · Referral bonus: <b><?= htmlspecialchars($settings['referral_bonus'] ?? '1.00') ?></b>
    </p>
  </div>

<?php elseif ($page === 'settings'): ?>
  <div class="page-title">Bot Settings</div>
  <div class="card">
    <form method="post" action="/admin/save_settings.php">
      <input type="hidden" name="form" value="bot">
      <label>Bot API Token</label>
      <input name="bot_token" value="<?= htmlspecialchars($settings['bot_token'] ?? '') ?>" autocomplete="off">
      <label>Bot Username</label>
      <input name="bot_username" value="<?= htmlspecialchars($settings['bot_username'] ?? '') ?>" placeholder="MyBot">
      <label>Welcome Message</label>
      <textarea name="welcome_text" rows="3"><?= htmlspecialchars($settings['welcome_text'] ?? '') ?></textarea>
      <label>Currency Name</label>
      <input name="currency_name" value="<?= htmlspecialchars($settings['currency_name'] ?? 'USDT') ?>">
      <label>Currency Symbol</label>
      <input name="currency_symbol" value="<?= htmlspecialchars($settings['currency_symbol'] ?? '$') ?>">
      <label>Currency Emoji ID</label>
      <input name="currency_emoji_id" value="<?= htmlspecialchars($settings['currency_emoji_id'] ?? '') ?>">
      <button type="submit" class="btn-full">Save Bot Settings</button>
    </form>
  </div>

<?php elseif ($page === 'payment'): ?>
  <div class="page-title">Payment Settings</div>
  <div class="card">
    <p class="hint">Payment channel = <b>notify only</b> (not join checklist). Use <b>@username</b>.</p>
    <form method="post" action="/admin/save_settings.php">
      <input type="hidden" name="form" value="payment">
      <label>Payment / Notify Channel</label>
      <input name="payment_channel" value="<?= htmlspecialchars($settings['payment_channel'] ?? '') ?>" placeholder="@mychannel">
      <label>Min Withdraw</label>
      <input name="min_withdraw" value="<?= htmlspecialchars($settings['min_withdraw'] ?? '1') ?>">
      <label>User Alert on Payout</label>
      <select name="user_payout_alert">
        <option value="1" <?= ($settings['user_payout_alert'] ?? '1') === '1' ? 'selected' : '' ?>>ON</option>
        <option value="0" <?= ($settings['user_payout_alert'] ?? '1') === '0' ? 'selected' : '' ?>>OFF</option>
      </select>
      <label>Auto-Pay</label>
      <select name="withdraw_mode">
        <option value="manual" <?= ($settings['withdraw_mode'] ?? 'manual') === 'manual' ? 'selected' : '' ?>>OFF</option>
        <option value="auto" <?= ($settings['withdraw_mode'] ?? '') === 'auto' ? 'selected' : '' ?>>ON</option>
      </select>
      <label>Hot Wallet Private Key</label>
      <input name="hot_wallet_private_key" type="password" value="<?= htmlspecialchars($settings['hot_wallet_private_key'] ?? '') ?>" autocomplete="off">
      <label>USDT Contract</label>
      <input name="usdt_contract" value="<?= htmlspecialchars($settings['usdt_contract'] ?? '0x55d398326f99059fF775485246999027B3197955') ?>">
      <label>Network</label>
      <input name="network" value="<?= htmlspecialchars($settings['network'] ?? 'BEP20') ?>">
      <label>RPC URL</label>
      <input name="rpc_url" value="<?= htmlspecialchars($settings['rpc_url'] ?? '') ?>">
      <label>Referral Bonus (per valid join)</label>
      <input name="referral_bonus" value="<?= htmlspecialchars($settings['referral_bonus'] ?? '1.00') ?>">
      <hr class="sep">
      <p class="hint"><b>User bot</b> message after approve</p>
      <label>User button text</label>
      <input name="user_channel_btn_text" value="<?= htmlspecialchars($settings['user_channel_btn_text'] ?? 'View Payment Channel') ?>">
      <label>User button premium emoji ID</label>
      <input name="user_channel_btn_emoji_id" value="<?= htmlspecialchars($settings['user_channel_btn_emoji_id'] ?? '') ?>">
      <hr class="sep">
      <p class="hint"><b>Payment channel</b> post button</p>
      <label>Channel button text</label>
      <input name="notify_btn_text" value="<?= htmlspecialchars($settings['notify_btn_text'] ?? 'Start Bot') ?>">
      <label>Channel button premium emoji ID</label>
      <input name="notify_btn_emoji_id" value="<?= htmlspecialchars($settings['notify_btn_emoji_id'] ?? '') ?>">
      <button type="submit" class="btn-full">Save Payment Settings</button>
    </form>
  </div>

<?php elseif ($page === 'channels'): ?>
  <div class="page-title">Channels (Join Checklist)</div>
  <div class="card">
    <p class="hint">Only these channels are required to join.</p>
    <form method="post" action="/admin/save_channel.php">
      <?php if ($editCh): ?>
        <input type="hidden" name="id" value="<?= (int)$editCh['id'] ?>">
        <p class="hint">Editing #<?= (int)$editCh['id'] ?> — <a href="/admin/?page=channels">Cancel</a></p>
      <?php endif; ?>
      <label>Title</label>
      <input name="title" required value="<?= htmlspecialchars($editCh['title'] ?? '') ?>">
      <label>Username</label>
      <input name="username" required value="<?= htmlspecialchars($editCh['username'] ?? '') ?>">
      <label>Invite Link</label>
      <input name="invite_link" required value="<?= htmlspecialchars($editCh['invite_link'] ?? '') ?>">
      <button type="submit" class="btn-full"><?= $editCh ? 'Update Channel' : 'Add Channel' ?></button>
    </form>
  </div>
  <div class="card"><div class="table-wrap"><table class="table">
      <tr><th>Title</th><th>Username</th><th>Link</th><th>Actions</th></tr>
      <?php foreach ($db->query('SELECT * FROM channels ORDER BY id DESC')->fetchAll() as $ch): ?>
      <tr>
        <td><?= htmlspecialchars($ch['title']) ?></td>
        <td>@<?= htmlspecialchars($ch['username']) ?></td>
        <td><code class="addr"><?= htmlspecialchars($ch['invite_link'] ?? '') ?></code></td>
        <td class="inline-form">
          <a class="btn btn-ok" style="padding:.45rem .7rem;text-decoration:none" href="/admin/?page=channels&amp;edit=<?= (int)$ch['id'] ?>">Edit</a>
          <a class="btn btn-bad" style="padding:.45rem .7rem;text-decoration:none;color:#fff" href="/admin/delete_channel.php?id=<?= (int)$ch['id'] ?>" onclick="return confirm('Delete?')">Delete</a>
        </td>
      </tr>
      <?php endforeach; ?>
  </table></div></div>

<?php elseif ($page === 'menu_buttons'): ?>
  <div class="page-title">Menu Buttons</div>
  <div class="card">
    <form method="post" action="/admin/save_menu_buttons.php">
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
    </form>
  </div>

<?php elseif ($page === 'messages'): ?>
  <div class="page-title">Message Images</div>
  <div class="card">
    <p class="hint">Enable a slot and paste a public image URL. <b>Referral Bonus</b> image is sent to the referrer when a friend completes join.</p>
    <form method="post" action="/admin/save_message_images.php">
    <?php
    $imgSlots = [
      'img_welcome' => 'Welcome',
      'img_join' => 'Join',
      'img_menu' => 'Menu',
      'img_wallet' => 'Wallet',
      'img_referrals' => 'Referrals',
      'img_payout' => 'Payout',
      'img_earn' => 'Earn',
      'img_payout_success' => 'Payout Success',
      'img_referral_bonus' => 'Referral Bonus Notify',
    ];
    foreach ($imgSlots as $k => $lab):
    ?>
      <label><?= htmlspecialchars($lab) ?> URL</label>
      <input name="<?= $k ?>" value="<?= htmlspecialchars($settings[$k] ?? '') ?>" placeholder="https://...">
      <label class="check-row">
        <input type="checkbox" name="<?= $k ?>_on" value="1" <?= ($settings[$k . '_on'] ?? '0') === '1' ? 'checked' : '' ?>>
        Enable <?= htmlspecialchars($lab) ?> image
      </label>
    <?php endforeach; ?>
    <button type="submit" class="btn-full">Save Images</button>
  </form>
  </div>

<?php elseif ($page === 'users'): ?>
  <div class="page-title">Users</div>
  <div class="card"><div class="table-wrap"><table class="table">
      <tr><th>User</th><th>Balance</th><th>Status</th><th>Edit</th><th>Actions</th></tr>
      <?php foreach ($db->query('SELECT id, username, first_name, last_name, balance, is_blocked FROM users ORDER BY created_at DESC LIMIT 200')->fetchAll() as $u):
        $uname = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')) ?: 'User';
        $handle = $u['username'] ? '@' . $u['username'] : '—';
      ?>
      <tr>
        <td><div class="user-name"><?= htmlspecialchars($uname) ?></div><div class="user-meta"><?= htmlspecialchars($handle) ?> · <?= (int)$u['id'] ?></div></td>
        <td><b><?= htmlspecialchars(number_format((float)$u['balance'], 4)) ?></b></td>
        <td><?= (int)$u['is_blocked'] ? '<span class="badge bad">Blocked</span>' : '<span class="badge ok">Active</span>' ?></td>
        <td><form method="post" action="/admin/user_action.php" class="inline-form">
          <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
          <input name="amount" type="number" step="any" min="0" class="amt" placeholder="0">
          <button name="action" value="set_balance">Set</button>
          <button name="action" value="add_balance">Add</button>
          <button name="action" value="cut_balance">Cut</button>
        </form></td>
        <td><form method="post" action="/admin/user_action.php" class="inline-form">
          <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
          <button name="action" value="<?= (int)$u['is_blocked'] ? 'unblock' : 'block' ?>" class="<?= (int)$u['is_blocked'] ? 'btn-ok' : 'btn-bad' ?>"><?= (int)$u['is_blocked'] ? 'Unblock' : 'Block' ?></button>
        </form></td>
      </tr>
      <?php endforeach; ?>
  </table></div></div>

<?php elseif ($page === 'withdrawals'): ?>
  <div class="page-title">Withdrawals</div>
  <div class="card"><div class="table-wrap"><table class="table">
      <tr><th>ID</th><th>User</th><th>Amount</th><th>Address</th><th>Status</th><th>Date</th><th>Action</th></tr>
      <?php foreach ($db->query('SELECT * FROM withdrawals ORDER BY id DESC LIMIT 100')->fetchAll() as $w): ?>
      <tr>
        <td><?= (int)$w['id'] ?></td>
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
<?php endif; ?>
</main>
</div>
<script>
function openNav() {
  document.getElementById('sidebar').classList.add('open');
  document.getElementById('navOverlay').classList.add('show');
}
function closeNav() {
  document.getElementById('sidebar').classList.remove('open');
  document.getElementById('navOverlay').classList.remove('show');
}
</script>
</body>
</html>
