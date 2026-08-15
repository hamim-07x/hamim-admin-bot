  <?php
    $activeNet = ($settings['active_payment_network'] ?? 'bsc') === 'ton' ? 'ton' : 'bsc';
    $tonApi = trim((string)($settings['ton_api_url'] ?? '')) ?: 'https://toncenter.com/api/v2';
    $rpc = trim((string)($settings['rpc_url'] ?? '')) ?: 'https://bsc-dataseed.binance.org/';
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
    <p class="hint"><b>2a · BSC wallet</b> — only private key is required; RPC/contract pre-filled</p>
    <form method="post" action="/admin/save_settings.php">
      <input type="hidden" name="form" value="payment_wallet_bsc">
      <label>Hot Wallet Private Key (paste only this)</label>
      <input name="hot_wallet_private_key" type="password" value="<?= htmlspecialchars($settings['hot_wallet_private_key'] ?? '') ?>" autocomplete="off" placeholder="hex without 0x">
      <label>Token Contract (pre-set)</label>
      <input name="usdt_contract" value="<?= htmlspecialchars($settings['usdt_contract'] ?? '0x55d398326f99059fF775485246999027B3197955') ?>">
      <label>RPC URL (pre-set)</label>
      <input name="rpc_url" value="<?= htmlspecialchars($rpc) ?>">
      <label>Chain ID</label>
      <input name="chain_id" value="<?= htmlspecialchars($settings['chain_id'] ?? '56') ?>">
      <label>Decimals</label>
      <input name="token_decimals" value="<?= htmlspecialchars($settings['token_decimals'] ?? '18') ?>">
      <button type="submit" class="btn-full">Save BSC Wallet</button>
    </form>
  </div>
  <div class="card">
    <p class="hint"><b>2b · TON / GRAM wallet</b> — only paste <b>24-word seed</b>; API URL is pre-set</p>
    <form method="post" action="/admin/save_settings.php">
      <input type="hidden" name="form" value="payment_wallet_ton">
      <label>24-word seed / mnemonic (only field you must fill)</label>
      <input name="ton_mnemonic" type="password" value="<?= htmlspecialchars($settings['ton_mnemonic'] ?? '') ?>" autocomplete="off" placeholder="word1 word2 … word24">
      <label>TON API URL (pre-set)</label>
      <input name="ton_api_url" value="<?= htmlspecialchars($tonApi) ?>">
      <label>TON API Key (optional — free from toncenter.com)</label>
      <input name="ton_api_key" value="<?= htmlspecialchars($settings['ton_api_key'] ?? '') ?>" autocomplete="off" placeholder="optional">
      <input type="hidden" name="ton_jetton_master" value="<?= htmlspecialchars($settings['ton_jetton_master'] ?? '') ?>">
      <input type="hidden" name="ton_payout_url" value="<?= htmlspecialchars($settings['ton_payout_url'] ?? '') ?>">
      <input type="hidden" name="ton_payout_secret" value="<?= htmlspecialchars($settings['ton_payout_secret'] ?? '') ?>">
      <p class="hint" style="margin-top:.5rem">
        Native TON/GRAM. API URL pre-filled.<br>
        <b>Demo ON</b> → full notifications (no chain send).<br>
        <b>Live Auto-Send</b> → seed saved + Auto-Send ON.
      </p>
      <button type="submit" class="btn-full">Save TON / GRAM Seed</button>
    </form>
  </div>
