<?php
/**
 * Premium custom emoji library (from packs: VariousAnimations9, IconsInTg, TONEmoji, Emoji_fan37).
 * Used by ce() defaults and broadcast text upgrade.
 */

/** Curated role → premium emoji-id + unicode fallback */
function premiumEmojiCatalog(): array
{
    return [
        // UI / status
        'ok'       => ['id' => '5021905410089550576', 'f' => '✅'],
        'no'       => ['id' => '5019523782004441717', 'f' => '❌'],
        'warn'     => ['id' => '4956611513369494230', 'f' => '⚠️'],
        'info'     => ['id' => '5974193375799152241', 'f' => 'ℹ️'],
        'fire'     => ['id' => '4956499161319998529', 'f' => '🔥'],
        'star'     => ['id' => '5152618775488496211', 'f' => '⭐'],
        'crown'    => ['id' => '5156877291397055163', 'f' => '👑'],
        'spark'    => ['id' => '4999015678238262018', 'f' => '✨'],
        'party'    => ['id' => '4996755833950831347', 'f' => '🎉'],
        'rocket'   => ['id' => '5188481279963715781', 'f' => '🚀'],
        'gift'     => ['id' => '5974412071238897156', 'f' => '🎁'],
        'diamond'  => ['id' => '5462902520215002477', 'f' => '💎'],
        'heart'    => ['id' => '4996980495100150380', 'f' => '❤'],
        'zap'      => ['id' => '4997289591011544358', 'f' => '⚡'],

        // Money / bot features
        'money'    => ['id' => '5417924076503062111', 'f' => '💰'],
        'cash'     => ['id' => '5201873447554145566', 'f' => '💵'],
        'coin'     => ['id' => '5382164415019768638', 'f' => '🪙'],
        'card'     => ['id' => '5472250091332993630', 'f' => '💳'],
        'wallet'   => ['id' => '5215420556089776398', 'f' => '👛'],
        'bank'     => ['id' => '5238132025323444613', 'f' => '🏦'],
        'chart'    => ['id' => '5783078953308655968', 'f' => '📊'],
        'up'       => ['id' => '5298614648138919107', 'f' => '📈'],
        'receipt'  => ['id' => '5204242830687494041', 'f' => '🧾'],
        'target'   => ['id' => '5461009483314517035', 'f' => '🎯'],
        'link'     => ['id' => '5440410042773824003', 'f' => '🔗'],
        'network'  => ['id' => '5974475701179387553', 'f' => '🌐'],
        'users'    => ['id' => '5976771524407856876', 'f' => '👥'],
        'user'     => ['id' => '5974038293120027938', 'f' => '👤'],
        'home'     => ['id' => '6008131872364694876', 'f' => '🏠'],
        'megaphone'=> ['id' => '5298609030321691620', 'f' => '📣'],
        'bell'     => ['id' => '5974076810386738645', 'f' => '🔔'],
        'search'   => ['id' => '5976655487276421359', 'f' => '🔎'],
        'back'     => ['id' => '5854967531793550989', 'f' => '⬅️'],
        'retry'    => ['id' => '6012661228910939253', 'f' => '🔄'],
        'settings' => ['id' => '5974104203688152439', 'f' => '⚙'],
        'lock'     => ['id' => '6003424016977628379', 'f' => '🔒'],
        'key'      => ['id' => '5307843983102204243', 'f' => '🔑'],
        'shield'   => ['id' => '6048537430036844009', 'f' => '🛡'],
        'bot'      => ['id' => '5971808079811972376', 'f' => '🤖'],
        'phone'    => ['id' => '5974098293813152457', 'f' => '📱'],
        'ticket'   => ['id' => '5298877105000439431', 'f' => '🏷️'],
        'trophy'   => ['id' => '5188344996356448758', 'f' => '🏆'],
        'wave'     => ['id' => '4963072209334567688', 'f' => '👋'],
        'chat'     => ['id' => '5235570365094188078', 'f' => '💬'],
        'mail'     => ['id' => '5472239203590888751', 'f' => '📩'],
        'pin'      => ['id' => '5976688884942114037', 'f' => '📌'],
        'block'    => ['id' => '5972201876773408053', 'f' => '🚫'],
        'plus'     => ['id' => '5971860323794160759', 'f' => '➕'],
        'brain'    => ['id' => '5053076169600009433', 'f' => '🧠'],
        'magnet'   => ['id' => '5202106638508512484', 'f' => '🧲'],
        'seed'     => ['id' => '5474417568053745249', 'f' => '🌱'],
        'plane'    => ['id' => '5972282179776940830', 'f' => '✈️'],
    ];
}

/** Unicode emoji (or short alias) → premium id for broadcast upgrade */
function unicodeToPremiumMap(): array
{
    $c = premiumEmojiCatalog();
    // Map common unicode → catalog keys
    $pairs = [
        '✅' => 'ok', '✔️' => 'ok', '☑' => 'ok',
        '❌' => 'no', '❎' => 'no', '✖' => 'no',
        '⚠️' => 'warn', '⚠' => 'warn', '❗' => 'warn', '‼️' => 'warn',
        'ℹ️' => 'info', 'ℹ' => 'info',
        '🔥' => 'fire',
        '⭐' => 'star', '⭐️' => 'star', '🌟' => 'spark',
        '👑' => 'crown',
        '✨' => 'spark',
        '🎉' => 'party', '🎊' => 'party',
        '🚀' => 'rocket',
        '🎁' => 'gift',
        '💎' => 'diamond',
        '❤' => 'heart', '❤️' => 'heart', '💕' => 'heart',
        '⚡' => 'zap', '⚡️' => 'zap',
        '💰' => 'money', '💵' => 'cash', '💸' => 'cash', '💴' => 'cash',
        '🪙' => 'coin',
        '💳' => 'card',
        '👛' => 'wallet', '👜' => 'wallet',
        '🏦' => 'bank',
        '📊' => 'chart', '📈' => 'up', '📉' => 'chart',
        '🧾' => 'receipt',
        '🎯' => 'target',
        '🔗' => 'link', '📎' => 'link',
        '🌐' => 'network', '🌍' => 'network', '🌎' => 'network',
        '👥' => 'users', '👤' => 'user',
        '🏠' => 'home', '🏡' => 'home',
        '📣' => 'megaphone', '📢' => 'megaphone',
        '🔔' => 'bell',
        '🔎' => 'search', '🔍' => 'search',
        '⬅️' => 'back', '◀' => 'back', '🔙' => 'back',
        '🔄' => 'retry', '🔁' => 'retry',
        '⚙' => 'settings', '⚙️' => 'settings',
        '🔒' => 'lock', '🔑' => 'key',
        '🛡' => 'shield', '🛡️' => 'shield',
        '🤖' => 'bot',
        '📱' => 'phone',
        '🏆' => 'trophy',
        '👋' => 'wave',
        '💬' => 'chat',
        '📩' => 'mail', '📧' => 'mail',
        '📌' => 'pin',
        '🚫' => 'block', '⛔' => 'block',
        '➕' => 'plus',
        '🧠' => 'brain',
        '🧲' => 'magnet',
        '🌱' => 'seed',
        '✈️' => 'plane', '✈' => 'plane',
        '🎟' => 'ticket', '🎫' => 'ticket',
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
        $id = $c[$roleOrId]['id'];
        $f = $c[$roleOrId]['f'];
        return '<tg-emoji emoji-id="' . $id . '">' . $f . '</tg-emoji>';
    }
    if (preg_match('/^\d{10,}$/', $roleOrId)) {
        return '<tg-emoji emoji-id="' . $roleOrId . '">' . $fallback . '</tg-emoji>';
    }
    return $fallback;
}

/**
 * Replace plain unicode emojis in text with premium <tg-emoji> tags.
 * Does not touch existing <tg-emoji> blocks.
 */
function upgradeTextEmojisToPremium(string $text): string
{
    $map = unicodeToPremiumMap();
    // Longest keys first (emoji with VS16 etc.)
    $keys = array_keys($map);
    usort($keys, static function ($a, $b) {
        return mb_strlen($b) <=> mb_strlen($a);
    });

    // Protect existing tg-emoji
    $slots = [];
    $text = preg_replace_callback('/<tg-emoji\s[^>]*>.*?<\/tg-emoji>/su', static function ($m) use (&$slots) {
        $k = "\x00PE" . count($slots) . "\x00";
        $slots[$k] = $m[0];
        return $k;
    }, $text) ?? $text;

    foreach ($keys as $uni) {
        if ($uni === '' || !str_contains($text, $uni)) {
            continue;
        }
        $info = $map[$uni];
        $tag = '<tg-emoji emoji-id="' . $info['id'] . '">' . $info['f'] . '</tg-emoji>';
        $text = str_replace($uni, $tag, $text);
    }

    foreach ($slots as $k => $v) {
        $text = str_replace($k, $v, $text);
    }
    return $text;
}

/** Defaults for ce() keys using this catalog */
function premiumCeDefaults(): array
{
    $c = premiumEmojiCatalog();
    return [
        'ce_welcome_1'  => $c['wave']['id'],
        'ce_welcome_2'  => $c['money']['id'],
        'ce_welcome_3'  => $c['link']['id'],
        'ce_welcome_4'  => $c['cash']['id'],
        'ce_welcome_5'  => $c['warn']['id'],
        'ce_welcome_6'  => $c['ok']['id'],
        'ce_join_1'     => $c['bank']['id'],
        'ce_join_2'     => $c['chat']['id'],
        'ce_join_ok'    => $c['ok']['id'],
        'ce_join_no'    => $c['no']['id'],
        'ce_retry'      => $c['retry']['id'],
        'ce_menu_1'     => $c['home']['id'],
        'ce_wallet_1'   => $c['money']['id'],
        'ce_balance'    => $c['cash']['id'],
        'ce_ref_1'      => $c['users']['id'],
        'ce_ref_2'      => $c['link']['id'],
        'ce_ref_rocket' => $c['rocket']['id'],
        'ce_ref_gift'   => $c['gift']['id'],
        'ce_payout_1'   => $c['card']['id'],
        'ce_payout_ok'  => $c['ok']['id'],
        'ce_payout_no'  => $c['no']['id'],
        'ce_card'       => $c['card']['id'],
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
        'ce_btn_payout'    => $c['card']['id'],
        'ce_btn_earn'      => $c['target']['id'],
        'ce_btn_back'      => $c['back']['id'],
        'ce_btn_cancel'    => $c['no']['id'],
        'ce_btn_agree'     => $c['ok']['id'],
        'ce_btn_retry'     => $c['retry']['id'],
        'ce_btn_channel'   => $c['megaphone']['id'],
    ];
}
