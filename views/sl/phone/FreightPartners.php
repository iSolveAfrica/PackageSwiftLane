<?php
/**
 * M-Partners: freight partners on a phone. Same $d as the web page (sl_freightPartnersData): ?type=, ?q=, ?id=.
 * "Add partner" posts to ajax/sl/freight_partner_ajax.php op=create with the web page's fields.
 */
require_once CDP_ROOT . '/helpers/sl_mx.php';
$can = !empty($d['canManage']);
$p = !empty($_GET['id']) ? ($d['p'] ?? null) : null;
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-ph-desk.css')) ?>">
<?= sl_phoneTop($p ? $p['n'] : 'Freight partners', $p ? $p['type'] : '', ['back' => $p ? sl_url(['id' => null]) : sl_route('FreightHub')]) ?>
<main class="mx-body">
<?php if (empty($d['ready'])): ?>
<?= sl_mxEmpty('users', 'Partners not installed', 'Ask an administrator to run the freight migration.') ?>
<?php elseif ($p): ?>
<section class="mx-card mx-kv"><div><span>Lanes</span><b><?= h($p['lane']) ?></b></div><div><span>Rate</span><b><?= h($p['rate']) ?></b></div><div><span>On time (90 days)</span><b><?= h($p['ot']) ?></b></div><div><span>Average delay</span><b><?= h($p['delay']) ?></b></div><div><span>Issues</span><b><?= h($p['iss']) ?></b></div><div><span>Spend this year</span><b><?= h($p['spend']) ?></b></div><div><span>Contract ends</span><b><?= h($p['con']) ?></b></div><div><span>Contact</span><b><?= h($p['who']) ?></b></div></section>
<?php if ($p['notes'] !== ''): ?><section class="mx-card mx-form"><p class="pd-note" style="margin:0;white-space:pre-line;color:var(--ink-2)"><?= h($p['notes']) ?></p></section><?php endif; ?>
<div class="mx-btns">
<?php if ($p['phone'] !== ''): ?><a class="mx-btn" href="tel:<?= h(preg_replace('/[^0-9+]/', '', $p['phone'])) ?>">Call <?= h($p['phone']) ?></a><?php endif; ?>
<?php if ($p['email'] !== ''): ?><a class="mx-btn sec" href="mailto:<?= h($p['email']) ?>">E-mail <?= h($p['email']) ?></a><?php endif; ?>
<a class="mx-btn sec" href="<?= h(sl_route('FreightSchedule')) ?>">Departures</a>
</div>
<?php else: ?>
<form method="get"><div class="mx-srch"><?php if (!empty($_GET['type'])): ?><input type="hidden" name="type" value="<?= h((string) $_GET['type']) ?>"><?php endif; ?><label><i class="ic i-search" aria-hidden="true"></i><input type="search" name="q" value="<?= h($d['q']) ?>" aria-label="Search partners" placeholder="Search name, lane or contact"></label></div></form>
<nav class="mx-seg" aria-label="Type"><?php foreach ($d['types'] as $t): ?><a class="<?= h($t['cls']) ?>" href="<?= h($t['href']) ?>"<?= $t['cls'] === 'on' ? ' aria-current="page"' : '' ?>><?= h($t['label']) ?></a><?php endforeach; ?></nav>
<?php if ($d['rows']): ?>
<section class="mx-card mx-list"><?php foreach ($d['rows'] as $r): ?><a class="mx-row" href="<?= h($r['href']) ?>"><span class="mx-li"><i class="ic <?= h($r['icon']) ?>" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($r['n']) ?></b><small><?= h($r['type'] . ($r['lane'] !== '—' ? ' · ' . $r['lane'] : '')) ?></small></span><span class="mx-rr"><b><?= $r['ot'] !== '—' ? h($r['ot']) . ' on time' : '—' ?></b></span></a><?php endforeach; ?></section>
<?php else: ?><?= sl_mxEmpty('users', 'No partners found', $can ? 'Add the airlines, lines and brokers you work with.' : '') ?><?php endif; ?>
<?php if ($can): ?><div class="mx-btns"><button type="button" class="mx-btn" data-sl-open="pd-fp-new">Add partner</button></div><?php endif; ?>
<?php endif; ?>
</main>
<?php if ($can && !empty($d['ready'])): ?>
<div class="sl-dialog pd-sheet" id="pd-fp-new" hidden><form class="pd-sheetf" data-sl-form action="ajax/sl/freight_partner_ajax.php" method="post" role="dialog" aria-label="Add partner">
<input type="hidden" name="op" value="create"><input type="hidden" name="id" value=""><h2>Add a freight partner</h2>
<label class="pd-f"><small>Name</small><input class="pd-in" name="name" required></label>
<label class="pd-f"><small>Type</small><select class="pd-in" name="type"><?php foreach ((array) $d['typeList'] as $tk => $tl): ?><option value="<?= h($tk) ?>"><?= h($tl) ?></option><?php endforeach; ?></select></label>
<div class="pd-row2"><label class="pd-f"><small>Code</small><input class="pd-in mono" name="code"></label><label class="pd-f"><small>Contract ends</small><input class="pd-in" type="date" name="contract_ends"></label></div>
<label class="pd-f"><small>Lanes</small><input class="pd-in" name="lanes" placeholder="JFK → ACC, Newark → Tema"></label>
<label class="pd-f"><small>Contact name</small><input class="pd-in" name="contact_name" autocomplete="off"></label>
<div class="pd-row2"><label class="pd-f"><small>Phone</small><input class="pd-in" type="tel" name="contact_phone" autocomplete="off"></label><label class="pd-f"><small>E-mail</small><input class="pd-in" type="email" name="contact_email" autocomplete="off"></label></div>
<div class="pd-row2"><label class="pd-f"><small>Rate</small><input class="pd-in mono" type="number" inputmode="decimal" step="0.0001" min="0" name="rate_value"></label><label class="pd-f"><small>Unit</small><input class="pd-in" name="rate_unit" placeholder="per kg"></label><label class="pd-f"><small>Currency</small><input class="pd-in mono" name="rate_currency" maxlength="5" placeholder="USD"></label></div>
<label class="pd-f"><small>API link</small><input class="pd-in" name="api_link" type="url"></label>
<label class="pd-f"><small>Notes</small><textarea class="pd-in" name="notes" rows="2"></textarea></label>
<div class="mx-btns"><button type="submit" class="mx-btn">Save</button><button type="button" class="mx-btn sec" data-sl-close>Cancel</button></div>
</form></div>
<?php endif; ?>
