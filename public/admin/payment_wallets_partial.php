  <?php
    $activeNet = ($settings['active_payment_network'] ?? 'bsc') === 'ton' ? 'ton' : 'bsc';
    $tonApi = trim((string)($settings['ton_api_url'] ?? '')) ?: 'https://toncenter.com/api/v2';
    $rpc = trim((string)($settings['rpc_url'] ?? '')) ?: 'https://bsc-dataseed.binance.org/';
    $host = (string)($_SERVER['HTTP_HOST'] ?? '');
    $defaultTonPayout = $host !== '' ? ('https://' . $host . '/ton_payout.php') : '';
    $tonPayout = trim((string)($settings['ton_payout_url'] ?? '')) ?: $defaultTonPayout;
  ?>
  <div class="card">
    <p class="hint"><b>2 · Active payout network</b></p>
    <form method="post" action="/admin/save_settings.php">
      <input type="hidden" name="form" value="payment_network">
      <label>Network</label>
      <select name="active_payment_network">
        <option value="bsc" <?= $activeNet === 'bsc' ? 'selected' : '' ?>>BSC (BEP-20)</option>
        <option value="ton" <?= $activeNet === 'ton' ? 'selected' : '' ?>>TON / GRAM (Telegram Wallet)</option>
      </select>
      <p class="hint" style="margin-top:.5rem">BSC → <code>0x…</code> · TON/GRAM → <code>EQ…</code> / <code>UQ…</code></p>
      <button type="submit" class="btn-full">Save Active Network</button>
    </form>
  </div>
  <div class="card">
    <p class="hint"><b>2a · BSC wallet</b> — private key + contract; multi-RPC fallback</p>
    <form method="post" action="/admin/save_settings.php">
      <input type="hidden" name="form" value="payment_wallet_bsc">
      <label>Hot Wallet Private Key</label>
      <input name="hot_wallet_private_key" type="password" value="<?= htmlspecialchars($settings['hot_wallet_private_key'] ?? '') ?>" autocomplete="off" placeholder="64 hex chars">
      <label>Token Contract (BEP-20)</label>
      <input name="usdt_contract" value="<?= htmlspecialchars($settings['usdt_contract'] ?? '0x55d398326f99059fF775485246999027B3197955') ?>">
      <label>RPC URL</label>
      <input name="rpc_url" value="<?= htmlspecialchars($rpc) ?>">
      <label>Chain ID</label>
      <input name="chain_id" value="<?= htmlspecialchars($settings['chain_id'] ?? '56') ?>">
      <label>Decimals</label>
      <input name="token_decimals" value="<?= htmlspecialchars($settings['token_decimals'] ?? '18') ?>">
      <button type="submit" class="btn-full">Save BSC Wallet</button>
    </form>
    <p class="hint" style="margin-top:.75rem">
      <a href="/admin/test_bsc.php" style="color:#7dd3fc">▶ Test BSC config</a> — hot wallet must hold <b>token + BNB</b>. Demo must be <b>OFF</b> for real send.
    </p>
  </div>
  <div class="card">
    <p class="hint"><b>2b · TON / GRAM</b></p>
    <form method="post" action="/admin/save_settings.php">
      <input type="hidden" name="form" value="payment_wallet_ton">
      <label>24-word seed</label>
      <input name="ton_mnemonic" type="password" value="<?= htmlspecialchars($settings['ton_mnemonic'] ?? '') ?>" autocomplete="off">
      <label>TON API URL</label>
      <input name="ton_api_url" value="<?= htmlspecialchars($tonApi) ?>">
      <label>TON API Key (optional)</label>
      <input name="ton_api_key" value="<?= htmlspecialchars($settings['ton_api_key'] ?? '') ?>" autocomplete="off">
      <label>ton_payout_url (live signer)</label>
      <input name="ton_payout_url" value="<?= htmlspecialchars($tonPayout) ?>">
      <label>ton_payout_secret</label>
      <input name="ton_payout_secret" type="password" value="<?= htmlspecialchars($settings['ton_payout_secret'] ?? '') ?>" autocomplete="off">
      <input type="hidden" name="ton_jetton_master" value="<?= htmlspecialchars($settings['ton_jetton_master'] ?? '') ?>">
      <p class="hint" style="margin-top:.5rem">
        PHP cannot sign TON alone. Live send needs a Node/Python signer at ton_payout_url.
        Demo ON = notifications without chain send.
      </p>
      <button type="submit" class="btn-full">Save TON / GRAM</button>
    </form>
  </div>
