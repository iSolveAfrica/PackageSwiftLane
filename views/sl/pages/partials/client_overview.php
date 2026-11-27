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
$addrOn = 0;
foreach ($addrs as $i => $a) if (!empty($a['default'])) { $addrOn = $i; break; }
$addrMark = fn(string $t) => $locker !== '' ? str_replace(h($locker), '<mark class="la-lk">' . h($locker) . '</mark>', h($t)) : h($t);
$addrMore = $GLOBALS['user']->cdp_hasPermission('client_virtual_mail_box_addresses') ? 'client_virtual_mail_box_addresses.php' : '';
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/overview-hero.css')) ?>">
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/locker-addresses.css')) ?>">
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/client-overview.css')) ?>">
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
      <p>Works at <?= count($addrs) === 1 ? 'our warehouse' : 'all ' . count($addrs) . ' warehouses (pick a country below)' ?>. Put it on every order so we know the parcel is yours.</p>
    </div>
    <?php if (!$addrs): ?>
    <p class="mut sm co-addr-empty">No warehouse address is set up yet. New addresses appear here as soon as we add them.</p>
    <?php else: ?>
    <style><?php foreach ($addrs as $i => $a): ?>#co-at-<?= $i ?>:checked~.co-addr-panes .co-p<?= $i ?>{display:flex}#co-at-<?= $i ?>:checked~.co-addr-tabs [for=co-at-<?= $i ?>]{background:var(--card);color:var(--ink);border-color:var(--line-2);box-shadow:0 1px 2px rgba(0,0,0,.06)}#co-at-<?= $i ?>:focus-visible~.co-addr-tabs [for=co-at-<?= $i ?>]{outline:2px solid var(--focus,var(--green));outline-offset:1px}<?php endforeach; ?></style>
    <?php foreach ($addrs as $i => $a): ?><input class="co-at" type="radio" name="co-at" id="co-at-<?= $i ?>" <?= $i === $addrOn ? 'checked' : '' ?> aria-label="<?= h($a['label']) ?> address"><?php endforeach; ?>
    <?php if (count($addrs) > 1): ?>
    <div class="co-addr-tabs" aria-hidden="true">
      <?php foreach ($addrs as $i => $a): ?><label for="co-at-<?= $i ?>"><span class="co-fl"><?= h($a['flag'] !== '' ? $a['flag'] : $a['cc']) ?></span><?= h($a['label']) ?><?= $a['default'] ? '<i class="co-main" title="Main address"></i>' : '' ?></label><?php endforeach; ?>
    </div>
    <?php endif; ?>
    <div class="co-addr-panes">
      <?php foreach ($addrs as $i => $a):
        $rows = array_merge([['Name', trim((($d['name'] ?? '') ?: $a['recipient']) . ($a['hasLocker'] || $locker === '' ? '' : ' (' . $locker . ')'))]], array_map(fn($l, $j) => [$j ? '' : 'Address', $l], $a['lines'], array_keys($a['lines'])));
        if (!$a['hasLocker'] && $locker !== '') $rows[] = ['Suite', 'Suite ' . $locker];
        if ($a['phone'] !== '') $rows[] = ['Phone', $a['phone']]; ?>
      <div class="co-addr-p co-p<?= $i ?>">
        <div class="la-ch"><span class="la-flag" aria-hidden="true"><?= h($a['flag'] !== '' ? $a['flag'] : $a['cc']) ?></span><div class="la-ct"><b><?= h($a['label']) ?></b><span><?= h(trim($a['city'] . ($a['zip'] !== '' ? ' · ' . $a['zip'] : ''), ' ·')) ?: 'Warehouse' ?></span></div><?php if ($a['default']): ?><span class="la-def">Main</span><?php endif; ?></div>
        <ul class="la-lines">
          <?php foreach ($rows as [$k, $v]): ?><li><small><?= h($k) ?></small><span><?= $addrMark($v) ?></span><button type="button" class="la-ib" data-sl-copy="<?= h($v) ?>" aria-label="Copy <?= h(($k ?: 'line') . ': ' . $v) ?>"><i class="ic ic-sm i-copy" aria-hidden="true"></i></button></li><?php endforeach; ?>
        </ul>
        <div class="la-cf"><button type="button" class="sl-btn sm" data-sl-copy="<?= h($a['full']) ?>"><i class="ic ic-sm i-copy" aria-hidden="true"></i>Copy full address</button><?php if ($locker !== ''): ?><span class="la-check"><i class="ic ic-sm i-check" aria-hidden="true"></i>Locker included</span><?php endif; ?></div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
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
