<?php
/** Active payout network: bsc | ton  (TON = Gram / former Toncoin — same network) */
function activePaymentNetwork(): string
{
    $n = strtolower(trim((string)getSetting('active_payment_network', 'bsc')));
    return in_array($n, ['bsc', 'ton'], true) ? $n : 'bsc';
}

function paymentNetworkLabel(): string
{
    return activePaymentNetwork() === 'ton' ? 'TON / GRAM' : 'BEP-20 (BSC)';
}

function isValidPayoutAddress(string $address): bool
{
    $address = trim($address);
    if (activePaymentNetwork() === 'ton') {
        if (preg_match('/^(EQ|UQ)[A-Za-z0-9_-]{46}$/', $address)) {
            return true;
        }
        if (preg_match('/^-?[0-9]:[a-fA-F0-9]{64}$/', $address)) {
            return true;
        }
        return false;
    }
    return (bool)preg_match('/^0x[a-fA-F0-9]{40}$/', $address);
}

function payoutAddressHint(): string
{
    if (activePaymentNetwork() === 'ton') {
        return 'TON / GRAM wallet (EQ… / UQ…)';
    }
    return 'BSC wallet (0x…)';
}
