<?php
/**
 * Address editor shared by the edit pages. Posts the legacy names address[i], country[i], state[i], city[i],
 * postal[i], address_id[i] and total_address (dataJs/sl/address_rows.js renumbers rows before submit and
 * loads states / cities from ajax/select2_states.php and ajax/select2_cities.php).
 * Expects $slA = ['rows' => sl_addressRows(), 'countries' => sl_countries(), 'delete' => endpoint|'', 'min' => 0|1, 'required' => bool].
 */
$slSel = function (string $name, array $opts, int $sel, string $ph, string $kind) {
    $o = '<option value="">' . h($ph) . '</option>';
    foreach ($opts as $x) {
        $o .= '<option value="' . (int) $x->id . '"' . ((int) $x->id === $sel ? ' selected' : '') . '>' . h($x->name) . '</option>';
    }
    return '<span class="sl-sel native"><select name="' . h($name) . '[]" data-addr-' . $kind . ' aria-label="' . h(ucfirst($kind)) . '">' . $o . '</select><i class="ic ic-sm i-down" aria-hidden="true"></i></span>';
};
$slRowHtml = function (array $r) use ($slSel, $slA) {
    ob_start(); ?>
<div class="sl-card col g12" data-addr-row style="background:var(--field)">
  <input type="hidden" name="address_id[]" value="<?= (int) $r['id'] ?>">
  <div class="row between"><b class="fw5 sm"><?= $r['id'] ? 'Saved address' : 'New address' ?></b><button type="button" class="sl-ib sm" aria-label="Remove address" data-addr-remove <?= $r['id'] && $slA['delete'] ? 'data-addr-delete="' . h($slA['delete']) . '" data-id="' . (int) $r['id'] . '"' : '' ?>><i class="ic ic-sm i-trash" aria-hidden="true"></i></button></div>
  <div class="grid cols3" style="gap:12px">
    <label class="sl-field"><span class="sl-lab">Country</span><?= $slSel('country', $slA['countries'], (int) $r['country'], 'Choose a country', 'country') ?></label>
    <label class="sl-field"><span class="sl-lab">Region / state</span><?= $slSel('state', $r['states'], (int) $r['state'], 'Choose a region', 'state') ?></label>
    <label class="sl-field"><span class="sl-lab">City</span><?= $slSel('city', $r['cities'], (int) $r['city'], 'Choose a city', 'city') ?></label>
  </div>
  <div class="grid cols2" style="gap:12px">
    <label class="sl-field"><span class="sl-lab">Address</span><input class="sl-in" name="address[]" value="<?= h($r['address']) ?>" placeholder="Street, house number, landmark"></label>
    <label class="sl-field"><span class="sl-lab">Postal / GPS code</span><input class="sl-in" name="postal[]" value="<?= h($r['postal']) ?>"></label>
  </div>
</div>
<?php return ob_get_clean();
};
$slBlank = ['id' => 0, 'country' => 0, 'state' => 0, 'city' => 0, 'postal' => '', 'address' => '', 'states' => [], 'cities' => []];
$slRows = $slA['rows'];
if (!$slRows && !empty($slA['min'])) {
    $slRows = [$slBlank];
}
?>
<div class="col g12" data-addr-list>
  <input type="hidden" name="total_address" value="<?= count($slRows) ?>">
  <?php foreach ($slRows as $slR) echo $slRowHtml($slR); ?>
  <div class="sl-empty mut sm" data-addr-empty <?= $slRows ? 'hidden' : '' ?>>No address saved yet.</div>
  <template data-addr-tpl><?= $slRowHtml($slBlank) ?></template>
  <div><button type="button" class="sl-btn ghost sm" data-addr-add><i class="ic ic-sm i-plus" aria-hidden="true"></i>Add address</button></div>
</div>
<script src="<?= h(cdp_asset('dataJs/sl/address_rows.js')) ?>"></script>
