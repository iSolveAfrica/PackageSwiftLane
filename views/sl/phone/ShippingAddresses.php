<?php
/**
 * M-Addresses: warehouse addresses customers ship to. Same $d as the web page (shipping_addresses.php).
 * ?edit=<id> or ?edit=new shows the address form: same fields and endpoint as the web
 * (ajax/sl/shipping_addresses_ajax.php op=save / toggle / delete).
 */
require_once CDP_ROOT . '/helpers/sl_mx.php';
$ajax = 'ajax/sl/shipping_addresses_ajax.php';
$slPhone['nav'] = 'staff';
$slPhone['tab'] = 'Menu';
$slPhone['scripts'][] = 'dataJs/sl/phone/settings.js';
$editKey = (string) ($_GET['edit'] ?? '');
$ed = null;
if ($d['canEdit'] && $editKey !== '') {
    if ($editKey === 'new') {
        $ed = ['id' => 0, 'cdb_countries_id' => 0, 'city' => '', 'postcode' => '', 'address' => '', 'label' => '', 'shipping_mode' => 'air', 'transit_days' => ''];
    } else {
        foreach ($d['rows'] as $r) if ($r['id'] === (int) $editKey) $ed = $r['raw'];
    }
}
$back = sl_route('ShippingAddresses');
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-settings.css')) ?>">
<?php if (!$ed): ?>
<?= sl_phoneTop('Shipping addresses', (int) $d['onCount'] . ' of ' . (int) $d['count'] . ' showing to customers', ['back' => sl_route('SettingsHub')], sl_phoneIcon('eye', 'See as customer', 'client_virtual_mail_box_addresses.php')) ?>
<main class="mx-body">
<?php if (!$d['hasExtra']): ?><div class="mx-ban warn"><i class="ic ic-sm i-alert" aria-hidden="true"></i><span>Label, mode and the show/hide switch need the database upgrade 2026_11_06_messaging_settings.</span></div><?php endif; ?>
<section class="mx-card mx-list">
<?php foreach ($d['rows'] as $r): $tag = $d['canEdit'] ? 'a' : 'div'; ?>
<<?= $tag ?> class="mx-row"<?= $d['canEdit'] ? ' href="' . h(sl_route('ShippingAddresses', ['edit' => $r['id']])) . '"' : '' ?>><span class="mx-li"><i class="ic i-pin" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($r['cc'] . ' · ' . $r['label']) ?></b><small><?= h($r['line'] . ($r['transit'] !== '' ? ' · ' . $r['transit'] : '')) ?></small></span><span class="mx-rr"><span class="sl-pill <?= $r['on'] ? 'delivered' : 'plain' ?>"><?= h($r['mode']) ?></span><?php if (!$r['on']): ?><small>Hidden</small><?php endif; ?></span></<?= $tag ?>>
<?php endforeach; ?>
<?php if (!$d['rows']): ?><?= sl_mxEmpty('pin', 'No shipping addresses yet', 'Add the warehouses customers ship their parcels to.') ?><?php endif; ?>
</section>
<p class="mx-note">“LOCKER ID” in an address is replaced with each customer's locker number.</p>
<?php if ($d['canEdit']): ?><div class="mx-btns"><a class="mx-btn" href="<?= h(sl_route('ShippingAddresses', ['edit' => 'new'])) ?>">Add address</a></div><?php endif; ?>
</main>
<?php else: ?>
<?= sl_phoneTop($ed['id'] ? 'Edit address' : 'New address', '', ['back' => $back]) ?>
<form data-sl-form action="<?= h($ajax) ?>" method="post" data-ph-done="<?= h($back) ?>">
<main class="mx-body">
<input type="hidden" name="op" value="save"><input type="hidden" name="id" value="<?= (int) $ed['id'] ?>">
<section class="mx-card mx-form">
<label class="mx-f"><small>Country</small><select class="mx-in" name="cdb_countries_id" required><option value="">Pick a country</option><?php foreach ($d['countries'] as $c): ?><option value="<?= (int) $c->id ?>"<?= (int) $c->id === (int) $ed['cdb_countries_id'] ? ' selected' : '' ?>><?= h($c->name) ?></option><?php endforeach; ?></select></label>
<label class="mx-f"><small>Label</small><input class="mx-in" name="label" maxlength="120" value="<?= h($ed['label']) ?>" placeholder="e.g. New Jersey air warehouse" autocomplete="off"></label>
<div class="mx-two"><label class="mx-f"><small>City</small><input class="mx-in" name="city" maxlength="190" value="<?= h($ed['city']) ?>" autocomplete="off"></label><label class="mx-f"><small>Postcode or phone</small><input class="mx-in" name="postcode" maxlength="40" value="<?= h($ed['postcode']) ?>" autocomplete="off"></label></div>
<label class="mx-f"><small>Address (write LOCKER ID where the suite goes)</small><textarea class="mx-in" name="address" rows="4"><?= h((string) $ed['address']) ?></textarea></label>
<label class="mx-f"><small>Transit time</small><input class="mx-in" name="transit_days" maxlength="40" value="<?= h($ed['transit_days']) ?>" placeholder="e.g. 7–10 days" autocomplete="off"></label>
<div class="mx-f"><small id="sa-ml">Shipping mode</small><div class="mx-fseg" role="group" aria-labelledby="sa-ml" data-mx-pick="#sa-mode"><?php foreach (['air' => 'Air', 'sea' => 'Sea', 'express' => 'Express'] as $k => $l): $on = ($ed['shipping_mode'] ?: 'air') === $k; ?><button type="button" class="<?= $on ? 'on' : '' ?>" aria-pressed="<?= $on ? 'true' : 'false' ?>" data-value="<?= $k ?>"><?= $l ?></button><?php endforeach; ?></div><input type="hidden" name="shipping_mode" id="sa-mode" value="<?= h($ed['shipping_mode'] ?: 'air') ?>"></div>
</section>
<?php if ($ed['id'] && $d['hasExtra']): $cur = null; foreach ($d['rows'] as $r) if ($r['id'] === (int) $ed['id']) $cur = $r; ?>
<section class="mx-card mx-tgs"><?= sl_mxToggle('Show to customers', 'Appears on every customer account right away', (bool) ($cur['on'] ?? true), $ajax, '', ['op' => 'toggle', 'id' => (int) $ed['id']]) ?></section>
<?php endif; ?>
<?php if ($ed['id']): ?><button class="mx-btn sec" type="button" data-sl-action="<?= h($ajax) ?>" data-p-op="delete" data-p-id="<?= (int) $ed['id'] ?>" data-sl-confirm="Delete this address? Customers stop seeing it.">Delete address</button><?php endif; ?>
</main>
<div class="mx-foot"><div class="mx-btns"><button class="mx-btn" type="submit"><?= $ed['id'] ? 'Save' : 'Save and show to customers' ?></button></div></div>
</form>
<?php endif; ?>
