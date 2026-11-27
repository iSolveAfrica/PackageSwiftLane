<?php
/**
 * M-Claims: insurance and claims on a phone. Same $d as the web page (sl_insuranceClaimsData): ?filter=, ?id= picks
 * the claim the buttons act on. Writes go to ajax/sl/insurance_claim_ajax.php with the web page's fields
 * (op=advance [offer_amount], op=reject reason, op=note note documents[], op=create).
 */
require_once CDP_ROOT . '/helpers/sl_mx.php';
$can = !empty($d['canManage']);
$c = $d['c'] ?? null;
$pf = $d['prefill'];
$k = [];
foreach ((array) $d['kpis'] as $x) $k[$x['l']] = $x;
$live = $c && !in_array($c['status'], ['paid', 'rejected'], true);
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-ph-desk.css')) ?>">
<?= sl_phoneTop('Insurance & claims', '', ['back' => sl_route('FreightHub')], $can && !empty($d['ready']) ? sl_phoneIcon('plus', 'New claim', sl_url(['new' => 1])) : '') ?>
<main class="mx-body">
<?php if (!empty($d['flash'])): ?><div class="mx-ban info"><i class="ic ic-sm i-check" aria-hidden="true"></i><span><?= h($d['flash']) ?></span></div><?php endif; ?>
<section class="mx-k c2">
<?php if (isset($k['Insured shipments'])): ?><?= sl_mxKpi('shield', 'Insured parcels', $k['Insured shipments']['v'], $k['Insured shipments']['s']) ?><?php endif; ?>
<?php if (isset($k['Open claims'])): ?><div class="mx-kt"><div class="mx-kh"><span class="mx-kb"><i class="ic ic-sm i-alert" aria-hidden="true"></i></span><small>Open claims</small></div><b><?= h($k['Open claims']['v']) ?></b><em class="warn"><?= h($k['Open claims']['s']) ?></em></div><?php endif; ?>
</section>
<?php if (empty($d['ready'])): ?>
<?= sl_mxEmpty('shield', 'Claims not installed', 'Ask an administrator to run the freight migration.') ?>
<?php else: ?>
<nav class="mx-seg" aria-label="Show"><?php foreach ($d['filters'] as $f): ?><a class="<?= h($f['cls']) ?>" href="<?= h($f['href']) ?>"<?= $f['cls'] === 'on' ? ' aria-current="page"' : '' ?>><?= h($f['label']) ?></a><?php endforeach; ?></nav>
<?php if ($d['claims']): ?>
<section class="mx-card mx-list"><?php foreach ($d['claims'] as $cl): ?><a class="mx-row" href="<?= h($cl['href']) ?>"<?= $cl['cls'] === 'on' ? ' aria-current="true"' : '' ?>><span class="mx-li"><i class="ic i-<?= $cl['cls'] === 'on' ? 'check' : 'shield' ?>" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($cl['no'] . ' · ' . $cl['type']) ?></b><small><?= h($cl['cust'] . ' · ' . $cl['amt'] . ' · ' . $cl['ship']) ?></small></span><span class="mx-rr"><span class="sl-pill <?= h($cl['pc']) ?>"><?= h($cl['st']) ?></span></span></a><?php endforeach; ?></section>
<?php else: ?><?= sl_mxEmpty('shield', 'No claims in this list') ?><?php endif; ?>
<?php if ($c): ?>
<?= sl_mxSec($c['no'] . ' · ' . $c['st']) ?>
<section class="mx-card mx-kv"><div><span>Customer</span><b><?= h($c['cust']) ?></b></div><div><span>Shipment</span><b><a href="<?= h($c['trackHref']) ?>"><?= h($c['ship']) ?></a></b></div><div><span>Declared · insured</span><b><?= h($c['decl'] . ' · ' . $c['insured']) ?></b></div><div><span>Claimed</span><b><?= h($c['amt']) ?></b></div><div><span>Offer</span><b><?= h($c['offer']) ?></b></div></section>
<?php if ($can && $live): ?>
<form class="mx-btns" data-sl-form data-sl-reload action="ajax/sl/insurance_claim_ajax.php" method="post"><input type="hidden" name="op" value="advance"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
<?php if ($c['next'] === 'approved'): ?><label class="pd-f"><small>Offer amount</small><input class="pd-in mono" name="offer_amount" type="number" inputmode="decimal" step="0.01" min="0" value="<?= h((string) $c['offerVal']) ?>"></label><?php endif; ?>
<button type="submit" class="mx-btn"><?= h($c['nextLabel'] . ' · ' . $c['no']) ?></button>
<button type="button" class="mx-btn sec" data-sl-open="pd-ic-note">Ask for more photos</button>
</form>
<?php elseif ($can): ?><div class="mx-btns"><button type="button" class="mx-btn sec" data-sl-open="pd-ic-note">Add a note</button></div><?php endif; ?>
<?php endif; ?>
<?php endif; ?>
</main>
<?php if ($can && !empty($d['ready'])): ?>
<?php if ($c): ?>
<div class="sl-dialog pd-sheet" id="pd-ic-note" hidden><form class="pd-sheetf" data-sl-form data-sl-reload action="ajax/sl/insurance_claim_ajax.php" method="post" enctype="multipart/form-data" role="dialog" aria-label="Note on <?= h($c['no']) ?>">
<input type="hidden" name="op" value="note"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"><h2>Note on <?= h($c['no']) ?></h2>
<label class="pd-f"><small>Note or request to the customer</small><textarea class="pd-in" name="note" rows="3" required><?= $live ? 'Please send more photos of the damage and the packaging.' : '' ?></textarea></label>
<label class="pd-f"><small>Attach documents</small><input class="pd-in" type="file" name="documents[]" accept="image/jpeg,image/png,image/webp,application/pdf" multiple style="padding-top:12px"></label>
<div class="mx-btns"><button type="submit" class="mx-btn">Add note</button><button type="button" class="mx-btn sec" data-sl-close>Cancel</button></div>
</form>
<?php if ($live): ?><form class="pd-sheetf" data-sl-form data-sl-reload action="ajax/sl/insurance_claim_ajax.php" method="post" style="border-radius:0;padding-top:0"><input type="hidden" name="op" value="reject"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"><label class="pd-f"><small>Or reject the claim</small><input class="pd-in" name="reason" placeholder="Reason to reject" required></label><button type="submit" class="mx-btn sec">Reject <?= h($c['no']) ?></button></form><?php endif; ?>
</div>
<?php endif; ?>
<div class="sl-dialog pd-sheet" id="pd-ic-new"<?= $pf['open'] ? '' : ' hidden' ?>><form data-sl-form action="ajax/sl/insurance_claim_ajax.php" method="post" enctype="multipart/form-data" role="dialog" aria-label="New claim">
<input type="hidden" name="op" value="create"><input type="hidden" name="exception_id" value="<?= (int) $pf['exception'] ?>"><h2>New claim</h2><p class="pd-note">Declared and insured values are taken from the shipment.</p>
<label class="pd-f"><small>Shipment tracking</small><input class="pd-in mono" name="order_track" required value="<?= h($pf['track']) ?>" autocomplete="off"></label>
<label class="pd-f"><small>Type</small><select class="pd-in" name="type"><option value="damaged"<?= $pf['type'] === 'damaged' ? ' selected' : '' ?>>Damaged</option><option value="lost"<?= $pf['type'] === 'lost' ? ' selected' : '' ?>>Lost</option></select></label>
<label class="pd-f"><small>Amount claimed</small><input class="pd-in mono" name="claim_amount" type="number" inputmode="decimal" step="0.01" min="0.01" required></label>
<label class="pd-f"><small>Photos / receipts</small><input class="pd-in" type="file" name="documents[]" accept="image/jpeg,image/png,image/webp,application/pdf" multiple style="padding-top:12px"></label>
<label class="pd-f"><small>What happened</small><textarea class="pd-in" name="notes" rows="3"></textarea></label>
<div class="mx-btns"><button type="submit" class="mx-btn">Open claim</button><a class="mx-btn sec" href="<?= h(sl_url(['new' => null, 'track' => null, 'exception' => null, 'type' => null])) ?>" data-sl-close>Cancel</a></div>
</form></div>
<?php endif; ?>
