  <?php $activeNet = ($settings['active_payment_network'] ?? 'bsc') === 'ton' ? 'ton' : 'bsc'; ?>
  <div class="card">
    <p class="hint"><b>2 · Active payout network</b> — which chain users withdraw on</p>
    <form method="post" action="/admin/save_settings.php">
      <input type="hidden" name="form" value="payment_network">
      <label>Network</label>
      <select name="active_payment_network">
        <option value="bsc" <?= $activeNet === 'bsc' ? 'selected' : '' ?>>BSC (BEP-20 custom token)</option>
        <option value="ton" <?= $activeNet === 'ton' ? 'selected' : '' ?>>TON / Gram (Telegram)</option>
      </select>
      <p class="hint" style="margin-top:.5rem">BSC → users enter <code>0x…</code> · TON → users enter <code>EQ…</code> / <code>UQ…</code></p>
      <button type="submit" class="btn-full">Save Active Network</button>
    </form>
  </div>
  <div class="card">
    <p class="hint"><b>2a · BSC / BEP-20 wallet</b> — used when Active Network = BSC</p>
    <form method="post" action="/admin/save_settings.php">
      <input type="hidden" name="form" value="payment_wallet_bsc">
      <label>Hot Wallet Private Key</label>
      <input name="hot_wallet_private_key" type="password" value="<?= htmlspecialchars($settings['hot_wallet_private_key'] ?? '') ?>" autocomplete="off">
      <label>Token Contract Address (BEP-20)</label>
      <input name="usdt_contract" value="<?= htmlspecialchars($settings['usdt_contract'] ?? '0x55d398326f99059fF775485246999027B3197955') ?>">
      <label>RPC URL</label>
      <input name="rpc_url" value="<?= htmlspecialchars($settings['rpc_url'] ?? '') ?>" placeholder="https://bsc-dataseed.binance.org/">
      <label>Chain ID</label>
      <input name="chain_id" value="<?= htmlspecialchars($settings['chain_id'] ?? '56') ?>">
      <label>Token Decimals</label>
      <input name="token_decimals" value="<?= htmlspecialchars($settings['token_decimals'] ?? '18') ?>">
      <button type="submit" class="btn-full">Save BSC Wallet</button>
    </form>
  </div>
  <div class="card">
    <p class="hint"><b>2b · TON / Gram wallet</b> — used when Active Network = TON</p>
    <form method="post" action="/admin/save_settings.php">
      <input type="hidden" name="form" value="payment_wallet_ton">
      <label>Wallet mnemonic (24 words, optional if using payout URL)</label>
      <input name="ton_mnemonic" type="password" value="<?= htmlspecialchars($settings['ton_mnemonic'] ?? '') ?>" autocomplete="off" placeholder="word1 word2 …">
      <label>TON API URL</label>
      <input name="ton_api_url" value="<?= htmlspecialchars($settings['ton_api_url'] ?? 'https://toncenter.com/api/v2') ?>">
      <label>TON API Key</label>
      <input name="ton_api_key" value="<?= htmlspecialchars($settings['ton_api_key'] ?? '') ?>" autocomplete="off">
      <label>Jetton master (Gram token contract — empty = native TON)</label>
      <input name="ton_jetton_master" value="<?= htmlspecialchars($settings['ton_jetton_master'] ?? '') ?>" placeholder="EQ…">
      <label>External TON payout URL (recommended for real auto-send)</label>
      <input name="ton_payout_url" value="<?= htmlspecialchars($settings['ton_payout_url'] ?? '') ?>" placeholder="https://your-signer.example/ton-send">
      <label>Payout URL secret</label>
      <input name="ton_payout_secret" type="password" value="<?= htmlspecialchars($settings['ton_payout_secret'] ?? '') ?>" autocomplete="off">
      <p class="hint" style="margin-top:.5rem">Real TON auto-send needs a small signer service at the payout URL (POST to, amount, secret → returns tx). Demo mode works without it.</p>
      <button type="submit" class="btn-full">Save TON Wallet</button>
    </form>
  </div>
