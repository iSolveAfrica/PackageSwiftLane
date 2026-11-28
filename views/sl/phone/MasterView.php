<?php
/**
 * M-Master: Gerald master view on a phone. $d from helpers/sl_phone_centres.php slpc_masterData() (master_view.php),
 * built from the same helpers/mgmt.php figures and branch scope as the web page's ajax/mgmt/mgmt_ajax.php.
 * Last 30 days; money only for roles cdp_mgmtCanMoney() allows.
 */
require_once CDP_ROOT . '/helpers/sl_mx.php';
require_once CDP_ROOT . '/helpers/sl_phone_centres.php';
$slPhone['nav'] = 'staff';
$slPhone['tab'] = 'Menu';
$s = $d['stats'];
$nb = count($d['branches']);
$money = fn(float $v) => sl_money($v);
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-centres.css')) ?>">
<?= sl_phoneTop('Gerald master view', sl_date($d['from'], 'M j') . ' – ' . sl_date($d['to'], 'M j'), ['back' => 'menu.php'], sl_phoneIcon('monitor', 'Full master view', 'master_view.php?view=web')) ?>
<main class="mx-body">
<section class="mx-k c2">
  <div class="mx-kt"><div class="mx-kh"><span class="mx-kb"><i class="ic ic-sm i-warehouse" aria-hidden="true"></i></span><small>Branches</small></div><b><?= $nb ? (int) $d['linked'] . ' of ' . $nb : '—' ?></b><em><?= $nb ? 'linked to an office' : 'none set up' ?></em></div>
  <div class="mx-kt"><div class="mx-kh"><span class="mx-kb"><i class="ic ic-sm i-box" aria-hidden="true"></i></span><small>Shipments · 30 days</small></div><b><?= h(sl_num($s['n'])) ?></b><?php if ($d['pct'] !== null): ?><em class="<?= $d['pct'] >= 0 ? 'up' : 'down' ?>"><?= $d['pct'] >= 0 ? '+' : '' ?><?= h((string) $d['pct']) ?>% vs before</em><?php endif; ?><?= slpc_spark($d['spark']) ?></div>
  <div class="mx-kt"><div class="mx-kh"><span class="mx-kb"><i class="ic ic-sm i-alert" aria-hidden="true"></i></span><small>Open issues</small></div><b><?= (int) $d['delayed']['count'] + (int) $d['open'] ?></b><em class="<?= $d['delayed']['count'] ? 'down' : '' ?>"><?= (int) $d['delayed']['count'] ?> late<?= $d['cxReady'] ? ' · ' . (int) $d['open'] . ' tickets' : '' ?></em></div>
<?php if ($d['money']): ?>
  <div class="mx-kt"><div class="mx-kh"><span class="mx-kb"><i class="ic ic-sm i-dollar" aria-hidden="true"></i></span><small>Revenue · 30 days</small></div><b><?= h($money($s['revenue'])) ?></b><em>margin <?= h($money($s['revenue'] - $s['cost'])) ?></em></div>
<?php else: ?>
  <div class="mx-kt"><div class="mx-kh"><span class="mx-kb"><i class="ic ic-sm i-users" aria-hidden="true"></i></span><small>Customers shipping</small></div><b><?= h(sl_num($s['customers'])) ?></b><em><?= h(sl_num($s['handed'])) ?> handed over</em></div>
<?php endif; ?>
</section>
<?= sl_mxSec('Branches') ?>
<?php if ($d['branches']): ?>
<section class="mx-card mx-list">
<?php foreach ($d['branches'] as $b): $st = !$b['linked'] ? ['Not linked', 'pending'] : ($b['delayed'] > 0 ? [$b['delayed'] . ' late', 'overdue'] : ['Good', 'delivered']); ?>
<a class="mx-row" href="<?= h(sl_route('StaffHub')) ?>"><span class="mx-li"><i class="ic i-warehouse" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($b['name']) ?></b><small><?= (int) $b['staff'] ?> staff · <?= h(sl_num($b['shipments'])) ?> shipments<?= $d['money'] ? ' · ' . h($money($b['revenue'])) : '' ?></small></span><span class="mx-rr"><span class="sl-pill <?= h($st[1]) ?>"><?= h($st[0]) ?></span></span></a>
<?php endforeach; ?>
</section>
<?php else: ?>
<?= sl_mxEmpty('warehouse', 'No branches you can see', 'Branches are set up in Staff & Roles.') ?>
<?php endif; ?>
<?php if ($d['delayed']['rows']): ?>
<?= sl_mxSec('Late shipments') ?>
<section class="mx-card mx-list">
<?php foreach ($d['delayed']['rows'] as $r): ?>
<a class="mx-row" href="<?= h(sl_route('ShipmentDetail', ['id' => (int) $r->order_id])) ?>"><span class="mx-li"><i class="ic i-clock" aria-hidden="true"></i></span><span class="mx-rm"><b class="mono"><?= h($r->trk) ?></b><small><?= h(trim((string) $r->customer) ?: '—') ?> · <?= h(str_replace('_', ' ', (string) $r->status)) ?></small></span><span class="mx-rr"><span class="sl-pill overdue"><?= (int) $r->days_late ?> d late</span></span></a>
<?php endforeach; ?>
</section>
<?php endif; ?>
<div class="mx-btns"><a class="mx-btn" href="<?= h(sl_route('Workspace')) ?>">My workspace</a><a class="mx-btn sec" href="master_view.php?view=web">Full master view</a></div>
</main>
