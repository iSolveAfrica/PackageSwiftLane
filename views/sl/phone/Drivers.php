<?php
/**
 * M-Riders: riders with today's progress. Same $d as the web page (views/sl/data/riders.php sl_driversData):
 * filter (all | route | avail | off), q, period and page are the web page's own GET parameters.
 */
require_once CDP_ROOT . '/helpers/sl_mx.php';
require_once CDP_ROOT . '/helpers/sl_phone_centres.php';
$slPhone['nav'] = 'staff';
$slPhone['tab'] = 'Menu';
$t = $d['tot'];
$pct = $t['assigned'] ? sl_pct($t['done'], $t['assigned']) : 0;
$daily = slpc_riderDaily(12);
$segs = ['route' => ['On route', $t['onroute']], 'avail' => ['Available', $t['avail']], 'off' => ['Suspended', $t['off']], 'all' => ['All', $d['n']]];
$acts = ($d['canMoney'] ? sl_phoneIcon('sliders', 'Split rules', sl_route('RiderSettings')) : '') . sl_phoneIcon('route', 'Live tracking', sl_route('DeliveryTracking'));
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-centres.css')) ?>">
<?= sl_phoneTop('Riders', $d['label'], ['back' => 'menu.php'], $acts) ?>
<main class="mx-body">
<section class="mx-k c2">
  <div class="mx-kt"><div class="mx-kh"><span class="mx-kb"><i class="ic ic-sm i-clock" aria-hidden="true"></i></span><small>Done <?= h(strtolower($d['label'])) ?></small></div><b><?= $t['assigned'] ? (int) $pct . '%' : '—' ?></b><?= slpc_spark(array_column($daily, 'n')) ?></div>
<?php if ($d['canMoney']): ?>
  <div class="mx-kt"><div class="mx-kh"><span class="mx-kb"><i class="ic ic-sm i-dollar" aria-hidden="true"></i></span><small>Delivery fees</small></div><b><?= h(sl_money($t['fees'])) ?></b><em><?= (int) $t['done'] ?> done · avg <?= h($d['avgFee']) ?></em></div>
<?php else: ?>
  <div class="mx-kt"><div class="mx-kh"><span class="mx-kb"><i class="ic ic-sm i-box" aria-hidden="true"></i></span><small>Delivered</small></div><b><?= (int) $t['done'] ?> of <?= (int) $t['assigned'] ?></b><em><?= (int) $t['failed'] ?> failed</em></div>
<?php endif; ?>
</section>
<div class="mx-seg" role="tablist" aria-label="Show riders">
<?php foreach ($segs as $k => $s): ?>
  <a role="tab" aria-selected="<?= $d['filter'] === $k ? 'true' : 'false' ?>" class="<?= $d['filter'] === $k ? 'on' : '' ?>" href="<?= h(sl_url(['filter' => $k, 'page' => null])) ?>"><?= h($s[0]) ?> <?= (int) $s[1] ?></a>
<?php endforeach; ?>
</div>
<form method="get" class="mx-srch"><input type="hidden" name="filter" value="<?= h($d['filter']) ?>"><input type="hidden" name="period" value="<?= h($d['period']) ?>"><label><i class="ic i-search" aria-hidden="true"></i><input type="search" name="q" value="<?= h($d['q']) ?>" aria-label="Search riders" placeholder="Search name, phone or plate" autocomplete="off"></label></form>
<?php if ($d['rows']): ?>
<section class="mx-card mx-list">
<?php foreach ($d['rows'] as $r): ?>
<a class="mx-row" href="<?= h(sl_route('RiderDetail', ['id' => $r['id']])) ?>"><span class="mx-ava"><?= h($r['ini']) ?></span><span class="mx-rm"><b><?= h($r['name']) ?></b><small><?= h(implode(' · ', array_filter([$r['zone'] ?: $r['veh'], $r['assigned'] ? $r['done'] . ' of ' . $r['assigned'] : 'No jobs']))) ?></small></span><span class="mx-rr"><span class="sl-pill <?= h($r['cls']) ?>"><?= h($r['st']) ?></span></span></a>
<?php endforeach; ?>
</section>
<?php if ($d['count'] > $d['page'] * $d['per']): ?><div class="pc-more"><a href="<?= h(sl_url(['page' => $d['page'] + 1])) ?>">Show more riders</a></div><?php endif; ?>
<?php else: ?>
<?= sl_mxEmpty('users', $d['q'] !== '' ? 'No rider matches “' . $d['q'] . '”' : 'No riders here', $d['filter'] !== 'all' ? 'Try another filter.' : 'Riders you add appear here.', $d['filter'] !== 'all' || $d['q'] !== '' ? 'Show all riders' : '', sl_url(['filter' => 'all', 'q' => null, 'page' => null])) ?>
<?php endif; ?>
</main>
<?php if ($d['canAdd']): ?><a class="mx-fab" href="<?= h(sl_route('InviteStaff')) ?>" aria-label="Add rider"><i class="ic i-plus" aria-hidden="true"></i></a><?php endif; ?>
