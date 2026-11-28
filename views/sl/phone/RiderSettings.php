<?php
if (($_GET['section'] ?? '') === 'quests' && !empty($d['ra'])) {
    $ra = $d['ra']; $phone = true; $slPhone['tab'] = 'Menu';
    echo sl_phoneTop('Quests and SOS', '', ['back' => 'rider_settings.php']);
    echo '<main class="mx-body">';
    include CDP_ROOT . '/views/sl/pages/partials/rider_admin.php';
    echo '</main>';
    return;
}
/**
 * M-RiderFees: rider fee rules and split. Same $d as the web page (views/sl/data/riders.php sl_riderSettingsData).
 * Writes: the existing ajax/sl/rider_fees_ajax.php with the web form's field names
 * (op save + rule[id][amount|pct|active]; op add + new_type, new_label, new_amount, new_pct, new_areas; op delete + id).
 */
require_once CDP_ROOT . '/helpers/sl_mx.php';
$slPhone['nav'] = 'staff';
$slPhone['tab'] = 'Menu';
$ro = $d['canEdit'] ? '' : ' disabled';
$cur = (string) ($core->currency ?? '');
$typeLabel = ['per_km' => 'Per km', 'bonus' => 'Bonus', 'penalty' => 'Deduction', 'extra' => 'Extra charge'];
$icon = ['per_km' => 'route', 'bonus' => 'star', 'penalty' => 'alert', 'extra' => 'plus'];
$amt = fn(array $r, string $label) => '<input class="mx-in pc-fee" name="rule[' . (int) $r['id'] . '][amount]" value="' . h($r['amount']) . '" inputmode="decimal" autocomplete="off" aria-label="' . h($label) . '"' . $ro . '>';
$pct = fn(array $r, string $label) => '<input class="mx-in pc-fee pc-pct" name="rule[' . (int) $r['id'] . '][pct]" value="' . h($r['pct']) . '" inputmode="decimal" autocomplete="off" aria-label="' . h($label) . '"' . $ro . '>';
$del = fn(array $r) => $d['canEdit'] ? '<button type="button" class="mx-ib pc-btn" aria-label="Remove ' . h($r['label']) . '" data-sl-action="ajax/sl/rider_fees_ajax.php" data-p-op="delete" data-p-id="' . (int) $r['id'] . '" data-sl-confirm="Remove ' . h($r['label']) . '?" data-sl-reload><i class="ic ic-sm i-trash" aria-hidden="true"></i></button>' : '';
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-centres.css')) ?>">
<?= sl_phoneTop('Rider fees', 'Fee per job and the rider\'s share', ['back' => 'menu.php'], sl_phoneIcon('users', 'Split sheet', sl_route('Drivers'))) ?>
<?php if (!$d['ready']): ?>
<main class="mx-body"><?= sl_mxEmpty('truck', 'Rider fee rules need the database upgrade', 'migrations/2026_11_06_people_staff.sql') ?></main>
<?php return; endif; ?>
<form data-sl-form data-sl-reload action="ajax/sl/rider_fees_ajax.php" method="post" class="pc-form" id="rf-save"><input type="hidden" name="op" value="save">
<main class="mx-body">
<div class="mx-ban info"><i class="ic ic-sm i-<?= $d['canEdit'] ? 'edit' : 'lock' ?>" aria-hidden="true"></i><span><?= $d['canEdit'] ? 'Fees are editable. A zone fee replaces the base fee; the vehicle sets the rider\'s share. Settled periods keep their amounts.' : 'You can see the fee rules. Only people allowed to manage rider fees can change them.' ?></span></div>
<?= sl_mxSec('Base fee') ?>
<section class="mx-card mx-list pc-wrap">
<?php foreach ($d['base'] as $r): ?>
<div class="mx-row"><span class="mx-li"><i class="ic i-<?= $r['type'] === 'per_pickup' ? 'download' : 'truck' ?>" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($r['label']) ?></b><small><?= $r['type'] === 'per_pickup' ? 'Pickup' : 'Delivery' ?> · <?= h($cur) ?> fee · % rider</small></span><span class="mx-rr pc-inl"><?= $amt($r, 'Fee for ' . $r['label']) ?><?= $pct($r, 'Rider share for ' . $r['label']) ?></span></div>
<?php endforeach; ?>
<?php if (!$d['base']): ?><?= sl_mxEmpty('truck', 'No base fee yet') ?><?php endif; ?>
</section>
<?= sl_mxSec('Rider keeps · by vehicle') ?>
<section class="mx-card mx-list pc-wrap">
<?php foreach ($d['vehicles'] as $v): ?>
<div class="mx-row"><span class="mx-li"><i class="ic i-truck" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($v['label']) ?></b><small><?= (int) $v['n'] ?> riders · <?= 100 - (float) $v['pct'] ?>% to us</small></span><span class="mx-rr pc-inl"><?= $pct($v, 'Rider share for ' . $v['label']) ?><?= $del($v) ?></span></div>
<?php endforeach; ?>
<?php if (!$d['vehicles']): ?><?= sl_mxEmpty('truck', 'No vehicle splits', 'Every rider gets the base share.') ?><?php endif; ?>
</section>
<?= sl_mxSec('Fee by zone') ?>
<section class="mx-card mx-list pc-wrap">
<?php foreach ($d['zones'] as $z): ?>
<div class="mx-row"><span class="mx-li"><i class="ic i-pin" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($z['label']) ?></b><small><?= h(trim(($z['cond']['areas'] ?? '') . ' · ' . (int) $z['n'] . ' riders', ' ·')) ?></small></span><span class="mx-rr pc-inl"><?= $amt($z, 'Fee for ' . $z['label']) ?><?= $del($z) ?></span></div>
<?php endforeach; ?>
<?php if (!$d['zones']): ?><?= sl_mxEmpty('pin', 'No zone fees yet', 'Every delivery uses the base fee.') ?><?php endif; ?>
</section>
<?php if ($d['conds']): ?>
<?= sl_mxSec('Fee conditions · ' . (int) $d['condOn'] . ' of ' . count($d['conds']) . ' on') ?>
<section class="mx-card mx-list pc-wrap">
<?php foreach ($d['conds'] as $r): ?>
<div class="mx-row"><button type="button" class="mx-sw<?= $r['active'] ? ' on' : '' ?>" role="switch" aria-checked="<?= $r['active'] ? 'true' : 'false' ?>" aria-label="<?= h($r['label']) ?> on" data-input="#rf-on-<?= (int) $r['id'] ?>"<?= $ro ?>></button><input type="hidden" id="rf-on-<?= (int) $r['id'] ?>" name="rule[<?= (int) $r['id'] ?>][active]" value="<?= (int) $r['active'] ?>"><span class="mx-rm"><b><?= h($r['label']) ?></b><small><?= h($typeLabel[$r['type']] ?? $r['type']) ?><?= $r['type'] === 'per_km' ? ' · per km' : '' ?></small></span><span class="mx-rr pc-inl"><?= $amt($r, 'Amount for ' . $r['label']) ?><?= $del($r) ?></span></div>
<?php endforeach; ?>
</section>
<?php endif; ?>
<?php if ($d['canEdit']): ?>
<details class="mx-card mx-form" id="rf-add"><summary class="mx-lnk" style="min-height:44px;display:flex;align-items:center;cursor:pointer">Add a rule</summary>
  <label class="mx-f"><small>Kind</small><select class="mx-in" name="new_type" form="rf-new"><option value="zone">Zone fee</option><option value="vehicle">Vehicle split</option><option value="extra">Extra charge</option><option value="bonus">Bonus</option><option value="penalty">Deduction</option><option value="per_km">Per km</option></select></label>
  <label class="mx-f"><small>Name (zone or vehicle name must match the rider's)</small><input class="mx-in" name="new_label" form="rf-new" placeholder="e.g. East Legon" autocomplete="off"></label>
  <label class="mx-f"><small>Amount</small><input class="mx-in" name="new_amount" form="rf-new" value="0" inputmode="decimal" autocomplete="off"></label>
  <label class="mx-f"><small>Rider %</small><input class="mx-in" name="new_pct" form="rf-new" value="70" inputmode="decimal" autocomplete="off"></label>
  <label class="mx-f"><small>Areas (zones only)</small><input class="mx-in" name="new_areas" form="rf-new" placeholder="Shiashie, Airport Res." autocomplete="off"></label>
  <button class="mx-btn sec pc-btn" type="submit" form="rf-new">Add rule</button>
</details>
<?php endif; ?>
<?php if ($d['log']): ?>
<?= sl_mxSec('Recent changes') ?>
<section class="mx-card mx-list pc-wrap">
<?php foreach ($d['log'] as $l): ?><div class="mx-row"><span class="mx-rm"><b><?= h($l['who']) ?></b><small><?= h($l['what']) ?></small></span><span class="mx-rr"><small><?= h($l['at']) ?></small></span></div><?php endforeach; ?>
</section>
<?php endif; ?>
<?php if (!empty($d['ra'])): ?><section class="mx-card mx-list"><?= sl_mxRow('flag', 'Quests and emergency number', 'Extra pay for trips, and who SOS calls', '', '?section=quests') ?></section><?php endif; ?>
</main>
<?php if ($d['canEdit']): ?><div class="mx-foot pc-foot"><div class="mx-btns"><button type="submit" class="mx-btn pc-btn">Save changes</button></div></div><?php endif; ?>
</form>
<?php if ($d['canEdit']): ?><form id="rf-new" data-sl-form data-sl-reload action="ajax/sl/rider_fees_ajax.php" method="post" hidden><input type="hidden" name="op" value="add"></form><?php endif; ?>
