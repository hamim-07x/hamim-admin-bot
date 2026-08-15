<?php
/**
 * BEP-20 (BSC) ERC-20 token transfer from hot wallet private key.
 * Requires: kornrunner/keccak, simplito/elliptic-php (composer).
 */
class BscTokenSender
{
    private string $rpc;
    private string $privateKey;
    private string $contract;
    private int $chainId;
    private int $decimals;

    public function __construct(
        string $rpcUrl,
        string $privateKeyHex,
        string $tokenContract,
        int $chainId = 56,
        int $decimals = 18
    ) {
        $this->rpc = rtrim($rpcUrl, '/');
        $pk = preg_replace('/^0x/i', '', trim($privateKeyHex));
        $this->privateKey = $pk;
        $this->contract = strtolower(trim($tokenContract));
        $this->chainId = $chainId;
        $this->decimals = $decimals;
    }

    public static function fromSettings(): ?self
    {
        $rpc = trim((string)getSetting('rpc_url', ''));
        $pk  = trim((string)getSetting('hot_wallet_private_key', ''));
        $ct  = trim((string)getSetting('usdt_contract', '0x55d398326f99059fF775485246999027B3197955'));
        $chainId = (int)getSetting('chain_id', '56');
        $decimals = (int)getSetting('token_decimals', '18');
        if ($rpc === '' || $pk === '' || $ct === '') {
            return null;
        }
        if ($chainId <= 0) {
            $chainId = 56;
        }
        if ($decimals <= 0) {
            $decimals = 18;
        }
        return new self($rpc, $pk, $ct, $chainId, $decimals);
    }

    /** @return array{ok:bool,tx?:string,error?:string} */
    public function transfer(string $toAddress, string $amountHuman): array
    {
        try {
            if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $toAddress)) {
                return ['ok' => false, 'error' => 'Invalid recipient address'];
            }
            $vendor = dirname(__DIR__) . '/vendor/autoload.php';
            if (is_file($vendor)) {
                require_once $vendor;
            }
            if (!class_exists('Elliptic\\EC') || !class_exists('kornrunner\\Keccak')) {
                return ['ok' => false, 'error' => 'Crypto libs missing (composer install required - redeploy Railway)'];
            }

            $from = $this->addressFromPrivateKey($this->privateKey);
            $amountWei = $this->toTokenUnits($amountHuman, $this->decimals);
            $data = $this->encodeTransfer($toAddress, $amountWei);
            $nonce = $this->rpcInt('eth_getTransactionCount', [$from, 'pending']);
            $gasPrice = $this->rpcInt('eth_gasPrice', []);
            $gasPrice = intdiv($gasPrice * 110, 100);
            $gasLimit = 100000;

            $tx = [
                'nonce' => $nonce,
                'gasPrice' => $gasPrice,
                'gas' => $gasLimit,
                'to' => $this->contract,
                'value' => 0,
                'data' => $data,
                'chainId' => $this->chainId,
            ];

            $raw = $this->signTransaction($tx, $this->privateKey);
            $txHash = $this->rpcString('eth_sendRawTransaction', [$raw]);
            if ($txHash === '' || !str_starts_with($txHash, '0x')) {
                return ['ok' => false, 'error' => 'RPC rejected transaction'];
            }
            return ['ok' => true, 'tx' => $txHash];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    private function toTokenUnits(string $amount, int $decimals): string
    {
        $amount = trim($amount);
        if (!preg_match('/^\d+(\.\d+)?$/', $amount)) {
            throw new RuntimeException('Invalid amount');
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
        } else {
            $h = '';
            $n = $dec;
            if (!function_exists('bccomp')) {
                $h = dechex((int)$dec);
            } else {
                while (bccomp($n, '0') > 0) {
                    $h = dechex((int)bcmod($n, '16')) . $h;
                    $n = bcdiv($n, '16', 0);
                }
            }
            if ($h === '') {
                $h = '0';
            }
        }
        return str_pad($h, $pad, '0', STR_PAD_LEFT);
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
        $nonce = $this->intToHex($tx['nonce']);
        $gasPrice = $this->intToHex($tx['gasPrice']);
        $gas = $this->intToHex($tx['gas']);
        $to = strtolower($tx['to']);
        $value = $this->intToHex($tx['value']);
        $data = $tx['data'];
        $chainId = (int)$tx['chainId'];

        $unsigned = $this->rlpEncode([
            $this->hexToBin($nonce),
            $this->hexToBin($gasPrice),
            $this->hexToBin($gas),
            $this->hexToBin($to),
            $this->hexToBin($value),
            $this->hexToBin($data),
            $this->hexToBin(dechex($chainId)),
            '',
            '',
        ]);

        $hash = \kornrunner\Keccak::hash($unsigned, 256);
        $ec = new \Elliptic\EC('secp256k1');
        $key = $ec->keyFromPrivate($pkHex, 'hex');
        $sig = $key->sign($hash, ['canonical' => true]);
        $r = str_pad($sig->r->toString(16), 64, '0', STR_PAD_LEFT);
        $s = str_pad($sig->s->toString(16), 64, '0', STR_PAD_LEFT);
        $v = $sig->recoveryParam + $chainId * 2 + 35;

        $signed = $this->rlpEncode([
            $this->hexToBin($nonce),
            $this->hexToBin($gasPrice),
            $this->hexToBin($gas),
            $this->hexToBin($to),
            $this->hexToBin($value),
            $this->hexToBin($data),
            $this->hexToBin(dechex($v)),
            hex2bin($r),
            hex2bin($s),
        ]);

        return '0x' . bin2hex($signed);
    }

    private function intToHex(int|string $n): string
    {
        $h = is_int($n) ? dechex($n) : dechex((int)$n);
        if ($h === '0') {
            return '0x0';
        }
        if (strlen($h) % 2 === 1) {
            $h = '0' . $h;
        }
        return '0x' . $h;
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

    private function rpc(string $method, array $params)
    {
        $payload = json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => $method,
            'params' => $params,
        ]);
        $ch = curl_init($this->rpc);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_TIMEOUT => 30,
        ]);
        $res = curl_exec($ch);
        curl_close($ch);
        if ($res === false) {
            throw new RuntimeException('RPC connection failed');
        }
        $data = json_decode($res, true);
        if (isset($data['error'])) {
            throw new RuntimeException($data['error']['message'] ?? 'RPC error');
        }
        return $data['result'] ?? null;
    }

    private function rpcInt(string $method, array $params): int
    {
        $r = $this->rpc($method, $params);
        if (!is_string($r)) {
            return 0;
        }
        return (int)hexdec($r);
    }

    private function rpcString(string $method, array $params): string
    {
        $r = $this->rpc($method, $params);
        return is_string($r) ? $r : '';
    }
}
