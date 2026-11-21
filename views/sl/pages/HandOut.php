<?php
/**
 * HandOut - counter hand-out (Warehouse menu). Data: views/sl/data/warehouse.php (sl_handOutData).
 * Left: live search (dataJs/sl/handout.js → ajax/sl/handout_ajax.php op=search) filters while you type and opens
 * the customer as soon as there is one match or an exact tracking number. Right: the customer panel
 * (partials/handout_panel.php): the pickup code checks itself on the 6th digit, managers can skip it, each
 * package opens in a pop-up (contents, payment, history) and is ticked with one click, then Hand over.
 * Writes: ajax/sl/warehouse_ajax.php (verify_customer, send_customer, handover). Every hand-over lands in the Hand-out Log.
 */
if (!isset($d)) { $d = []; }
$c = $d['c'];
?>
<div class="sl-head"><div><h1 class="sl-title" style="font-size:22px">Hand out packages</h1><p class="sl-lede">Counter at <?= h($d['office']) ?> · type a name, locker, phone or tracking number: the customer opens by itself.</p></div>
  <div class="row g8 wrap"><span class="sl-badge row g6"><span style="width:8px;height:8px;border-radius:50%;background:var(--green);display:inline-block"></span><span data-ho-today><?= (int) $d['doneToday'] ?></span>&nbsp;handed out today</span><span class="sl-badge">You:&nbsp;<span data-ho-mine><?= (int) $d['mine'] ?></span></span><a class="sl-btn ghost" href="<?= sl_route('HandoverLog') ?>"><i class="ic ic-sm i-activity" aria-hidden="true"></i>Hand-out Log</a></div></div>
<div class="ho" id="ho-page" data-open="<?= (int) $d['sel'] ?>">
<aside class="sl-card col g8 ho-queue" aria-label="Customers with parcels ready">
  <label class="ho-find"><i class="ic i-search" aria-hidden="true"></i><input id="ho-q" value="<?= h($d['q']) ?>" autocomplete="off" autofocus aria-label="Search customer or tracking" placeholder="Name, locker, phone or tracking"><span class="ho-spin" data-ho-spin hidden aria-hidden="true"></span></label>
  <div class="row between"><span class="mut sm" data-ho-count><?= (int) $d['qN'] ?> customer<?= (int) $d['qN'] === 1 ? '' : 's' ?> with parcels ready</span></div>
  <div class="ho-queue-list" id="ho-list" role="listbox" aria-label="Matching customers">
    <?php foreach ($d['queue'] as $q): ?><button type="button" class="ho-q <?= $q['id'] === $d['sel'] ? 'on' : '' ?>" data-ho-cust="<?= (int) $q['id'] ?>" role="option"><span class="sl-av"><?= h($q['ini']) ?></span><span class="col grow" style="min-width:0;text-align:left"><b class="fw5 sm ho-queue-name"><?= h($q['name']) ?></b><span class="mut ho-queue-meta"><?= h($q['lock'] ?: '—') ?> · <?= h($q['nL']) ?></span><span class="mut ho-queue-meta">ready <?= h($q['wait']) ?></span></span><span class="sl-pill delivered"><?= (int) $q['n'] ?></span></button><?php endforeach; ?>
    <?php if (!$d['queue']): ?><span class="mut sm">Nobody has a parcel ready<?= $d['q'] !== '' ? ' for this search' : '' ?>.</span><?php endif; ?>
  </div>
</aside>
<section class="ho-big" id="ho-panel" aria-live="polite">
  <?php if ($c): include __DIR__ . '/partials/handout_panel.php'; else: ?>
  <div class="ho-empty"><span class="sl-tile"><i class="ic i-user" aria-hidden="true"></i></span><h2 style="margin:0;font-size:20px;font-weight:600">Who is collecting?</h2><span class="mut">Start typing on the left. With one match, or a full tracking number, the customer opens here.</span></div>
  <?php endif; ?>
</section>
</div>

<div class="sl-dialog" id="ho-pkg" hidden><div class="sl-modal ho-pkgmodal" role="dialog" aria-modal="true" aria-labelledby="ho-pkg-t">
  <div class="row between g8"><div class="col"><b class="fw5 mono" id="ho-pkg-t" style="font-size:20px"></b><span class="mut sm" data-p="sub"></span></div><button type="button" class="sl-ib" data-sl-close aria-label="Close"><i class="ic i-x" aria-hidden="true"></i></button></div>
  <div class="row g8 wrap" data-p="pills"></div>
  <div class="sl-kv" data-p="kv"></div>
  <div class="col g6"><b class="fw5">What is inside</b><div class="ho-items" data-p="items"></div></div>
  <div class="col g6"><b class="fw5">Latest tracking</b><div class="col g4 sm" data-p="hist"></div></div>
  <div class="row g8 wrap"><button type="button" class="sl-btn" data-p="tick"><i class="ic ic-sm i-check" aria-hidden="true"></i><span>Check this package</span></button><a class="sl-btn ghost" data-p="receipt" target="_blank">Receipt</a><a class="sl-btn ghost" data-p="view" target="_blank">Full details</a></div>
</div></div>
