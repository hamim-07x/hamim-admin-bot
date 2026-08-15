<?php
/**
 * BEP-20 (BSC) transfer — multi-RPC, minimum gas floor (fixes GasPrice=50000000 errors).
 */
class BscTokenSender
{
    private array $rpcs;
    private string $privateKey;
    private string $contract;
    private int $chainId;
    private int $decimals;
    private string $activeRpc = '';

    public function __construct(
        string $rpcUrl,
        string $privateKeyHex,
        string $tokenContract,
        int $chainId = 56,
        int $decimals = 18
    ) {
        $list = [];
        foreach (preg_split('/[\s,;]+/', $rpcUrl) as $u) {
            $u = rtrim(trim($u), '/');
            if ($u !== '' && preg_match('#^https?://#i', $u)) {
                $list[] = $u;
            }
        }
        foreach ([
            'https://bsc-dataseed.binance.org',
            'https://bsc-dataseed1.binance.org',
            'https://bsc-dataseed2.binance.org',
            'https://rpc.ankr.com/bsc',
            'https://bsc.publicnode.com',
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

            $lastErr = '';
            foreach ($this->rpcs as $rpc) {
                $this->activeRpc = $rpc;
                try {
                    $nonceHex = $this->rpcQuantity('eth_getTransactionCount', [$from, 'pending']);
                    $gasPriceHex = $this->rpcQuantity('eth_gasPrice', []);
                    if ($gasPriceHex === '0' || $gasPriceHex === '') {
                        $gasPriceHex = '12a05f200';
                    } else {
                        $gasPriceHex = $this->hexMulPercent($gasPriceHex, 150);
                    }
                    $gasPriceHex = $this->hexMax($gasPriceHex, '12a05f200');
                    $gasPriceHex = $this->hexMax($gasPriceHex, '2faf080');

                    $gasLimitHex = '30d40';
                    try {
                        $est = $this->rpcQuantity('eth_estimateGas', [[
                            'from' => $from,
                            'to' => $this->contract,
                            'data' => $data,
                        ]]);
                        if ($est !== '0' && $est !== '') {
                            $gasLimitHex = $this->hexMulPercent($est, 150);
                            $gasLimitHex = $this->hexMax($gasLimitHex, '186a0');
                        }
                    } catch (Throwable $e) {
                        $lastErr = 'estimateGas: ' . $e->getMessage()
                            . ' (need TOKEN + BNB on hot wallet; check contract)';
                    }

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
                    $txHash = $this->rpcCall('eth_sendRawTransaction', [$raw]);
                    if (is_string($txHash) && str_starts_with($txHash, '0x') && strlen($txHash) >= 66) {
                        return ['ok' => true, 'tx' => $txHash, 'from' => $from];
                    }
                    $lastErr = 'RPC returned invalid tx hash';
                } catch (Throwable $e) {
                    $lastErr = $e->getMessage();
                    continue;
                }
            }
            return [
                'ok' => false,
                'error' => $lastErr !== '' ? $lastErr : 'All RPCs failed',
                'from' => $from,
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
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
        return (hexdec($a) >= hexdec($b)) ? $a : $b;
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
        $payload = json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => $method,
            'params' => $params,
        ]);
        $ch = curl_init($this->activeRpc);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
        $res = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        if ($res === false) {
            throw new RuntimeException('RPC connection failed (' . $this->activeRpc . '): ' . $err);
        }
        $data = json_decode($res, true);
        if (isset($data['error'])) {
            $msg = is_array($data['error'])
                ? ($data['error']['message'] ?? json_encode($data['error']))
                : (string)$data['error'];
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
