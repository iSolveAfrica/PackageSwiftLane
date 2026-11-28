<?php
/**
 * M-CTrack (live_tracking.php, customer, view LiveTracking): the customer's moving parcels (sl_trackConsoleData
 * source 'mine'). The map and journey sheet follow the first parcel in the list; tiles and tabs use the same
 * f / q parameters as the web page.
 */
$slPhone['nav'] = 'customer';
$slPhone['tab'] = 'Track';
$tc = (array) ($d['tc'] ?? []);
$items = (array) ($tc['items'] ?? []);
$t = (array) ($tc['tally'] ?? []);
$filter = (string) ($d['filter'] ?? 'all');
$q = (string) ($d['q'] ?? '');
$labels = sl_tcFilters();
$counts = (array) ($tc['counts'] ?? []);
$first = $items[0] ?? null;
$canShip = $GLOBALS['user']->cdp_hasPermission('courier_add_client');
if ($first) {
    $pct = max(0, min(100, (int) $first['pct']));
    $tt = max(0.04, min(0.96, $pct / 100));
    $px = (1 - $tt) ** 2 * 96 + 2 * (1 - $tt) * $tt * 214 + $tt ** 2 * 300;
    $py = (1 - $tt) ** 2 * 132 + 2 * (1 - $tt) * $tt * 96 + $tt ** 2 * 262;
    $hist = array_reverse(array_slice((array) $first['hist'], 0, 5));
    $place = fn($x) => trim((string) ($x['city'] ?? '') . (($x['code'] ?? '') !== '' ? ' · ' . $x['code'] : ''));
}
$stCls = fn($it) => !empty($it['arrived']) ? 'ok' : (!empty($it['late']) ? 'bad' : 'sky');
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-auth.css')) ?>">
<?= sl_phoneTop('Live tracking', '', ['back' => sl_route('Main')], '<a class="mx-ib" href="#lt-more" aria-label="More actions"><i class="ic i-more" aria-hidden="true"></i></a>') ?>
<main class="mx-body">
<?php if ($first): ?>
<div class="b1-trk b1-tab">
<div class="b1-tmap"><svg viewBox="0 0 390 380" preserveAspectRatio="xMidYMid slice" aria-hidden="true"><rect width="390" height="380" fill="#DCE5E8"/><g fill="#F1EEE8" stroke="#DED8CD" stroke-width="1.5"><path d="M-20 52 C 30 38, 92 44, 124 84 C 146 112, 128 150, 100 170 C 80 186, 70 212, 46 226 C 24 238, 0 236, -20 240Z"/><path d="M40 262 C 82 250, 126 268, 140 300 C 148 330, 124 362, 104 400 L 52 400 C 50 360, 36 300, 40 262Z"/><path d="M252 32 C 292 20, 352 30, 410 52 L 410 120 C 370 132, 330 116, 300 126 C 282 108, 250 76, 252 32Z"/><path d="M262 160 C 302 146, 366 148, 410 168 L 410 400 L 336 400 C 326 350, 306 316, 296 290 C 280 268, 248 222, 262 160Z"/></g><path d="M96 132 Q 214 96 300 262" fill="none" stroke="#161615" stroke-opacity=".35" stroke-width="2.5" stroke-dasharray="3 7" stroke-linecap="round"/><path d="M96 132 Q 214 96 300 262" pathLength="100" fill="none" stroke="#D9792B" stroke-width="4" stroke-linecap="round" stroke-dasharray="<?= $pct ?> 100"/><circle cx="96" cy="132" r="8" fill="#161615" stroke="#fff" stroke-width="3"/><g transform="translate(300 262)"><path d="M0 0c-9-12-15-18-15-26a15 15 0 0 1 30 0c0 8-6 14-15 26z" fill="#E5484D" stroke="#fff" stroke-width="2"/><circle cx="0" cy="-26" r="5.5" fill="#fff"/></g><?php if ($pct > 0 && $pct < 100): ?><g transform="translate(<?= round($px, 1) ?> <?= round($py, 1) ?>)"><circle r="17" fill="#fff" stroke="#161615" stroke-width="2"/><circle r="6" fill="#D9792B"/></g><?php endif; ?></svg>
<span class="b1-mlab" style="left:58px;top:146px"><?= h($place($first['from'])) ?></span><span class="b1-mlab" style="left:200px;top:276px"><?= h($place($first['to'])) ?></span>
<span class="b1-mchip"><i class="ic ic-sm <?= $first['mode'] === 'sea' ? 'i-ship' : 'i-plane' ?>" aria-hidden="true"></i><span><?= h((string) $first['where']) ?> · <b><?= $pct ?>%</b></span></span><a class="b1-mrt" href="<?= h($first['href']) ?>" aria-label="Open <?= h($first['code']) ?>"><i class="ic ic-sm i-expand" aria-hidden="true"></i></a></div>
<section class="b1-sh"><div class="b1-grab"></div><div class="b1-oh"><div><b class="b1-oid">#<?= h($first['code']) ?></b><span class="b1-rt"><?= h((string) ($first['from']['city'] ?? '')) ?><i class="ic ic-sm i-arrow" aria-hidden="true"></i><?= h((string) ($first['to']['city'] ?? '')) ?></span></div><span class="b1-st <?= $stCls($first) ?>"><?= h($first['st']) ?></span></div>
<ol class="b1-tl"><?php foreach ($hist as $i => $hh): ?><li class="<?= $i === count($hist) - 1 ? 'cur' : 'done' ?>"><i></i><div><b><?= h(ucfirst($hh['t'])) ?></b><small><?= h($hh['at']) ?></small></div><?php if ($hh['loc'] !== ''): ?><em><?= h($hh['loc']) ?></em><?php endif; ?></li><?php endforeach; ?>
<?php if (!$hist): ?><li class="cur"><i></i><div><b><?= h($first['st']) ?></b><small>Registered <?= h($first['created']) ?></small></div></li><?php endif; ?>
<?php if (empty($first['arrived'])): ?><li class="todo"><i></i><div><b>Arrives</b><small><?= ($first['eta'] ?? 'Not set') !== 'Not set' ? 'Expected ' . h($first['eta']) : 'No ETA yet' ?></small></div><em><?= h((string) ($first['to']['city'] ?? '')) ?></em></li><?php endif; ?>
</ol>
<div class="b1-pair"><a class="b1-amb" href="<?= h($first['href']) ?>"><i class="ic ic-sm i-box" aria-hidden="true"></i>Open record</a><a class="b1-wht" href="support.php?q=<?= rawurlencode('Help with ' . $first['code']) ?>"><i class="ic ic-sm i-message" aria-hidden="true"></i>Chat support</a></div></section>
</div>
<?php endif; ?>
<div class="b1-pad">
<section class="b1-tiles">
<a class="b1-tile<?= $filter === 'air' ? ' on' : '' ?>" href="<?= h(sl_url(['f' => 'air'])) ?>"><div class="b1-th"><span><i class="ic i-plane" aria-hidden="true"></i></span><em>Open</em></div><b><?= (int) ($t['air'] ?? 0) ?></b><small>My air shipments</small><div class="b1-tf"><span class="b1-wn"><i class="ic ic-sm i-route" aria-hidden="true"></i> Air only</span><i><i class="ic ic-sm i-right" aria-hidden="true"></i></i></div></a>
<a class="b1-tile<?= $filter === 'sea' ? ' on' : '' ?>" href="<?= h(sl_url(['f' => 'sea'])) ?>"><div class="b1-th"><span><i class="ic i-ship" aria-hidden="true"></i></span><em>Open</em></div><b><?= (int) ($t['sea'] ?? 0) ?></b><small>My sea packages</small><div class="b1-tf"><span class="b1-up"><i class="ic ic-sm i-route" aria-hidden="true"></i> Sea only</span><i><i class="ic ic-sm i-right" aria-hidden="true"></i></i></div></a>
</section>
<div class="mx-srch"><form method="get" action="live_tracking.php" role="search"><?php if ($filter !== 'all'): ?><input type="hidden" name="f" value="<?= h($filter) ?>"><?php endif; ?><label><i class="ic i-search" aria-hidden="true"></i><input type="search" name="q" value="<?= h($q) ?>" aria-label="Tracking number or store tracking" placeholder="Tracking number or store tracking" enterkeyhint="search"></label></form></div>
<nav class="mx-seg" aria-label="Show"><?php foreach ($labels as $fk => $fl): if ($fk === 'con' && !isset($t['air_con']) && !isset($t['sea_con'])) continue; ?><a class="<?= $filter === $fk ? 'on' : '' ?>" href="<?= h(sl_url(['f' => $fk === 'all' ? null : $fk])) ?>"<?= $filter === $fk ? ' aria-current="page"' : '' ?>><?= h($fl) ?><?= isset($counts[$fk]) && $counts[$fk] > 0 && in_array($fk, ['all', 'late'], true) ? ' ' . (int) $counts[$fk] : '' ?></a><?php endforeach; ?></nav>
<?php foreach ($items as $i => $it): $bar = !empty($it['late']) ? 'bad' : (!empty($it['arrived']) ? 'ok' : ''); ?>
<details class="b1-ex"<?= $i === 0 ? ' open' : '' ?>><summary><span class="b1-pi" style="width:44px;height:44px;border-radius:50%;background:var(--active);display:grid;place-items:center;flex:none"><i class="ic <?= $it['kind'] === 'consolidation' ? 'i-layers' : ($it['mode'] === 'sea' ? 'i-ship' : 'i-plane') ?>" aria-hidden="true"></i></span><div><b><?= h($it['code']) ?> · <?= h($it['kindL']) ?></b><small><?= h((string) $it['where']) ?> · <?= h($it['st']) ?></small><span style="display:flex;gap:6px;align-items:center"><?php if (!empty($it['late'])): ?><span class="b1-late">Delayed</span><?php elseif (empty($it['arrived'])): ?><span class="b1-live">Moving</span><?php endif; ?></span></div><i class="ic ic-sm i-down" aria-hidden="true"></i></summary>
<div class="b1-exb"><div class="b1-pbar" role="img" aria-label="<?= (int) $it['pct'] ?>% travelled"><i class="<?= $bar ?>" style="width:<?= (int) $it['pct'] ?>%"></i></div><div class="b1-pf"><span><?= (int) $it['pct'] ?>% travelled</span><span style="text-align:right"><?= h($it['estimate']) ?></span></div>
<div class="b1-exk"><div><small>ETA</small><b><?= h($it['eta']) ?></b></div><?php if (!empty($it['money'])): ?><div><small>Total</small><b><?= h($it['total']) ?></b></div><div><small>Payment</small><b><?= h($it['pay']) ?></b></div><?php else: ?><div><small>Weight</small><b><?= h($it['weight']) ?></b></div><?php endif; ?></div>
<a class="b1-lk dk" style="align-self:flex-start" href="<?= h($it['href']) ?>">Open record<i class="ic ic-sm i-right" aria-hidden="true"></i></a></div></details>
<?php endforeach; ?>
<?php if (!$items): ?>
<section class="b1-empty"><span><i class="ic i-route" aria-hidden="true"></i></span><b><?= $q !== '' ? 'Nothing matches “' . h($q) . '”' : 'Nothing is moving right now' ?></b><small><?= $q !== '' ? 'Check the number and try again.' : 'Your parcels show up here as soon as we receive them at a warehouse.' ?></small><div class="b1-btns"><a class="b1-lk" href="<?= h(sl_route('PublicTracking')) ?>">Track with a number</a><?php if ($canShip): ?><a class="b1-lk dk" href="courier_add_client.php">Ship a parcel</a><?php endif; ?></div></section>
<?php endif; ?>
<section class="mx-card mx-list" id="lt-more">
<a class="mx-row" href="<?= h(sl_route('PublicTracking')) ?>"><span class="mx-li"><i class="ic i-search" aria-hidden="true"></i></span><span class="mx-rm"><b>Track any number</b></span><i class="ic ic-sm mx-ch i-right" aria-hidden="true"></i></a>
<a class="mx-row" href="<?= h(sl_route('CustomerNotifications')) ?>"><span class="mx-li"><i class="ic i-bell" aria-hidden="true"></i></span><span class="mx-rm"><b>Notification settings</b></span><i class="ic ic-sm mx-ch i-right" aria-hidden="true"></i></a>
<a class="mx-row" href="support.php"><span class="mx-li"><i class="ic i-help" aria-hidden="true"></i></span><span class="mx-rm"><b>Get help</b></span><i class="ic ic-sm mx-ch i-right" aria-hidden="true"></i></a>
</section>
</div>
</main>
