<?php
/**
 * M-AgentPortal: the partner (agent) portal on a phone (agent_portal.php; agents see their own,
 * administrators any agent with ?agent_id=, as the controller and ajax/staffops/staffops_ajax.php decide).
 * Data: helpers/staff_workspace.php cdp_staffAgentPortal(), the same the web page loads by AJAX.
 */
require_once CDP_ROOT . '/views/sl/data/phone_people.php';
require_once CDP_ROOT . '/helpers/staff_workspace.php';
$slPhone['nav'] = 'staff';
$aid = (cdp_opsRank($user) >= 3 && (int) ($d['agentId'] ?? 0)) ? (int) $d['agentId'] : cdp_opsUid();
$ap = !empty($d['ready']) ? cdp_staffAgentPortal($aid) : null;
$k = $ap ? array_column($ap['kpis'], 'value', 'label') : [];
$month = (int) ($k['Shipments this month'] ?? 0);
$open = (int) ($k['Open shipments'] ?? 0);
$ready = 0;
foreach ($ap['shipments'] ?? [] as $s) if (stripos((string) $s->status, 'ready') !== false || stripos((string) $s->status, 'pick') !== false) $ready++;
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-ph-people.css')) ?>">
<?= sl_phoneTop('Partner portal', $ap ? $ap['agent']['name'] : '', ['back' => 'menu.php']) ?>
<main class="mx-body">
<?php if (!$ap): ?>
<div class="mx-ban bad"><i class="ic ic-sm i-alert" aria-hidden="true"></i><span>The staff tables could not be created.</span></div>
<?php else: ?>
<?php if (!$ap['agent']['linked']): ?><div class="mx-ban warn"><i class="ic ic-sm i-info" aria-hidden="true"></i><span>This account is not linked to an agency yet, so no shipments are counted. An administrator links it under Staff &amp; Branches.</span></div><?php endif; ?>
<section class="mx-hero">
  <div class="mx-hk"><?= h($ap['agent']['name']) ?> · this month<span class="mx-tag">Agent</span></div>
  <b class="mx-hv"><?= sl_num($month) ?> parcel<?= $month === 1 ? '' : 's' ?></b>
  <small>Commission pending <?= h((string) ($k['Commission pending'] ?? '0.00')) ?> · paid <?= h((string) ($k['Commission paid (all time)'] ?? '0.00')) ?></small>
  <small><?= h($ap['rule']) ?></small>
</section>
<section class="mx-act c4">
  <a href="<?= h(sl_route('NewShipment')) ?>"><span><i class="ic i-plus" aria-hidden="true"></i></span><small>Book parcel</small></a>
  <a href="<?= h(sl_route('PackageIntake')) ?>"><span><i class="ic i-scan" aria-hidden="true"></i></span><small>Receive</small></a>
  <a href="<?= h(sl_route('HandOut')) ?>"><span><i class="ic i-key" aria-hidden="true"></i></span><small>Hand out</small></a>
  <a href="<?= h(sl_route('Customers')) ?>"><span><i class="ic i-users" aria-hidden="true"></i></span><small>Customers</small></a>
</section>
<section class="mx-card mx-list">
  <a class="mx-row" href="<?= h(sl_route('AirShipments')) ?>"><span class="mx-li"><i class="ic i-truck" aria-hidden="true"></i></span><span class="mx-rm"><b><?= sl_num($open) ?> open shipment<?= $open === 1 ? '' : 's' ?></b><small>Not yet handed over</small></span><span class="mx-rr"><span class="sl-pill transit">In transit</span></span></a>
  <a class="mx-row" href="<?= h(sl_route('WarehousePickups')) ?>"><span class="mx-li"><i class="ic i-box" aria-hidden="true"></i></span><span class="mx-rm"><b><?= sl_num($ready) ?> waiting for pickup</b><small>Of the latest <?= count($ap['shipments']) ?> shipments</small></span><span class="mx-rr"><span class="sl-pill delivered">Ready</span></span></a>
</section>
<div class="mx-sec"><h2>Shipments</h2></div>
<?php if ($ap['shipments']): ?>
<section class="mx-card mx-list">
<?php foreach (array_slice($ap['shipments'], 0, 10) as $s): ?>
  <a class="mx-row" href="<?= h(sl_route('PublicTracking', ['order_track' => $s->trk])) ?>"><span class="mx-li"><i class="ic i-plane" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($s->trk) ?></b><small><?= h(trim($s->customer) ?: 'Customer') ?> · <?= h(sl_date($s->order_date, 'j M')) ?></small></span><span class="mx-rr"><span class="sl-pill plain"><?= h($s->status ?: '—') ?></span></span></a>
<?php endforeach; ?>
</section>
<?php else: ?>
<?= sl_mxEmpty('box', 'No shipments yet') ?>
<?php endif; ?>
<?php if ($ap['statements']): ?>
<div class="mx-sec"><h2>Commission statements</h2></div>
<section class="mx-card mx-list">
<?php foreach (array_slice($ap['statements'], 0, 6) as $st): ?>
  <div class="mx-row"><span class="mx-li"><i class="ic i-receipt" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h(sl_date($st->period . '-01', 'F Y')) ?></b><small><?= (int) $st->items ?> shipments · pending <?= number_format((float) $st->pending, 2) ?> · approved <?= number_format((float) $st->approved, 2) ?></small></span><span class="mx-rr"><b><?= number_format((float) $st->paid, 2) ?></b><small>paid</small></span></div>
<?php endforeach; ?>
</section>
<?php endif; ?>
<?php if ($ap['announcements']): ?>
<div class="mx-sec"><h2>Announcements</h2></div>
<section class="mx-card mx-list pp-wrap">
<?php foreach ($ap['announcements'] as $a): ?>
  <div class="mx-row"><span class="mx-li"><i class="ic i-bell" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($a->title) ?></b><small><?= h(mb_strimwidth((string) $a->body, 0, 220, '…')) ?></small></span></div>
<?php endforeach; ?>
</section>
<?php endif; ?>
<?php endif; ?>
</main>
