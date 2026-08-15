<?php
/**
 * TON / GRAM (former Toncoin). API URL pre-set; admin pastes 24-word seed only.
 */
class TonTokenSender
{
    private string $apiUrl;
    private string $apiKey;
    private string $mnemonic;
    private string $jetton;
    private string $payoutUrl;
    private string $payoutSecret;

    public function __construct(
        string $apiUrl,
        string $apiKey,
        string $mnemonic,
        string $jettonMaster = '',
        string $payoutUrl = '',
        string $payoutSecret = ''
    ) {
        $this->apiUrl = rtrim($apiUrl !== '' ? $apiUrl : 'https://toncenter.com/api/v2', '/');
        $this->apiKey = $apiKey;
        $this->mnemonic = $mnemonic;
        $this->jetton = trim($jettonMaster);
        $this->payoutUrl = rtrim($payoutUrl, '/');
        $this->payoutSecret = $payoutSecret;
    }

    public static function fromSettings(): ?self
    {
        $api = trim((string)getSetting('ton_api_url', 'https://toncenter.com/api/v2'));
        if ($api === '') {
            $api = 'https://toncenter.com/api/v2';
        }
        $key = trim((string)getSetting('ton_api_key', ''));
        $mn  = trim((string)getSetting('ton_mnemonic', ''));
        $jet = trim((string)getSetting('ton_jetton_master', ''));
        $url = trim((string)getSetting('ton_payout_url', ''));
        $sec = trim((string)getSetting('ton_payout_secret', ''));
        if ($mn === '' && $url === '') {
            return null;
        }
        return new self($api, $key, $mn, $jet, $url, $sec);
    }

    /** @return array{ok:bool,tx?:string,error?:string} */
    public function transfer(string $toAddress, string $amountHuman): array
    {
        try {
            if (!preg_match('/^(EQ|UQ)[A-Za-z0-9_-]{46}$/', $toAddress)
                && !preg_match('/^-?[0-9]:[a-fA-F0-9]{64}$/', $toAddress)) {
                return ['ok' => false, 'error' => 'Invalid TON / GRAM address'];
            }

            if ($this->payoutUrl !== '') {
                $headers = ['Content-Type: application/json'];
                if ($this->apiKey !== '') {
                    $headers[] = 'X-API-Key: ' . $this->apiKey;
                }
                $payload = json_encode([
                    'to' => $toAddress,
                    'amount' => $amountHuman,
                    'jetton' => $this->jetton,
                    'mnemonic' => $this->mnemonic,
                    'secret' => $this->payoutSecret,
                    'network' => 'ton',
                    'api_url' => $this->apiUrl,
                    'api_key' => $this->apiKey,
                ]);
                $ch = curl_init($this->payoutUrl);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_HTTPHEADER => $headers,
                    CURLOPT_POSTFIELDS => $payload,
                    CURLOPT_TIMEOUT => 45,
                ]);
                $res = curl_exec($ch);
                $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                if ($res === false) {
                    return ['ok' => false, 'error' => 'TON payout service unreachable'];
                }
                $data = json_decode($res, true);
                if ($code >= 200 && $code < 300 && !empty($data['ok']) && !empty($data['tx'])) {
                    return ['ok' => true, 'tx' => (string)$data['tx']];
                }
                return ['ok' => false, 'error' => (string)($data['error'] ?? 'TON payout service failed')];
            }

            if ($this->mnemonic !== '') {
                return [
                    'ok' => false,
                    'error' => 'TON seed saved. Use Demo mode for notifications, or set external payout URL for live chain send.',
                ];
            }

            return ['ok' => false, 'error' => 'TON not configured — paste 24-word seed in Payment Settings'];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}
