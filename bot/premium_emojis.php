<?php
/**
 * Premium custom emoji library.
 * FinanceEmoji pack preferred: https://t.me/addemoji/FinanceEmoji
 * Broken IDs 5019523782004441717 / 5021905410089550576 replaced.
 */

function premiumEmojiCatalog(): array
{
    return [
        // UI / status — working IDs only
        'ok'       => ['id' => '5206607081334906820', 'f' => '✅'],
        'no'       => ['id' => '5210952531676504517', 'f' => '❌'],
        'warn'     => ['id' => '5382194935057372936', 'f' => '⚠️'],
        'info'     => ['id' => '5262844652964303985', 'f' => 'ℹ️'],
        'fire'     => ['id' => '5267102644886853973', 'f' => '🔥'],
        'star'     => ['id' => '5267500801240092311', 'f' => '⭐'],
        'crown'    => ['id' => '5156877291397055163', 'f' => '👑'],
        'spark'    => ['id' => '4999015678238262018', 'f' => '✨'],
        'party'    => ['id' => '4996755833950831347', 'f' => '🎉'],
        'rocket'   => ['id' => '5195033767969839232', 'f' => '🚀'],
        'gift'     => ['id' => '5305699699204837855', 'f' => '🎁'],
        'diamond'  => ['id' => '5462902520215002477', 'f' => '💎'],
        'heart'    => ['id' => '5267102644886853973', 'f' => '❤'],
        'zap'      => ['id' => '4997289591011544358', 'f' => '⚡'],

        // Money / FinanceEmoji pack
        'money'    => ['id' => '5287231198098117669', 'f' => '💰'],
        'cash'     => ['id' => '5197434882321567830', 'f' => '💵'],
        'coin'     => ['id' => '5377505475015235101', 'f' => '🪙'],
        'card'     => ['id' => '5445353829304387411', 'f' => '💳'],
        'wallet'   => ['id' => '5287231198098117669', 'f' => '💰'],
        'bank'     => ['id' => '5332455502917949981', 'f' => '🏦'],
        'chart'    => ['id' => '5197503331215361533', 'f' => '📈'],
        'up'       => ['id' => '5197503331215361533', 'f' => '📈'],
        'receipt'  => ['id' => '5444856076954520455', 'f' => '🧾'],
        'target'   => ['id' => '5310278924616356636', 'f' => '🎯'],
        'link'     => ['id' => '5332724926216428039', 'f' => '🔗'],
        'network'  => ['id' => '5224450179368767019', 'f' => '🌐'],
        'users'    => ['id' => '5332724926216428039', 'f' => '👥'],
        'user'     => ['id' => '5332724926216428039', 'f' => '👤'],
        'home'     => ['id' => '5267500801240092311', 'f' => '🏠'],
        'megaphone'=> ['id' => '5332455502917949981', 'f' => '📣'],
        'bell'     => ['id' => '5974076810386738645', 'f' => '🔔'],
        'search'   => ['id' => '5976655487276421359', 'f' => '🔎'],
        'back'     => ['id' => '5416041192905265756', 'f' => '⬅️'],
        'retry'    => ['id' => '5375338737028841420', 'f' => '🔄'],
        'settings' => ['id' => '5974104203688152439', 'f' => '⚙'],
        'lock'     => ['id' => '6003424016977628379', 'f' => '🔒'],
        'key'      => ['id' => '5307843983102204243', 'f' => '🔑'],
        'shield'   => ['id' => '5197288647275071607', 'f' => '🛡'],
        'bot'      => ['id' => '5971808079811972376', 'f' => '🤖'],
        'phone'    => ['id' => '5974098293813152457', 'f' => '📱'],
        'ticket'   => ['id' => '5240228673738527951', 'f' => '🏷️'],
        'trophy'   => ['id' => '5188344996356448758', 'f' => '🏆'],
        'wave'     => ['id' => '5267500801240092311', 'f' => '👋'],
        'chat'     => ['id' => '5303138782004924588', 'f' => '💬'],
        'mail'     => ['id' => '5472239203590888751', 'f' => '📩'],
        'pin'      => ['id' => '5976688884942114037', 'f' => '📌'],
        'block'    => ['id' => '5210952531676504517', 'f' => '🚫'],
        'plus'     => ['id' => '5971860323794160759', 'f' => '➕'],
        'brain'    => ['id' => '5053076169600009433', 'f' => '🧠'],
        'magnet'   => ['id' => '5377535110289576661', 'f' => '🧲'],
        'seed'     => ['id' => '5474417568053745249', 'f' => '🌱'],
        'plane'    => ['id' => '5201691993775818138', 'f' => '✈️'],
        'daily'    => ['id' => '5274055917766202507', 'f' => '🗓'],
        'ads'      => ['id' => '5294167145079395967', 'f' => '🛍'],
        'payout'   => ['id' => '5445355530111437729', 'f' => '📤'],
    ];
}

function unicodeToPremiumMap(): array
{
    $c = premiumEmojiCatalog();
    $pairs = [
        '✅' => 'ok', '✔️' => 'ok', '☑' => 'ok',
        '❌' => 'no', '❎' => 'no', '✖' => 'no',
        '⚠️' => 'warn', '⚠' => 'warn', '❗' => 'warn',
        'ℹ️' => 'info', 'ℹ' => 'info',
        '🔥' => 'fire', '⭐' => 'star', '⭐️' => 'star', '🌟' => 'spark',
        '👑' => 'crown', '✨' => 'spark', '🎉' => 'party', '🎊' => 'party',
        '🚀' => 'rocket', '🎁' => 'gift', '💎' => 'diamond',
        '❤' => 'heart', '❤️' => 'heart',
        '⚡' => 'zap', '⚡️' => 'zap',
        '💰' => 'money', '💵' => 'cash', '💸' => 'cash',
        '🪙' => 'coin', '💳' => 'card', '👛' => 'wallet',
        '🏦' => 'bank', '📊' => 'chart', '📈' => 'up',
        '🧾' => 'receipt', '🎯' => 'target', '🔗' => 'link',
        '🌐' => 'network', '🌍' => 'network', '🌎' => 'network',
        '👥' => 'users', '👤' => 'user', '🏠' => 'home',
        '📣' => 'megaphone', '📢' => 'megaphone', '🔔' => 'bell',
        '⬅️' => 'back', '🔄' => 'retry', '🛡' => 'shield',
        '🤖' => 'bot', '🏆' => 'trophy', '👋' => 'wave',
        '💬' => 'chat', '🚫' => 'block', '🗓' => 'daily', '🛍' => 'ads',
        '📤' => 'payout',
    ];
    $out = [];
    foreach ($pairs as $uni => $key) {
        if (isset($c[$key])) {
            $out[$uni] = $c[$key];
        }
    }
    return $out;
}

function premiumTag(string $roleOrId, string $fallback = '⭐'): string
{
    $c = premiumEmojiCatalog();
    if (isset($c[$roleOrId])) {
        return '<tg-emoji emoji-id="' . $c[$roleOrId]['id'] . '">' . $c[$roleOrId]['f'] . '</tg-emoji>';
    }
    if (preg_match('/^\d{10,}$/', $roleOrId)) {
        return '<tg-emoji emoji-id="' . $roleOrId . '">' . $fallback . '</tg-emoji>';
    }
    return $fallback;
}

function upgradeTextEmojisToPremium(string $text): string
{
    $map = unicodeToPremiumMap();
    $keys = array_keys($map);
    usort($keys, static function ($a, $b) {
        return mb_strlen($b) <=> mb_strlen($a);
    });
    $slots = [];
    $text = preg_replace_callback('/<tg-emoji\s[^>]*>.*?<\/tg-emoji>/su', static function ($m) use (&$slots) {
        $k = "\x00PE" . count($slots) . "\x00";
        $slots[$k] = $m[0];
        return $k;
    }, $text) ?? $text;
    foreach ($keys as $uni) {
        if ($uni === '' || !str_contains($text, $uni)) continue;
        $info = $map[$uni];
        $tag = '<tg-emoji emoji-id="' . $info['id'] . '">' . $info['f'] . '</tg-emoji>';
        $text = str_replace($uni, $tag, $text);
    }
    foreach ($slots as $k => $v) {
        $text = str_replace($k, $v, $text);
    }
    return $text;
}

function premiumCeDefaults(): array
{
    $c = premiumEmojiCatalog();
    return [
        'ce_welcome_1'  => $c['wave']['id'],
        'ce_welcome_2'  => $c['money']['id'],
        'ce_welcome_3'  => $c['network']['id'],
        'ce_welcome_4'  => $c['rocket']['id'],
        'ce_welcome_5'  => $c['shield']['id'],
        'ce_welcome_6'  => $c['ok']['id'],
        'ce_join_1'     => $c['bank']['id'],
        'ce_join_2'     => $c['chat']['id'],
        'ce_join_ok'    => $c['ok']['id'],
        'ce_join_no'    => $c['no']['id'],
        'ce_retry'      => $c['retry']['id'],
        'ce_menu_1'     => $c['star']['id'],
        'ce_wallet_1'   => $c['money']['id'],
        'ce_balance'    => $c['cash']['id'],
        'ce_ref_1'      => $c['users']['id'],
        'ce_ref_2'      => $c['link']['id'],
        'ce_ref_rocket' => $c['rocket']['id'],
        'ce_ref_gift'   => $c['gift']['id'],
        'ce_payout_1'   => $c['payout']['id'],
        'ce_payout_ok'  => $c['ok']['id'],
        'ce_payout_no'  => $c['no']['id'],
        'ce_card'       => $c['card']['id'],
        'ce_address'    => $c['card']['id'],
        'ce_network'    => $c['network']['id'],
        'ce_earn_1'     => $c['target']['id'],
        'ce_target'     => $c['target']['id'],
        'ce_warn'       => $c['warn']['id'],
        'ce_ok'         => $c['ok']['id'],
        'ce_no'         => $c['no']['id'],
        'ce_fire'       => $c['fire']['id'],
        'ce_chart'      => $c['chart']['id'],
        'ce_receipt'    => $c['receipt']['id'],
        'ce_btn_wallet'    => $c['wallet']['id'],
        'ce_btn_referrals' => $c['users']['id'],
        'ce_btn_payout'    => $c['payout']['id'],
        'ce_btn_earn'      => $c['target']['id'],
        'ce_btn_back'      => $c['back']['id'],
        'ce_btn_cancel'    => $c['no']['id'],
        'ce_btn_agree'     => $c['ok']['id'],
        'ce_btn_retry'     => $c['retry']['id'],
        'ce_btn_channel'   => $c['megaphone']['id'],
        'ce_daily'         => $c['daily']['id'],
        'ce_ads'           => $c['ads']['id'],
        'ce_gift'          => $c['gift']['id'],
    ];
}
