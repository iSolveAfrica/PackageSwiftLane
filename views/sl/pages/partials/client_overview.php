<?php
/**
 * Customer dashboard top (desktop and tablet; phones use partials/mobile_dashboard.php), in the same
 * card style as the staff control panel (partials/overview_hero.php, assets/swiftlane/overview-hero.css):
 * the customer's card on the left, parcel and bills cards in the middle, the locker ID and warehouse
 * addresses on the right (one country at a time, tabs scroll when there are many), then the last 12 months
 * and what they ship most.
 * Data: sl_clientDashboardData() + sl_clientCardsData() in views/sl/data/portal.php.
 */
$cc = $d['cc'];
$h0 = (int) date('G');
$act = (array) ($d['parcelActivity'] ?? []);
$bars = function (array $bars, string $cls = '', string $label = 'Last 14 days') {
    if (!$bars) return '';
    $o = '<div class="ovh-bars ' . h($cls) . '" role="img" aria-label="' . h($label) . '">';
    $n = count($bars);
    foreach ($bars as $i => $b) $o .= '<i class="' . ($i === $n - 1 ? 'now' : '') . '" style="' . h($b['s']) . '" title="' . h($b['t']) . '"></i>';
    return $o . '</div>';
};
$yearN = max(1, (int) $d['year']);
$wherePeak = max(1, max(array_map(fn($w) => $w['n'], $cc['where']) ?: [0]));
$delivery = $d['mobileDelivery'] ?? null;
$addrs = array_values((array) ($d['addresses'] ?? []));
$locker = (string) ($d['locker'] ?? '');
$addrMore = $GLOBALS['user']->cdp_hasPermission('client_virtual_mail_box_addresses') ? 'client_virtual_mail_box_addresses.php' : '';
// Customer service: chat (support.php), WhatsApp / phone / email from Settings > Company.
$coCore = $GLOBALS['core'] ?? null;
$svcTel = preg_replace('/[^\d+]/', '', (string) ($coCore->c_phone ?? ''));
$svcWa = preg_replace('/\D+/', '', (string) ($coCore->cell_phone ?? ''));
$svcMail = filter_var(trim((string) ($coCore->site_email ?? '')), FILTER_VALIDATE_EMAIL) ?: '';
$coUid = (int) ($_SESSION['userid'] ?? 0);
$svcOpen = sl_tableExists('cdb_cs_tickets') ? (int) sl_scalar("SELECT COUNT(*) FROM cdb_cs_tickets WHERE customer_id = :u AND is_internal = 0 AND status NOT IN ('resolved','closed')", [':u' => $coUid]) : 0;
// Referrals: only when loyalty and referrals are switched on (Loyalty settings).
$ref = null;
if (is_file(CDP_ROOT . '/helpers/loyalty.php')) {
    require_once CDP_ROOT . '/helpers/loyalty.php';
    if (sl_loyaltyReferralOn()) {
        $coL = sl_loyaltyForCustomer($coUid);
        if (!empty($coL['enabled']) && !empty($coL['code'])) {
            $coS = sl_loyaltySettings();
            $coLink = sl_loyaltySignupLink((string) $coL['code']);
            $ref = ['code' => (string) $coL['code'], 'n' => (int) $coL['referrals'], 'pts' => (int) $coS['referral_referrer_points'], 'friend' => trim((string) $coS['referral_friend_reward']),
                'share' => 'Ship with ' . ($coCore->site_name ?? 'Swift Lane') . '. Register with my code ' . $coL['code'] . ': ' . $coLink];
        }
    }
}
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/overview-hero.css')) ?>">
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/locker-addresses.css')) ?>">
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/client-overview.css')) ?>">
<script src="<?= h(cdp_asset('dataJs/sl/client_wallet.js')) ?>" defer></script>
<div class="ovh-wrap"><div class="ovh-grid co-grid<?= count($cc['cards']) < 2 ? ' co-one' : '' ?>" style="--rows:2">
  <section class="ovh-me" aria-label="Your account">
    <div class="ovh-me-top">
      <?= $slPic(64, false) ?>
      <div class="col grow" style="min-width:0">
        <span class="ovh-eyebrow"><?= h(($h0 < 12 ? 'Good morning' : ($h0 < 17 ? 'Good afternoon' : 'Good evening')) . ' · ' . date('l j M')) ?></span>
        <h1 class="ovh-name">Welcome back, <?= h($d['first']) ?></h1>
        <span class="mut sm"><?= $d['locker'] !== '' ? 'Locker <b class="mono">' . h($d['locker']) . '</b>' : 'Customer' ?><?= $d['since'] ? ' · shipping with us since ' . h($d['since']) : '' ?></span>
      </div>
      <a class="sl-btn sm ghost ovh-prof" href="<?= h(sl_route('UserHome')) ?>"><i class="ic ic-sm i-user" aria-hidden="true"></i>My profile</a>
    </div>

    <div class="co-next<?= $delivery || $d['ready'] ? ' hot' : '' ?>">
      <?php if ($delivery): ?>
        <div><small>Your delivery</small><b><?= $delivery->state === 'onroute' ? 'Your rider is on the way' : 'Delivery requested' ?></b><span><?= h($delivery->address) ?></span></div>
        <a class="sl-btn sm" href="delivery_request.php?id=<?= (int) $delivery->id ?>">View delivery</a>
      <?php elseif ($d['ready']): ?>
        <div><small>Collection</small><b><?= (int) $d['ready'] ?> <?= (int) $d['ready'] === 1 ? 'parcel is' : 'parcels are' ?> ready for pickup</b></div>
        <a class="sl-btn sm" href="<?= h(sl_route('PickupCodes')) ?>">Show pickup codes</a>
      <?php elseif ($d['latest']): ?>
        <div><small>Your latest parcel</small><b class="mono"><?= h($d['latest']['id']) ?></b><span><?= h($d['latest']['st']) ?></span></div>
        <a class="sl-btn sm ghost" href="<?= h($d['latest']['href']) ?>">View parcel</a>
      <?php else: ?>
        <div><b>Shipping your first parcel?</b><span>Use your locker address below when you order.</span></div>
        <a class="sl-btn sm" href="#addresses">Find my address</a>
      <?php endif; ?>
    </div>

    <div class="ovh-kpis">
      <div class="ovh-kpi"><span class="ovh-kl">Parcels this year</span><b><?= sl_num($d['year']) ?></b><span class="ovh-ku">air shipments + sea packages</span></div>
      <div class="ovh-kpi"><span class="ovh-kl">Delivered this year</span><b><?= sl_num($d['deliveredYear']) ?><small>/<?= sl_num($d['year']) ?></small></b><span class="ovh-meter" role="img" aria-label="<?= (int) $d['deliveredYear'] ?> of <?= (int) $d['year'] ?> delivered"><i style="width:<?= sl_pct($d['deliveredYear'], $yearN) ?>%"></i></span></div>
      <div class="ovh-kpi"><span class="ovh-kl">On the way</span><b><?= sl_num($d['onWay']) ?></b><span class="ovh-ku"><?= sl_num($d['atWh']) ?> at the warehouse</span></div>
      <?php if ($d['finance']): ?><div class="ovh-kpi"><span class="ovh-kl">Balance due</span><b class="<?= $d['dueN'] ? 'co-bad' : '' ?>"><?= h($d['dueMoney']) ?></b><span class="ovh-ku"><?= $d['dueN'] ? (int) $d['dueN'] . ' unpaid' . ($d['dueDate'] ? ' · due ' . h($d['dueDate']) : '') : 'all paid' ?></span></div>
      <?php else: ?><div class="ovh-kpi"><span class="ovh-kl">Ready for pickup</span><b><?= sl_num($d['ready']) ?></b><span class="ovh-ku">waiting for you</span></div><?php endif; ?>
    </div>

    <div class="ovh-kinds">
      <div class="row between sm"><span class="fw5">Where my parcels are now</span><span class="mut"><?= sl_num($d['active']) ?> not yet delivered</span></div>
      <?php foreach ($cc['where'] as $w): ?><div class="ovh-kind"><span><?= h($w['l']) ?></span><span class="ovh-kbar"><i style="width:<?= $w['n'] ? max(3, round(100 * $w['n'] / $wherePeak)) : 0 ?>%;background:<?= $w['c'] ?>"></i></span><b><?= sl_num($w['n']) ?></b></div><?php endforeach; ?>
    </div>

    <?php if ($act): ?>
    <div class="ovh-me-bars">
      <div class="row between sm"><span class="mut">My parcels registered · last 14 days</span><span class="mut"><?= sl_num(array_sum($act)) ?> in total</span></div>
      <?= $bars(sl_sparkBars(array_values($act), 4, array_map(fn($x) => date('D j M', strtotime($x)), array_keys($act))), 'violet', 'Parcels registered per day, last 14 days') ?>
    </div>
    <?php endif; ?>

    <div class="ovh-chips">
      <?php if ($d['canPrealert']): ?><a href="<?= h(sl_route('PreAlerts')) ?>"><i class="ic ic-sm i-inbox" aria-hidden="true"></i>Add pre-alert</a><?php endif; ?>
      <?php if ($d['canShip']): ?><a href="courier_add_client.php"><i class="ic ic-sm i-plus" aria-hidden="true"></i>New shipment</a><?php endif; ?>
      <a href="<?= h(sl_route('DeliveryRequest')) ?>"><i class="ic ic-sm i-truck" aria-hidden="true"></i>Request delivery</a>
      <a href="<?= h(sl_route('PickupCodes')) ?>"><i class="ic ic-sm i-key" aria-hidden="true"></i>Pickup codes <?= $d['ready'] ? '<b>' . (int) $d['ready'] . '</b>' : '' ?></a>
      <?php if ($d['canBills']): ?><a href="<?= h(sl_route('MyBills')) ?>"><i class="ic ic-sm i-card" aria-hidden="true"></i>Pay bills<?= $d['dueN'] ? ' <b>' . (int) $d['dueN'] . '</b>' : '' ?></a><?php endif; ?>
      <a href="#addresses"><i class="ic ic-sm i-pin" aria-hidden="true"></i>My addresses</a>
      <a href="support.php"><i class="ic ic-sm i-message" aria-hidden="true"></i>Get help</a>
    </div>
  </section>

  <?php foreach ($cc['cards'] as $c): ?>
  <a class="ovh-card t-<?= h($c['tone']) ?>" href="<?= h($c['href']) ?>">
    <div class="ovh-ch"><span class="ovh-eyebrow"><i class="ic ic-sm <?= h($c['icon']) ?>" aria-hidden="true"></i><?= h($c['eyebrow']) ?></span><?php if (!empty($c['alert'])): ?><span class="ovh-alert"><i class="ic ic-sm i-alert" aria-hidden="true"></i><?= h($c['alert']) ?></span><?php else: ?><i class="ic ic-sm i-expand ovh-go" aria-hidden="true"></i><?php endif; ?></div>
    <b class="ovh-big"><?= h($c['big']) ?></b>
    <span class="ovh-lede"><?= h($c['lede']) ?></span>
    <?php if ($c['sub']): ?><div class="ovh-sub" style="--n:<?= count($c['sub']) ?>"><?php foreach ($c['sub'] as $s): ?><span><b><?= h($s['n']) ?></b><small><?= h($s['l']) ?></small></span><?php endforeach; ?></div><?php endif; ?>
    <?= $bars($c['bars'], '', (string) $c['barsL']) ?>
    <div class="ovh-foot"><span><?= h($c['barsL']) ?></span><span><?= h($c['barsR']) ?></span></div>
  </a>
  <?php endforeach; ?>

  <section class="co-addr" id="addresses" aria-labelledby="co-addr-h">
    <div class="co-addr-hero">
      <div class="row between"><span class="la-eyebrow" id="co-addr-h"><i class="ic ic-sm i-key" aria-hidden="true"></i>Your locker ID</span><?php if ($addrMore): ?><a class="la-link" href="<?= h($addrMore) ?>">How it works <i class="ic ic-sm i-right" aria-hidden="true"></i></a><?php endif; ?></div>
      <div class="la-id"><b class="mono"><?= h($locker !== '' ? $locker : '—') ?></b><?php if ($locker !== ''): ?><button type="button" class="la-copy" data-sl-copy="<?= h($locker) ?>" aria-label="Copy locker ID"><i class="ic ic-sm i-copy" aria-hidden="true"></i>Copy</button><?php endif; ?></div>
      <p>Works at <?= count($addrs) === 1 ? 'our warehouse' : 'all ' . count($addrs) . ' warehouses' ?>. Put it on every order so we know the parcel is yours.</p>
    </div>
    <?php if (!$addrs): ?>
    <p class="mut sm co-addr-empty">No warehouse address is set up yet. New addresses appear here as soon as we add them.</p>
    <?php else: ?>
    <?php $awName = (string) ($d['name'] ?? ''); include __DIR__ . '/address_wallet.php'; ?>
    <?php endif; ?>

    <div class="co-help">
      <div class="co-svc">
        <div class="co-blk-h"><span class="co-blk-ic"><i class="ic ic-sm i-message" aria-hidden="true"></i></span><div><b>Customer service</b><small><?= $svcOpen ? $svcOpen . ' open request' . ($svcOpen === 1 ? '' : 's') . ' · we reply in the chat' : 'Questions about a parcel, a bill or an address' ?></small></div></div>
        <div class="co-svc-a">
          <a class="co-svc-b" href="support.php"><i class="ic ic-sm i-message" aria-hidden="true"></i><span>Chat with us</span></a>
          <?php if ($svcWa !== ''): ?><a class="co-svc-b" href="https://wa.me/<?= h($svcWa) ?>" target="_blank" rel="noopener"><i class="ic ic-sm i-phone" aria-hidden="true"></i><span>WhatsApp</span></a><?php endif; ?>
          <?php if ($svcTel !== ''): ?><a class="co-svc-b" href="tel:<?= h($svcTel) ?>"><i class="ic ic-sm i-phone" aria-hidden="true"></i><span>Call</span></a><?php endif; ?>
          <?php if ($svcMail !== ''): ?><a class="co-svc-b" href="mailto:<?= h($svcMail) ?>"><i class="ic ic-sm i-send" aria-hidden="true"></i><span>Email</span></a><?php endif; ?>
        </div>
      </div>
      <?php if ($ref): ?>
      <div class="co-ref">
        <div class="co-blk-h"><span class="co-blk-ic"><i class="ic ic-sm i-star" aria-hidden="true"></i></span><div><b>Invite a friend<?= $ref['pts'] ? ', earn ' . sl_num($ref['pts']) . ' points' : '' ?></b><small><?= $ref['friend'] !== '' ? 'They get ' . h($ref['friend']) . '. ' : '' ?>You earn when their first parcel is delivered.</small></div></div>
        <div class="co-ref-code"><span class="mono"><?= h($ref['code']) ?></span><button type="button" class="co-ref-b ico" data-sl-copy="<?= h($ref['code']) ?>" aria-label="Copy referral code" title="Copy code"><i class="ic ic-sm i-copy" aria-hidden="true"></i></button><button type="button" class="co-ref-b pri" data-sl-copy="<?= h($ref['share']) ?>" data-co-share="<?= h($ref['share']) ?>"><i class="ic ic-sm i-send" aria-hidden="true"></i>Share link</button></div>
        <div class="co-ref-f"><span><?= sl_num($ref['n']) ?> friend<?= $ref['n'] === 1 ? '' : 's' ?> joined</span><a href="<?= h(sl_route('MyRewards')) ?>">My rewards <i class="ic ic-sm i-right" aria-hidden="true"></i></a></div>
      </div>
      <?php endif; ?>
    </div>
  </section>
</div></div>

<?php
$yr = $cc['year'];
$mk = array_keys($yr['air']);
$tot = array_map(fn($k) => $yr['air'][$k] + $yr['sea'][$k], $mk);
$peak = max(1, max($tot ?: [0]));
$sum12 = array_sum($tot);
$busyI = $sum12 ? array_search(max($tot), $tot, true) : false;
$itemPeak = max(1, max(array_map(fn($i) => $i['q'], $cc['items']) ?: [0]));
?>
<section class="co-year" aria-labelledby="co-year-h">
  <article class="co-card co-months">
    <div class="co-ch"><div><span class="ovh-eyebrow"><i class="ic ic-sm i-chart" aria-hidden="true"></i><span id="co-year-h">My last 12 months</span></span><div class="co-big"><?= sl_num($sum12) ?> <small>parcels</small></div></div>
      <div class="co-legend"><span><i class="air"></i>Air <?= sl_num(array_sum($yr['air'])) ?></span><span><i class="sea"></i>Sea <?= sl_num(array_sum($yr['sea'])) ?></span></div></div>
    <div class="co-cols" <?= sl_chartAttrs([['name' => 'Air', 'values' => array_values($yr['air']), 'color' => 'var(--ch-2)'], ['name' => 'Sea', 'values' => array_values($yr['sea']), 'color' => 'var(--ch-1)']], array_map(fn($k) => date('F Y', strtotime($k . '-01')), $mk), 'My parcels per month') ?> role="img" aria-label="My parcels per month, air and sea, last 12 months">
      <?php foreach ($mk as $i => $k): $a = $yr['air'][$k]; $s = $yr['sea'][$k]; ?><span><i class="sea" style="height:<?= round(100 * $s / $peak, 1) ?>%"></i><i class="air<?= $i === 11 ? ' now' : '' ?>" style="height:<?= round(100 * $a / $peak, 1) ?>%"></i></span><?php endforeach; ?>
    </div>
    <div class="co-axis"><?php foreach ($mk as $k): ?><span><?= h(date('M', strtotime($k . '-01'))) ?></span><?php endforeach; ?></div>
    <div class="co-stats">
      <div><span>Busiest month</span><b><?= $busyI !== false ? h(date('M Y', strtotime($mk[$busyI] . '-01'))) : '—' ?></b><?php if ($busyI !== false): ?><small><?= sl_num($tot[$busyI]) ?> parcels</small><?php endif; ?></div>
      <div><span>Weight this year</span><b><?= h(sl_weight($yr['weight'])) ?></b></div>
      <?php if ($d['finance']): ?><div><span>Spent this year</span><b><?= h(sl_money($yr['spent'])) ?></b></div><?php endif; ?>
      <div><span>Average a month</span><b><?= sl_num($sum12 / 12, 1) ?></b><small>parcels</small></div>
    </div>
  </article>
  <article class="co-card">
    <div class="co-ch"><div><span class="ovh-eyebrow"><i class="ic ic-sm i-box" aria-hidden="true"></i>What I ship most</span><div class="co-mid">Last 12 months</div></div></div>
    <?php if ($cc['items']): ?>
    <ol class="co-rank" <?= sl_chartAttrs([['name' => 'Pieces', 'values' => array_map(fn($i) => $i['q'], $cc['items'])]], array_map(fn($i) => $i['label'], $cc['items']), 'What I ship most') ?>>
      <?php foreach ($cc['items'] as $n => $it): ?><li><span class="co-rk"><?= $n + 1 ?></span><span class="co-rn"><b><?= h($it['label']) ?></b><span class="co-track"><i style="width:<?= max(3, round(100 * $it['q'] / $itemPeak, 1)) ?>%"></i></span></span><span class="co-rv"><?= sl_num($it['q']) ?> pcs</span></li><?php endforeach; ?>
    </ol>
    <?php else: ?><p class="mut sm" style="margin:0">Your item list shows here once your parcels have declared items.</p><?php endif; ?>
  </article>
</section>
