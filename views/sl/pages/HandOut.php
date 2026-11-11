<?php
/**
 * HandOut - from docs/handover/artifact-source/HandOut.dc.html. Counter hand-out by pickup code or tracking.
 * Data: views/sl/data/warehouse.php (sl_handOutData). Steps: pick the customer from the queue (or search name, locker,
 * phone or tracking) → enter the code they show → fetch from the bay and scan each label → checks → hand over (Picked up).
 * Writes: ajax/sl/warehouse_ajax.php (verify_customer, send_customer, handover). Search by tracking: pickup_client.php?tracking=.
 */
if (!isset($d)) { $d = []; }
$c = $d['c'];
$st = !$c ? 1 : 2;
?>
<div class="sl-head"><div><h1 class="sl-title" style="font-size:22px">Hand out packages</h1><p class="sl-lede">Counter at <?= h($d['office']) ?> · enter the pickup code, fetch from the bay, scan each package to confirm, hand over.</p></div><div class="row g8 wrap"><span class="sl-badge row g6"><span class="pr-dot" style="width:8px;height:8px;border-radius:50%;background:var(--green);display:inline-block"></span><?= (int) $d['doneToday'] ?> handed out today</span><a class="sl-btn ghost" href="<?= sl_route('WarehousePickups') ?>"><i class="ic ic-sm i-clock" aria-hidden="true"></i>Today's hand-outs</a></div></div>
<div class="ho-steps"><?php foreach (['Customer', 'Pickup code', 'Scan packages', 'Hand over'] as $i => $t): ?><span class="<?= $i + 1 < $st ? 'done' : ($i + 1 === $st ? 'on' : '') ?>"><b><?= $i + 1 ?></b><?= h($t) ?></span><?php endforeach; ?></div>
<div class="ho">
<aside class="sl-card col g8" style="padding:14px"><div class="row between"><b class="fw5">Waiting at the counter</b><span class="sl-badge"><?= (int) $d['qN'] ?></span></div><span class="mut" style="font-size:14px">Customers with parcels ready</span>
<?php foreach ($d['queue'] as $q): ?><a class="ho-q <?= h($q['cls']) ?>" href="<?= h($q['href']) ?>" style="color:inherit"><span class="sl-av"><?= h($q['ini']) ?></span><span class="col grow" style="min-width:0"><b class="fw5 sm"><?= h($q['name']) ?></b><span class="mut" style="font-size:14px"><?= h($q['lock']) ?> · <?= h($q['n']) ?> · <?= h($q['wait']) ?></span></span><span class="sl-pill <?= h($q['pc']) ?>"><?= h($q['st']) ?></span></a><?php endforeach; ?>
<?php if (!$d['queue']): ?><span class="mut sm">Nobody has a parcel ready<?= $d['q'] !== '' ? ' for this search' : '' ?>.</span><?php endif; ?>
<form method="get" class="sl-search" style="max-width:none;min-width:0;margin-top:6px"><i class="ic ic-sm i-search" aria-hidden="true"></i><input name="q" value="<?= h($d['q']) ?>" aria-label="Find customer" placeholder="Name, locker, phone or tracking"></form></aside>
<section class="ho-big" id="ho-flow" data-customer="<?= (int) ($c['id'] ?? 0) ?>">
<?php if (!$c): ?><div class="col g6"><h2 style="margin:0;font-size:22px;font-weight:600">Who is collecting?</h2><span class="mut">Pick the customer from the queue, or search by name, locker, phone or a tracking number.</span></div><div class="sl-empty"><span class="sl-tile"><i class="ic i-user" aria-hidden="true"></i></span>No customer selected</div>
<?php else: ?>
<div class="ho-cust"><span class="sl-av lg"><?= h($c['ini']) ?></span><span class="col grow"><b class="fw5" style="font-size:18px"><?= h($c['name']) ?></b><span class="mut sm"><?= h($c['lock']) ?> · <?= h(cdp_pickupCodeMask($c['phone'])) ?> · <?= count($c['pkgs']) ?> parcel(s) ready</span></span><span class="sl-pill <?= h($c['payCls']) ?>"><?= h($c['payL']) ?></span></div>
<div class="col g6 ho-hide-done"><h2 style="margin:0;font-size:22px;font-weight:600">Ask for the pickup code</h2><span class="mut"><?= $c['code']['live'] ? 'Sent ' . h($c['code']['live']['sent_at']) . ($c['code']['live']['channels'] ? ' by ' . h(implode(', ', $c['code']['live']['channels'])) : '') : 'No code sent yet: send one and ask the customer to read it out.' ?></span></div>
<div class="row g8 ho-hide-done" role="group" aria-label="Pickup code"><?php for ($i = 1; $i <= 6; $i++): ?><input class="sl-in mono ho-digit ops-digit" inputmode="numeric" maxlength="1" aria-label="Digit <?= $i ?>" autocomplete="off"><?php endfor; ?></div>
<div class="ho-bad ho-hide-done" id="ho-bad" hidden><i class="ic i-x" aria-hidden="true"></i><span></span></div>
<div class="row g8 wrap ho-hide-done"><?php if ($d['canCode']): ?><button class="sl-btn" type="button" id="ho-verify"><i class="ic ic-sm i-key" aria-hidden="true"></i>Check code</button><button class="sl-btn ghost" type="button" id="ho-send"><i class="ic ic-sm i-message" aria-hidden="true"></i>Send a new code by WhatsApp, SMS and e-mail</button><?php endif; ?><span class="mut sm">No code? Check their ID card first. 5 wrong entries void the code.</span></div>
<div class="row between ho-hide-done"><b class="fw5">Fetch from the bay, then scan each package</b></div>
<?php foreach ($c['pkgs'] as $p): ?><label class="ho-pk ho-hide-done" data-item="<?= h($p['module'] . ':' . $p['oid']) ?>" data-trk="<?= h($p['id']) ?>" data-cleared="<?= $p['cleared'] ? 1 : 0 ?>"><span class="ho-bay"><small>BAY</small><b><?= h($p['bay']) ?></b></span><span class="col" style="min-width:0"><b class="fw5"><?= h($p['id']) ?></b><span class="mut sm"><?= h($p['what']) ?> · <?= h($p['w']) ?> · <?= $p['module'] === 'sea' ? 'sea' : 'air' ?><?= $p['cleared'] ? '' : ' · awaiting clearance' ?></span></span><span class="mono sm"><?= h($p['days']) ?></span><input type="checkbox" disabled aria-label="Hand over <?= h($p['id']) ?>"></label><?php endforeach; ?>
<label class="ho-scan ho-hide-done"><span class="ho-beam" aria-hidden="true"></span><input id="ho-scan-in" aria-label="Scan barcode" placeholder="Scan the label barcode or type the tracking number"><span class="mut sm">Scanner ready</span></label>
<div class="ho-bad ho-hide-done" id="ho-scanbad" hidden><i class="ic i-x" aria-hidden="true"></i><span></span></div>
<div class="grid cols3 ho-hide-done" style="gap:8px"><button type="button" class="ho-chk ho-need"><span class="sl-chk" aria-hidden="true"></span>ID card checked</button><button type="button" class="ho-chk <?= $c['unpaid'] ? 'ho-need' : '' ?>" <?= $c['unpaid'] ? '' : 'disabled aria-disabled="true"' ?>><span class="sl-chk <?= $c['unpaid'] ? '' : 'on' ?>" aria-hidden="true"></span><?= $c['unpaid'] ? 'Balance settled' : 'Nothing to pay' ?></button><button type="button" class="ho-chk ho-need"><span class="sl-chk" aria-hidden="true"></span>Customer signed</button></div>
<div id="ho-step2" hidden class="ho-hide-done"></div>
<?php if ($d['canPkg']): ?><button class="ho-pri ho-hide-done" type="button" id="ho-go" disabled><i class="ic i-check" aria-hidden="true"></i><span>Hand over</span></button><?php endif; ?><span class="mut sm ho-hide-done" id="ho-left"></span>
<a class="sl-btn ghost ho-hide-done" href="<?= sl_route('HandOut') ?>">Cancel · back to the queue</a>
<div class="ho-done" id="ho-done" hidden><span class="sl-tile" style="width:72px;height:72px;border-radius:22px;background:var(--green-bg);color:var(--green-ink)"><i class="ic i-check" aria-hidden="true"></i></span><h2 style="margin:0;font-size:26px;font-weight:600"></h2><span class="mut">Receipt sent by WhatsApp and e-mail · logged in Warehouse Pickups</span><a class="ho-pri" style="max-width:360px" href="<?= sl_route('HandOut') ?>"><i class="ic i-right" aria-hidden="true"></i>Next customer</a></div>
<?php endif; ?>
</section></div>
<?php $slPreVerified = $c['verifiedItems'] ?? []; include CDP_ROOT . '/views/sl/inc/handover_js.php'; ?>
