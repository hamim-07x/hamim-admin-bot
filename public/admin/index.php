<?php
require_once __DIR__ . '/../../config/bootstrap.php';
requireAdmin();
$settings = getAllSettings();
$page = $_GET['page'] ?? 'dashboard';
$nav = [
  'dashboard' => 'Dashboard',
  'settings' => 'Bot Settings',
  'payment' => 'Payment Settings',
  'channels' => 'Channels',
  'menu_buttons' => 'Menu Buttons',
  'messages' => 'Message Images',
  'users' => 'Users',
  'withdrawals' => 'Withdrawals',
  'webhook' => 'Webhook',
];
$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);
$db = getDB();
$userCount = (int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn();
$wdPending = (int)$db->query("SELECT COUNT(*) FROM withdrawals WHERE status='pending'")->fetchColumn();
$chCount = (int)$db->query('SELECT COUNT(*) FROM channels WHERE is_active=1')->fetchColumn();
$editId = (int)($_GET['edit'] ?? 0);
$editCh = null;
if ($page === 'channels' && $editId > 0) {
    $st = $db->prepare('SELECT * FROM channels WHERE id = ?');
    $st->execute([$editId]);
    $editCh = $st->fetch() ?: null;
}
?><!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin Panel</title><link rel="stylesheet" href="/admin/admin.css"></head>
<body><div class="layout">
<aside class="sidebar">
  <h2>HAMIM Admin</h2>
  <?php foreach ($nav as $k=>$label): ?>
    <a href="/admin/?page=<?= urlencode($k) ?>" class="<?= $page===$k?'active':'' ?>"><?= htmlspecialchars($label) ?></a>
  <?php endforeach; ?>
  <a href="/admin/logout.php">Logout</a>
</aside>
<main class="main">
<?php if ($flash): ?><div class="flash"><?= htmlspecialchars($flash) ?></div><?php endif; ?>

<?php if ($page === 'dashboard'): ?>
  <div class="page-title">Dashboard</div>
  <div class="card">Users: <b><?= $userCount ?></b></div>
  <div class="card">Active channels: <b><?= $chCount ?></b></div>
  <div class="card">Pending withdrawals: <b><?= $wdPending ?></b></div>
  <div class="card">Webhook: <b><?= ($settings['webhook_enabled']??'0')==='1'?'ON':'OFF' ?></b></div>
  <div class="card">Auto-pay: <b><?= ($settings['withdraw_mode']??'')==='auto'?'ON':'OFF' ?></b></div>

<?php elseif ($page === 'settings'): ?>
  <div class="page-title">Bot Settings</div>
  <div class="card">
    <p class="hint">Bot identity + welcome + currency only. Payment channel is under <b>Payment Settings</b> (notify only — not join checklist).</p>
    <form method="post" action="/admin/save_settings.php">
      <input type="hidden" name="form" value="bot">
      <label>Bot API Token</label>
      <input name="bot_token" value="<?= htmlspecialchars($settings['bot_token']??'') ?>" placeholder="123456:ABC..." autocomplete="off">
      <label>Bot Username</label>
      <input name="bot_username" value="<?= htmlspecialchars($settings['bot_username']??'') ?>" placeholder="MyBot (without @)">
      <label>Welcome Message</label>
      <textarea name="welcome_text" rows="3"><?= htmlspecialchars($settings['welcome_text']??'') ?></textarea>
      <label>Currency Name</label>
      <input name="currency_name" value="<?= htmlspecialchars($settings['currency_name']??'USDT') ?>">
      <label>Currency Symbol</label>
      <input name="currency_symbol" value="<?= htmlspecialchars($settings['currency_symbol']??'$') ?>" placeholder="$">
      <label>Currency Emoji ID (premium / custom)</label>
      <input name="currency_emoji_id" value="<?= htmlspecialchars($settings['currency_emoji_id']??'') ?>" placeholder="5197434882321567830">
      <button type="submit" class="btn-full">Save Bot Settings</button>
    </form>
  </div>

<?php elseif ($page === 'payment'): ?>
  <div class="page-title">Payment Settings</div>
  <div class="card">
    <p class="hint"><b>Payment channel is NOT a join checklist channel.</b> It is only used to post payout notifications. Bot must be admin there.</p>
    <form method="post" action="/admin/save_settings.php">
      <input type="hidden" name="form" value="payment">
      <label>Payment / Notify Channel</label>
      <input name="payment_channel" value="<?= htmlspecialchars($settings['payment_channel']??'') ?>" placeholder="@mychannel">
      <p class="hint">Only for approve/paid alerts — users are never forced to join this channel.</p>

      <label>Min Withdraw</label>
      <input name="min_withdraw" value="<?= htmlspecialchars($settings['min_withdraw']??'1') ?>">

      <label>User Alert on Payout</label>
      <select name="user_payout_alert">
        <option value="1" <?= ($settings['user_payout_alert']??'1')==='1'?'selected':'' ?>>ON — notify user</option>
        <option value="0" <?= ($settings['user_payout_alert']??'1')==='0'?'selected':'' ?>>OFF</option>
      </select>

      <label>Auto-Pay (BSC)</label>
      <select name="withdraw_mode">
        <option value="manual" <?= ($settings['withdraw_mode']??'manual')==='manual'?'selected':'' ?>>OFF — manual approve</option>
        <option value="auto" <?= ($settings['withdraw_mode']??'')==='auto'?'selected':'' ?>>ON — mark paid + notify</option>
      </select>

      <label>Hot Wallet Private Key (server only)</label>
      <input name="hot_wallet_private_key" type="password" value="<?= htmlspecialchars($settings['hot_wallet_private_key']??'') ?>" placeholder="0x..." autocomplete="off">
      <label>USDT Contract (BEP-20)</label>
      <input name="usdt_contract" value="<?= htmlspecialchars($settings['usdt_contract']??'0x55d398326f99059fF775485246999027B3197955') ?>">
      <label>Network</label>
      <input name="network" value="<?= htmlspecialchars($settings['network']??'BEP20') ?>">
      <label>RPC URL (optional)</label>
      <input name="rpc_url" value="<?= htmlspecialchars($settings['rpc_url']??'') ?>">
      <label>Referral Bonus</label>
      <input name="referral_bonus" value="<?= htmlspecialchars($settings['referral_bonus']??'1.00') ?>">

      <hr style="border:0;border-top:1px solid #333;margin:1.25rem 0">
      <p class="hint">Channel notify message: inline button under the alert (opens your bot).</p>
      <label>Notify button text</label>
      <input name="notify_btn_text" value="<?= htmlspecialchars($settings['notify_btn_text']??'Start Bot') ?>" placeholder="Start Bot">
      <label>Notify button premium emoji ID</label>
      <input name="notify_btn_emoji_id" value="<?= htmlspecialchars($settings['notify_btn_emoji_id']??'') ?>" placeholder="5416041192905265756">

      <button type="submit" class="btn-full">Save Payment Settings</button>
    </form>
  </div>

<?php elseif ($page === 'channels'): ?>
  <div class="page-title">Channels (Join Checklist)</div>
  <div class="card">
    <p class="hint">Only channels listed here are required to join. Payment channel is separate (notify only).</p>
    <form method="post" action="/admin/save_channel.php">
      <?php if ($editCh): ?>
        <input type="hidden" name="id" value="<?= (int)$editCh['id'] ?>">
        <p class="hint">Editing channel #<?= (int)$editCh['id'] ?> — <a href="/admin/?page=channels">Cancel edit</a></p>
      <?php endif; ?>
      <label>Title</label>
      <input name="title" required value="<?= htmlspecialchars($editCh['title'] ?? '') ?>">
      <label>Username</label>
      <input name="username" required placeholder="channelusername" value="<?= htmlspecialchars($editCh['username'] ?? '') ?>">
      <label>Invite / Join Link (required)</label>
      <input name="invite_link" required placeholder="https://t.me/..." value="<?= htmlspecialchars($editCh['invite_link'] ?? '') ?>">
      <button type="submit" class="btn-full"><?= $editCh ? 'Update Channel' : 'Add Channel' ?></button>
    </form>
  </div>
  <div class="card" style="overflow-x:auto">
    <table class="table">
      <tr><th>Title</th><th>Username</th><th>Link</th><th>Actions</th></tr>
      <?php foreach ($db->query('SELECT * FROM channels ORDER BY id DESC')->fetchAll() as $ch): ?>
      <tr>
        <td><?= htmlspecialchars($ch['title']) ?></td>
        <td>@<?= htmlspecialchars($ch['username']) ?></td>
        <td><code class="addr"><?= htmlspecialchars($ch['invite_link']??'') ?></code></td>
        <td class="inline-form">
          <a class="btn-ok" style="padding:.45rem .7rem;border-radius:8px;display:inline-block" href="/admin/?page=channels&amp;edit=<?= (int)$ch['id'] ?>">Edit</a>
          <a class="btn-bad" style="padding:.45rem .7rem;border-radius:8px;display:inline-block;color:#fff" href="/admin/delete_channel.php?id=<?= (int)$ch['id'] ?>" onclick="return confirm('Delete this channel?')">Delete</a>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>

<?php elseif ($page === 'menu_buttons'): ?>
  <div class="page-title">Menu Buttons + Premium Icons</div>
  <div class="card">
    <form method="post" action="/admin/save_menu_buttons.php">
      <label>1) Wallet button text</label>
      <input name="menu_btn_wallet" value="<?= htmlspecialchars($settings['menu_btn_wallet']??'USDT Wallet') ?>">
      <label>Wallet premium emoji ID</label>
      <input name="ce_btn_wallet" value="<?= htmlspecialchars($settings['ce_btn_wallet']??'') ?>">
      <label>2) Referrals button text</label>
      <input name="menu_btn_referrals" value="<?= htmlspecialchars($settings['menu_btn_referrals']??'Referrals') ?>">
      <label>Referrals premium emoji ID</label>
      <input name="ce_btn_referrals" value="<?= htmlspecialchars($settings['ce_btn_referrals']??'') ?>">
      <label>3) Payout button text</label>
      <input name="menu_btn_payout" value="<?= htmlspecialchars($settings['menu_btn_payout']??'USDT Payout') ?>">
      <label>Payout premium emoji ID</label>
      <input name="ce_btn_payout" value="<?= htmlspecialchars($settings['ce_btn_payout']??'') ?>">
      <label>4) Earn button text</label>
      <input name="menu_btn_earn" value="<?= htmlspecialchars($settings['menu_btn_earn']??'EARN MORE') ?>">
      <label>Earn premium emoji ID</label>
      <input name="ce_btn_earn" value="<?= htmlspecialchars($settings['ce_btn_earn']??'') ?>">
      <hr style="border:0;border-top:1px solid #333;margin:1.2rem 0">
      <label>Back button emoji ID</label>
      <input name="ce_btn_back" value="<?= htmlspecialchars($settings['ce_btn_back']??'') ?>">
      <label>Cancel button emoji ID</label>
      <input name="ce_btn_cancel" value="<?= htmlspecialchars($settings['ce_btn_cancel']??'') ?>">
      <label>OK / Agree / Confirm emoji ID</label>
      <input name="ce_btn_agree" value="<?= htmlspecialchars($settings['ce_btn_agree']??'') ?>">
      <label>Retry button emoji ID</label>
      <input name="ce_btn_retry" value="<?= htmlspecialchars($settings['ce_btn_retry']??'') ?>">
      <label>Channel join button emoji ID</label>
      <input name="ce_btn_channel" value="<?= htmlspecialchars($settings['ce_btn_channel']??'') ?>">
      <button type="submit" class="btn-full">Save Menu + Premium Icons</button>
    </form>
  </div>

<?php elseif ($page === 'messages'): ?>
  <div class="page-title">Message Images</div>
  <div class="card"><form method="post" action="/admin/save_message_images.php">
    <?php
    $imgSlots = [
      'img_welcome' => 'Welcome',
      'img_join' => 'Join',
      'img_menu' => 'Menu',
      'img_wallet' => 'Wallet',
      'img_referrals' => 'Referrals',
      'img_payout' => 'Payout',
      'img_earn' => 'Earn',
      'img_payout_success' => 'Payout Success (user + channel notify)',
    ];
    foreach ($imgSlots as $k => $lab):
    ?>
      <label><?= htmlspecialchars($lab) ?> URL</label>
      <input name="<?= $k ?>" value="<?= htmlspecialchars($settings[$k]??'') ?>" placeholder="https://...">
      <label><input type="checkbox" name="<?= $k ?>_on" value="1" <?= ($settings[$k.'_on']??'0')==='1'?'checked':'' ?>> Enable image for <?= htmlspecialchars($lab) ?></label>
    <?php endforeach; ?>
    <button type="submit" class="btn-full">Save Images</button>
  </form></div>

<?php elseif ($page === 'users'): ?>
  <div class="page-title">Users</div>
  <div class="card" style="overflow-x:auto">
    <table class="table users-table">
      <tr><th>User</th><th>Balance</th><th>Status</th><th>Edit balance</th><th>Actions</th></tr>
      <?php foreach ($db->query('SELECT id, username, first_name, last_name, balance, is_blocked, created_at FROM users ORDER BY created_at DESC LIMIT 200')->fetchAll() as $u):
        $uname = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
        if ($uname === '') $uname = 'User';
        $handle = $u['username'] ? '@'.$u['username'] : '—';
      ?>
      <tr>
        <td class="user-cell">
          <div class="user-name"><?= htmlspecialchars($uname) ?></div>
          <div class="user-meta"><?= htmlspecialchars($handle) ?> · ID <code><?= (int)$u['id'] ?></code></div>
        </td>
        <td><b><?= htmlspecialchars(number_format((float)$u['balance'], 4)) ?></b></td>
        <td><?= (int)$u['is_blocked'] ? '<span class="badge bad">Blocked</span>' : '<span class="badge ok">Active</span>' ?></td>
        <td>
          <form method="post" action="/admin/user_action.php" class="inline-form">
            <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
            <input name="amount" type="number" step="any" min="0" placeholder="0.00" class="amt">
            <button name="action" value="set_balance" type="submit">Set</button>
            <button name="action" value="add_balance" type="submit">Add</button>
            <button name="action" value="cut_balance" type="submit">Cut</button>
          </form>
        </td>
        <td>
          <form method="post" action="/admin/user_action.php" class="inline-form">
            <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
            <?php if ((int)$u['is_blocked']): ?>
              <button name="action" value="unblock" type="submit" class="btn-ok">Unblock</button>
            <?php else: ?>
              <button name="action" value="block" type="submit" class="btn-bad">Block</button>
            <?php endif; ?>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>

<?php elseif ($page === 'withdrawals'): ?>
  <div class="page-title">Withdrawals</div>
  <div class="card" style="overflow-x:auto">
    <table class="table">
      <tr><th>ID</th><th>User</th><th>Amount</th><th>Address</th><th>Status</th><th>Date</th><th>Action</th></tr>
      <?php foreach ($db->query('SELECT * FROM withdrawals ORDER BY id DESC LIMIT 100')->fetchAll() as $w): ?>
      <tr>
        <td><?= (int)$w['id'] ?></td>
        <td><code><?= (int)$w['user_id'] ?></code></td>
        <td><b><?= htmlspecialchars((string)$w['amount']) ?></b></td>
        <td><code class="addr"><?= htmlspecialchars($w['address']) ?></code></td>
        <td><?= htmlspecialchars($w['status']) ?></td>
        <td><?= htmlspecialchars($w['created_at']) ?></td>
        <td>
          <?php if (($w['status'] ?? '') === 'pending'): ?>
          <form method="post" action="/admin/withdraw_action.php" class="inline-form">
            <input type="hidden" name="id" value="<?= (int)$w['id'] ?>">
            <button name="action" value="approve" type="submit" class="btn-ok">Approve</button>
            <button name="action" value="reject" type="submit" class="btn-bad">Reject</button>
          </form>
          <?php else: ?>—<?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>

<?php elseif ($page === 'webhook'): ?>
  <div class="page-title">Webhook</div>
  <div class="card">
    <p>Status: <b><?= ($settings['webhook_enabled']??'0')==='1'?'ON':'OFF' ?></b></p>
    <p class="hint">URL: https://<?= htmlspecialchars($_SERVER['HTTP_HOST']??'YOUR-APP.up.railway.app') ?>/webhook.php</p>
    <form method="post" action="/admin/set_webhook.php">
      <button name="action" value="set" class="btn-full">Set Webhook + Enable</button>
      <button name="action" value="delete" class="btn-full" style="margin-top:.5rem;background:#555;color:#fff">Delete Webhook + Disable</button>
    </form>
  </div>
<?php endif; ?>
</main></div></body></html>
