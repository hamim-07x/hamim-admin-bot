  <?php
    $activeNet = ($settings['active_payment_network'] ?? 'bsc') === 'ton' ? 'ton' : 'bsc';
    $rpc = trim((string)($settings['rpc_url'] ?? '')) ?: 'https://bsc-dataseed.binance.org/';
  ?>
  <div class="card">
    <p class="hint"><b>2 · Active payout network</b></p>
    <form method="post" action="/admin/save_settings.php">
      <input type="hidden" name="form" value="payment_network">
      <label>Network</label>
      <select name="active_payment_network">
        <option value="bsc" <?= $activeNet === 'bsc' ? 'selected' : '' ?>>BSC (BEP-20) — real on-chain when Demo OFF</option>
        <option value="ton" <?= $activeNet === 'ton' ? 'selected' : '' ?>>TON / GRAM — demo + admin approve only (no on-chain)</option>
      </select>
      <p class="hint" style="margin-top:.5rem">BSC → <code>0x…</code> · GRAM → <code>EQ…</code> / <code>UQ…</code></p>
      <button type="submit" class="btn-full">Save Active Network</button>
    </form>
  </div>
  <div class="card">
    <p class="hint"><b>2a · BSC wallet</b> (only needed for real BSC send)</p>
    <form method="post" action="/admin/save_settings.php">
      <input type="hidden" name="form" value="payment_wallet_bsc">
      <label>Hot Wallet Private Key</label>
      <input name="hot_wallet_private_key" type="password" value="<?= htmlspecialchars($settings['hot_wallet_private_key'] ?? '') ?>" autocomplete="off">
      <label>Token Contract</label>
      <input name="usdt_contract" value="<?= htmlspecialchars($settings['usdt_contract'] ?? '0x55d398326f99059fF775485246999027B3197955') ?>">
      <label>RPC URL</label>
      <input name="rpc_url" value="<?= htmlspecialchars($rpc) ?>">
      <label>Chain ID</label>
      <input name="chain_id" value="<?= htmlspecialchars($settings['chain_id'] ?? '56') ?>">
      <label>Decimals</label>
      <input name="token_decimals" value="<?= htmlspecialchars($settings['token_decimals'] ?? '18') ?>">
      <button type="submit" class="btn-full">Save BSC Wallet</button>
    </form>
    <p class="hint" style="margin-top:.5rem"><a href="/admin/test_bsc.php" style="color:#7dd3fc">▶ Test BSC</a></p>
  </div>
  <div class="card">
    <p class="hint"><b>2b · TON / GRAM</b> — no chain send. Works like Demo: auto / demo / admin approve → success notifications. Users enter EQ/UQ address.</p>
    <p class="hint">No seed / API / payout URL required.</p>
  </div>
