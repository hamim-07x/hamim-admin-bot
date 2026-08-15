  <?php $activeNet = ($settings['active_payment_network'] ?? 'bsc') === 'ton' ? 'ton' : 'bsc'; ?>
  <div class="card">
    <p class="hint"><b>2 · Active payout network</b> — which chain users withdraw on</p>
    <form method="post" action="/admin/save_settings.php">
      <input type="hidden" name="form" value="payment_network">
      <label>Network</label>
      <select name="active_payment_network">
        <option value="bsc" <?= $activeNet === 'bsc' ? 'selected' : '' ?>>BSC (BEP-20 custom token)</option>
        <option value="ton" <?= $activeNet === 'ton' ? 'selected' : '' ?>>TON / GRAM (same network — former Toncoin)</option>
      </select>
      <p class="hint" style="margin-top:.5rem">BSC → <code>0x…</code> · TON/GRAM → <code>EQ…</code> / <code>UQ…</code> (Telegram Wallet address)</p>
      <button type="submit" class="btn-full">Save Active Network</button>
    </form>
  </div>
  <div class="card">
    <p class="hint"><b>2a · BSC / BEP-20 wallet</b> — used when Active Network = BSC</p>
    <form method="post" action="/admin/save_settings.php">
      <input type="hidden" name="form" value="payment_wallet_bsc">
      <label>Hot Wallet Private Key</label>
      <input name="hot_wallet_private_key" type="password" value="<?= htmlspecialchars($settings['hot_wallet_private_key'] ?? '') ?>" autocomplete="off" placeholder="hex without 0x">
      <label>Token Contract Address (BEP-20)</label>
      <input name="usdt_contract" value="<?= htmlspecialchars($settings['usdt_contract'] ?? '0x55d398326f99059fF775485246999027B3197955') ?>">
      <label>RPC URL</label>
      <input name="rpc_url" value="<?= htmlspecialchars($settings['rpc_url'] ?? 'https://bsc-dataseed.binance.org/') ?>" placeholder="https://bsc-dataseed.binance.org/">
      <label>Chain ID</label>
      <input name="chain_id" value="<?= htmlspecialchars($settings['chain_id'] ?? '56') ?>">
      <label>Token Decimals</label>
      <input name="token_decimals" value="<?= htmlspecialchars($settings['token_decimals'] ?? '18') ?>">
      <button type="submit" class="btn-full">Save BSC Wallet</button>
    </form>
  </div>
  <div class="card">
    <p class="hint"><b>2b · TON / GRAM wallet</b> — same TON network (Gram = former Toncoin). Used when Active Network = TON</p>
    <form method="post" action="/admin/save_settings.php">
      <input type="hidden" name="form" value="payment_wallet_ton">
      <label>Wallet seed / mnemonic (24 words) — optional if using payout URL</label>
      <input name="ton_mnemonic" type="password" value="<?= htmlspecialchars($settings['ton_mnemonic'] ?? '') ?>" autocomplete="off" placeholder="word1 word2 word3 …">
      <label>TON Center API URL</label>
      <input name="ton_api_url" value="<?= htmlspecialchars($settings['ton_api_url'] ?? 'https://toncenter.com/api/v2') ?>">
      <label>TON Center API Key</label>
      <input name="ton_api_key" value="<?= htmlspecialchars($settings['ton_api_key'] ?? '') ?>" autocomplete="off" placeholder="from https://toncenter.com">
      <label>Jetton master (leave empty for native TON/GRAM coin)</label>
      <input name="ton_jetton_master" value="<?= htmlspecialchars($settings['ton_jetton_master'] ?? '') ?>" placeholder="EQ… only if custom jetton">
      <label>External TON payout URL (for real auto-send)</label>
      <input name="ton_payout_url" value="<?= htmlspecialchars($settings['ton_payout_url'] ?? '') ?>" placeholder="https://your-server/ton-send">
      <label>Payout URL secret</label>
      <input name="ton_payout_secret" type="password" value="<?= htmlspecialchars($settings['ton_payout_secret'] ?? '') ?>" autocomplete="off">
      <p class="hint" style="margin-top:.5rem">
        <b>What you need for live TON/GRAM send:</b><br>
        1) Hot wallet mnemonic (or external signer URL)<br>
        2) toncenter API key (free)<br>
        3) Auto-Send ON + Active Network = TON<br>
        Demo mode works without real send. Native TON/GRAM needs no jetton master.
      </p>
      <button type="submit" class="btn-full">Save TON / GRAM Wallet</button>
    </form>
  </div>
