<?php
/**
 * BEP-20 (BSC) — ultra-low gas (cap ~0.12 gwei), nonce fix, balance preflight.
 * Target fee similar to ~0.05 gwei × ~65k gas ≈ $0.001–0.01 range.
 */
class BscTokenSender
{
    private array $rpcs;
    private string $privateKey;
    private string $contract;
    private int $chainId;
    private int $decimals;
    private string $activeRpc = '';

    public function __construct(string $rpcUrl, string $privateKeyHex, string $tokenContract, int $chainId = 56, int $decimals = 18)
    {
        $list = [];
        foreach (preg_split('/[\s,;]+/', $rpcUrl) as $u) {
            $u = rtrim(trim($u), '/');
            if ($u !== '' && preg_match('#^https?://#i', $u)) {
                $list[] = $u;
            }
        }
        // Prefer official Binance seeds first (stable + often lower quoted gas)
        foreach ([
            'https://bsc-dataseed.binance.org',
            'https://bsc-dataseed1.binance.org',
            'https://bsc-dataseed2.binance.org',
            'https://bsc-dataseed3.binance.org',
            'https://bsc-dataseed4.binance.org',
            'https://bsc-dataseed1.defibit.io',
            'https://bsc-dataseed1.ninicoin.io',
            'https://1rpc.io/bnb',
        ] as $fb) {
            if (!in_array($fb, $list, true)) {
                $list[] = $fb;
            }
        }
        $this->rpcs = $list;
        $pk = preg_replace('/^0x/i', '', trim($privateKeyHex));
        $this->privateKey = strtolower($pk);
        $this->contract = strtolower(trim($tokenContract));
        if (!str_starts_with($this->contract, '0x')) {
            $this->contract = '0x' . $this->contract;
        }
        $this->chainId = $chainId > 0 ? $chainId : 56;
        $this->decimals = $decimals > 0 ? $decimals : 18;
    }

    public static function fromSettings(): ?self
    {
        $rpc = trim((string)getSetting('rpc_url', 'https://bsc-dataseed.binance.org/'));
        $pk  = trim((string)getSetting('hot_wallet_private_key', ''));
        $ct  = trim((string)getSetting('usdt_contract', '0x55d398326f99059fF775485246999027B3197955'));
        $chainId = (int)getSetting('chain_id', '56');
        $decimals = (int)getSetting('token_decimals', '18');
        if ($pk === '' || $ct === '') {
            return null;
        }
        $pkClean = preg_replace('/^0x/i', '', $pk);
        if (!preg_match('/^[a-fA-F0-9]{64}$/', $pkClean)) {
            return null;
        }
        return new self($rpc !== '' ? $rpc : 'https://bsc-dataseed.binance.org/', $pkClean, $ct, $chainId, $decimals);
    }

    public function getFromAddress(): string
    {
        $this->loadCrypto();
        return $this->addressFromPrivateKey($this->privateKey);
    }

    /** @return array{ok:bool,tx?:string,error?:string,from?:string} */
    public function transfer(string $toAddress, string $amountHuman): array
    {
        try {
            $this->loadCrypto();
            $toAddress = strtolower(trim($toAddress));
            if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $toAddress)) {
                return ['ok' => false, 'error' => 'Invalid recipient address (need 0x + 40 hex)'];
            }
            $from = $this->addressFromPrivateKey($this->privateKey);
            $amountWei = $this->toTokenUnits($amountHuman, $this->decimals);
            if ($amountWei === '0') {
                return ['ok' => false, 'error' => 'Amount is zero'];
            }
            $data = $this->encodeTransfer($toAddress, $amountWei);
            $gasPriceHex = $this->fetchGasPriceHex();
            $gasLimitHex = $this->estimateGasHex($from, $data);
            $pre = $this->preflightBalances($from, $amountWei, $gasPriceHex, $gasLimitHex);
            if (!($pre['ok'] ?? false)) {
                return ['ok' => false, 'error' => (string)$pre['error'], 'from' => $from];
            }
            $nonceDec = $this->fetchMaxNonceDecimal($from);
            $lastErr = '';
            $errors = [];
            for ($attempt = 0; $attempt < 10; $attempt++) {
                $nonceHex = ltrim($this->decToHexPad((string)$nonceDec, 0), '0') ?: '0';
                $tx = [
                    'nonce' => $nonceHex,
                    'gasPrice' => $gasPriceHex,
                    'gas' => $gasLimitHex,
                    'to' => $this->contract,
                    'value' => '0',
                    'data' => $data,
                    'chainId' => $this->chainId,
                ];
                $raw = $this->signTransaction($tx, $this->privateKey);
                foreach ($this->rpcs as $rpc) {
                    $this->activeRpc = $rpc;
                    try {
                        $txHash = $this->rpcCall('eth_sendRawTransaction', [$raw]);
                        if (is_string($txHash) && preg_match('/^0x[a-fA-F0-9]{64}$/', $txHash)) {
                            return ['ok' => true, 'tx' => $txHash, 'from' => $from];
                        }
                        $lastErr = 'invalid tx hash from RPC';
                    } catch (Throwable $e) {
                        $msg = $e->getMessage();
                        $lastErr = $msg;
                        $host = parse_url($rpc, PHP_URL_HOST) ?: $rpc;
                        if (stripos($msg, 'already known') !== false || stripos($msg, 'known transaction') !== false || stripos($msg, 'already imported') !== false) {
                            if (preg_match('/(0x[a-fA-F0-9]{64})/', $msg, $hm)) {
                                return ['ok' => true, 'tx' => $hm[1], 'from' => $from];
                            }
                            $computed = '0x' . \kornrunner\Keccak::hash(hex2bin(substr($raw, 2)), 256);
                            return ['ok' => true, 'tx' => $computed, 'from' => $from];
                        }
                        if (preg_match('/next nonce[:\s]+(\d+)/i', $msg, $m2)) {
                            $next = (int)$m2[1];
                            if ($next > $nonceDec) {
                                $nonceDec = $next;
                            } else {
                                $nonceDec++;
                            }
                            break;
                        }
                        if (stripos($msg, 'nonce too low') !== false) {
                            $nonceDec = max($nonceDec + 1, $this->fetchMaxNonceDecimal($from));
                            break;
                        }
                        if (stripos($msg, 'nonce too high') !== false) {
                            $nonceDec = $this->fetchMaxNonceDecimal($from);
                            break;
                        }
                        // Underpriced: bump a little, but stay under hard CAP
                        if (stripos($msg, 'underpriced') !== false || stripos($msg, 'replacement transaction') !== false) {
                            $gasPriceHex = $this->bumpGasPrice($gasPriceHex, 115);
                            break;
                        }
                        if (stripos($msg, 'insufficient funds') !== false || stripos($msg, 'insufficient balance') !== false) {
                            return ['ok' => false, 'error' => 'Hot wallet has no BNB for gas fee (or gas finished). Add BNB to hot wallet.', 'from' => $from];
                        }
                        if (stripos($msg, 'timeout') !== false || stripos($msg, 'Unauthorized') !== false || stripos($msg, 'RPC') !== false) {
                            $errors[] = "$host: skip";
                            continue;
                        }
                        $errors[] = "$host: $msg";
                        continue;
                    }
                }
                if ($attempt >= 2) {
                    $fresh = $this->fetchMaxNonceDecimal($from);
                    if ($fresh > $nonceDec) {
                        $nonceDec = $fresh;
                    }
                }
            }
            $summary = $lastErr;
            if ($errors) {
                $summary = implode(' | ', array_slice($errors, -4));
                if ($lastErr !== '') {
                    $summary .= ' | ' . $lastErr;
                }
            }
            return ['ok' => false, 'error' => $this->humanizeError($summary !== '' ? $summary : 'All RPCs failed'), 'from' => $from];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $this->humanizeError($e->getMessage())];
        }
    }

    private function preflightBalances(string $from, string $amountWei, string $gasPriceHex, string $gasLimitHex): array
    {
        $bnbHex = '0';
        foreach ($this->rpcs as $rpc) {
            $this->activeRpc = $rpc;
            try {
                $bnbHex = $this->rpcQuantity('eth_getBalance', [$from, 'latest']);
                break;
            } catch (Throwable $e) {
                continue;
            }
        }
        $bnb = $this->hexToDecString($bnbHex);
        $gasCost = $this->hexMulHex($gasPriceHex, $gasLimitHex);
        if ($this->decCmp($bnb, $gasCost) < 0) {
            return ['ok' => false, 'error' => 'Hot wallet has no BNB for gas fee (or gas almost finished). Send some BNB to the hot wallet, then try again.'];
        }
        $dataBal = '0x70a08231' . str_pad(strtolower(preg_replace('/^0x/i', '', $from)), 64, '0', STR_PAD_LEFT);
        $tokenHex = null;
        foreach ($this->rpcs as $rpc) {
            $this->activeRpc = $rpc;
            try {
                $r = $this->rpcCall('eth_call', [['to' => $this->contract, 'data' => $dataBal], 'latest']);
                if (is_string($r) && $r !== '' && $r !== '0x') {
                    $tokenHex = preg_replace('/^0x/i', '', $r) ?: '0';
                    break;
                }
            } catch (Throwable $e) {
                continue;
            }
        }
        if ($tokenHex !== null) {
            $tokenBal = $this->hexToDecString($tokenHex);
            if ($this->decCmp($tokenBal, $amountWei) < 0) {
                return ['ok' => false, 'error' => 'Hot wallet token balance is too low for this payout. Add tokens to the hot wallet.'];
            }
        }
        return ['ok' => true];
    }

    private function humanizeError(string $msg): string
    {
        $l = strtolower($msg);
        if (str_contains($l, 'insufficient funds') || str_contains($l, 'insufficient balance')) {
            return 'Hot wallet has no BNB for gas fee (or gas finished). Add BNB to hot wallet.';
        }
        if (str_contains($l, 'transfer amount exceeds') || str_contains($l, 'exceeds balance')) {
            return 'Hot wallet token balance is too low for this payout.';
        }
        if (str_contains($l, 'execution reverted')) {
            return 'On-chain transfer reverted. Check token contract, amount, and hot wallet balances (token + BNB gas).';
        }
        return $msg;
    }

    private function hexToDecString(string $hex): string
    {
        $hex = preg_replace('/^0x/i', '', $hex) ?: '0';
        $hex = ltrim($hex, '0') ?: '0';
        if (function_exists('gmp_init')) {
            return gmp_strval(gmp_init($hex, 16), 10);
        }
        return (string)hexdec($hex);
    }

    private function hexMulHex(string $a, string $b): string
    {
        $a = preg_replace('/^0x/i', '', $a) ?: '0';
        $b = preg_replace('/^0x/i', '', $b) ?: '0';
        if (function_exists('gmp_init')) {
            return gmp_strval(gmp_mul(gmp_init($a, 16), gmp_init($b, 16)), 10);
        }
        return (string)(hexdec($a) * hexdec($b));
    }

    private function decCmp(string $a, string $b): int
    {
        if (function_exists('gmp_cmp')) {
            return gmp_cmp($a, $b);
        }
        if (function_exists('bccomp')) {
            return bccomp($a, $b, 0);
        }
        return $a <=> $b;
    }

    private function fetchMaxNonceDecimal(string $from): int
    {
        $max = 0;
        $tried = 0;
        foreach ($this->rpcs as $rpc) {
            if ($tried >= 5) {
                break;
            }
            $this->activeRpc = $rpc;
            $tried++;
            try {
                $p = $this->rpcQuantity('eth_getTransactionCount', [$from, 'pending']);
                $l = $this->rpcQuantity('eth_getTransactionCount', [$from, 'latest']);
                $max = max($max, $this->hexToDecInt($p), $this->hexToDecInt($l));
            } catch (Throwable $e) {
                continue;
            }
        }
        return $max;
    }

    /**
     * Ultra-low gas strategy (matches early txs ~0.05 gwei / ~$0.001):
     * - floor 0.05 gwei
     * - prefer network * 102% but HARD CAP 0.12 gwei (keeps fee under ~1 cent typically)
     * - underpriced bumps stay under 0.25 gwei max emergency
     */
    private function fetchGasPriceHex(): string
    {
        $floor = '2faf080';   // 50_000_000  = 0.05 gwei
        $cap   = '7270e00';   // 120_000_000 = 0.12 gwei
        $gas   = $floor;

        $samples = [];
        $tried = 0;
        foreach ($this->rpcs as $rpc) {
            if ($tried >= 4) {
                break;
            }
            $this->activeRpc = $rpc;
            $tried++;
            try {
                $gp = $this->rpcQuantity('eth_gasPrice', []);
                if ($gp !== '0' && $gp !== '') {
                    $samples[] = $gp;
                }
            } catch (Throwable $e) {
                continue;
            }
        }

        if ($samples) {
            // Use the lowest quoted network gas among samples (cheapest path)
            $lowest = $samples[0];
            foreach ($samples as $s) {
                $lowest = $this->hexMin($lowest, $s);
            }
            // +2% only — avoid the old 105–112% bloat
            $gas = $this->hexMulPercent($lowest, 102);
        }

        // Enforce floor and hard cap
        $gas = $this->hexMax($gas, $floor);
        $gas = $this->hexMin($gas, $cap);
        return $gas;
    }

    /** Bump on underpriced, but never exceed emergency 0.25 gwei. */
    private function bumpGasPrice(string $current, int $percent): string
    {
        $emergencyCap = 'ee6b280'; // 250_000_000 = 0.25 gwei
        $next = $this->hexMulPercent($current, $percent);
        return $this->hexMin($this->hexMax($next, '2faf080'), $emergencyCap);
    }

    private function estimateGasHex(string $from, string $data): string
    {
        // Typical BEP-20 transfer ~45k–65k; keep tight multiplier
        $limit = 'fde8'; // 65_000 default
        foreach ($this->rpcs as $rpc) {
            $this->activeRpc = $rpc;
            try {
                $est = $this->rpcQuantity('eth_estimateGas', [[
                    'from' => $from,
                    'to' => $this->contract,
                    'data' => $data,
                ]]);
                if ($est !== '0' && $est !== '') {
                    // Only +5% headroom (was 110%)
                    $limit = $this->hexMulPercent($est, 105);
                    $limit = $this->hexMax($limit, 'c350'); // min 50_000
                    // Hard max 120_000 for simple transfer
                    if (function_exists('gmp_init') && gmp_cmp(gmp_init($limit, 16), gmp_init('1d4c0', 16)) > 0) {
                        $limit = '1d4c0';
                    }
                    break;
                }
            } catch (Throwable $e) {
                continue;
            }
        }
        return $limit;
    }

    private function hexToDecInt(string $hex): int
    {
        $hex = preg_replace('/^0x/i', '', $hex) ?: '0';
        if (function_exists('gmp_init')) {
            return (int)gmp_strval(gmp_init($hex, 16), 10);
        }
        return (int)hexdec($hex);
    }

    private function loadCrypto(): void
    {
        $vendor = dirname(__DIR__) . '/vendor/autoload.php';
        if (is_file($vendor)) {
            require_once $vendor;
        }
        if (!class_exists(\Elliptic\EC::class) || !class_exists(\kornrunner\Keccak::class)) {
            throw new RuntimeException('Crypto libs missing — Railway redeploy needed (composer)');
        }
    }

    private function toTokenUnits(string $amount, int $decimals): string
    {
        $amount = trim($amount);
        if (stripos($amount, 'e') !== false) {
            $amount = sprintf('%.18f', (float)$amount);
        }
        if (!preg_match('/^\d+(\.\d+)?$/', $amount)) {
            throw new RuntimeException('Invalid amount: ' . $amount);
        }
        [$i, $f] = array_pad(explode('.', $amount, 2), 2, '');
        $f = substr(str_pad($f, $decimals, '0'), 0, $decimals);
        $raw = ltrim($i . $f, '0');
        return $raw === '' ? '0' : $raw;
    }

    private function encodeTransfer(string $to, string $amountInt): string
    {
        $toClean = strtolower(preg_replace('/^0x/i', '', $to));
        $amountHex = $this->decToHexPad($amountInt, 64);
        return '0xa9059cbb' . str_pad($toClean, 64, '0', STR_PAD_LEFT) . $amountHex;
    }

    private function decToHexPad(string $dec, int $pad): string
    {
        if (function_exists('gmp_init')) {
            $h = gmp_strval(gmp_init($dec, 10), 16);
        } elseif (function_exists('bccomp')) {
            $h = '';
            $n = $dec;
            while (bccomp($n, '0') > 0) {
                $h = dechex((int)bcmod($n, '16')) . $h;
                $n = bcdiv($n, '16', 0);
            }
            if ($h === '') {
                $h = '0';
            }
        } else {
            $h = dechex((int)$dec);
        }
        if ($pad > 0) {
            return str_pad($h, $pad, '0', STR_PAD_LEFT);
        }
        return $h === '' ? '0' : $h;
    }

    private function addressFromPrivateKey(string $pkHex): string
    {
        $ec = new \Elliptic\EC('secp256k1');
        $key = $ec->keyFromPrivate($pkHex, 'hex');
        $pub = $key->getPublic(false, 'hex');
        if (str_starts_with($pub, '04')) {
            $pub = substr($pub, 2);
        }
        $hash = \kornrunner\Keccak::hash(hex2bin($pub), 256);
        return '0x' . substr($hash, -40);
    }

    private function signTransaction(array $tx, string $pkHex): string
    {
        $nonce = $this->quantityToRlpBin($tx['nonce']);
        $gasPrice = $this->quantityToRlpBin($tx['gasPrice']);
        $gas = $this->quantityToRlpBin($tx['gas']);
        $to = strtolower($tx['to']);
        $value = $this->quantityToRlpBin($tx['value']);
        $data = $tx['data'];
        $chainId = (int)$tx['chainId'];
        $unsigned = $this->rlpEncode([
            $nonce, $gasPrice, $gas,
            $this->hexToBin($to), $value, $this->hexToBin($data),
            $this->hexToBin(dechex($chainId)), '', '',
        ]);
        $hash = \kornrunner\Keccak::hash($unsigned, 256);
        $ec = new \Elliptic\EC('secp256k1');
        $key = $ec->keyFromPrivate($pkHex, 'hex');
        $sig = $key->sign($hash, ['canonical' => true]);
        $r = str_pad($sig->r->toString(16), 64, '0', STR_PAD_LEFT);
        $s = str_pad($sig->s->toString(16), 64, '0', STR_PAD_LEFT);
        $rec = $sig->recoveryParam ?? 0;
        $v = (int)$rec + $chainId * 2 + 35;
        $signed = $this->rlpEncode([
            $nonce, $gasPrice, $gas,
            $this->hexToBin($to), $value, $this->hexToBin($data),
            $this->hexToBin(dechex($v)), hex2bin($r), hex2bin($s),
        ]);
        return '0x' . bin2hex($signed);
    }

    private function quantityToRlpBin(string $q): string
    {
        $q = trim($q);
        if ($q === '' || $q === '0' || $q === '0x' || $q === '0x0') {
            return '';
        }
        if (str_starts_with($q, '0x') || str_starts_with($q, '0X')) {
            $hex = preg_replace('/^0x/i', '', $q);
        } elseif (ctype_digit($q)) {
            $hex = ltrim($this->decToHexPad($q, 0), '0');
            if ($hex === '') {
                return '';
            }
        } else {
            $hex = preg_replace('/^0x/i', '', $q);
        }
        $hex = ltrim($hex, '0');
        if ($hex === '') {
            return '';
        }
        if (strlen($hex) % 2) {
            $hex = '0' . $hex;
        }
        return hex2bin($hex) ?: '';
    }

    private function hexMax(string $a, string $b): string
    {
        $a = preg_replace('/^0x/i', '', $a) ?: '0';
        $b = preg_replace('/^0x/i', '', $b) ?: '0';
        if (function_exists('gmp_init')) {
            return gmp_cmp(gmp_init($a, 16), gmp_init($b, 16)) >= 0 ? $a : $b;
        }
        $a = ltrim($a, '0') ?: '0';
        $b = ltrim($b, '0') ?: '0';
        if (strlen($a) !== strlen($b)) {
            return strlen($a) > strlen($b) ? $a : $b;
        }
        return strcasecmp($a, $b) >= 0 ? $a : $b;
    }

    private function hexMin(string $a, string $b): string
    {
        $a = preg_replace('/^0x/i', '', $a) ?: '0';
        $b = preg_replace('/^0x/i', '', $b) ?: '0';
        if (function_exists('gmp_init')) {
            return gmp_cmp(gmp_init($a, 16), gmp_init($b, 16)) <= 0 ? $a : $b;
        }
        $a = ltrim($a, '0') ?: '0';
        $b = ltrim($b, '0') ?: '0';
        if (strlen($a) !== strlen($b)) {
            return strlen($a) < strlen($b) ? $a : $b;
        }
        return strcasecmp($a, $b) <= 0 ? $a : $b;
    }

    private function hexMulPercent(string $hexQty, int $percent): string
    {
        $hex = preg_replace('/^0x/i', '', $hexQty);
        if ($hex === '' || $hex === '0') {
            return '0';
        }
        if (function_exists('gmp_init')) {
            $n = gmp_init($hex, 16);
            $n = gmp_div(gmp_mul($n, (string)$percent), '100');
            return gmp_strval($n, 16);
        }
        return dechex((int)floor(hexdec($hex) * $percent / 100));
    }

    private function hexToBin(string $hex): string
    {
        $hex = preg_replace('/^0x/i', '', $hex);
        if ($hex === '' || $hex === '0') {
            return '';
        }
        if (strlen($hex) % 2) {
            $hex = '0' . $hex;
        }
        return hex2bin($hex) ?: '';
    }

    private function rlpEncode($input): string
    {
        if (is_array($input)) {
            $out = '';
            foreach ($input as $item) {
                $out .= $this->rlpEncode($item);
            }
            return $this->rlpLength(strlen($out), 0xc0) . $out;
        }
        $bin = is_string($input) ? $input : '';
        if (strlen($bin) === 1 && ord($bin) < 0x80) {
            return $bin;
        }
        return $this->rlpLength(strlen($bin), 0x80) . $bin;
    }

    private function rlpLength(int $len, int $offset): string
    {
        if ($len < 56) {
            return chr($len + $offset);
        }
        $hex = dechex($len);
        if (strlen($hex) % 2) {
            $hex = '0' . $hex;
        }
        $binLen = hex2bin($hex);
        return chr(strlen($binLen) + $offset + 55) . $binLen;
    }

    private function rpcCall(string $method, array $params)
    {
        $payload = json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params]);
        $ch = curl_init($this->activeRpc);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        $res = curl_exec($ch);
        $err = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($res === false || $res === '') {
            throw new RuntimeException('RPC timeout/fail (' . $this->activeRpc . '): ' . ($err ?: 'empty'));
        }
        $data = json_decode($res, true);
        if (!is_array($data)) {
            throw new RuntimeException('RPC bad JSON HTTP ' . $code);
        }
        if (isset($data['error'])) {
            $msg = is_array($data['error']) ? ($data['error']['message'] ?? json_encode($data['error'])) : (string)$data['error'];
            throw new RuntimeException($msg);
        }
        return $data['result'] ?? null;
    }

    private function rpcQuantity(string $method, array $params): string
    {
        $r = $this->rpcCall($method, $params);
        if (!is_string($r) || $r === '' || $r === '0x') {
            return '0';
        }
        return preg_replace('/^0x/i', '', $r) ?: '0';
    }
}
